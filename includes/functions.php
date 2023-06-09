<?php

/**
 * Get the customer ID for a user and creates a new customer if one does not exist.
 * 
 * @since 1.0
 *
 * @param int $user_id User ID. Defaults to current user.
 *
 * @return string|null Customer ID or null if customer cannot be created.
 */
function rwstripe_get_customer_id_for_user( $user_id = null ) {
    // If no user ID is provided, use the current user.
    if ( empty( $user_id ) ) {
        global $current_user;
        $user_id = $current_user->ID;
    }

    // If we still don't have a user ID, bail.
    if ( empty( $user_id ) ) {
        return null;
    }

	// Get the Stripe account-specific meta key for customer IDs.
	$meta_key = rwstripe_get_meta_key( 'customer_id' );
	if ( empty( $meta_key ) ) {
		return null;
	}

    // Get the customer ID for the user.
    $customer_id = get_user_meta( $user_id, $meta_key, true );

    // If the user does not have a customer ID yet, create a new customer.
    if ( empty( $customer_id ) ) {
        $rwstripe = RWStripe_Stripe::get_instance();
        $user = get_userdata( $user_id );
        $new_customer = $rwstripe->create_customer_with_email( $user->user_email );
        if ( is_string( $new_customer ) ) {
            // If we cannot create a new customer, bail.
            return null;
        }
        $customer_id = $new_customer->id;
        update_user_meta( $user_id, $meta_key, $customer_id );
    }
    return $customer_id;
}

/**
 * Get the RWStripe meta key for the current Stripe account by appending
 * the account ID and environment to the passed base meta key.
 *
 * @since 1.0
 *
 * @param string $base_meta_key Base meta key.
 * @return string|null Meta key or null if account ID or environment is not set.
 */
function rwstripe_get_meta_key( $base_meta_key ) {
	// Get Stripe account ID and environment from options.
	$account_id = get_option( 'rwstripe_stripe_account_id' );
	$environment = get_option( 'rwstripe_stripe_environment' );
	if ( empty( $account_id ) || empty( $environment ) ) {
		return null;
	}

	return 'rwstripe_' . $base_meta_key . '_' . $account_id . '_' . $environment;
}

/**
 * Get a link to the Stripe Dashboard for the current Stripe account.
 *
 * @since 1.0
 *
 * @return string Link to the Stripe Dashboard.
 */
function rwstripe_get_dashboard_link() {
	// Get Stripe account ID and environment from options.
	$account_id = get_option( 'rwstripe_stripe_account_id' );
	$environment = get_option( 'rwstripe_stripe_environment' );

	$dashboard_url = 'https://dashboard.stripe.com/';
	if ( ! empty( $account_id ) ) {
		$dashboard_url .= $account_id . '/';
	}
	if ( $environment === 'test' ) {
		$dashboard_url .= 'test/';
	}
	return $dashboard_url;
}

/**
 * Register the restricted_product_ids post meta
 * so that it can be updated in the block editor.
 *
 * @since 1.0
 */
