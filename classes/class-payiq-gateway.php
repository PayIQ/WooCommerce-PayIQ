<?php
/**
 * Class WC_Gateway_PayIQ
 */
class WC_Gateway_PayIQ extends WC_Payment_Gateway {

	protected $alternative_icon;
	protected $alternative_icon_width;
	protected $language;
	protected $payment_method;
	protected $auto_capture;
	protected $service_name;
	protected $shared_secret;
	protected $testmode;
    protected $testmode_service_name;
    protected $testmode_shared_secret;
	protected $debug;

	protected $api_client = null;

	public function __construct() {

		//parent::__construct();

		$this->id = 'payiq';
		$this->icon = '';
		$this->has_fields = false;
		$this->method_title = __( 'PayIQ', 'payiq-wc-gateway' );
		$this->method_description = __( 'PayIQ', 'payiq-wc-gateway' );

		$this->order_button_text  = __( 'Proceed to PayIQ', 'payiq-wc-gateway' );

		// Load the form fields for options page.
		$this->init_form_fields();

		// Load the settings using WooCommerce Settings API.
		$this->init_settings();

		// Define option variables
		$this->title                    = ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->description              = ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';

		$this->alternative_icon         = ( isset( $this->settings['alternative_icon'] ) ) ? $this->settings['alternative_icon'] : '';
		$this->alternative_icon_width   = ( isset( $this->settings['alternative_icon_width'] ) ) ? $this->settings['alternative_icon_width'] : '';
		$this->language                 = ( isset( $this->settings['language'] ) ) ? $this->settings['language'] : 'sv';
		$this->payment_method           = ( isset( $this->settings['payment_method'] ) ) ? $this->settings['payment_method'] : 'notset';
		$this->auto_capture             = ( isset( $this->settings['auto_capture'] ) ) ? $this->settings['auto_capture'] : '';

		$this->service_name             = ( isset( $this->settings['service_name'] ) ) ? $this->settings['service_name'] : '';
		$this->shared_secret            = ( isset( $this->settings['shared_secret'] ) ) ? $this->settings['shared_secret'] : '';

		$this->testmode                 = ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';
        $this->testmode_service_name    = ( isset( $this->settings['testmode_service_name'] ) ) ? $this->settings['testmode_service_name'] : '';
        $this->testmode_shared_secret   = ( isset( $this->settings['testmode_shared_secret'] ) ) ? $this->settings['testmode_shared_secret'] : '';

        $this->debug                    = ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : 'no';

		// Reqister hook for saving admin options
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		if ( class_exists( 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		} else {
			$this->debug = false;
		}

		//Register the support for the subscriptions
		$this->supports = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change'
		);

	}

	/**
	 * @param  $order WooCommerce order object or ID
	 * @return PayIQAPI PayIQ API wrapper
	 */
	function get_api_client( $order = null ) {

		if ( is_numeric( $order ) && intval( $order ) > 0 ) {
			$order = wc_get_order( $order );
		}

		if ( $order ) {

		}

		require_once WC_PAYIQ_PLUGIN_DIR . 'classes/class-soapclient.php';
		require_once WC_PAYIQ_PLUGIN_DIR . 'classes/class-payiq-api.php';

		$this->api_client = new PayIQAPI( $this->service_name, $this->shared_secret, $order, $this->debug );

		return $this->api_client;
	}

