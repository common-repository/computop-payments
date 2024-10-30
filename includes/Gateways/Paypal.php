<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\PaypalGateway;
use ComputopSdk\Struct\RequestData\PaypalRequestData;
use ComputopSdk\Struct\ResponseData\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paypal extends AbstractGateway {

	public const GATEWAY_ID                     = 'computop_paypal';
	public const SDK_GATEWAY_REQUEST_DATA_CLASS = PaypalRequestData::class;
	public $id                                  = self::GATEWAY_ID;
	public $icon                                = COMPUTOP_PLUGIN_URL . '/assets/img/paypal.svg';
	public $supports                            = array(
		'products',
		'refunds',
		'computop_authorize',
	);

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop PayPal', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new PaypalGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'                                => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop PayPal', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                                  => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'PayPal', 'computop-payments' ),
				),
				'description'                            => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
				AbstractGateway::OPTION_KEY_CAPTURE_MODE => array(
					'title'       => __( 'Capture Mode', 'computop-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => 'AUTO',
					'options'     => array(
						'MANUAL' => __( 'Manual Capture', 'computop-payments' ),
						'AUTO'   => __( 'Immediate Capture', 'computop-payments' ),
					),
				),
				'express_checkout_enabled'               => array(
					'title'       => __( 'Allow Express Checkout', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'express_checkout_client_id'             => array(
					'title'       => __( 'Express Checkout Client ID', 'computop-payments' ),
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
			)
		);
	}


	public function process_payment( $order_id ) {
		if ( ! empty( WC()->session->get( 'computop_paypal_data' ) ) ) {
			$requestData = new PaypalRequestData();
			$order       = wc_get_order( $order_id );
			$this->addOrderData( $requestData, $order );
			$requestData->ReqId = $this->getRequestId( $order, array( 'is-express' => 1 ) ); // additional parameter to avoid reqid conflicts with express checkout
			if ( $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE ) === 'MANUAL' ) {
				$requestData->TxType = PaypalRequestData::PAYPAL_TX_TYPE_AUTH;
			} else {
				$requestData->TxType = PaypalRequestData::PAYPAL_TX_TYPE_ORDER;
			}
			$requestData->PayID        = WC()->session->get( 'computop_paypal_data' )['PayID'];
			$requestData->PayPalMethod = PaypalRequestData::PAYPAL_METHOD_SHORTCUT;
			$response                  = $this->sdkGateway->postData( $requestData, PaypalGateway::METHOD_COMPLETE );

			if ( $response->responseArray['Data']['Status'] === Response::STATUS_OK ) {
				if ( $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE ) === 'AUTO' ) {
					$order->payment_complete( $response->responseArray['Data']['PayID'] );
				} else {
					$this->setOrderAuthorized( $order, $response->responseArray['Data']['PayID'] );
				}
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
		}

		$requestData = new PaypalRequestData();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );

		$requestData->Capture = $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE );
		if ( $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE ) === 'MANUAL' ) {
			$requestData->TxType = PaypalRequestData::PAYPAL_TX_TYPE_AUTH;
		} else {
			$requestData->TxType = PaypalRequestData::PAYPAL_TX_TYPE_ORDER;
		}
		$this->logRequestData( $requestData );
		$response = $this->sdkGateway->postData( $requestData );
		$return   = array(
			'result'   => 'success',
			'redirect' => $response->responseArray['paypalurl'],
		);
		return $return;
	}


	public function getExpressCheckoutData() {
		$requestData               = new PaypalRequestData();
		$requestData->MerchantId   = Main::getInstance()->getConfig()->merchantId;
		$requestData->TransID      = uniqid();
		$requestData->Amount       = (int) ( WC()->cart->get_total( 'plain' ) * 100 );
		$requestData->Currency     = get_woocommerce_currency();
		$requestData->RefNr        = uniqid();
		$requestData->URLSuccess   = WC()->api_request_url( 'computop_express_checkout_success' );
		$requestData->URLFailure   = WC()->api_request_url( 'computop_express_checkout_success' ); // paygate will always redirect to failure url
		$requestData->URLNotify    = WC()->api_request_url( 'computop_notify' );
		$requestData->PayPalMethod = PaypalRequestData::PAYPAL_METHOD_SHORTCUT;
		$requestData->Capture      = 'MANUAL';
		$requestData->TxType       = PaypalRequestData::PAYPAL_TX_TYPE_AUTH;
		return $this->sdkGateway->getEncryptedRequestData( $requestData );
	}
}