function rwstripe_register_post_meta() {
	$meta_key = rwstripe_get_meta_key( 'restricted_product_ids' );
	if ( empty( $meta_key ) ) {
		// Not connected to a Stripe account.
		return;
	}

	register_meta( 
		'post', 
		$meta_key, 
		array(
 			'type'		=> 'array',
 			'single'	=> true,
 			'show_in_rest'	=> array(
				'schema' => array(
					'type' => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
			),
 		)
	);
}
add_action( 'init', 'rwstripe_register_post_meta' );


/**
 * Output the "restricted content" message.
 *
 * @since 1.0
 *
 * @param array|string $product_ids The product IDs that restrict the content.
 */
function rwstripe_restricted_content_message( $product_ids ) {
	if ( ! is_array( $product_ids ) ) {
		$product_ids = array( $product_ids );
	}

	// Build an array of purchasable products.
	$purchasable_products = array();
	$errors = array();
	$RWStripe_Stripe = RWStripe_Stripe::get_instance();
	foreach ( $product_ids as $product_id ) {
		$product = $RWStripe_Stripe->get_product( $product_id );
		if ( ! empty( $product->default_price ) ) {
			$purchasable_products[] = $product;
		} elseif ( is_string( $product ) ) {
			$errors[] = $product;
		} else {
			$errors[] = sprintf( esc_html__( 'Product %s does not have a default price.', 'restrict-with-stripe' ), $product_id );
		}
	}

	// If the user is an admin and there are errors, show them.
	if ( current_user_can( 'manage_options' ) && ! empty( $errors ) ) {
		echo '<div>';
		echo '<h3>' . esc_html__( 'Admins Only', 'restrict-with-stripe' ) . '</h3>';
		echo '<p>' . esc_html__( 'The following errors occured while building the restricted content message:', 'restrict-with-stripe' ) . '</p>';
		echo '<ul>';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	// Build restricted content message.
	ob_start();
	?>
	<div class="rwstripe-checkout">
		<?php
		if ( empty( $purchasable_products ) ) {
			// No products available for purchase.
			esc_html_e( 'This product is not purchasable.', 'restrict-with-stripe' );
		} elseif ( ! is_user_logged_in() ) {
			// User not logged in. Show form to create account and purchase product.
			?>
			<div class="rwstripe-checkout-heading"><?php esc_html_e( 'Purchase Access', 'restrict-with-stripe' ); ?></div>

			<?php
				// Show a message if on a term archive.
				if ( is_category() || is_tag() ) { ?>
					<p><?php echo sprintf( esc_html__( 'Complete checkout now to access everything in %s.', 'restrict-with-stripe' ), '<em>' . get_the_archive_title() . '</em>' ); ?></p>
					<?php
				}

				// Show price if only one product is available.
				if ( count( $purchasable_products ) == 1 ) {
					$price = $RWStripe_Stripe->get_price( $purchasable_products[0]->default_price );
					echo rwstripe_format_price( $price );
				}

			?>
			<p><?php
                $allowed_html = array(
                    'a' => array (
                        'class' => array(),
                        'href' => array(),
                        'id' => array(),
                        'target' => array(),
                        'title' => array(),
                    ),
                );
                echo wp_kses( sprintf( __( 'Create a new account or <a href="%s">log in</a> to purchase access.', 'restrict-with-stripe' ), wp_login_url( get_permalink() ) ), $allowed_html );
            ?></p>
			<div class="rwstripe-error"></div>
			<form class="rwstripe-register">
				<input type="email" name="rwstripe-email" placeholder="<?php echo esc_attr( esc_html__( 'Email Address', 'restrict_with_stripe' ) ); ?>" />
				<?php
				// Maybe collect a password.
				if ( get_option( 'rwstripe_collect_password', true ) ) {
					?>
					<input type="password" name="rwstripe-password" placeholder="<?php esc_attr_e( 'Password', 'restrict_with_stripe' ); ?>" autocomplete="on" />
					<?php
				}

				// Show dropdown of products to purchase.
				rwstripe_restricted_content_message_render_product_dropdown( $purchasable_products );

				// Build text for submit button.
				$submit_text = esc_html__('Create Account &amp; Checkout', 'restrict-with-stripe' );
				?>
				<button type="submit" class="rwstripe-checkout-button"><?php echo esc_html( $submit_text ); ?></button>
			</form>
			<?php
		} else {
			// User is logged in. Show form to purchase product.
			?>
			<div class="rwstripe-checkout-heading"><?php esc_html_e( 'Purchase Access', 'restrict-with-stripe' ); ?></div>
			<?php
				// Show a message if on a term archive.
				if ( is_category() || is_tag() ) { ?>
					<p><?php echo sprintf( esc_html__( 'Complete checkout now to access everything in %s.', 'restrict-with-stripe' ), '<em>' . get_the_archive_title() . '</em>' ); ?></p>
					<?php
				}

				// Show price if only one product is available.
				if ( count( $purchasable_products ) == 1 ) {
					$price = $RWStripe_Stripe->get_price( $purchasable_products[0]->default_price );
					echo rwstripe_format_price( $price );
				}
			?>
			<div class="rwstripe-error"></div>
			<form class="rwstripe-register">
				<?php
				// Show dropdown of products to purchase.
				rwstripe_restricted_content_message_render_product_dropdown( $purchasable_products );

				// Build text for submit button.
				$submit_text = esc_html__('Checkout Now', 'restrict-with-stripe' );
				?>
				<button type="submit" class="rwstripe-checkout-button"><?php echo esc_html( $submit_text ); ?></button>
			</form>
			<?php
		}
		?>
	</div>
	<?php
	$restricted_content_message = ob_get_clean();

	/**
	 * Filter the restricted content message.
	 *
	 * @since TBD
	 *
	 * @param string $restricted_content_message The restricted content message.
	 * @param array  $purchasable_products       The purchasable products.
	 * @return string
	 */
	$restricted_content_message = apply_filters( 'rwstripe_restricted_content_message', $restricted_content_message, $purchasable_products );

	echo $restricted_content_message;
}

/**
 * Helper function for rendering the product dropdown in the restricted content message.
 *
 * @since 1.0
 *
 * @param array $purchasable_products The products to render in the dropdown.
 */
function rwstripe_restricted_content_message_render_product_dropdown( $purchasable_products ) {
	// If there are multiple purchasable products, show a dropdown of products.
	if ( count( $purchasable_products ) > 1 ) {
		$RWStripe_Stripe = RWStripe_Stripe::get_instance();
		?>
		<select class="rwstripe-select-product" name="rwstripe-product-id">
			<option value="">-- <?php esc_html_e( 'Choose one', 'restrict_with_stripe' ); ?> --</option>
			<?php
			foreach ( $purchasable_products as $product ) {
				$price = $RWStripe_Stripe->get_price( $product->default_price );
				?>
				<option value="<?php echo esc_attr( $product->default_price ); ?>"><?php echo esc_html( $product->name ) . ' (' . rwstripe_format_price( $price, true ) . ')'; ?></option>
				<?php
			}
			?>
		</select>
		<?php
	} else {
		?>
		<input type="hidden" name="rwstripe-product-id" value="<?php echo esc_attr( $purchasable_products[0]->default_price ); ?>" />
		<?php
	}
}