	/**
	 * Define option fields (WooCommerce Settings API)
	 */
	function init_form_fields() {

		$this->form_fields = [
			'enabled' => [
				'title'         => __( 'Enable/Disable', 'payiq-wc-gateway' ),
				'type'          => 'checkbox',
				'label'         => __( 'Enable PayIQ payment gateway', 'payiq-wc-gateway' ),
				'default'       => 'yes'
			],
			'title' => [
				'title'         => __( 'Title', 'payiq-wc-gateway' ),
				'type'          => 'text',
				'description'   => __( 'This is the title of the payment option that the user sees during checkout, in emails and order history.', 'payiq-wc-gateway' ),
				'default'       => __( 'PayIQ', 'payiq-wc-gateway' ),
				'desc_tip'      => true,
			],
			'description' => [
				'title'         => __( 'Description', 'payiq-wc-gateway' ),
				'type'          => 'textarea',
				'description'   => __( 'This is the description of the payment option that the user sees during checkout.', 'payiq-wc-gateway' ),
				'default'       => __( 'Pay via PayIQ using credit card or bank transfer.', 'payiq-wc-gateway' )
			],
			'alternative_icon'         => [
				'title'       => __( 'Alternative payment icon', 'payiq-wc-gateway' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Add the URL to an alternative payment icon that the user sees during checkout. Leave blank to use the default image. Alternative payment method logos can be found <a href="%s" target="_blank">here</a>.', 'payiq-wc-gateway' ), 'https://secure.payiq.se/customer/Support/Logos' ),
				'default'     => ''
			],
			'alternative_icon_width'   => [
				'title'       => __( 'Icon width', 'payiq-wc-gateway' ),
				'type'        => 'text',
				'description' => __( 'The width of the Alternative payment icon.', 'payiq-wc-gateway' ),
				'default'     => ''
			],
			'language' => [
				'title'         => __( 'Language', 'payiq-wc-gateway' ),
				'type'          => 'select',
				'options'       => [
					'en'            => __( 'English', 'payiq-wc-gateway' ),
					'fi'            => __( 'Finnish', 'payiq-wc-gateway' ),
					'no'            => __( 'Norwegian', 'payiq-wc-gateway' ),
					'sv'            => __( 'Swedish', 'payiq-wc-gateway' ),
				],
				'description' => __( 'Set the language in which the page will be opened when the customer is redirected to PayIQ.', 'payiq-wc-gateway' ),
				'default'     => 'sv'
			],
			/*
            'payment_method' => array(
                'title'         => __( 'Payment Method', 'payiq-wc-gateway' ),
                'type'          => 'select',
                'options'       => array(
                    'NotSet'        => __( 'Not set', 'payiq-wc-gateway' ),
                    'Card'          => __( 'Card payment', 'payiq-wc-gateway' ),
                    'Direct'        => __( 'Direct bank transfer', 'payiq-wc-gateway' )
                ),
                'description'   => __( '"Not set" allows the user to choose between card payment and direct bank transfer in the payment window', 'payiq-wc-gateway' ),
                'default'       => 'no'
            ),
            */
			'auto_capture' => [
				'title'         => __( 'Transaction capture', 'payiq-wc-gateway' ),
				'type'          => 'select',
				'options'       => [
					'yes'           => __( 'On Purchase', 'payiq-wc-gateway' ),
					'complete'      => __( 'On order completion', 'payiq-wc-gateway' ),
					'no'            => __( 'No', 'payiq-wc-gateway' )
				],
				'description'   => __( '"On purchase" means that the money in transferred from the customers account immediately. With "On order completion" the money is transferred when the order is marked as completed. With "No" the transfer needs to be triggered manually from the PayIQ admin.', 'payiq-wc-gateway' ),
				'default'       => 'no'
			],
			'service_name' => [
				'title'         => __( 'Service name', 'payiq-wc-gateway' ),
				'type'          => 'text',
				'description'   => __( 'Unique id of your integration. You get this from PayIQ.', 'payiq-wc-gateway' ),
				'default'       => ''
			],
			'shared_secret' => [
				'title'         => __( 'Shared secret', 'payiq-wc-gateway' ),
				'type'          => 'text',
				'description'   => __( 'Unique key for your integration. You get this from PayIQ.', 'payiq-wc-gateway' ),
				'default'       => ''
			],
			'proxy_ips' => [
				'title'         => __( 'Proxy IP-addresses', 'payiq-wc-gateway' ),
				'type'          => 'text',
				'description'   => __( 'If you are using a proxy such as Varnish or Nginx(as a proxy), we need to validate the client IPs for security reasons. Enter all proxy IPs here separated by comma.', 'payiq-wc-gateway' ),
				'default'       => ''
			],
            'testmode'    => array(
                'title'   => __( 'Test Mode', 'payiq-wc-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PayIQ Sandbox/Test Mode.', 'payiq-wc-gateway' ),
                'default' => 'yes'
            ),
            'testmode_service_name'                 => array(
                'title'   => __( 'Service Name Sandbox', 'payiq-wc-gateway' ),
                'type'    => 'text',
                'label'   => __( 'Service Name Sandbox.', 'payiq-wc-gateway' ),
                'default' => ''
            ),
            'testmode_shared_secret'                 => array(
                'title'   => __( 'Shared Secret Sandbox', 'payiq-wc-gateway' ),
                'type'    => 'text',
                'label'   => __( 'Shared Secret Sandbox.', 'payiq-wc-gateway' ),
                'default' => ''
            ),
			'debug'                    => [
				'title'   => __( 'Debug', 'payiq-wc-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging (<code>wp-content/uploads/wc-logs/payiq-*.log</code>)', 'payiq-wc-gateway' ),
				'default' => 'no'
			]

		];

	}

	/**
	 * Debug mode?
	 * @return bool
	 */
	function is_debug() {
		return ( $this->debug == 'yes' ? true : false );
	}


	/**
	 * Gets icon for display in payment method selector in checkout
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_html  = '';
		$icon_src   = '';
		$icon_width = '';
		if ( $this->alternative_icon ) {
			$icon_src   = $this->alternative_icon;
			$icon_width = $this->alternative_icon_width;
		} else {
			$icon_src   = WC_PAYIQ_PLUGIN_URL.'/assets/logo_small.png';
			$icon_width = '145';
		}

		$icon_html = '<img src="' . $icon_src . '" alt="PayIQ" style="max-width:' . $icon_width . 'px"/>';

		return apply_filters( 'wc_payiq_icon_html', $icon_html );
	}



	/**
	 * There are no payment fields for PayIQ, but we want to show the description if set.
	 **/
	function payment_fields() {
		$description = $this->get_description();

		if ( ! empty( $description ) ) {
			echo wpautop( wptexturize( $description ) );
		}
	}

	/**
	 * Process checkout
	 * @param int $order_id
	 * @return array
	 */
	function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status( 'pending', __( 'Awaiting PayIQ payment', 'payiq-wc-gateway' ) );

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		WC()->cart->empty_cart();

		$order_id = $order->id;


		$redirect_url = $this->get_payment_window_url( $order );

		$this->set_default_order_meta( $order );


		if ( ! empty( $redirect_url ) && strpos( '.payiq.se', $redirect_url ) !== false ) {

			return [
				'result' => 'fail',
				'redirect' => $redirect_url
			];
		}

		// Return redirect to payment window
		return [
			'result' => 'success',
			'redirect' => $redirect_url
		];
	}

