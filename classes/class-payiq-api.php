<?php


class PayIQAPI
{
	static $service_url = 'https://secure.payiq.se/api/v2/soap/PaymentService';
	static $vsdl_url = 'https://secure.payiq.se/api/v2/soap/PaymentService?wsdl';

	protected $service_name = null; //Your registered PayIQ service name.
	protected $shared_secret = null; //Your registered PayIQ service name.
	protected $order = null;
	protected $client = null; //Your registered PayIQ service name.
	protected $myclient = null; //Your registered PayIQ service name.
	protected $debug = false;



	protected $logger = null;

	function __construct( $service_name, $shared_secret, $order = null, $debug = false ) {

		$this->service_name = $service_name;
		$this->shared_secret = $shared_secret;
		$this->order = $order;

		$this->setDebug( $debug );

		$this->logger = new WC_Logger();

		$this->client = new PayIQSoapClient(
			self::$vsdl_url, //null,
			[
				//'soap_version'  => 'SOAP_1_2',
				//'location' => get_service_url( $endpoint ),
				'uri'           => self::$vsdl_url,
				'trace'         => 1,
				'exceptions'    => 1,
				'use'           => SOAP_LITERAL,
				'encoding'      => 'utf-8',
				'keep_alive'    => true,

				'cache_wsdl'    => WSDL_CACHE_NONE,
				'stream_context' => stream_context_create(
					[
						'http' => [
							'header' => 'Content-Encoding: gzip, deflate'."\n".'Expect: 100-continue'."\n".'Connection: Keep-Alive'
						],
					 ]
				)
			]
		);

		$this->myclient = new SoapClient(
			self::$vsdl_url, //null,
			[
				//'soap_version'  => 'SOAP_1_2',
				//'location' => get_service_url( $endpoint ),
				'uri' => self::$vsdl_url,
				'trace' => 1,
				'exceptions' => 0,
				'use' => SOAP_LITERAL,
				'encoding' => 'utf-8',
				'keep_alive'    => true,

				'cache_wsdl'    => WSDL_CACHE_NONE,
				'stream_context' => stream_context_create(
					[
						'http' => [
							'header' => 'Content-Encoding: gzip, deflate'."\n".'Expect: 100-continue'."\n".'Connection: Keep-Alive'
						],
					 ]
				)
			]
		);
	}

	/**
	 * @return boolean
	 */
	public function isDebug() {

		return $this->debug;
	}

	/**
	 * @param boolean $debug
	 */
	public function setDebug( $debug ) {

		$this->debug = $debug;
	}

	function setOrder( $order ) {

		$this->order = $order;
	}

	function getChecksum( $type = 'PrepareSession' ) {
		if ( ! $this->order ) {

			return false;
		}

		switch ( $type ) {

			case 'CaptureTransaction':

				//ServiceName, TransactionId, Timestamp,  SharedSecret
				$transaction_id = get_post_meta( $this->order->id, 'payiq_transaction_id', true );

				$raw_sting = $this->service_name . $transaction_id . $this->get_timestamp();
				break;

			case 'ReverseTransaction':
			case 'GetTransactionLog':
			case 'GetTransactionDetails':

				//ServiceName, TransactionId, Timestamp,  SharedSecret
			$transaction_id = get_post_meta( $this->order->id, '_transaction_id', true );

			$raw_sting = $this->service_name . $transaction_id . $this->get_timestamp();
				break;

			case 'CreditInvoice':
			case 'ActivateInvoice':

				//ServiceName, TransactionId, Timestamp,  SharedSecret
				$transaction_id = get_post_meta( $this->order->id, 'payiq_transaction_id', true );

				$raw_sting = $this->service_name . $transaction_id . $this->get_timestamp();

				break;

			case 'RefundTransaction':
			case 'AuthorizeSubscription':

				//ServiceName, SubscriptionId, Amount, CurrencyCode, OrderReference, Timestamp, SharedSecret
				$subscription_id = get_post_meta( $this->order->id, '_payiq_subscription_id', true );
				$currency = get_post_meta( $this->order->id, '_order_currency', true );

				$raw_sting = $this->service_name . $subscription_id . ($this->get_order_totals_decimals())  . $currency . $this->get_order_ref() . $this->get_timestamp();

				break;

			case 'GetSavedCards':

				//ServiceName, CustomerId, Timestamp,  SharedSecret
				$raw_sting = $this->service_name . $this->get_customer_ref() . $this->get_timestamp();
				break;

			case 'DeleteSavedCard':
			case 'AuthorizeRecurring':

				//ServiceName, CardId, Amount, CurrencyCode, OrderReference, Timestamp,  SharedSecret
			case 'CreateInvoice':
			case 'CheckSsn':

				return false;

			case 'PrepareSession':
			default:

				//ServiceName, Amount, CurrencyCode, OrderReference, Timestamp, SharedSecret
				$raw_sting = $this->service_name . ($this->get_order_totals_decimals()) . $this->order->get_order_currency() . $this->get_order_ref() . $this->get_timestamp();

				break;
		}

		/**
		 * Example data:
		 * ServiceName = “TestService”
		 * Amount = “15099”
		 * CurrencyCode = “SEK”
		 * OrderReference = “abc123”
		 * SharedSecret = “ncVFrw1H”
		 */

		$str = strtolower( $raw_sting ) .  $this->shared_secret;

		return hash('sha512',  $str );
	}

