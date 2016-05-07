<?php


class PayIQ {


	function __construct() {

		add_action( 'init', [ $this, 'init' ] );
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

		// Add custom action links
		add_filter( 'plugin_action_links_' . WC_PAYIQ_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

		add_action( 'admin_menu', [$this, 'add_plugin_menu'] );

		//add_action('admin_enqueue_scripts', array($this, 'admin_options_styles'));

		add_action( 'woocommerce_order_status_completed', [ $this, 'capture_transaction' ] );

		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'display_order_meta' ] );
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

		add_submenu_page(
			'woocommerce', __( 'PayIQ', 'payiq-wc-gateway' ), __( 'PayIQ', 'payiq-wc-gateway' ), 'manage_woocommerce', 'payiq-wc-gateway', [$this, 'display_debug_log_page']
		);
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

		wp_send_json( $response );
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
		?>
		<h3>Debug log</h3>

		<br/>
<textarea class="debug_log_view" disabled><?php echo $this->get_debug_log(); ?></textarea>
<style>
	.debug_log_view[disabled] {
		verflow: auto;
		height: 80vh;
		max-height: 100vh;
		width: 95%;
		padding: 20px 2%;
		color: #000;
	}
</style>
<script>
	jQuery('.debug_log_view').css('height', jQuery( window ).height() - (jQuery('.debug_log_view').offset().top + 100));
</script>

		<?php
	}



	function get_debug_log() {

		$logfile = wc_get_log_file_path( 'payiq' );

		echo $logfile;

		return file_get_contents( $logfile );

	}
}