	function set_default_order_meta( $order ) {

		$meta_fields = [
			'_payiq_order_reference'            => '',
			'_payiq_transaction_id'             => '',
			'_payiq_order_payment_method'       => '',
			'_payiq_order_payment_directbank'   => '',
			'_payiq_order_authorized'           => 'No',
			'_payiq_order_captured'             => 'No',
		];

		foreach ( $meta_fields as $meta_key => $meta_value ) {
			update_post_meta( $order->id, $meta_key, $meta_value );
		}
	}

	/**
	 * Send a PrepareSession to PayIQ API to initialize a payment and get the link to payment window
	 * @param $order
	 * @return bool|string
	 */
	function get_payment_window_url( $order ) {

		$api = $this->get_api_client();

		$api->setOrder( $order );

		$prepare_session_data = [
			'auto_capture' => $this->auto_capture,
		];

		$redirect_url = $api->prepareSession( $prepare_session_data );


		return $redirect_url;
	}

	/**
	 * Capture money for an open transaction. Should only be called when auto_capture = false
	 * @param $order
	 * @param $transaction_id
	 * @return bool
	 */
	function capture_transaction( $order, $transaction_id ) {

		$api = $this->get_api_client();

		$api->setOrder( $order );

		update_post_meta( $order->id, 'payiq_transaction_id', $transaction_id );

		if (preg_match( '/[^a-z_\-0-9]/i', $transaction_id ) ) {
			if ( $this->debug ) {
				$this->logger->add( 'payiq', 'Invalid transaction id: '.$transaction_id );
			}
			return false;
		}


		$data = $api->CaptureTransaction( $transaction_id );

		/*
         * Example response:
        Array
        (
            [Succeeded] => false
            [ErrorCode] => TransactionCannotBeManaged
            [AuthorizedAmount] => 0
            [SettledAmount] => 0
        )
        */

		if ( $data['Succeeded'] == 'false' ) {

			if ( $this->is_debug() ) {
				$this->logger->add( 'payiq', 'Capture transaction failed for order #'.$order->id.'. Reason: '. $data['ErrorCode'] );
			}
			$order->add_order_note( __( 'PayIQ callback failed. Error code: '.$data['ErrorCode'], 'payiq-wc-gateway' ) );
		}
		if ( $data['ErrorCode'] == 'TransactionCannotBeManaged' ) {

			if ( $this->is_debug() ) {
				$this->logger->add( 'payiq', 'Transaction alread captured for order #'.$order->id.'.' );
			}
		}
		elseif ( $data['SettledAmount'] != ($order->get_total() * 100) ) {

			if ( $this->is_debug() ) {
				$this->logger->add( 'payiq', 'SettledAmount does not match order total for #'.$order->id.'.' );
			}
		}
		else { //Everything seams to be ok

			if ( $this->is_debug() ) {
				$this->logger->add( 'payiq', 'Transaction captured for order #'.$order->id.'.' );
			}

			return true;
		}

		// TODO: Handle error

		return false;

	}

