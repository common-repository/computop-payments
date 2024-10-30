<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\AmazonPayGateway;
use ComputopSdk\Struct\RequestData\AmazonPayRequestData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AmazonPay extends AbstractGateway {

	public const GATEWAY_ID   = 'computop_amazonpay';
	public $id                = self::GATEWAY_ID;
	public $icon              = COMPUTOP_PLUGIN_URL . '/assets/img/amazon-pay.svg';
	public static $checkoutJs = array(
		'US' => 'https://static-na.payments-amazon.com/checkout.js',
		'EU' => 'https://static-eu.payments-amazon.com/checkout.js',
		'UK' => 'https://static-eu.payments-amazon.com/checkout.js',
		'JP' => 'https://static-fe.payments-amazon.com/checkout.js',
	);
	public $supports          = array(
		'products',
		'refunds',
	);


	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop Amazon Pay', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new AmazonPayGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop Amazon Pay', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'Amazon Pay', 'computop-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
				'merchant_id' => array(
					'title'       => __( 'Amazon Pay Merchant ID', 'computop-payments' ),
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'region'      => array(
					'title'       => __( 'Amazon Pay Region', 'computop-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'EUR',
					'options'     => array(
						'EU' => 'EU',
						'UK' => 'UK',
						'US' => 'US',
						'JP' => 'JP',
					),
				),
			)
		);
	}

	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		parent::payment_fields();
		wp_register_script( 'computop_amazon_pay_js', COMPUTOP_PLUGIN_URL . '/assets/js/amazon_pay.js', array(), COMPUTOP_VERSION, true );
		wp_localize_script(
			'computop_amazon_pay_js',
			'cpt_amazonPay',
			array(
				'merchantId'       => $this->get_option( 'merchant_id' ),
				'ledgerCurrency'   => $this->getLedgerCurrency(),
				'sandbox'          => Main::isSandbox() ? 'yes' : 'no',
				'checkoutLanguage' => $this->getCheckoutLanguage(),
			)
		);
		wp_enqueue_script( 'computop_amazon_pay_js' );
		wp_enqueue_script( 'computop_amazon_pay_library_js', self::$checkoutJs[ $this->get_option( 'region' ) ], array(), COMPUTOP_VERSION, true );
		echo '<div id="cpt-amazon-pay-button"></div>';
		if ( empty( $this->get_description() ) ) {
			echo '<style>.payment_box.payment_method_computop_amazonpay{display:none !important;}</style>';
		}
	}

	protected function addAmazonPayAddress( AmazonPayRequestData $requestData, \WC_Order $order ) {
		// use the existing logic
		$temporaryRequestData = new AmazonPayRequestData();
		$this->addAddressData( $temporaryRequestData, $order );

		$requestData->Name          = $temporaryRequestData->sdFirstName . ' ' . $temporaryRequestData->sdLastName;
		$requestData->sdStreet      = $temporaryRequestData->sdStreet;
		$requestData->sdCity        = $temporaryRequestData->sdCity;
		$requestData->sdCountryCode = $temporaryRequestData->sdCountryCode;
		$requestData->SDZipcode     = $temporaryRequestData->sdZip;
		$requestData->sdPhone       = $order->get_shipping_phone() ? $order->get_shipping_phone() : ( $order->get_billing_phone() ? $order->get_billing_phone() : '000000000' );
	}

	public function getCheckoutLanguage(): string {
		$region = $this->get_option( 'region' );
		if ( $region === 'UK' ) {
			return 'en_GB';
		} elseif ( $region === 'US' ) {
			return 'en_US';
		} elseif ( $region === 'JP' ) {
			return 'ja_JP';
		}

		$language = strtolower( substr( get_locale(), 0, 2 ) );
		if ( in_array( $language, array( 'de', 'fr', 'it', 'es' ) ) ) {
			return $language . '_' . strtoupper( $language );
		}
		return 'en_GB';
	}

	public function getLedgerCurrency() {
		switch ( $this->get_option( 'region' ) ) {
			case 'UK':
				return 'GBP';
			case 'US':
				return 'USD';
			case 'JP':
				return 'JPY';
		}

		return 'EUR';
	}

	public function process_payment( $order_id ) {
		$requestData = new AmazonPayRequestData();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		$this->addAmazonPayAddress( $requestData, $order );
		$requestData->URLCancel = $requestData->URLFailure;
		$requestData->ShopUrl   = $requestData->URLSuccess;
		$response               = $this->sdkGateway->postData( $requestData );

		return array(
			'result'      => 'success',
			'method'      => 'cptAmazonPay',
			'paymentData' => array(
				'apButtonSignature'   => $response->responseArray['Data']['buttonsignature'],
				'apButtonPayload'     => $response->responseArray['Data']['buttonpayload'],
				'apButtonPublicKeyId' => $response->responseArray['Data']['buttonpublickeyid'],
			),
			'redirect'    => '#',
		);
	}
}
