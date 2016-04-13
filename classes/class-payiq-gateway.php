<?php
/**
 * Class WC_Gateway_Dibs_CC
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
    protected $debug;

    protected $api_client = null;

    public function __construct()
    {
        //parent::__construct();

        $this->id = 'payiq';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __( "PayIQ", 'woocommerce-gateway-payiq' );
        $this->method_description = __( "PayIQ", 'woocommerce-gateway-payiq' );

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
        $this->debug                    = ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : 'no';

        // Reqister hook for saving admin options
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );


        if( class_exists( 'WC_Logger' ) ) {
            $this->logger = new WC_Logger();
        } else {
            $this->debug = false;
        }
    }


    /**
     * @param  $order WooCommerce order object or ID
     * @return PayIQAPI PayIQ API wrapper
     */
    function get_api_client( $order = null ) {

        if( is_numeric($order) && intval($order) > 0) {
            $order = wc_get_order( $order );
        }

        if( $order ) {

        }

        require_once WC_PAYIQ_PLUGIN_DIR . 'classes/class-soapclient.php';
        require_once WC_PAYIQ_PLUGIN_DIR . 'classes/class-payiq-api.php';

        $this->api_client = new PayIQAPI( $this->service_name, $this->shared_secret, $order, $this->debug );

        return $this->api_client;
    }

    /**
     * Define option fields (WooCommerce Settings API)
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'woocommerce-gateway-payiq' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable PayIQ payment gateway', 'woocommerce-gateway-payiq' ),
                'default'       => 'yes'
            ),
            'title' => array(
                'title'         => __( 'Title', 'woocommerce-gateway-payiq' ),
                'type'          => 'text',
                'description'   => __( 'This is the title of the payment option that the user sees during checkout, in emails and order history.', 'woocommerce-gateway-payiq' ),
                'default'       => __( "PayIQ", 'woocommerce-gateway-payiq' ),
                'desc_tip'      => true,
            ),
            'description' => array(
                'title'         => __( 'Description', 'woocommerce-gateway-payiq' ),
                'type'          => 'textarea',
                'description'   => __( 'This is the description of the payment option that the user sees during checkout.', 'woocommerce-gateway-payiq' ),
                'default'       => __( "Pay via PayIQ using credit card or bank transfer.", 'woocommerce-gateway-payiq' )
            ),
            'alternative_icon'         => array(
                'title'       => __( 'Alternative payment icon', 'woocommerce-gateway-payiq' ),
                'type'        => 'text',
                'description' => sprintf( __( 'Add the URL to an alternative payment icon that the user sees during checkout. Leave blank to use the default image. Alternative payment method logos can be found <a href="%s" target="_blank">here</a>.', 'woocommerce-gateway-payiq' ), 'https://secure.payiq.se/customer/Support/Logos' ),
                'default'     => ''
            ),
            'alternative_icon_width'   => array(
                'title'       => __( 'Icon width', 'woocommerce-gateway-payiq' ),
                'type'        => 'text',
                'description' => __( 'The width of the Alternative payment icon.', 'woocommerce-gateway-payiq' ),
                'default'     => ''
            ),
            'language' => array(
                'title'         => __( 'Language', 'woocommerce-gateway-payiq' ),
                'type'          => 'select',
                'options'       => array(
                    'en'            => __( 'English', 'woocommerce-gateway-payiq' ),
                    'fi'            => __( 'Finnish', 'woocommerce-gateway-payiq' ),
                    'no'            => __( 'Norwegian', 'woocommerce-gateway-payiq' ),
                    'sv'            => __( 'Swedish', 'woocommerce-gateway-payiq' ),
                ),
                'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'woocommerce-gateway-payiq' ),
                'default'     => 'sv'
            ),
            /*
            'payment_method' => array(
                'title'         => __( 'Payment Method', 'woocommerce-gateway-payiq' ),
                'type'          => 'select',
                'options'       => array(
                    'NotSet'        => __( 'Not set', 'woocommerce-gateway-payiq' ),
                    'Card'          => __( 'Card payment', 'woocommerce-gateway-payiq' ),
                    'Direct'        => __( 'Direct bank transfer', 'woocommerce-gateway-payiq' )
                ),
                'description'   => __( '"Not set" allows the user to choose between card payment and direct bank transfer in the payment window', 'woocommerce-gateway-payiq' ),
                'default'       => 'no'
            ),
            */
            'auto_capture' => array(
                'title'         => __( 'Transaction capture', 'woocommerce-gateway-payiq' ),
                'type'          => 'select',
                'options'       => array(
                    'yes'           => __( 'On Purchase', 'woocommerce-gateway-payiq' ),
                    'complete'      => __( 'On order completion', 'woocommerce-gateway-payiq' ),
                    'no'            => __( 'No', 'woocommerce-gateway-payiq' )
                ),
                'description'   => __( '"On purchase" means that the money in transferred from the customers account immediately. With "On order completion" the money is transferred when the order is marked as completed. With "No" the transfer needs to be triggered manually from the PayIQ admin.', 'woocommerce-gateway-payiq' ),
                'default'       => 'no'
            ),
            'service_name' => array(
                'title'         => __( 'Service name', 'woocommerce-gateway-payiq' ),
                'type'          => 'text',
                'description'   => __( 'Unique id of your integration. You get this from PayIQ.', 'woocommerce-gateway-payiq' ),
                'default'       => ''
            ),
            'shared_secret' => array(
                'title'         => __( 'Shared secret', 'woocommerce-gateway-payiq' ),
                'type'          => 'text',
                'description'   => __( 'Unique key for your integration. You get this from PayIQ.', 'woocommerce-gateway-payiq' ),
                'default'       => ''
            ),
            'proxy_ips' => array(
                'title'         => __( 'Proxy IP-addresses', 'woocommerce-gateway-payiq' ),
                'type'          => 'text',
                'description'   => __( 'If you are using a proxy such as Varnish or Nginx(as a proxy), we need to validate the client IPs for security reasons. Enter all proxy IPs here separated by comma.', 'woocommerce-gateway-payiq' ),
                'default'       => ''
            ),
            /*
            'testmode'                 => array(
                'title'   => __( 'Test Mode', 'woocommerce-gateway-payiq' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable PayIQ Sandbox/Test Mode.', 'woocommerce-gateway-payiq' ),
                'default' => 'yes'
            ),
            */
            'debug'                    => array(
                'title'   => __( 'Debug', 'woocommerce-gateway-dibs' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable logging (<code>wp-content/uploads/wc-logs/payiq-*.log</code>)', 'woocommerce-gateway-dibs' ),
                'default' => 'no'
            )


        );

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

        return apply_filters( 'wc_dibs_icon_html', $icon_html );
    }



    /**
     * There are no payment fields for PayIQ, but we want to show the description if set.
     **/
    function payment_fields() {
        $description = $this->get_description();

        if ( !empty( $description ) ) {
            echo wpautop( wptexturize( $description ) );
        }
    }

    /**
     * Process checkout
     * @param int $order_id
     * @return array
     */
    function process_payment( $order_id )
    {
        $order = wc_get_order( $order_id );

        // Mark as on-hold (we're awaiting the cheque)
        $order->update_status('on-hold', __( 'Awaiting PayIQ payment', 'woocommerce-gateway-payiq' ));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Remove cart
        WC()->cart->empty_cart();

        $redirect_url = $this->get_payment_window_url( $order );

        $this->set_default_order_meta( $order );

        if( !empty( $redirect_url ) && strpos( 'secure.payiq.se', $redirect_url ) !== false ) {

            return array(
                'result' => 'fail',
                'redirect' => $redirect_url
            );
        }

        // Return redirect to payment window
        return array(
            'result' => 'success',
            'redirect' => $redirect_url
        );
    }

    function set_default_order_meta( $order ) {

        $meta_fields = array(
            '_payiq_order_reference'            => '',
            '_payiq_transaction_id'             => '',
            '_payiq_order_payment_method'       => '',
            '_payiq_order_payment_directbank'   => '',
            '_payiq_order_authorized'           => 'No',
            '_payiq_order_captured'             => 'No',
        );

        foreach( $meta_fields as $meta_key => $meta_value ) {
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

        $prepare_session_data = array(
            'auto_capture' => $this->auto_capture,
        );

        $redirect_url = $api->prepareSession( $prepare_session_data );

        return $redirect_url;
    }

    /**
     * Capture money for an open transaction. Should only be called when auto_capture = false
     * @param $order
     * @param $transaction_id
     * @return bool
     */
    function capture_transaction($order, $transaction_id ) {

        $api = $this->get_api_client();

        $api->setOrder( $order );

        update_post_meta( $order->id, 'payiq_transaction_id', $_GET['transactionid'] );

        if(preg_match('/[^a-z_\-0-9]/i', $transaction_id))
        {
            if( $this->debug ) {
                $this->logger->add( 'payiq', 'Invalid transaction id: '.$transaction_id );
            }
            return false;
        }

        $client_ip = $this->get_client_ip();

        if( !$client_ip ) {
            if( $this->debug ) {
                $this->logger->add( 'payiq', 'Invalid IP: '.$client_ip.' (If you use a proxy you should add the IP to the allowed proxies field)' );
            }
            return false;
        }

        $data = $api->CaptureTransaction( $transaction_id, $client_ip );

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

        if( $data['Succeeded'] == 'false' ) {

            if( $this->is_debug() ) {
                $this->logger->add( 'payiq', 'Capture transaction failed for order #'.$order->id.'. Reason: '. $data['ErrorCode'] );
            }
            $order->add_order_note( __('PayIQ callback failed. Error code: '.$data['ErrorCode'], 'woocommerce-gateway-payiq') );
        }
        if( $data['ErrorCode'] == 'TransactionCannotBeManaged' ) {

            if( $this->is_debug() ) {
                $this->logger->add( 'payiq', 'Transaction alread captured for order #'.$order->id.'.' );
            }
        }
        elseif( $data['SettledAmount'] != ($this->order->get_total() * 100) ) {

            if( $this->is_debug() ) {
                $this->logger->add( 'payiq', 'SettledAmount does not match order total for #'.$order->id.'.' );
            }
        }
        else { //Everything seams to be ok

            if( $this->is_debug() ) {
                $this->logger->add( 'payiq', 'Transaction captured for order #'.$order->id.'.' );
            }

            return true;
        }

        // TODO: Handle error

        return false;

    }

    function cancel_order( $order, $post_data ) {


        if ( $order->status == 'pending' ) {

            // Cancel order and restore stock
            $order->cancel_order( __( 'Order cancelled by customer.', 'woocommerce-gateway-payiq' ) );

            // Show notice for customer
            wc_add_notice( __( 'Your order was cancelled.', 'woocommerce-gateway-payiq' ), 'error' );

        } elseif ( $order->status != 'pending' ) {

            wc_add_notice( __( 'Your order is not pending payment and could not be cancelled. If you think this is wrong, please contact us for assistance.', 'woocommerce-gateway-payiq' ), 'error' );

        } else {

            wc_add_notice( __( 'Invalid order.', 'woocommerce-gateway-payiq' ), 'error' );
        }

        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    public function validate_callback($order, $post_data ) {

        $required_fields = array (
            'servicename',
            'transactionid',
            'orderreference',
            'authorizedamount',
            'operationtype',
            'currency',
            'operationamount',
            'settledamount',
            'message',
            'customername',
            'paymentmethod',
            'directbank',
            'subscriptionid',
            'checksum',
        );

        foreach( $required_fields as $required_field ) {

            if( !isset( $_GET[$required_field] ) ) {

                $this->logger->add( 'payiq', 'Missing fields: ' . print_r( array_diff( $required_fields, array_keys($_GET)), true ) );

                return false;
            }
        }

        $api = $this->get_api_client();
        $api->setOrder( $order );

        $checksum_valid = $api->validateChecksum( $post_data, $post_data['checksum'] );

        if( $checksum_valid  !== true ) {

            if( !isset( $_GET[$required_field] ) ) {

                $this->logger->add( 'payiq', 'Raw string: ' . $checksum_valid['raw_sting'] );
                $this->logger->add( 'payiq', 'Checksums: Generated: ' . $checksum_valid['generated'] . '  - Sent: ' . $post_data['checksum']);

                return false;
            }
        }

        return true;
    }


    function process_callback( $order, $post_data ) {

        var_dump($this->is_debug());
        if( $this->is_debug() ) {

            $this->log->add( 'payiq', 'PayIQ callback URI: ' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] );
            $this->log->add( 'payiq', 'PayIQ callback values: ' . print_r($_GET, true) );
        }


        if( $this->validate_callback( $order, $post_data ) !== true ) {

            status_header(400, 'Bad Request');

            return array(
                'status'    => 'error',
                'msg'       => 'Bad Request'
            );
        }

        // Callback is valid. Let's process it

        $order_id = $order->id;

        add_post_meta( $order_id, '_payiq_transaction_id', $post_data['transactionid'], true );
        add_post_meta( $order_id, '_transaction_id', $post_data['transactionid'], true );



        if ( $order->status == 'completed' || $order->status == 'processing' ) {

            if ( $this->debug == 'yes' ) {
                $this->log->add( 'payiq', 'Aborting, Order #' . $order->id . ' is already complete.' );
            }

            return array(
                'status'    => 'error',
                'msg'       => 'Order already processed'
            );
        }

        if( $post_data['operationtype'] == 'capture' ) {

            if( $post_data['operationtype'] == $post_data['settledamount'] ) {

                $this->payment_captured( $order );

                return array(
                    'status'    => 'ok',
                    'msg'       => 'authorized'
                );
            }
            else {

                $order->add_order_note( printf( __( 'Aborting, captured amount does not equal order amount for order #%d. Please check this order manually.', 'woocommerce-gateway-payiq' ), $order->id ) );

                if ( $this->debug == 'yes' ) {
                    $this->log->add( 'payiq', 'Aborting, captured amount does not equal order amount for order #' . $order->id . '. Please check this order manually.' );
                }


                return array(
                    'status'    => 'error',
                    'msg'       => 'Order total does not match captured amount'
                );
            }

        } elseif( $post_data['operationtype'] == 'authorize' ) {

            $this->payment_authorized( $order );

            return array(
                'status'    => 'ok',
                'msg'       => 'authorized'
            );

        }

        return array(
            'status'    => 'ok',
            'msg'       => ''
        );
    }

    private function payment_authorized( $order ) {

        update_post_meta( $order->id, '_payiq_order_authorized', 'yes' );
        $order->add_order_note( __( 'PayIQ transaction authorized.', 'woocommerce-gateway-payiq' ) );
    }


    private function payment_captured( $order ) {

        $order->payment_complete();

        update_post_meta( $order->id, '_payiq_order_captured', 'yes' );
        $order->add_order_note( __( 'PayIQ transaction captured.', 'woocommerce-gateway-payiq' ) );
    }


    /**
     * @return bool
     */
    function get_client_ip() {

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $proxy_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $proxy_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else {
            return $_SERVER['REMOTE_ADDR'];
        }

        //Validate proxy IPs
        $proxy_ips = array_map( function( $ip ) {
            return trim( $ip );
        }, explode( ',', $this->proxy_ips ) );

        if( in_array( $proxy_ip, $proxy_ips) ) {
            return $proxy_ip;
        }

        // Not valid
        return false;
    }


    /**
     * Called on PayIQ callback to finalize the payment
     */
    function payment_complete()
    {
        print_r( $_REQUEST );

        die();

        $order_id = 0;

        $order = wc_get_order( $order_id );



        // Prepare redirect url
        $redirect_url = $order->get_checkout_order_received_url();

        // Return to Thank you page if this is a buyer-return-to-shop callback
        wp_redirect( $redirect_url );
        exit;
    }


}