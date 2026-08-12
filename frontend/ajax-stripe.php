<?php
/**
 * AJAX handler: create a Stripe PaymentIntent (inline card flow).
 *
 * Registered for both logged-in and logged-out users.
 *
 * @package RingoCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_ringo_stripe_create_intent',        'ringo_ajax_stripe_create_intent' );
add_action( 'wp_ajax_nopriv_ringo_stripe_create_intent', 'ringo_ajax_stripe_create_intent' );

/**
 * Create a Stripe PaymentIntent and return its client secret to the browser.
 *
 * The browser then uses the client secret with Stripe.js to confirm the
 * payment without any server-side redirect.
 */
function ringo_ajax_stripe_create_intent() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ringo_stripe_intent_nonce' ) ) {
		wp_send_json_error( [ 'message' => 'Security check failed' ] );
	}

	$package_label        = isset( $_POST['package_name'] )  ? trim( (string) wp_unslash( $_POST['package_name'] ) ) : '';
	$boat_post_id         = isset( $_POST['boat_post_id'] )   ? (int) wp_unslash( $_POST['boat_post_id'] )           : 0;
	$form_id              = isset( $_POST['form_id'] )        ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
	$user_email           = isset( $_POST['user_email'] )     ? sanitize_email( wp_unslash( $_POST['user_email'] ) )  : '';
	$package_price_client = isset( $_POST['package_price'] )      ? (float) wp_unslash( $_POST['package_price'] )      : 0;
	$base_price_client    = isset( $_POST['base_package_price'] ) ? (float) wp_unslash( $_POST['base_package_price'] ) : 0;
	$addon_ids            = ringo_get_requested_addon_ids( $_POST['addon_ids'] ?? [] );

	if ( ! $package_label ) {
		if ( $boat_post_id ) {
			ringo_record_draft_failure( $boat_post_id, 'payment_setup_error', 'Stripe package was missing.', [ 'source' => 'stripe_create_intent' ] );
		}
		wp_send_json_error( [ 'message' => 'Package is required', 'condition' => 'payment_setup_error' ] );
	}
	if ( ! $boat_post_id ) {
		wp_send_json_error( [ 'message' => 'Boat post ID missing' ] );
	}

	$post = get_post( $boat_post_id );
	if ( ! $post || $post->post_type !== 'boats' ) {
		wp_send_json_error( [ 'message' => 'Boat not found' ] );
	}
	if ( $post->post_status !== 'draft' ) {
		wp_send_json_error( [ 'message' => 'Boat must be in draft before payment (current: ' . $post->post_status . ')' ] );
	}

	if ( $user_email && is_email( $user_email ) ) {
		ringo_save_customer_email_if_provided( $boat_post_id, $user_email );
	}

	if ( ! ringo_load_stripe_sdk() ) {
		ringo_record_draft_failure( $boat_post_id, 'gateway_unavailable', 'Stripe library missing.', [ 'source' => 'stripe_create_intent' ] );
		wp_send_json_error( [ 'message' => 'Stripe library missing', 'condition' => 'gateway_unavailable' ] );
	}

	$secret = ringo_get_active_stripe_secret();
	if ( ! $secret ) {
		ringo_record_draft_failure( $boat_post_id, 'gateway_unavailable', 'Stripe secret key is not configured.', [ 'source' => 'stripe_create_intent' ] );
		wp_send_json_error( [ 'message' => 'Stripe is not configured. Please contact admin.', 'condition' => 'gateway_unavailable' ] );
	}

	\Stripe\Stripe::setApiKey( $secret );

	// Recalculate the package and every selected add-on on the server. The
	// browser total is never trusted for the amount charged.
	$fallback_base = $base_price_client > 0 ? $base_price_client : $package_price_client;
	$totals        = ringo_get_checkout_totals( $package_label, $addon_ids, $fallback_base );
	$amount        = (float) $totals['subtotal'];
	if ( $totals['base'] <= 0 || $amount <= 0 ) {
		ringo_record_draft_failure( $boat_post_id, 'payment_setup_error', 'Stripe checkout total was invalid.', [ 'source' => 'stripe_create_intent', 'package' => $package_label ] );
		wp_send_json_error( [ 'message' => 'Invalid checkout total', 'condition' => 'payment_setup_error' ] );
	}

	ringo_save_boat_addons( $boat_post_id, $totals['addon_ids'], (float) $totals['base'] );

	// Apply coupon if provided.
	$coupon_code = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( trim( (string) wp_unslash( $_POST['coupon_code'] ) ) ) ) : '';
	if ( $coupon_code ) {
		$coupon_result = ringo_apply_coupon( $coupon_code, $amount, $user_email );
		if ( ! is_wp_error( $coupon_result ) ) {
			$amount = $coupon_result['final_price'];
		}
	}

	$description = ringo_get_package_description( $package_label )
		?: 'Boat listing package: ' . $package_label;
	$addon_names = array_map( static function ( $item ) { return (string) $item['name']; }, $totals['addon_items'] );
	if ( $addon_names ) {
		$description .= ' + ' . implode( ', ', $addon_names );
	}

	ringo_update_payment_activity( $boat_post_id, 'stripe_intent_request', 'stripe', [ 'source' => 'server' ] );

	try {
		$intent = \Stripe\PaymentIntent::create( [
			'amount'               => (int) round( $amount * 100 ),
			'currency'             => 'usd',
			'payment_method_types' => [ 'card' ],
			'description'          => substr( $description, 0, 500 ),
			'metadata'             => [
				'form_id'   => $form_id ?: '',
				'package'   => $package_label,
				'post_id'   => (string) $boat_post_id,
				'post_type'     => 'boats',
				'base_amount'  => number_format( (float) $totals['base'], 2, '.', '' ),
				'addons_total' => number_format( (float) $totals['addons_total'], 2, '.', '' ),
				'addon_ids'    => substr( implode( ',', $totals['addon_ids'] ), 0, 500 ),
			],
			'receipt_email' => ( $user_email && is_email( $user_email ) ) ? $user_email : null,
		] );

		update_post_meta( $boat_post_id, '_ringo_checkout_created_at', time() );

		if ( $coupon_code ) {
			update_post_meta( $boat_post_id, '_ringo_coupon_code', sanitize_text_field( $coupon_code ) );
			ringo_increment_coupon_use( $coupon_code, $user_email );
		}

		ringo_update_payment_activity( $boat_post_id, 'stripe_intent_created', 'stripe', [ 'payment_intent_id' => $intent->id ] );

		ringo_set_payment_meta(
			$boat_post_id,
			'stripe',
			'unpaid',
			$package_label,
			$amount,
			$intent->id,
			'',
			$form_id
		);

		delete_post_meta( $boat_post_id, '_ringo_pending_email_sent' );
		delete_post_meta( $boat_post_id, '_ringo_pending_admin_email_sent' );
		delete_post_meta( $boat_post_id, '_ringo_new_draft_admin_email_sent' );

		wp_send_json_success( [
			'payment_intent_id' => $intent->id,
			'client_secret'     => $intent->client_secret,
		] );

	} catch ( \Exception $e ) {
		$message   = 'Stripe error: ' . $e->getMessage();
		$condition = ringo_is_gateway_timeout( $e->getMessage() ) ? 'gateway_timeout' : 'stripe_intent_error';
		ringo_record_draft_failure( $boat_post_id, $condition, $message, [ 'source' => 'stripe_create_intent_exception' ] );
		wp_send_json_error( [ 'message' => $message, 'condition' => $condition ] );
	}
}