	function validateChecksum( $post_data, $checksum ) {

		$raw_sting = $this->service_name .
			$post_data['orderreference'] .
			$post_data['transactionid'] .
			$post_data['operationtype'] .
			$post_data['authorizedamount'] .
			$post_data['settledamount'] .
			$post_data['currency'] .
			$this->shared_secret;

		$generated_checksum = hash('sha512',  strtolower( $raw_sting ) );

		if ( $generated_checksum == $checksum ) {
			return true;
		}



		return [
			'generated' => $generated_checksum,
			'raw_sting' => $raw_sting
		];
	}

	function get_timestamp(){
		$timestamp = gmdate('Y-m-d') . 'T' . gmdate('H:i:s') . 'Z' ;
		return $timestamp;
	}

	function get_service_url( $endpoint ) {

		return self::$service_url . '/' . $endpoint;
	}

	/*
    function get_client( $endpoint ) {

        return new SoapClient(
            null,
            array(
                'location' => get_service_url( $endpoint ),
                'uri' => self::$vsdl_url,
                'trace' => 1,
                'use' => SOAP_LITERAL,
            )
        );
    }
    */

	function api_call( $endpoint, $data ) {

		try {

			$response = $this->client->__soapCall( $endpoint, $data );

			return $response;
		} catch (Exception $e) {

			$this->logger->add(
				'payiq',
				PHP_EOL.PHP_EOL .
				$this->client->__getLastResponseHeaders() .
				PHP_EOL .
				$this->client->__getLastResponse() .
				PHP_EOL.PHP_EOL .
				$this->client->__getLastRequestHeaders() .
				PHP_EOL .
				$this->client->__getLastRequest() .
				PHP_EOL .
				'Error: '.$e->faultstring .
				PHP_EOL.PHP_EOL
			);

		}

	}

	function get_order_ref() {

		return $this->order->id;
	}

	function get_customer_ref() {

		$customer_id = $this->order->get_user_id();

		// If guest
		if ( $customer_id == 0 ) {
			return '';
		}

		return 'customer_' . $customer_id;

		return $this->get_soap_string( 'CustomerReference', 'customer_' . $customer_id );
	}

	/**
	 * @return bool
	 */
	static function get_client_ip() {

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$proxy_ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$proxy_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		else {
			return $_SERVER['REMOTE_ADDR'];
		}

		//Validate proxy IPs
		$proxy_ips = array_map( function( $ip ) {
			return trim( $ip );
		}, explode( ',', PayIQ::get_gateway_options('proxy_ips') ) );

		if ( in_array( $proxy_ip, $proxy_ips ) ) {
			return $proxy_ip;
		}


			if ( PayIQ::get_gateway_options('debug') ) {
				$logger = new WC_Logger();
				$logger->add( 'payiq', 'Invalid IP: '.$proxy_ip.' (If you use a proxy you should add the IP to the allowed proxies field)' );
			}


		// Not valid
		return false;
	}

	function get_order_items() {

		$items = $this->order->get_items();

		$order_items = [];

		foreach ( $items as $item ) {

			if ( isset( $item['variation_id'] ) && $item['variation_id'] > 0 ) {
				$product = new WC_Product( $item['variation_id'] );
			} else {
				$product = new WC_Product( $item['product_id'] );
			}

			$sku = $product->get_sku();

			// Use product ID if SKU is not set
			if ( empty( $sku ) ) {
				$sku = $product->get_id();
			}

			$order_items[] = [
				'Description'   => $product->get_title(),
				'SKU'           => $sku,
				'Quantity'      => $item['qty'],
				'UnitPrice'     => $this->format_price( ($item['line_total'] + $item['line_tax']) / $item['qty'] )
			];


		}

		//TODO: Add support for custom fees

		$fees = $this->order->get_fees();

		foreach ( $fees as $fee ) {

			$order_items[] = [
				'Description'   => $fee['name'],
				'SKU'           => '',
				'Quantity'      => 1,
				'UnitPrice'     => $this->format_price( $fee['line_total'] + $fee['line_tax'] )
			];
		}

		$shipping_methods = $this->order->get_shipping_methods();

		foreach ( $shipping_methods as $shipping_method ) {

			$tax_total = 0;
			$taxes = maybe_unserialize( $shipping_method['taxes'] );

			if ( is_array( $taxes ) ) {
				foreach ( $taxes as $tax ) {
					$tax_total += $tax;
				}
			}

			$order_items[] = [
				'Description'   => $shipping_method['name'],
				'SKU'           => $shipping_method['type'] . '_' . $shipping_method['method_id'],
				'Quantity'      => 1,
				'UnitPrice'     => $this->format_price( $shipping_method['cost'] + $tax_total )
			];
		}

		return $order_items;
	}

