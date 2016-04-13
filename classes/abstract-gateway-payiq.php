<?php

abstract class WC_Gateway_PayIQ extends WC_Payment_Gateway {

	public function __construct() {
		global $woocommerce;

		// Currency
		$this->selected_currency = get_woocommerce_currency();

	}



	protected function get_url( $method, $params ) {

		$params['servicename'] = $this->getServicename();
		$params['username'] = $this->getUsername();
		$params['password'] = $this->getPassword();

		foreach ($params as $k => $v ) {
			$uArr[] = $k . '=' . $v;
		}

		$url = $this->getBaseUrl() . '/' . $method . '?' . implode( '&', $uArr );
		return $url;
	}
}