	function payment_failed( $order, $post_data ) {

		$this->logger = new WC_Logger();
		$this->logger->add( 'payiq', print_r( $order, true ));
		$this->logger->add( 'payiq', print_r( $post_data, true ));

		// Show notice for customer
		wc_add_notice( __( 'The payment failed. Please try another payment method or another card.', 'woocommerce-gateway-payiq' ), 'error' );

		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;

	}

	function cancel_order( $order, $post_data ) {

		$this->logger = new WC_Logger();
		$this->logger->add( 'payiq', print_r( $order, true ));
		$this->logger->add( 'payiq', print_r( $post_data, true ));


		if ( $order->status == 'pending' ) {


			$order->update_status(
				'pending',
				__(
					'PayIQ payment failed.'.
					'Either the customer canceled the payment or '.
					'there was not enough funds on the card to cover the order.'
					, 'payiq-wc-gateway'
				)
			);


			// Cancel order and restore stock
			//$order->cancel_order( __( 'Order cancelled by customer.', 'woocommerce-gateway-payiq' ) );



			wp_safe_redirect( $order->get_checkout_payment_url() );
			exit;

			// Show notice for customer
			wc_add_notice( __( 'Your order was cancelled.', 'woocommerce-gateway-payiq' ), 'error' );

		} elseif ( $order->status != 'pending' ) {

			$order->update_status(
				'processing',
				__(
					'PayIQ order could not be canceled.'
					, 'payiq-wc-gateway'
				)
			);
			wc_add_notice( __( 'Your order is not pending payment and could not be cancelled. If you think this is wrong, please contact us for assistance.', 'woocommerce-gateway-payiq' ), 'error' );

		} else {

			wc_add_notice( __( 'Invalid order.', 'woocommerce-gateway-payiq' ), 'error' );
		}

		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}


	public function validate_callback( $order, $post_data ) {

		$required_fields = [
			'servicename',
			'transactionid',
			'orderreference',
			'authorizedamount',
			'operationtype',
			//'currency',
			'operationamount',
			'settledamount',
			'message',
			'customername',
			'paymentmethod',
			'directbank',
			'subscriptionid',
			'checksum',
		];

		foreach ( $required_fields as $required_field ) {

			if ( ! isset( $_GET[$required_field] ) ) {


				$this->logger->add( 'payiq', 'Missing fields: ' . print_r( array_diff( $required_fields, array_keys( $_GET ) ), true ) );

				return false;
			}
		}

		$api = $this->get_api_client();
		$api->setOrder( $order );

		$checksum_valid = $api->validateChecksum( $post_data, $post_data['checksum'] );

		if ( $checksum_valid !== true ) {


			if ( ! isset( $_GET[$required_field] ) ) {

				$this->logger->add( 'payiq', 'Raw string: ' . $checksum_valid['raw_sting'] );
				$this->logger->add( 'payiq', 'Checksums: Generated: ' . $checksum_valid['generated'] . '  - Sent: ' . $post_data['checksum'] );


				return false;
			}
		}

		return true;
	}


	function process_callback( $order, $post_data ) {

		var_dump( $this->is_debug() );
		if ( $this->is_debug() ) {

			$this->logger->add( 'payiq', 'PayIQ callback URI: ' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] );
			$this->logger->add( 'payiq', 'PayIQ callback values: ' . print_r( $_GET, true ) );
		}