	function get_order_totals_decimals(){

		$order_items = $this->get_order_items();
		$order_total = 0;
		foreach($order_items as $order_item){

			$item_price = $order_item['UnitPrice'] * $order_item['Quantity'];
			$order_total += $item_price;
		}

		return $order_total;
	}

	function get_order_description() {

		$items = $this->order->get_items();

		$order_items = [];

		foreach ( $items as $item ) {

			$order_items[] = $item['name'] . ' x ' . $item['qty'] . ' ' . $item['line_total'];
		}

		return sprintf( __( 'Order #%s.' ), $this->order->id ) . sprintf( 'Items: %s.', implode( ',', $order_items ) );
	}

	function get_transaction_settings( $options = [] ) {

		$order_id = $this->order->id;

		$data = [
			'AutoCapture'       => 'true',  //( isset( $options ) ? 'true' : 'false' ),
			'CallbackUrl'       => trailingslashit( site_url( '/woocommerce/payiq-callback' ) ),
			'CreateSubscription' => 'false',
			'DirectPaymentBank' => '',
			'FailureUrl'        => trailingslashit( site_url( '/woocommerce/payiq-failure' ) ),
			//Allowed values: Card, Direct, NotSet
			'PaymentMethod'     => 'NotSet',
			'SuccessUrl'        => trailingslashit( site_url( '/woocommerce/payiq-success' ) ),
		];

		if(function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription( $this->order )){
			$data['CreateSubscription'] = 'true';
		}

		return $data;
	}

	function get_order_info() {

		$data = [
			'OrderReference' => $this->get_order_ref(),
			'OrderItems' => $this->get_order_items(),
			'Currency' => $this->order->get_order_currency(),
			// Optional alphanumeric string to indicate the transaction category.
			// Enables you to group and filter the transaction log and reports based on a custom criterion of your choice.
			//'OrderCategory' => '',
			// Optional order description displayed to end‐user on the payment site.
			'OrderDescription' => '',
		];

		return $data;

		$data = [
			'a:OrderReference' => $this->get_order_ref(),
			'a:OrderItems' => $this->get_order_items(),
			'a:Currency' => $this->order->get_order_currency(),
			// Optional alphanumeric string to indicate the transaction category.
			// Enables you to group and filter the transaction log and reports based on a custom criterion of your choice.
			//'OrderCategory' => '',
			// Optional order description displayed to end‐user on the payment site.
			//'OrderDescription' => '',
		];

		return $this->get_soap_object( 'OrderInfo', $data );

	}

	function get_request_xml( $method, $data = [] ) {

		$template_file = WC_PAYIQ_PLUGIN_DIR.'xml-templates/' . $method . '.php';

		if ( ! file_exists( $template_file ) ) {
			return false;
		}

		ob_start();

		require $template_file;

		$xml = ob_get_clean();

		return $xml;
	}

	function format_price( $price ) {

		return intval( $price * 100 );

	}

	function prepareSession( $options = [] ) {

		$data = [
			'Checksum' => $this->getChecksum( 'PrepareSession' ),
			'CustomerReference' => $this->get_customer_ref(),
			'Language' => 'sv',
			'OrderInfo' => $this->get_order_info(),
			'ServiceName' => $this->service_name,
			'Timestamp' => $this->get_timestamp(),
			'TransactionSettings' => $this->get_transaction_settings( $options ),
		];

		$xml = $this->get_request_xml( 'PrepareSession', $data );

		$response = $this->client->__myDoRequest( $xml, 'PrepareSession' );

		$dom = new DOMDocument();
		$dom->loadXML( $response );
		$ns = 'http://schemas.wiredge.se/payment/api/v2/objects';

		$data = $this->get_xml_fields( $response, [
			'RedirectUrl'
		], $ns);

		$redirect_url = $data['RedirectUrl'];

		return $redirect_url;
	}

