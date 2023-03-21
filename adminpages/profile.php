<?php

/**
 * Allow admins to set Stripe Customer IDs for users and
 * access their Stripe Customer Portal.
 *
 * @since 1.0
 *
 * @param WP_User $user User being viewed.
 */
function rwstripe_edit_user_profile( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Get the meta key for the customer ID.
	$meta_key = rwstripe_get_meta_key( 'customer_id' );
	if ( empty( $meta_key ) ) {
		// We are not connected to a Stripe account.
		return;
	}

	$customer_id = rwstripe_get_customer_id_for_user( $user->ID );
	$rwstripe_stripe = RWStripe_Stripe::get_instance();
	?>
	<div class="rwstripe-edit-profile">
		<h2><?php esc_html_e( 'Restrict With Stripe', 'restrict-with-stripe' ); ?></h2>
		<table class="form-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Customer ID', 'restrict-with-stripe' ); ?></th>
					<td>
						<code><?php echo esc_html( $customer_id ); ?></code>
						<input type="input" style="display: none;" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( $customer_id ); ?>" />
						<a href="javascript:void(0);" id="rwstripe_edit_customer_id"><?php esc_html_e( 'edit', 'restrict-with_stripe' ); ?></a></td>
				</tr>
				<?php
					if ( ! empty( $customer_id ) ) {
						?>
						<tr>
							<th><?php esc_html_e( 'View Stripe Customer', 'restrict-with-stripe' ); ?></th>
							<td><a target="_blank" href="<?php echo esc_url( rwstripe_get_dashboard_link() . 'customers/' . $customer_id ); ?>"><?php echo esc_url( rwstripe_get_dashboard_link() . 'customers/' . $customer_id ); ?></a></td>
						</tr>
						<?php
					}
				?>
			</tbody>
		</table>
	</div>
	<?php
}
add_action( 'show_user_profile', 'rwstripe_edit_user_profile' );
add_action( 'edit_user_profile', 'rwstripe_edit_user_profile' );

/**
 * Save the Stripe Customer ID for a user.
 *
 * @since 1.0
 */
function rwstripe_user_profile_update() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! empty( $_REQUEST['user_id'] ) ) {
		$user_id = intval( $_REQUEST['user_id'] );
	} else {
		return;
	}

	$meta_key = rwstripe_get_meta_key( 'customer_id' );
	if ( ! empty( $meta_key ) && isset( $_REQUEST[ $meta_key ] ) ) {
		update_user_meta( $user_id, $meta_key, sanitize_text_field( $_REQUEST[ $meta_key ] ) );
	}
}
add_action( 'personal_options_update', 'rwstripe_user_profile_update' );
add_action( 'edit_user_profile_update', 'rwstripe_user_profile_update' );

/**
 * If a user's email address change, try to update it in Stripe.
 *
 * @since 1.0
 *
 * @param int $user_id ID of user whose email address changed.
 * @param WP_User $old_user Old user object.
 */
function rwstripe_user_email_change( $user_id, $old_user ) {
	// Check if the email address changed.
	$new_user = get_userdata( $user_id );
	if ( $new_user->user_email != $old_user->user_email ) {
		// Get the Stripe customer.
		$customer_id = rwstripe_get_customer_id_for_user( $user_id );

		// Update the Stripe customer email.
		if ( ! empty( $customer_id ) ) {
			$rwstripe_stripe = RWStripe_Stripe::get_instance();
			$rwstripe_stripe->update_customer_email( $customer_id, $new_user->user_email );
		}
	}
}
add_action( 'profile_update', 'rwstripe_user_email_change', 10, 2 );

/**
 * When creating a new user, give the option to set the Stripe Customer ID.
 *
 * @since 1.0.5
 */
function rwstripe_user_new_form() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$meta_key = rwstripe_get_meta_key( 'customer_id' );
	if ( empty( $meta_key ) ) {
		// We are not connected to a Stripe account.
		return;
	}

	?>
	<h2><?php esc_html_e( 'Restrict With Stripe', 'restrict-with-stripe' ); ?></h2>
	<p><?php esc_html_e( 'If you would like to link this new user to an existing Stripe Customer, you can enter the Stripe Customer ID here.', 'restrict-with-stripe' ); ?></p>
	<table class="form-table">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Stripe Customer ID', 'restrict-with-stripe' ); ?></th>
				<td>
					<input type="input" name="<?php echo esc_attr( $meta_key ); ?>" value="" />
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}
add_action( 'user_new_form', 'rwstripe_user_new_form' );

/**
 * When creating a new user, save the Stripe Customer ID.
 *
 * @since 1.0.5
 *
 * @param int $user_id ID of user being created.
 */
function rwstripe_user_new_form_save( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$meta_key = rwstripe_get_meta_key( 'customer_id' );
	if ( ! empty( $meta_key ) && isset( $_REQUEST[ $meta_key ] ) ) {
		update_user_meta( $user_id, $meta_key, sanitize_text_field( $_REQUEST[ $meta_key ] ) );
	}
}
add_action( 'user_register', 'rwstripe_user_new_form_save' );
