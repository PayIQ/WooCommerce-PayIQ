<?php


class PayIQ {


	function __construct() {

		add_action( 'init', [ $this, 'init' ] );

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

	function log_callback( $type = '' ) {

		$logger = new WC_Logger();

		$logger->add(
			'payiq',
			PHP_EOL.PHP_EOL .
			(!empty( $type ) ? 'Callback type: ' . $type . PHP_EOL : '') .
			'Callback URI: '.$_SERVER['REQUEST_URI'] .
			PHP_EOL.PHP_EOL .
			'Callback params: '.print_r($_REQUEST, true) .
			PHP_EOL.PHP_EOL
		);

	}


	function init() {

		$request_uri = $_SERVER['REQUEST_URI'];

		if( preg_match( '/\/woocommerce\/payiq-(callback|success|failure)/', $request_uri, $match ) )
		{
			$type = $match[1];

			$this->log_callback( $type );


			switch( $type ) {

				case 'failure' :

					$this->process_failed( );
					break;
				case 'success' :

					$this->process_success( );


					break;
				case 'callback' :

					$this->process_callback( );

					break;
			}

		}
		//die();
		// Add custom action links
		add_filter( 'plugin_action_links_' . WC_PAYIQ_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

		add_action( 'admin_menu', [$this, 'add_plugin_menu'] );

		//add_action('admin_enqueue_scripts', array($this, 'admin_options_styles'));

		add_action( 'woocommerce_order_status_completed', [ $this, 'capture_transaction' ] );

		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'display_order_meta' ] );

		add_action('woocommerce_scheduled_subscription_payment_payiq', [ $this, 'trigger_subscription_payments' ], 10, 2);

	}

	function display_order_meta() {

		global $theorder, $post;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		$order = $theorder;

		$meta_keys = [
			'_payiq_order_reference'            => __( 'Order reference', 'payiq-wc-gateway' ),
			'_payiq_transaction_id'             => __( 'Transaction ID', 'payiq-wc-gateway' ),
			'_payiq_order_payment_method'       => __( 'Payment method', 'payiq-wc-gateway' ),
			'_payiq_order_payment_directbank'   => __( 'Bank', 'payiq-wc-gateway' ),
			'_payiq_order_authorized'           => __( 'Authorized', 'payiq-wc-gateway' ),
			'_payiq_order_captured'             => __( 'Captured', 'payiq-wc-gateway' ),
		];

		?>
		<div class="" style="clear: both; padding-top: 10px">
			<h4>PayIQ</h4><p>

			<?php foreach ( $meta_keys as $meta_key => $label ) :

				$meta_value = get_post_meta( $order->id, $meta_key, true );

				if ( ! empty( $meta_value ) ) : ?>

					<?php echo $label; ?>: <?php echo $meta_value; ?><br/>

				<?php endif; ?>
			<?php endforeach; ?>
			</p></div>
		<?php
	}

	function add_plugin_menu() {

		if( self::get_gateway_options('debug') == 'yes' ) {
			add_submenu_page(
				'woocommerce', __( 'PayIQ', 'payiq-wc-gateway' ), __( 'PayIQ', 'payiq-wc-gateway' ), 'manage_woocommerce', 'payiq-wc-gateway', [$this, 'display_debug_log_page']
			);
		}
	}

	function add_action_links( $links ) {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payiq' ) . '">' . __( 'Settings', 'payiq-wc-gateway' ) . '</a>',
		];

