<?php
/**
 * Global helper functions.
 *
 * @package RingoCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Logging ─────────────────────────────────────────────────────────────────

if ( ! function_exists( 'ringo_log' ) ) {
	/**
	 * Write a prefixed line to the PHP error log.
	 *
	 * @param string     $message Human-readable label.
	 * @param mixed|null $data    Optional context (array, object, or scalar).
	 */
	function ringo_log( $message, $data = null ) {
		$line = '🧾 [Ringo Pay] ' . $message;
		if ( null !== $data ) {
			$line .= ' | ' . ( is_array( $data ) || is_object( $data )
				? wp_json_encode( $data )
				: (string) $data );
		}
		error_log( $line );

		// Persist to DB so the admin Email Logs page can display entries.
		$entry = [
			'time'    => current_time( 'mysql' ),
			'message' => $message,
			'data'    => $data,
		];
		$logs = get_option( 'ringo_email_logs', [] );
		array_unshift( $logs, $entry );         // newest first
		$logs = array_slice( $logs, 0, 200 );   // cap at 200 entries
		update_option( 'ringo_email_logs', $logs, false );
	}
}

// ─── Package helpers ─────────────────────────────────────────────────────────

/**
 * Return the canonical, lower-cased package key from a raw label.
 *
 * @param  string $label Raw label from form field or meta.
 * @return string        E.g. "featured", "vip".
 */
function ringo_normalize_package_key( $label ) {
	return preg_replace( '/\s+/', ' ', strtolower( trim( (string) $label ) ) );
}

/**
 * Return the price for a package by its label.
 *
 * @param  string $package_label Raw label.
 * @return float
 */
function ringo_get_package_price( $package_label ) {
	$key      = ringo_normalize_package_key( $package_label );
	$settings = ringo_get_settings();
	$prices   = isset( $settings['prices'] ) && is_array( $settings['prices'] )
		? $settings['prices']
		: [];

	return isset( $prices[ $key ] ) ? (float) $prices[ $key ] : 0.0;
}

/**
 * Return the description for a package by its label.
 *
 * @param  string $package_label Raw label.
 * @return string
 */
function ringo_get_package_description( $package_label ) {
	$key      = ringo_normalize_package_key( $package_label );
	$settings = ringo_get_settings();
	$descs    = isset( $settings['descriptions'] ) && is_array( $settings['descriptions'] )
		? $settings['descriptions']
		: [];

	return isset( $descs[ $key ] ) ? (string) $descs[ $key ] : '';
}

// ─── Post / email helpers ────────────────────────────────────────────────────

/**
 * Retrieve the customer e-mail associated with a boat post.
 *
 * @param  int $post_id
 * @return string Sanitised e-mail, or empty string.
 */
function ringo_get_form_email( $post_id ) {
	$candidates = [
		get_post_meta( $post_id, 'email',                    true ),
		get_post_meta( $post_id, 'Email',                    true ),
		get_post_meta( $post_id, '_ringo_customer_email',    true ),
	];

	foreach ( $candidates as $v ) {
		$v = sanitize_email( (string) $v );
		if ( $v && is_email( $v ) ) {
			return $v;
		}
	}

	return '';
}

/**
 * Persist a customer e-mail on the boat post (without overwriting an existing one).
 *
 * @param int    $post_id
 * @param string $email
 */
function ringo_save_customer_email_if_provided( $post_id, $email ) {
	$email = sanitize_email( (string) $email );
	if ( ! $email || ! is_email( $email ) ) {
		return;
	}

	update_post_meta( $post_id, '_ringo_customer_email', $email );

	if ( ! get_post_meta( $post_id, 'email', true ) ) {
		update_post_meta( $post_id, 'email', $email );
	}
}

/**
 * Build the URL for a user to return and complete/edit their draft listing.
 *
 * @param  int $post_id
 * @return string
 */
function ringo_get_draft_edit_url( $post_id ) {
	$post_id = (int) $post_id;
	return $post_id
		? home_url( '/account/edit-post/?_post_id=' . $post_id )
		: home_url( '/account/edit-post/' );
}

// ─── Payment meta helpers ────────────────────────────────────────────────────

/**
 * Write initial payment metadata to a boat post.
 *
 * @param int    $post_id
 * @param string $provider       "stripe" | "paypal"
 * @param string $status         "unpaid" | "paid" | "followed_up"
 * @param string $package
 * @param float  $amount
 * @param string $provider_id    Payment-intent ID or PayPal order ID.
 * @param string $checkout_url   Optional URL saved for abandoned follow-up display.
 * @param string $form_id
 */
function ringo_set_payment_meta(
	$post_id,
	$provider,
	$status,
	$package,
	$amount,
	$provider_id   = '',
	$checkout_url  = '',
	$form_id       = ''
) {
	update_post_meta( $post_id, '_ringo_payment_provider',  sanitize_text_field( (string) $provider ) );
	update_post_meta( $post_id, '_ringo_checkout_status',   sanitize_text_field( (string) $status ) );

	if ( $form_id ) {
		update_post_meta( $post_id, '_ringo_form_id', sanitize_text_field( (string) $form_id ) );
	}
	if ( $package ) {
		update_post_meta( $post_id, '_ringo_package', sanitize_text_field( (string) $package ) );
	}
	if ( (float) $amount > 0 ) {
		update_post_meta( $post_id, '_ringo_amount', (float) $amount );
	}

	if ( $provider === 'stripe' && $provider_id ) {
		update_post_meta( $post_id, '_ringo_stripe_payment_intent', sanitize_text_field( (string) $provider_id ) );
	} elseif ( $provider === 'paypal' && $provider_id ) {
		update_post_meta( $post_id, '_ringo_paypal_order_id', sanitize_text_field( (string) $provider_id ) );
	}

	if ( $checkout_url ) {
		update_post_meta( $post_id, '_ringo_checkout_url', esc_url_raw( $checkout_url ) );
	}
}
