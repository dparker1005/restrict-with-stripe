<?php

define( "RWSTRIPE_STRIPE_API_VERSION", "2022-11-15" );

class RWStripe_Stripe {

	// Singlton class.
	private static $instance = null;

	/**
	 * Connect to Stripe.
	 *
	 * @since 1.0
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
	 * @since 1.0
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
	 * Get the Stripe account object.
	 *
	 * Includes branding settings.
	 *
	 * @since 1.0
	 *
	 * @return Stripe\Account|string The Stripe account object or an error message.
	 */
	public function get_account() {
		static $account;
		if ( ! isset( $account ) ) {
			try {
				$account = Stripe\Account::retrieve();
			} catch ( Exception $e ) {
				$account = $e->getMessage();
			}
		}
		return $account;
	}

	/**
	 * Get a price from Stripe.
	 *
	 * @since 1.0
	 *
	 * @param string $price_id to get.
	 * @return Stripe\Price|string error message or Stripe\Price object.
	 */
	public function get_price( $price_id ) {
		static $prices = array();
		if ( ! isset( $prices[ $price_id ] ) ) {
			try {
				$prices[ $price_id ] = Stripe\Price::retrieve( $price_id );
			} catch ( Exception $e ) {
				$prices[ $price_id ] = $e->getMessage();
			}
		}
		return $prices[ $price_id ];
	}

	/**
	 * Get all products from Stripe.
	 *
	 * @since 1.0
	 *
	 * @return Stripe\Product[]|string Array of Stripe\Product objects or error message.
	 */
	public function get_all_products() {
		static $products = null;
		if ( $products === null ) {
			try {
				$products = Stripe\Product::all( array( 
					'limit' => 100,
					'active' => true,
				) );
			} catch ( Exception $e ) {
				$products = $e->getMessage();
			}
		}
		return $products;
	}

	/**
	 * Get a product from Stripe.
	 *
	 * @since 1.0
	 *
	 * @param string $product_id to get.
	 * @return Stripe\Product|string error message or Stripe\Product object.
	 */
	public function get_product( $product_id ) {
		$all_products = $this->get_all_products();
		if ( is_string( $all_products ) ) {
			return 'Could not get products. ' . $all_products;
		}
		foreach ( $all_products->data as $product ) {
			if ( $product->id == $product_id ) {
				return $product;
			}
		}
		return 'Could not find product ' . $product_id;
	}