		// Merge our new link with the default ones
		return array_merge( $plugin_links, $links );
	}

	function get_order_from_reference( $order_id = false ) {

		if( $order_id == false ) {
			$order_id = $_GET['orderreference'];
		}


		//$order_id = intval( str_replace( 'order', '', $order_id ) );
		$order_id = intval( $order_id );

		if ( $order_id > 0 ) {
			return wc_get_order( $order_id );
		}
		else {
			return false;
		}
	}

	function process_success() {

		$order = $this->get_order_from_reference();
		//$order = new WC_Order( $order->id );


		if( $order === false )
		{
			return;
		}

		//$order->update_status('processing', __('Order paid with PayIQ', 'payiq-wc-gateway'));
		$order->payment_complete();

		$success_url = $order->get_checkout_order_received_url();

		wp_redirect( $success_url );
		exit;
	}

	function process_failed() {

		$order = $this->get_order_from_reference();

		if( $order === false )
		{
			return;
		}

		$gateway = new WC_Gateway_PayIQ();
		$gateway->payment_failed( $order, stripslashes_deep( $_GET ) );
		//$gateway->cancel_order( $order, stripslashes_deep( $_GET ) );
	}

	function process_callback() {

		$order = $this->get_order_from_reference( $_GET['orderreference'] );

		if( $order === false )
		{
			return;
		}


		$gateway = new WC_Gateway_PayIQ();

		//$gateway->validate_callback( $order, stripslashes_deep( $_GET ) );
		$response = $gateway->process_callback( $order, stripslashes_deep( $_GET ) );

		if($response['status'] == 'ok')
		{
			echo 'OK';
			die();
		}
		wp_send_json( $response );
	}

	function trigger_subscription_payments( $order_total, $order ) {
		global $woocommerce;

		//$id = '1654';

		if($order_total == 0){
			$order_total = get_post_meta($order->id, '_order_total',  true );
		}

		$order->update_status( 'pending', __( 'Awaiting PayIQ payment', 'payiq-wc-gateway' ) );

		//$subscription_period = WC_Subscriptions_Order::get_subscription_period( $order );

/*		$sign_up_fee = WC_Subscriptions_Order::get_sign_up_fee( $order );

		$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $order );

		$order_total = WC_Subscriptions_Order::get_recurring_total( $order );

		$subscription_interval = WC_Subscriptions_Order::get_subscription_interval( $order );

		$subscription_installments = WC_Subscriptions_Order::get_subscription_length( $order ) / $subscription_interval;

		$subscription_trial_length = WC_Subscriptions_Order::get_subscription_trial_length( $order );*/

/*		foreach ( $order->get_items() as $item ) { /* This loops through each item in the order
			$date = WC_Subscriptions_Order::get_next_payment_date ( $order, $item['product_id'] ); // This should get the next payment date...
			if ( $date ) {
				echo '<p style="margin: 16px 0 8px; text-align: left;">Your next payment is due on <strong>' . date( 'l jS F Y', strtotime( $date ) ) . '</strong></p>';
			}
		}*/
		//$order = $this->get_order_from_reference( $_GET['orderreference'] );
		//$amount_to_charge = WC_Subscriptions_Order::get_recurring_total( $order );
		//$order = new WC_Order( $order->id );

		//Add order to the log list under woocommerce->payiq
		$logger = new WC_Logger();
		$logger->add( 'payiq', 'Invalid IP: '.print_r($order, true) . 'trigger_sub_payment');
		$logger->add( 'payiq', 'ORDER ID: '. print_r($order->id, true) . 'trigger_sub_payment');
		//$logger->add( 'payiq', 'SUB ID: '. print_r($subscription) . 'trigger_sub_payment');

		//Take out the subscription id from the order
		$sub_id = get_post_meta($order->id, '_subscription_renewal',  true );

		//take out the parent id from the subscription to get the parent order
		$sub_id_parent = wp_get_post_parent_id( $sub_id );

		//Get the payiq subscription id from parent order
		$payiq_sub_id = get_post_meta($sub_id_parent, '_payiq_subscription_id',  true );



		//Add the payiq subscriptionid to postmeta
		add_post_meta($order->id, '_payiq_subscription_id', $payiq_sub_id,  true);

		if( $order === false )
		{
			return;
		}
		$gateway = new WC_Gateway_PayIQ();

		//Call on schedule subscription payment
		$gateway->scheduled_subscription_payment( $order_total, $order);

	}

	/**
	 * Capture transaction
	 */
	function capture_transaction( $order ) {

		if ( is_numeric( $order ) && (int) $order > 0 ) {

			$order = wc_get_order( $order );
		}

		$gateway = new WC_Gateway_PayIQ();

		return $gateway->capture_transaction( $order, $_GET['transactionid'] );
	}


	function display_debug_log_page() {

		if( self::get_gateway_options('debug') == 'yes' ) {
			self::get_view( 'debug-log' );
		}
	}

	static function get_gateway_options( $key ) {

		$options = get_option( 'woocommerce_payiq_settings', array() );

		if( key_exists( $key, $options ) ) {
			return $options[$key];
		}
		return $options;
	}

	static function get_view( $view ) {

		$view_path = WC_PAYIQ_PLUGIN_DIR . 'views/' . $view . '.php';

		if( file_exists( $view_path )) {
			require $view_path;
		}
	}

	static function get_debug_log() {

		$logfile = wc_get_log_file_path( 'payiq' );

		if( !is_writable( $logfile ) || ( !file_exists($logfile) && !touch( $logfile ) ) ) {
			return __( 'Debug log not writable', 'payiq-wc-gateway' );
		}

		return file_get_contents( $logfile );

	}
}
