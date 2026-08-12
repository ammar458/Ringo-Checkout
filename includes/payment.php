<?php
/**
 * Core payment-processing logic.
 *
 * Handles publishing a boat post after a successful payment and triggering
 * the post-payment notification emails.
 *
 * @package RingoCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize legacy/native boat meta before WordPress fires save_post on publish.
 *
 * The BassBoat4Sale child theme expects the `gallery` value to be a
 * comma-separated list of attachment IDs. Early native-form versions stored an
 * array instead. The theme then called explode() on that array during save_post,
 * causing a fatal TypeError after a successful payment.
 *
 * @param int $post_id Boat post ID.
 * @return void
 */
function ringo_prepare_boat_meta_for_publish( $post_id ) {
	$gallery = get_post_meta( $post_id, 'gallery', true );

	if ( ! is_array( $gallery ) ) {
		return;
	}

	$ids = [];
	array_walk_recursive(
		$gallery,
		static function ( $value ) use ( &$ids ) {
			$id = absint( $value );
			if ( $id ) {
				$ids[] = $id;
			}
		}
	);

	$ids = array_values( array_unique( $ids ) );
	update_post_meta( $post_id, 'gallery', implode( ',', $ids ) );

	ringo_log(
		'Normalized gallery meta before publish',
		[
			'post_id' => (int) $post_id,
			'images'  => count( $ids ),
		]
	);
}


/**
 * Finish a paid boat after its background image upload completes.
 *
 * @param int $post_id Boat post ID.
 * @return bool
 */
function ringo_finalize_paid_boat_after_assets( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || 'complete' !== get_post_meta( $post_id, '_ringo_native_assets_status', true ) ) {
		return false;
	}
	if ( 'paid' !== get_post_meta( $post_id, '_ringo_checkout_status', true ) && ! get_post_meta( $post_id, '_ringo_publish_pending_assets', true ) ) {
		return false;
	}

	$provider = (string) get_post_meta( $post_id, '_ringo_payment_provider', true );
	if ( 'stripe' === $provider ) {
		$provider_id = (string) get_post_meta( $post_id, '_ringo_stripe_payment_intent', true );
	} elseif ( 'paypal' === $provider ) {
		$provider_id = (string) get_post_meta( $post_id, '_ringo_paypal_order_id', true );
	} else {
		$provider_id = 'background-assets-' . $post_id;
	}

	$package = (string) get_post_meta( $post_id, '_ringo_package', true );
	$amount  = (float) get_post_meta( $post_id, '_ringo_amount', true );
	delete_post_meta( $post_id, '_ringo_publish_pending_assets' );
	return ringo_process_paid( $post_id, $provider ?: 'pending', $provider_id, $package, $amount );
}
add_action( 'ringo_finalize_paid_boat_after_assets', 'ringo_finalize_paid_boat_after_assets' );

/**
 * Mark a boat post as paid, publish it, and dispatch notification emails.
 *
 * This function is idempotent with respect to publishing: if the post is
 * already published it will not be modified again.
 *
 * @param  int    $post_id       ID of the 'boats' post.
 * @param  string $provider      'stripe' | 'paypal'
 * @param  string $provider_id   Stripe PaymentIntent ID or PayPal order ID.
 * @param  string $package_name  Raw package label stored in form meta.
 * @param  float  $package_price Charged amount.
 * @return bool                  TRUE on success, FALSE on failure.
 */
