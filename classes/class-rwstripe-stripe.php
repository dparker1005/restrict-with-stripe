<?php

define( "RWSTRIPE_STRIPE_API_VERSION", "2020-03-02" );

class RWStripe_Stripe {

	// Singlton class.
	private static $instance = null;

	/**
	 * Connect to Stripe.
	 *
	 * @since TBD
	 */
	private function __construct() {
		$modules = array( 'curl', 'mbstring', 'json' );

		foreach ( $modules as $module ) {
			if ( ! extension_loaded( $module ) ) {
				// Missing a dependency.
				return;
			}
		}

        if ( ! class_exists( "Stripe\Stripe" ) ) {
			require_once( RWSTRIPE_DIR . "/includes/lib/Stripe/init.php" );
		}

		Stripe\Stripe::setApiKey( get_option( 'rwstripe_stripe_access_token', '' ) );
		Stripe\Stripe::setAPIVersion( RWSTRIPE_STRIPE_API_VERSION );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since TBD
	 *
	 * @return RWStripe_Stripe
	 */
	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new RWStripe_Stripe();
		}
		return self::$instance;
    }

	/**
	 * Get a price from Stripe.
	 *
	 * @since TBD
	 *
	 * @param string $price_id to get.
	 * @return Stripe\Price|null
	 */
	public function get_price( $price_id ) {
		static $prices = array();
		if ( ! isset( $prices[ $price_id ] ) ) {
			try {
				$prices[ $price_id ] = Stripe\Price::retrieve( $price_id );
			} catch ( Exception $e ) {
				$prices[ $price_id ] = null;
			}
		}
		return $prices[ $price_id ];
	}

	/**
	 * Get all products from Stripe.
	 *
	 * @since TBD
	 *
	 * @return Stripe\Product[] Array of Stripe\Product objects.
	 */
	public function get_all_products() {
		static $products = null;
		if ( $products === null ) {
			try {
				$products = Stripe\Product::all( array( 'limit' => 100 ) );
			} catch ( Exception $e ) {
				$products = array();
			}
		}
		return $products;
	}

	/**
	 * Get the default price for a given product in Stripe. 
	 *
	 * @since TBD
	 *
	 * @param string $product_id to get prices for.
	 * @return Stripe\Price|null The default price for the product or null if no default price exists.
	 */
	public function get_default_price_for_product( $product_id ) {
		$all_products = $this->get_all_products();
		foreach ( $all_products as $product ) {
			if ( $product->id == $product_id ) {
				$price_id = $product->default_price;
				if ( ! empty( $price_id ) ) {
					return $this->get_price( $price_id );
				}
			}
		}
		return null;
	}

	/**
	 * Get all prices in Stripe.
	 *
	 * TODO: Maybe trash this method.
	 *
	 * @since TBD
	 *
	 * @return Stripe\Price[] Array of Stripe\Price objects.
	 */
	private function get_all_prices() {
		static $prices = null;
		if ( $prices === null ) {
			try {
				$prices = Stripe\Price::all( array( 'limit' => 100000 ) );
			} catch ( Exception $e ) {
				$prices = array();
			}
		}
		return $prices;
	}

	/**
	 * Create a new customer in Stripe with a given email address.
	 *
	 * @since TBD
	 *
	 * @param string $email to create customer with.
	 * @return Stripe\Customer|null
	 */
	public function create_customer_with_email( $email ) {
		try {
			$customer = Stripe\Customer::create( array( 'email' => $email ) );
		} catch ( Exception $e ) {
			$customer = null;
		}
		return $customer;
	}

	/**
	 * Update the email for a given customer in Stripe.
	 *
	 * @since TBD
	 *
	 * @param string $customer_id to update.
	 * @param string $email to update with.
	 */
	public function update_customer_email( $customer_id, $email ) {
		try {
			$customer = Stripe\Customer::retrieve( $customer_id );
			$customer->email = $email;
			$customer->save();
		} catch ( Exception $e ) {
			// Do nothing.
		}
	}

	/**
	 * Get a Customer Portal URL for a given customer.
	 *
	 * @since TBD
	 *
	 * @param string $customer_id to get URL for.
	 * @return string|null
	 */
	public function get_customer_portal_url( $customer_id ) {
		// Before we can send the user to the customer portal,
		// we need to have a portal configuration.
		$portal_configurations = array();
		try {
			// Get all active portal configurations.
			$portal_configurations = Stripe\BillingPortal\Configuration::all( array( 'active' => true, 'limit' => 100 ) );
		} catch( Exception $e ) {
			// Error getting portal configurations. We'll try and create one below.
		}

		// Check if one of the portal configurations is default.
		foreach ( $portal_configurations as $portal_configuration ) {
			if ( $portal_configuration->is_default ) {
				$portal_configuration_id = $portal_configuration->id;
				break;
			}
		}

		// If we don't have a default portal configuration, check if this plugin has created one.
		if ( ! isset( $portal_configuration_id ) ) {
			$saved_portal_configuration_id = get_option( 'rwstripe_portal_configuration_id', '' );
			foreach( $portal_configurations as $portal_configuration ) {
				if ( $portal_configuration->id == $saved_portal_configuration_id ) {
					$portal_configuration_id = $portal_configuration->id;
					break;
				}
			}
		}

		// If we still don't have a portal configuration, create one.
		if ( ! isset( $portal_configuration_id ) ) {
			$portal_configuration_params = array(
				'business_profile' => array(
					'headline' => esc_html__( 'Manage Your Subscriptions', 'woocommerce-gateway-stripe' ),
				),
				'features' => array(
					'customer_update' => array( 'enabled' => true, 'allowed_updates' => array( 'address', 'phone', 'tax_id' ) ),
					'invoice_history' => array( 'enabled' => true ),
					'payment_method_update' => array( 'enabled' => true ),
					'subscription_cancel' => array( 'enabled' => true ),
					'subscription_pause' => array( 'enabled' => true ),
				),
			);
			try {
				$portal_configuration = Stripe\BillingPortal\Configuration::create( $portal_configuration_params );
			} catch( Exception $e ) {
				$portal_configuration = null;
			}

			if ( ! empty( $portal_configuration ) ) {
				$portal_configuration_id = $portal_configuration->id;
				update_option( 'rwstripe_portal_configuration_id', $portal_configuration_id );
			}
		}

		// If we still don't have a portal configuration, we can't create a customer portal URL.
		if ( ! isset( $portal_configuration_id ) ) {
			return null;
		}

		// Get the customer portal URL.
		try {
			$session = \Stripe\BillingPortal\Session::create([
				'customer' => $customer_id,
				'return_url' => get_site_url(),
				'configuration' => $portal_configuration_id,
			]);
			return $session->url;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Check if a customer has an active subscription for a given product or has
	 * purchased it as a one-time payment.
	 *
	 * @since TBD
	 *
	 * @param string $customer_id to check.
	 * @param string[] $product_ids to check.
	 * @return bool
	 */
	public function customer_has_product( $customer_id, $product_ids ) {
		// Make sure that $product_ids is an array.
		if ( ! is_array( $product_ids ) ) {
			$product_ids = array( $product_ids );
		}

		// Get all subscriptions for the customer.
		$subscription_params = array(
			'customer' => $customer_id,
			'limit' => 100,
		);
		try {
			$subscriptions = Stripe\Subscription::all( $subscription_params );
		} catch( Exception $e ) {
			$subscriptions = array();
		}

		// Check if the customer has an active subscription for any of the products.
		foreach( $subscriptions as $subscription ) {
			// Make sure that the subscription is active or trialing.
			if ( $subscription->status !== 'active' && $subscription->status !== 'trialing' ) {
				continue;
			}

			// Check if the subscription has any of the product IDs.
			foreach ( $subscription->items as $item ) {
				if ( in_array( $item->price->product, $product_ids ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get a $0 reccurring price for the given product.
	 * If one does not exist, create it.
	 *
	 * @since TBD
	 *
	 * @param string $product_id Product to get free recurring price for.
	 * @param string $currency   Currency to get price in.
	 * @return Stripe\Price|null
	 */
	private function get_free_recurring_price_for_product( $product_id, $currency ) {
		// Look for an existing price for the product.
		$prices = $this->get_all_prices();
		foreach ( $prices as $price ) {
			if ( $price->product === $product_id &&
				$price->unit_amount === 0 &&
				$price->currency === $currency &&
				$price->type === 'recurring' &&
				$price->active
			) {
				return $price;
			}
		}

		// No exising price found, create a new one.
		try {
			$price = Stripe\Price::create( array(
				'product' => $product_id,
				'unit_amount' => 0,
				'currency' => $curency,
				'recurring' => array(
					'interval' => 'year',
					'interval_count' => 1,
				),
			) );
		} catch ( Exception $e ) {
			$price = null;
		}
		return $price;
	}

	/**
	 * Create a checkout session.
	 *
	 * @since TBD
	 *
	 * @param string $price_id to create session for.
	 * @param string $customer_id to create session for.
	 * @param string $redirect_url to redirect to after checkout.
	 * @return Stripe\Checkout\Session|null
	 */
	public function create_checkout_session( $price_id, $customer_id, $redirect_url ) {
		$price = $this->get_price( $price_id );
		if ( empty( $price ) || empty( $customer_id ) || empty( $redirect_url ) ) {
			return;
		}

		// Set up line items.
		$line_items = array(
			array(
				'price' => $price_id,
				'quantity' => 1,
			),
		);

		// If price is a one-time payment, we also want to set up a free subscription to track access.
		if ( $price['type'] !== 'recurring' ) {
			$free_price = $this->get_free_recurring_price_for_product( $price['product'], $price['currency'] );
			if ( empty( $free_price ) ) {
				// Can't send user to checkout, access would not be given after payment.
				return;
			}
			$line_items[] = array(
				'price' => $free_price['id'],
				'quantity' => 1,
			);
		}
		
		$checkout_session_params = array(
			'customer' => $customer_id,
			'line_items' => $line_items,
			'mode' => 'subscription',
			'success_url' => $redirect_url,
			'cancel_url' => $redirect_url,
			'subscription_data' => array(
				'application_fee_percent' => 2,
			),
		);

		try {
			return \Stripe\Checkout\Session::create( $checkout_session_params );
		} catch ( Exception $e ) {
			return null;
		}
	}
}