		if ( $this->validate_callback( $order, $post_data ) !== true ) {

			status_header( 400, 'Bad Request' );

			return [
				'status'    => 'error',
				'msg'       => 'Bad Request'
			];
		}

		// Callback is valid. Let's process it

		$order_id = $order->id;

		add_post_meta( $order_id, '_payiq_transaction_id', $post_data['transactionid'], true );
		add_post_meta( $order_id, '_transaction_id', $post_data['transactionid'], true );


		//Kolla om order innehåller subscription
		if(wcs_order_contains_subscription( $order )){

			//transactionId
			$transactionId = $post_data['transactionid'];

			$api_client = $this->get_api_client();

			$api_client->setOrder( $order );

			//Call upon GetTransactionDetails in class-payiq-api and get the subscription id from payiqs api
			$payiq_subscription_id = $api_client->GetTransactionDetails( $transactionId );



			add_post_meta( $order_id, '_payiq_subscription_id',  $payiq_subscription_id['SubscriptionId'], true );


		}


		if ( $order->status == 'completed' || $order->status == 'processing' ) {

			if ( $this->debug == 'yes' ) {
				$this->logger->add( 'payiq', 'Aborting, Order #' . $order->id . ' is already complete.' );
			}

			return [
				'status'    => 'error',
				'msg'       => 'Order already processed'
			];
		}

		if ( $post_data['operationtype'] == 'capture' ) {

			if ( $post_data['operationtype'] == $post_data['settledamount'] ) {

				$this->payment_captured( $order );

				return [
					'status'    => 'ok',
					'msg'       => 'authorized'
				];
			}
			else {

				$order->add_order_note( printf( __( 'Aborting, captured amount does not equal order amount for order #%d. Please check this order manually.', 'payiq-wc-gateway' ), $order->id ) );

				if ( $this->debug == 'yes' ) {
					$this->log->add( 'payiq', 'Aborting, captured amount does not equal order amount for order #' . $order->id . '. Please check this order manually.' );
				}

				return [
					'status'    => 'error',
					'msg'       => 'Order total does not match captured amount'
				];
			}
		} elseif ( $post_data['operationtype'] == 'authorize' ) {

			$this->payment_authorized( $order );

			return [
				'status'    => 'ok',
				'msg'       => 'authorized'
			];

		}

		return [
			'status'    => 'ok',
			'msg'       => ''
		];
	}

	private function payment_authorized( $order ) {

		update_post_meta( $order->id, '_payiq_order_authorized', 'yes' );
		$order->add_order_note( __( 'PayIQ transaction authorized.', 'payiq-wc-gateway' ) );
	}


	private function payment_captured( $order ) {

		$order->payment_complete();

		update_post_meta( $order->id, '_payiq_order_captured', 'yes' );
		$order->add_order_note( __( 'PayIQ transaction captured.', 'payiq-wc-gateway' ) );
	}





	/**
	 * Called on PayIQ callback to finalize the payment
	 */
	function payment_complete() {
/*
		print_r( $_REQUEST );

		die();*/

		$order_id = 0;

		$order = wc_get_order( $order_id );

		// Prepare redirect url
		$redirect_url = $order->get_checkout_order_received_url();

		// Return to Thank you page if this is a buyer-return-to-shop callback
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Process subscription payments to the billing periods ( triggered when billing period is fulfilled)
	 */
	function scheduled_subscription_payment( $order_total, $order ) {

		$charge = $this->process_subscription_payment( $order, $order_total );

		if ( is_wp_error( $charge ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, '' );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}

	}

	/**
	 * Process the subscription payment and return the result of the payment where success or not
	 *
	 * @access      public
	 * @param       int $amount
	 * @return      array
	 */
	public function process_subscription_payment(  $order, $amount_to_charge ) {

		//get instance
		$api_client = $this->get_api_client();
		$api_client->setOrder($order);

		// AuthorizeSubscription()
		$response = $api_client->AuthorizeSubscription();

		//Get transactionId from response
		$transaction_id = $response['TransactionId'];

		//capture_transaction
		$is_captured = $this->capture_transaction($order, $transaction_id);

		if($is_captured){
			$this->payment_captured($order);
			return true;
		}

		return false;
	}
}
