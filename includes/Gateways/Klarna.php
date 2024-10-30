<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\KlarnaHostedPaymentPageGateway;
use ComputopSdk\Struct\RequestData\KlarnaCreateOrderRequestData;
use ComputopSdk\Struct\RequestData\KlarnaHostedPaymentPageRequestData;
use ComputopSdk\Struct\RequestData\Subtypes\Article;
use ComputopSdk\Struct\ResponseData\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Klarna extends AbstractGateway {

	public const GATEWAY_ID = 'computop_klarna';
	public $id              = self::GATEWAY_ID;
	public $icon            = COMPUTOP_PLUGIN_URL . '/assets/img/klarna.svg';

	public function __construct() {
		parent::__construct();
		$this->supports[]   = 'computop_authorize';
		$this->method_title = __( 'Computop Klarna', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new KlarnaHostedPaymentPageGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop Klarna', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'Klarna', 'computop-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
				'account'     => array(
					'title'       => __( 'Account', 'computop-payments' ),
					'type'        => 'text',
					'description' => wp_sprintf(
						__( 'In Paygate, several Klarna user names can be stored on one MerchantID and controlled by the Account parameter. By default, please use the value "1"; if you have several Klarna accounts, please coordinate the values with <a href="%s">Computop Helpdesk.</a>', 'computop-payments' ),
						'mailto:helpdesk@computop.com'
					),
					'placeholder' => '1',
					'default'     => '1',
				),
			)
		);
	}

	public function addKlarnaData( KlarnaHostedPaymentPageRequestData $requestData, \WC_Order $order ) {
		$this->addAddressData( $requestData, $order );

		$requestData->bdEmail   = $order->get_billing_email();
		$requestData->sdEmail   = $order->get_billing_email();
		$requestData->bdCompany = $order->get_billing_company();
		$requestData->sdCompany = $order->get_shipping_company();

		$requestData->ArticleList = array();

		foreach ( $order->get_items() as $item ) {
			$article           = new Article();
			$article->name     = $item->get_name();
			$article->quantity = $item->get_quantity();

			$totalLinePrice = 0;
			$price          = 0;
			$vatRate        = 0;
			if ( is_callable( array( $item, 'get_total' ) ) ) {
				$totalLinePrice = $item->get_subtotal() + $item->get_subtotal_tax();
				$price          = round( $item->get_quantity() ? $totalLinePrice / $item->get_quantity() : 0, 2 );
				if ( $item->get_subtotal() > 0 ) {
					$vatRate = round( $item->get_subtotal_tax() / $item->get_subtotal() * 100, 1 );
				}
			}

			if ( $price != 0 ) {
				$article->unit_price       = round( $price * 100 );
				$article->tax_rate         = round( $vatRate * 100 );
				$article->total_tax_amount = round( $item->get_subtotal_tax() * 100 );
				$article->total_amount     = round( $totalLinePrice * 100 );
			}

			$requestData->ArticleList[] = $article;
			$requestData->TaxAmount     = round( $order->get_total_tax() * 100 );
		}
	}

	public function process_payment( $order_id ) {
		$requestData = new KlarnaHostedPaymentPageRequestData();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		$this->addKlarnaData( $requestData, $order );

		$requestData->setLanguage( substr( strtolower( get_locale() ), 0, 2 ) );
		if ( $this->get_option( 'account' ) ) {
			$requestData->Account = $this->get_option( 'account' );
		}

		$this->logRequestData( $requestData );
		$url    = $this->sdkGateway->getUrl( $requestData );
		$return = array(
			'result'   => 'success',
			'redirect' => $url,
		);
		return $return;
	}

	public function createKlarnaOrder( \WC_Order $order, string $PayID, string $extToken ) {
		$requestData = new KlarnaCreateOrderRequestData();
		$this->addOrderData( $requestData, $order );
		$requestData->ReqId = $this->getRequestId(
			$order,
			array(
				'PayID'  => $PayID,
				'method' => KlarnaHostedPaymentPageGateway::METHOD_CREATE_ORDER,
			)
		);
		$this->addKlarnaData( $requestData, $order );
		$requestData->PayID      = $PayID;
		$requestData->EventToken = KlarnaCreateOrderRequestData::EVENT_TOKEN_CREATE_ORDER;
		$requestData->TokenExt   = $extToken;
		$response                = $this->sdkGateway->postData( $requestData, KlarnaHostedPaymentPageGateway::METHOD_CREATE_ORDER );
		if ( Main::isSandbox() ) {
			$order->add_order_note( 'Klarna create order response: ' . print_r( $response->responseArray, true ) );
		}
		if ( ! isset( $response->responseArray['Data']['Status'] ) || $response->responseArray['Data']['Status'] !== Response::STATUS_OK ) {
			throw new \Exception( 'Klarna order creation failed' );
		}
	}
}
