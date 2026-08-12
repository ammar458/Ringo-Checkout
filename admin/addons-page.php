<?php
/**
 * Add-on manager screen.
 *
 * @package RingoCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render and process the add-on manager.
 *
 * @return void
 */
function ringo_render_addons_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage checkout add-ons.', 'ringo-checkout' ) );
	}

	$notice = '';
	if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && isset( $_POST['ringo_save_addons'] ) ) {
		check_admin_referer( 'ringo_save_addons_action', 'ringo_addons_nonce' );
		$rows = isset( $_POST['addons'] ) ? wp_unslash( $_POST['addons'] ) : [];
		$rows = is_array( $rows ) ? $rows : [];

		foreach ( $rows as $key => $row ) {
			if ( is_array( $row ) && ! empty( $row['delete'] ) ) {
				unset( $rows[ $key ] );
			}
		}

		update_option( 'ringo_checkout_addons', ringo_sanitize_addon_rows( $rows ), false );
		$notice = 'Add-ons saved.';
	}

	$addons = ringo_get_addons( false );
	?>
	<div class="wrap">
		<h1>Checkout Add-ons</h1>
		<p>Form add-ons change the Add Boat form. System add-ons affect price and fulfillment only. Every payment amount is recalculated on the server.</p>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'ringo_save_addons_action', 'ringo_addons_nonce' ); ?>
			<div style="overflow:auto;">
			<table class="widefat striped" id="ringo-addons-table" style="min-width:1300px;">
				<thead>
					<tr>
						<th style="width:65px">Active</th>
						<th style="width:180px">Name</th>
						<th>Description</th>
						<th style="width:110px">Price</th>
						<th style="width:120px">Type</th>
						<th style="width:170px">Related field</th>
						<th style="width:95px">Amount</th>
						<th style="width:75px">Order</th>
						<th style="width:65px">Delete</th>
					</tr>
				</thead>
				<tbody>
					<?php $row_index = 0; foreach ( $addons as $addon ) : ?>
						<tr data-ringo-addon-row>
							<td><input type="checkbox" name="addons[<?php echo esc_attr( $row_index ); ?>][enabled]" value="1" <?php checked( ! empty( $addon['enabled'] ) ); ?>></td>
							<td><input type="hidden" name="addons[<?php echo esc_attr( $row_index ); ?>][id]" value="<?php echo esc_attr( $addon['id'] ); ?>"><input class="regular-text" style="width:100%" type="text" name="addons[<?php echo esc_attr( $row_index ); ?>][name]" value="<?php echo esc_attr( $addon['name'] ); ?>" required></td>
							<td><textarea class="large-text" rows="2" name="addons[<?php echo esc_attr( $row_index ); ?>][description]"><?php echo esc_textarea( $addon['description'] ); ?></textarea></td>
							<td><input style="width:100%" type="number" min="0" step="0.01" name="addons[<?php echo esc_attr( $row_index ); ?>][price]" value="<?php echo esc_attr( number_format( (float) $addon['price'], 2, '.', '' ) ); ?>" required></td>
							<td>
								<select name="addons[<?php echo esc_attr( $row_index ); ?>][addon_type]" data-ringo-addon-type style="width:100%">
									<option value="form" <?php selected( $addon['addon_type'], 'form' ); ?>>Form add-on</option>
									<option value="system" <?php selected( $addon['addon_type'], 'system' ); ?>>System add-on</option>
								</select>
							</td>
							<td>
								<select name="addons[<?php echo esc_attr( $row_index ); ?>][field_effect]" data-ringo-addon-effect style="width:100%">
									<option value="none" <?php selected( $addon['field_effect'], 'none' ); ?>>No form field</option>
									<option value="gallery_images" <?php selected( $addon['field_effect'], 'gallery_images' ); ?>>Gallery photos</option>
									<option value="video_fields" <?php selected( $addon['field_effect'], 'video_fields' ); ?>>Video URL fields</option>
								</select>
							</td>
							<td><input style="width:100%" type="number" min="0" step="1" name="addons[<?php echo esc_attr( $row_index ); ?>][effect_value]" value="<?php echo esc_attr( (int) $addon['effect_value'] ); ?>" data-ringo-addon-amount></td>
							<td><input style="width:100%" type="number" step="1" name="addons[<?php echo esc_attr( $row_index ); ?>][sort]" value="<?php echo esc_attr( (int) $addon['sort'] ); ?>"></td>
							<td><input type="checkbox" name="addons[<?php echo esc_attr( $row_index ); ?>][delete]" value="1"></td>
						</tr>
					<?php $row_index++; endforeach; ?>
				</tbody>
			</table>
			</div>

			<p><button type="button" class="button" id="ringo-add-addon-row">Add New Add-on</button></p>
			<p class="submit"><button type="submit" class="button button-primary" name="ringo_save_addons" value="1">Save Add-ons</button></p>
		</form>

		<div class="card" style="max-width:900px;padding:18px 22px;">
			<h2 style="margin-top:0;">How form add-ons work</h2>
			<p><strong>Gallery photos:</strong> the amount is added to the package photo limit. Example: Standard has 4 photos and an add-on amount of 10 gives 14 photos.</p>
			<p><strong>Video URL fields:</strong> the amount is added to the package video allowance. Standard starts with 0, Featured starts with 1, and VIP/Pro start with 2.</p>
			<p><strong>System add-on:</strong> no form field changes. Use this for Boosted Post, On-site Listing, menu placement, or other services.</p>
		</div>

		<hr>
		<h2>Services Page</h2>
		<p>Create a normal WordPress page and place this shortcode in it:</p>
		<p><code>[ringo_addon_services]</code></p>
	</div>
	<script>
	(function(){
		var button = document.getElementById('ringo-add-addon-row');
		var body = document.querySelector('#ringo-addons-table tbody');
		if (!button || !body) return;

		function syncRow(row) {
			var type = row.querySelector('[data-ringo-addon-type]');
			var effect = row.querySelector('[data-ringo-addon-effect]');
			var amount = row.querySelector('[data-ringo-addon-amount]');
			var isForm = type && type.value === 'form';
			if (effect) {
				effect.disabled = !isForm;
				if (!isForm) effect.value = 'none';
			}
			if (amount) {
				var hasEffect = isForm && effect && effect.value !== 'none';
				amount.disabled = !hasEffect;
				if (!hasEffect) amount.value = '0';
			}
		}

		body.addEventListener('change', function(event){
			if (event.target.matches('[data-ringo-addon-type], [data-ringo-addon-effect]')) {
				var row = event.target.closest('[data-ringo-addon-row]');
				if (row) syncRow(row);
			}
		});

		button.addEventListener('click', function(){
			var index = body.querySelectorAll('tr').length + 1000;
			var row = document.createElement('tr');
			row.setAttribute('data-ringo-addon-row', '');
			row.innerHTML = '<td><input type="checkbox" name="addons['+index+'][enabled]" value="1" checked></td>' +
				'<td><input type="hidden" name="addons['+index+'][id]" value=""><input class="regular-text" style="width:100%" type="text" name="addons['+index+'][name]" value="" required></td>' +
				'<td><textarea class="large-text" rows="2" name="addons['+index+'][description]"></textarea></td>' +
				'<td><input style="width:100%" type="number" min="0" step="0.01" name="addons['+index+'][price]" value="0.00" required></td>' +
				'<td><select style="width:100%" name="addons['+index+'][addon_type]" data-ringo-addon-type><option value="form">Form add-on</option><option value="system" selected>System add-on</option></select></td>' +
				'<td><select style="width:100%" name="addons['+index+'][field_effect]" data-ringo-addon-effect><option value="none" selected>No form field</option><option value="gallery_images">Gallery photos</option><option value="video_fields">Video URL fields</option></select></td>' +
				'<td><input style="width:100%" type="number" min="0" step="1" name="addons['+index+'][effect_value]" value="0" data-ringo-addon-amount></td>' +
				'<td><input style="width:100%" type="number" step="1" name="addons['+index+'][sort]" value="100"></td>' +
				'<td><input type="checkbox" name="addons['+index+'][delete]" value="1"></td>';
			body.appendChild(row);
			syncRow(row);
		});

		var rows = body.querySelectorAll('[data-ringo-addon-row]');
		for (var i = 0; i < rows.length; i++) syncRow(rows[i]);
	})();
	</script>
	<?php
}