function ringo_process_paid( $post_id, $provider, $provider_id, $package_name, $package_price ) {
	$post = get_post( $post_id );

	if ( ! $post || $post->post_type !== 'boats' ) {
		ringo_log( 'ringo_process_paid: invalid post', [ 'post_id' => $post_id ] );
		return false;
	}

	$asset_status  = (string) get_post_meta( $post_id, '_ringo_native_assets_status', true );
	$defer_publish = in_array( $asset_status, [ 'uploading', 'failed' ], true );

	if ( $post->post_status === 'draft' && ! $defer_publish ) {
		ringo_prepare_boat_meta_for_publish( $post_id );

		try {
			$updated = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ], true );
		} catch ( \Throwable $e ) {
			ringo_log(
				'ringo_process_paid: publish threw an error',
				[
					'post_id' => (int) $post_id,
					'error'   => $e->getMessage(),
				]
			);
			return false;
		}

		if ( is_wp_error( $updated ) ) {
			ringo_log( 'ringo_process_paid: publish failed', $updated->get_error_message() );
			return false;
		}
	}

	// Core payment meta.
	update_post_meta( $post_id, '_ringo_paid',             1 );
	update_post_meta( $post_id, '_ringo_checkout_status',  'paid' );
	update_post_meta( $post_id, '_ringo_payment_provider', sanitize_text_field( $provider ) );
	ringo_update_payment_activity( $post_id, 'payment_complete', $provider, [
		'source'      => 'ringo_process_paid',
		'provider_id' => sanitize_text_field( (string) $provider_id ),
	] );

	// Keep failure history for tracing, but clear the active failure marker once
	// a retry succeeds so the boat no longer appears to have an unresolved issue.
	if ( get_post_meta( $post_id, '_ringo_draft_failure_condition', true ) ) {
		update_post_meta( $post_id, '_ringo_draft_failure_resolved_at', time() );
		delete_post_meta( $post_id, '_ringo_draft_failure_condition' );
		delete_post_meta( $post_id, '_ringo_draft_failure_message' );
		delete_post_meta( $post_id, '_ringo_draft_failure_time' );
	}

	if ( $provider === 'stripe' ) {
		update_post_meta( $post_id, '_ringo_stripe_payment_intent', sanitize_text_field( $provider_id ) );
	} elseif ( $provider === 'paypal' ) {
		update_post_meta( $post_id, '_ringo_paypal_order_id', sanitize_text_field( $provider_id ) );
	}

	if ( $package_name ) {
		update_post_meta( $post_id, '_ringo_package', sanitize_text_field( (string) $package_name ) );
	}
	if ( (float) $package_price > 0 ) {
		update_post_meta( $post_id, '_ringo_amount', (float) $package_price );
	}

	if ( $defer_publish ) {
		update_post_meta( $post_id, '_ringo_publish_pending_assets', 1 );
		if ( ! wp_next_scheduled( 'ringo_finalize_paid_boat_after_assets', [ (int) $post_id ] ) ) {
			wp_schedule_single_event( time() + 15, 'ringo_finalize_paid_boat_after_assets', [ (int) $post_id ] );
		}
		ringo_log( 'Paid boat publish deferred until background images finish', [ 'post_id' => (int) $post_id, 'asset_status' => $asset_status ] );
		return true;
	}

	delete_post_meta( $post_id, '_ringo_publish_pending_assets' );

	// Archive the checkout URL so it no longer shows as "open" in the tracker.
	$last_url = get_post_meta( $post_id, '_ringo_checkout_url', true );
	if ( $last_url ) {
		update_post_meta( $post_id, '_ringo_checkout_url_last', esc_url_raw( $last_url ) );
		delete_post_meta( $post_id, '_ringo_checkout_url' );
	}

	// Send notification emails.
	ringo_send_publish_email( $post_id, $package_name, $package_price );
	ringo_send_admin_listing_email( $post_id, $package_name, $package_price );

	return true;
}

// ─── Administrator bypass AJAX handler ───────────────────────────────────────

/**
 * AJAX: publish a boat directly without payment (administrator only).
 *
 * Accepts: boat_post_id, package_name, nonce.
 * Sets provider to 'admin' and amount to 0 to indicate a free/bypassed listing.
 */
add_action( 'wp_ajax_ringo_admin_bypass', 'ringo_ajax_admin_bypass' );

function ringo_ajax_admin_bypass() {
	// Only administrators may use this endpoint.
	if ( ! current_user_can( 'administrator' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	if ( ! check_ajax_referer( 'ringo_admin_bypass_nonce', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}

	$post_id      = isset( $_POST['boat_post_id'] ) ? (int) $_POST['boat_post_id'] : 0;
	$package_name = isset( $_POST['package_name'] ) ? sanitize_text_field( wp_unslash( $_POST['package_name'] ) ) : '';

	if ( ! $post_id ) {
		wp_send_json_error( 'Missing boat_post_id' );
	}

	if ( get_post_meta( $post_id, '_ringo_checkout_status', true ) === 'paid' ) {
		wp_send_json_success( 'Already published' );
	}

	$success = ringo_process_paid( $post_id, 'admin', 'admin-bypass-' . get_current_user_id(), $package_name, 0 );

	if ( $success ) {
		ringo_log( 'Admin bypass: boat published', [ 'post_id' => $post_id, 'user' => get_current_user_id() ] );
		wp_send_json_success( 'Published' );
	} else {
		wp_send_json_error( 'Failed to publish post' );
	}
}