	/**
	 * Get the default price for a given product in Stripe. 
	 *
	 * @since 1.0
	 *
	 * @param string $product_id to get prices for.
	 * @return Stripe\Price|null|string The default price for the product, null if no default price exists, or error message.
	 */
	public function get_default_price_for_product( $product_id ) {
		$all_products = $this->get_all_products();
		if ( is_string( $all_products ) ) {
			return 'Could not get products. ' . $all_products;
		}
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
	 * @since 1.0
	 *
	 * @return Stripe\Price[]|string Array of Stripe\Price objects or error message.
	 */
	private function get_all_prices() {
		static $prices = null;
		if ( $prices === null ) {
			try {
				$prices = Stripe\Price::all( array( 'limit' => 100000 ) );
			} catch ( Exception $e ) {
				$prices = $e->getMessage();
			}
		}
		return $prices;
	}

	/**
	 * Create a new customer in Stripe with a given email address.
	 *
	 * @since 1.0
	 *
	 * @param string $email to create customer with.
	 * @return Stripe\Customer|string Stripe\Customer object or error message.
	 */
	public function create_customer_with_email( $email ) {
		try {
			$customer = Stripe\Customer::create( array( 'email' => $email ) );
		} catch ( Exception $e ) {
			$customer = $e->getMessage();
		}
		return $customer;
	}

	/**
	 * Update the email for a given customer in Stripe.
	 *
	 * @since 1.0
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
	 * @since 1.0
	 *
	 * @param string $customer_id to get URL for.
	 * @return Stripe\BillingPortal\Session|string Stripe\BillingPortal\Session object or error message.
	 */
	public function get_customer_portal_session( $customer_id ) {
		// Before we can send the user to the customer portal,
		// we need to have a portal configuration.
		$portal_configurations = array();
		try {
			// Get all active portal configurations.
			$portal_configurations = Stripe\BillingPortal\Configuration::all( array( 'active' => true, 'limit' => 100 ) );
		} catch( Exception $e ) {
			// Error getting portal configurations.
			return $e->getMessage();
		}

		// Check if one of the portal configurations is default.
		foreach ( $portal_configurations as $portal_configuration ) {
			if ( $portal_configuration->is_default ) {
				$portal_configuration_id = $portal_configuration->id;
				break;
			}
		}

		// If we still don't have a portal configuration, create one.
		if ( empty( $portal_configuration_id ) ) {
			$portal_configuration_params = array(
				'business_profile' => array(
					'headline' => esc_html__( 'Manage Your Subscriptions', 'restrict-with-stripe' ),
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
				// Error creating portal configuration.
				return $e->getMessage();
			}

			if ( ! empty( $portal_configuration ) ) {
				$portal_configuration_id = $portal_configuration->id;
			}
		}

		// Get the customer portal URL.
		try {
			$session = \Stripe\BillingPortal\Session::create([
				'customer' => $customer_id,
				'return_url' => get_site_url(),
				'configuration' => $portal_configuration_id,
			]);
			return $session;
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Get all subscriptions for a given customer.
	 *
	 * @since 1.0
	 *
	 * @param string $customer_id to get subscriptions for.
	 * @return Stripe\Subscription[]|string Array of Stripe\Subscription objects or error message.
	 */
	public function get_subscriptions_for_customer( $customer_id ) {
		static $cached_subscriptions = array();
		if ( isset( $cached_subscriptions[ $customer_id ] ) ) {
			return $cached_subscriptions[ $customer_id ];
		}

		try {
			$subscriptions = Stripe\Subscription::all( array( 'customer' => $customer_id ) );
		} catch ( Exception $e ) {
			$subscriptions = $e->getMessage();
		}

		$cached_subscriptions[ $customer_id ] = $subscriptions;
		return $subscriptions;
	}

	/**
	 * Check if a customer has an active subscription for a given product or has
	 * purchased it as a one-time payment.
	 *
	 * @since 1.0
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

		// Set up a variable to track errors if we get any.
		$error = '';

		// Check subscriptions for access.
		$subscriptions = $this->get_subscriptions_for_customer( $customer_id );
		if ( is_string( $subscriptions ) ) {
			$error .= __( 'Error getting subscriptions.', 'restrict-with-stripe' ) . ' ' . $subscriptions;
		} else {
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
		}

		// Check one-time payment invoices for access.
		try {
			$invoices = Stripe\Invoice::all( array( 'customer' => $customer_id, 'status' => 'paid' ) );
			foreach ( $invoices as $invoice ) {
				// If the invoice is part of a subscription, ignore it.
				if ( ! empty( $invoice->subscription ) ) {
					continue;
				}

				// Check if we are ignoring this invoice.
				if ( ! empty( $invoice->metadata ) && ! empty( $invoice->metadata['rwstripe-ignore'] ) ) {
					continue;
				}

				// Check if the invoice has any of the product IDs.
				foreach ( $invoice->lines as $line ) {
					if ( in_array( $line->price->product, $product_ids ) ) {
						return true;
					}
				}
			}
		} catch ( Exception $e ) {
			if ( ! empty( $error ) ) {
				$error .= ' ';
			}
			$error .= __( 'Error getting invoices.', 'restrict-with-stripe' ) . ' ' . $e->getMessage();
		}

		return empty( $error ) ? false : $error;
	}

	/**
	 * Create a checkout session.
	 *
	 * @since 1.0
	 *
	 * @param string $price_id to create session for.
	 * @param string $customer_id to create session for.
	 * @param string $redirect_url to redirect to after checkout.
	 * @return Stripe\Checkout\Session|string Stripe\Checkout\Session object or error message.
	 */
	public function create_checkout_session( $price_id, $customer_id, $redirect_url ) {
		$price = $this->get_price( $price_id );
		if ( is_string( $price ) ) {
			return 'Could not get price.' . $price;
		}

		// Set up line items.
		$line_items = array(
			array(
				'price' => $price_id,
				'quantity' => 1,
			),
		);

		// Remove anchors and query strings from the redirect URL.
		$cleaned_redirect_url = strtok( $redirect_url, '?' );
		$cleaned_redirect_url = strtok( $cleaned_redirect_url, '#' );

		// Set up checkout session params.
		$checkout_session_params = array(
			'customer' => $customer_id,
			'line_items' => $line_items,
			'success_url' => $cleaned_redirect_url,
			'cancel_url' => $cleaned_redirect_url,
		);

		if ( $price['type'] === 'recurring' ) {
			// If price is recurring, set up subscription params.
			$checkout_session_params['mode'] = 'subscription';
			$checkout_session_params['subscription_data'] = array(
				'application_fee_percent' => 2,
			);
			$checkout_session_params['payment_method_collection'] = 'if_required';
		} else {
			// If price is one-time, set up payment params.
			$checkout_session_params['mode'] = 'payment';
			$checkout_session_params['payment_intent_data'] = array(
				'application_fee_amount' => floor( $price['unit_amount'] * 0.02 ),
			);
			$checkout_session_params['invoice_creation']['enabled'] = true;
		}

		/**
		 * Filters the checkout session params.
		 *
		 * @since TBD
		 *
		 * @param array $checkout_session_params Checkout session params.
		 * @param Stripe\Price $price Price object.
		 * @param string $customer_id Customer ID.
		 * @param string $redirect_url Redirect URL.
		 * @return array
		 */
		$checkout_session_params = apply_filters( 'rwstripe_checkout_session_params', $checkout_session_params, $price, $customer_id, $redirect_url );

		try {
			return \Stripe\Checkout\Session::create( $checkout_session_params );
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}
}
