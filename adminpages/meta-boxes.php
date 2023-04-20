<?php

/**
 * Display Restrict With Stripe meta box.
 * Will show in classic editor.
 *
 * @since 1.0.6
 */
function rwstripe_meta_box() {
    global $post;

    // Get all products from Stripe.
    $RWStripe_Stripe = RWStripe_Stripe::get_instance();
    $products = $RWStripe_Stripe->get_all_products();
    $meta_key = rwstripe_get_meta_key( 'restricted_product_ids' );
    if ( empty( $meta_key ) ) {
        // Not connected to Stripe.
        echo '<p>' . esc_html__( 'You must connect to Stripe to restrict content.', 'restrict-content-pro' ) . '</p>';
    } elseif ( is_string( $products ) ) {
        echo '<p>' . esc_html__( 'Error getting products.', 'restrict-with-stripe' ) . ' ' . esc_html( $products ) . '</p>';
    } else {
        // Get products that are already restricted.
        $restriction_meta = get_post_meta( $post->ID, $meta_key, true );
        if ( ! is_array( $restriction_meta ) ) {
            $restriction_meta = array();
        }

        // If we don't have any products, show a message.
        if ( empty( $products ) ) {
            echo '<p>' . esc_html__( 'No products found. Please create a product in Stripe.', 'restrict-with-stripe' ) . '</p>';
        }

        // If we have lots of products, put checkboxes in a scrollable div.
        if ( count( $products ) > 10 ) {
            ?>
            <div class="rwstripe-scrollable-div">
            <?php
        }

        // Render checkboxes for each product.
        foreach ( $products as $product ) {
            ?>
            <div class="rwstripe-clickable">
                <label for="<?php echo esc_attr( $product->id ); ?>">
                    <input type="checkbox" id="<?php echo esc_attr( $product->id ); ?>" name="<?php echo esc_attr( $meta_key ); ?>[]" value="<?php echo esc_attr( $product->id ); ?>" <?php checked( in_array( $product->id, $restriction_meta ) ); ?> >
                    <?php 
                        echo esc_html( $product->name );
                        if ( empty( $product->default_price ) ) {
                            echo ' (' . esc_html__( 'no default price set', 'restrict-with-stripe' ) . ')';
                        }
                    ?>
                </label>
            </div>
            <?php
        }

        // Close scrollable div.
        if ( count( $products ) > 10 ) {
            ?>
            </div>
            <?php
        }

        ?><p><a href="<?php echo esc_url( rwstripe_get_dashboard_link() . 'products/?active=true' ); ?>" target="_blank"><?php esc_html_e( 'Manage Products in Stripe', 'restrict-with-stripe' ); ?></a></p><?php

        // Add a nonce field so we can check for it later.
        wp_nonce_field( 'rwstripe_save_meta_box', 'rwstripe_meta_box_nonce' );
    }
}

/**
 * Save Restrict With Stripe meta box.
 *
 * @since 1.0.6
 */
function rwstripe_save_meta_box() {
    global $post;

    // Check our nonce.
    if ( ! isset( $_POST['rwstripe_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['rwstripe_meta_box_nonce'], 'rwstripe_save_meta_box' ) ) {
        return;
    }

    // Get the meta key for restricted products.
    $meta_key = rwstripe_get_meta_key( 'restricted_product_ids' );
    if ( empty( $meta_key ) ) {
        // Not connected to Stripe.
        return;
    }

    // Get the products that are checked.
    $product_ids = isset( $_POST[ $meta_key ] ) ? array_map( 'sanitize_text_field', (array) $_POST[ $meta_key ] ) : array();
	$product_ids = array_map( 'trim', $product_ids );
	$product_ids = array_filter( $product_ids );
	$product_ids = array_unique( $product_ids );

    // Save products to post meta.
    update_post_meta( $post->ID, $meta_key, $product_ids );
}

/**
 * Wrapper to add meta boxes for classic editor.
 *
 * @since 1.0.6
 */
function rwstripe_meta_box_wrapper() {
    // If the block editor is being used, skip adding the meta boxes.
	$current_screen = get_current_screen();
	if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
		return;
	}
	add_action( 'save_post', 'rwstripe_save_meta_box' );
	add_meta_box( 'rwstripe_meta_box', __( 'Restrict With Stripe', 'restrict-with-stripe' ), 'rwstripe_meta_box', 'page', 'side', 'high' );
	add_meta_box( 'rwstripe_meta_box', __( 'Restrict With Stripe', 'restrict-with-stripe' ), 'rwstripe_meta_box', 'post', 'side', 'high' );
}
add_action( 'current_screen', 'rwstripe_meta_box_wrapper' );