	function AuthorizeRecurring() {

		$data = [
			'ServiceName' => $this->service_name,
			'Checksum' => $this->getChecksum( 'AuthorizeRecurring' ),
			'CardId' => '',
			'Currency' => $this->order->get_order_currency(),
			'Amount' => '',
			'OrderReference' => $this->get_order_ref(),
			'CustomerReference' => $this->get_customer_ref(),
			//'TransactionSettings' => new TransactionSettings(),
			'ClientIpAddress' =>  self::get_client_ip(),
			'Timestamp' => $this->get_timestamp(),

		];

		$xml = $this->get_request_xml( 'AuthorizeRecurring', $data );

		$response = $this->client->__myDoRequest( $xml, 'AuthorizeRecurring' );

		$data = $this->get_xml_fields( $response, [
			'Succeeded', 'ErrorCode', 'TransactionId', 'SubscriptionId'
		]);

		return $data;
	}

	function AuthorizeSubscription() {

		$order_id = $this->order->id;
		$subscription_id = get_post_meta($order_id, '_payiq_subscription_id', true);
		$currency = get_post_meta( $order_id, '_order_currency', true );
		$amount_to_charge = $this->order->get_total();
		$amount_to_charge = $amount_to_charge * 100;

		$data = [
			'ServiceName' => $this->service_name,
			'Checksum' => $this->getChecksum( 'AuthorizeSubscription' ),
			'SubscriptionId' => $subscription_id,
			'Amount' => $amount_to_charge,
			'Currency' => $currency,
			'OrderReference' => $this->get_order_ref(),
			'ClientIpAddress' =>  self::get_client_ip(),
			'Timestamp' => $this->get_timestamp(),
		];


		$xml = $this->get_request_xml( 'AuthorizeSubscription', $data );

		$response = $this->client->__myDoRequest( $xml, 'AuthorizeSubscription' );

		$data = $this->get_xml_fields( $response, [
			'TransactionId'
		]);

		return $data;
	}

	function GetSavedCards() {

		$data = [
			'ServiceName' 		=> $this->service_name,
			'Checksum'			=> $this->getChecksum( 'GetSavedCards' ),
			'CustomerReference' => $this->get_customer_ref(),
			'Timestamp' 		=> $this->get_timestamp(),
		];

		$xml = $this->get_request_xml( 'AuthorizeSubscription', $data );

		$response = $this->client->__myDoRequest( $xml, 'AuthorizeSubscription' );

		$data = $this->get_xml_fields( $response, [
			'Cards'
		]);

		return $data;
	}


	function GetTransactionDetails( $TransactionId ) {
		$data = [
			'Checksum'          => $this->getChecksum( 'GetTransactionDetails' ),
			'ServiceName'       => $this->service_name,
			'TransactionId'     => $TransactionId,
			'Timestamp' 		=> $this->get_timestamp(),
		];

		$xml = $this->get_request_xml( 'GetTransactionDetails', $data );

		$response = $this->client->__myDoRequest( $xml, 'GetTransactionDetails' );

		$data = $this->get_xml_fields( $response, [
			'SubscriptionId'
		]);

		return $data;
	}

	function CaptureTransaction( $TransactionId ) {

		$data = [
			'Checksum'          => $this->getChecksum( 'CaptureTransaction' ),
			'ClientIpAddress'   => self::get_client_ip(),
			'ServiceName'       => $this->service_name,
			'TransactionId'     => $TransactionId,
			'Timestamp' 		=> $this->get_timestamp(),

		];

		$xml = $this->get_request_xml( 'CaptureTransaction', $data );


		$response = $this->client->__myDoRequest( $xml, 'CaptureTransaction' );

		$data = $this->get_xml_fields( $response, [
			'Succeeded', 'ErrorCode', 'AuthorizedAmount', 'SettledAmount'
		]);

		return $data;

	}


	function get_xml_fields( $xml, $fields = [], $namespace = 'http://schemas.wiredge.se/payment/api/v2/objects' ) {

		$xmldoc = new DOMDocument();
		$xmldoc->loadXML( $xml );

		$data = [];

		foreach ( $fields as $field ) {

			if ( $xmldoc->getElementsByTagNameNS( $namespace, $field )->length > 0 ) {
				$data[$field] = $xmldoc->getElementsByTagNameNS( $namespace, $field )->item( 0 )->nodeValue;
			}
			else {
				$data[$field] = '';
			}
		}

		return $data;
	}



	function get_payment_window_url() {

	}

	function get_soap_string( $name, $data ) {

		return new SoapVar( $data, XSD_STRING, null, null, 'a:'.$name );

	}

	function get_soap_object( $name, $data ) {

		return new SoapVar( $data, SOAP_ENC_OBJECT, null, null, 'a:'.$name );

	}
}
