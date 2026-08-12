<?php
/**
 * Stripe SDK loader and active-key helpers.
 *
 * @package RingoCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attempt to load the Stripe PHP SDK.
 *
 * Checks for a Composer autoloader at the WordPress root first, then falls
 * back to a local vendor copy shipped with this plugin.
 *
 * @return bool TRUE when \Stripe\Stripe is available.
 */
function ringo_load_stripe_sdk() {
	$composer = ABSPATH . 'vendor/autoload.php';
	if ( file_exists( $composer ) ) {
		require_once $composer;
	}

	if ( ! class_exists( '\Stripe\Stripe' ) ) {
		$local = RINGO_CHECKOUT_DIR . 'vendor/stripe/stripe-php/init.php';
		if ( file_exists( $local ) ) {
			require_once $local;
		}
	}

	return class_exists( '\Stripe\Stripe' );
}

/**
 * Return the currently active Stripe secret key.
 *
 * Falls back to the legacy RINGO_STRIPE_SECRET_KEY constant if keys are not
 * yet configured via the settings page.
 *
 * @return string
 */
function ringo_get_active_stripe_secret() {
	$s = ringo_get_settings();

	if ( $s['mode'] === 'live' && ! empty( $s['stripe_live_secret'] ) ) {
		return $s['stripe_live_secret'];
	}
	if ( $s['mode'] === 'test' && ! empty( $s['stripe_test_secret'] ) ) {
		return $s['stripe_test_secret'];
	}
	if ( defined( 'RINGO_STRIPE_SECRET_KEY' ) && RINGO_STRIPE_SECRET_KEY ) {
		return RINGO_STRIPE_SECRET_KEY;
	}

	return '';
}

/**
 * Return the currently active Stripe publishable key.
 *
 * @return string
 */
function ringo_get_active_stripe_publishable() {
	$s = ringo_get_settings();

	if ( $s['mode'] === 'live' && ! empty( $s['stripe_live_publishable'] ) ) {
		return $s['stripe_live_publishable'];
	}
	if ( $s['mode'] === 'test' && ! empty( $s['stripe_test_publishable'] ) ) {
		return $s['stripe_test_publishable'];
	}
	if ( defined( 'RINGO_STRIPE_PUBLISHABLE_KEY' ) && RINGO_STRIPE_PUBLISHABLE_KEY ) {
		return RINGO_STRIPE_PUBLISHABLE_KEY;
	}

	return '';
}
