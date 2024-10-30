<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopPayments\Util;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Struct\CaptureRequestData\AbstractCaptureRequestData;
use ComputopSdk\Struct\CaptureRequestData\CardCaptureRequestData;
use ComputopSdk\Struct\Config\Config;
use ComputopSdk\Struct\CreditRequestData\BaseCreditRequestData;
use ComputopSdk\Struct\InquireRequestData\InquireRequestData;
use ComputopSdk\Struct\RequestData\AbstractRequestData;
use ComputopSdk\Struct\ResponseData\InquireResponse;
use ComputopSdk\Struct\ResponseData\Response;
use WC_Payment_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractGateway extends WC_Payment_Gateway {


	public const OPTION_KEY_CAPTURE_MODE = 'capture_mode';
	public $supports                     = array(
		'products',
		'refunds',
	);
	public ?array $allowedCurrencies     = null;
	public ?array $allowedCountries      = null;

	public AbstractSdkGateway $sdkGateway;

	public function __construct() {
		$this->plugin_id = 'computop-payments';
		$this->init_settings();
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->method_description = '';
		$this->sdkGateway         = $this->create_sdk_gateway();
	}

	protected function addOrderData( AbstractRequestData $requestData, \WC_Order $order ) {
		$requestData->EtiID      = 'WooCommerce v' . WC()->version . ', Plugin v' . COMPUTOP_VERSION;
		$requestData->MerchantId = Main::getInstance()->getConfig()->merchantId;
		$requestData->Amount     = round( $order->get_total() * 100 );
		$requestData->Currency   = $order->get_currency();
		$requestData->RefNr      = 'WC-' . $order->get_order_number();
		$requestData->TransID    = Main::TRANSACTION_PREFIX . $order->get_id();
		$requestData->OrderDesc  = $order->get_order_number();
		$requestData->URLSuccess = WC()->api_request_url( 'computop_success' );
		$requestData->URLFailure = WC()->api_request_url( 'computop_failure' );
		$requestData->URLNotify  = WC()->api_request_url( 'computop_notify' );
		$requestData->ReqId      = $this->getRequestId( $order );
	}

	protected function getRequestId( $order, array $additionalParameters = array() ) {
		$requestIdData = array(
			$order->get_id(),
			$order->get_billing_first_name(),
			$order->get_billing_last_name(),
			$order->get_billing_address_1(),
			$order->get_billing_postcode(),
			$order->get_billing_city(),
			$order->get_billing_country(),
			$order->get_shipping_first_name(),
			$order->get_shipping_last_name(),
			$order->get_shipping_address_1(),
			$order->get_shipping_postcode(),
			$order->get_shipping_city(),
			$order->get_shipping_country(),
			$order->get_total(),
			$order->get_billing_email(),
		);
		$requestIdData = array_merge( $requestIdData, $additionalParameters );
		return substr( $order->get_id() . '_' . $this->id . '_' . md5( implode( '|', $requestIdData ) ), 0, 30 );
	}

	protected function addCaptureData( AbstractCaptureRequestData $requestData, \WC_Order $order, $amount = null ) {
		$requestData->MerchantId = Main::getInstance()->getConfig()->merchantId;
		$requestData->Amount     = (int) ( $amount ?? round( $order->get_total() * 100 ) );
		$requestData->Currency   = $order->get_currency();
		$requestData->TransID    = Main::TRANSACTION_PREFIX . $order->get_id();
		$requestData->ReqId      = 'C' . $this->getRequestId( $order );
		if ( Main::isSandbox() ) {
			$requestData->OrderDesc = 'Test:0000';
		}
	}

	protected function addCreditData( BaseCreditRequestData $requestData, \WC_Order $order, $amount = null, $reason = '' ) {
		$requestData->MerchantId = Main::getInstance()->getConfig()->merchantId;
		$requestData->Amount     = (int) round( ( $amount ?? $order->get_total() ) * 100 );
		$requestData->Currency   = $order->get_currency();
		$requestData->TransID    = Main::TRANSACTION_PREFIX . $order->get_id();
		$requestData->ReqId      = 'R' . $this->getRequestId( $order, array( $amount, $reason, gmdate( 'YmdHi' ) ) );
		$requestData->PayID      = $order->get_transaction_id();
		$requestData->Reason     = $reason;
		$requestData->OrderDesc  = $reason ? $reason : null;
		if ( Main::isSandbox() ) {
			$requestData->OrderDesc = 'Test:0000';
		}
	}

	protected function addAddressData( AbstractRequestData $requestData, \WC_Order $order ) {
		$requestData->bdFirstName   = $order->get_billing_first_name();
		$requestData->bdLastName    = $order->get_billing_last_name();
		$requestData->bdStreet      = $order->get_billing_address_1();
		$requestData->bdZip         = $order->get_billing_postcode();
		$requestData->bdCity        = $order->get_billing_city();
		$requestData->bdCountryCode = $order->get_billing_country();

		// is shipping address different?
		if ( $this->isShippingAddressEmptyOrIdentical( $order ) ) {
			$requestData->sdFirstName   = $requestData->bdFirstName;
			$requestData->sdLastName    = $requestData->bdLastName;
			$requestData->sdStreet      = $requestData->bdStreet;
			$requestData->sdZip         = $requestData->bdZip;
			$requestData->sdCity        = $requestData->bdCity;
			$requestData->sdCountryCode = $requestData->bdCountryCode;
		} else {
			$requestData->sdFirstName   = $order->get_shipping_first_name();
			$requestData->sdLastName    = $order->get_shipping_last_name();
			$requestData->sdStreet      = $order->get_shipping_address_1();
			$requestData->sdZip         = $order->get_shipping_postcode();
			$requestData->sdCity        = $order->get_shipping_city();
			$requestData->sdCountryCode = $order->get_shipping_country();
		}
	}

	protected function isShippingAddressEmptyOrIdentical( \WC_Order $order ) {
		$isEmpty = empty( $order->get_shipping_first_name() ) && empty( $order->get_shipping_last_name() ) && empty( $order->get_shipping_address_1() ) && empty( $order->get_shipping_postcode() ) && empty( $order->get_shipping_city() ) && empty( $order->get_shipping_country() );
		if ( $isEmpty ) {
			return true;
		}

		$isIdentical = $order->get_billing_first_name() === $order->get_shipping_first_name() && $order->get_billing_last_name() === $order->get_shipping_last_name() && $order->get_billing_address_1() === $order->get_shipping_address_1() && $order->get_billing_postcode() === $order->get_shipping_postcode() && $order->get_billing_city() === $order->get_shipping_city() && $order->get_billing_country() === $order->get_shipping_country();
		if ( $isIdentical ) {
			return true;
		}
		return false;
	}

	public function setOrderAuthorized( \WC_Order $order, $transactionId = null ) {
		if ( get_option( Main::SETTINGS_ORDER_STATUS_AUTHORIZED ) ) {
			$order->update_status( get_option( Main::SETTINGS_ORDER_STATUS_AUTHORIZED ) );
		}
		if ( $transactionId ) {
			$order->set_transaction_id( $transactionId );
		}
		$order->update_meta_data( Main::ORDER_META_IS_AUTHORIZED, 'yes' );
		$order->add_order_note(
			sprintf(
				__( 'Order authorized by Computop. Transaction ID: %s', 'computop-payments' ),
				$transactionId ?? $order->get_transaction_id()
			)
		);
		$order->save();
	}

	public function setOrderCaptured( \WC_Order $order, $transactionId = null ) {
		// if (get_option(Main::SETTINGS_ORDER_STATUS_CAPTURED)) {
		// $order->update_status(get_option(Main::SETTINGS_ORDER_STATUS_CAPTURED));
		// }
		if ( $transactionId ) {
			$order->set_transaction_id( $transactionId );
		}
		$order->payment_complete( $transactionId );
		$order->update_meta_data( Main::ORDER_META_IS_CAPTURED, 'yes' );
		$order->add_order_note(
			sprintf(
				__( 'Order captured. Transaction ID: %s', 'computop-payments' ),
				$transactionId ?? $order->get_transaction_id()
			)
		);
		$order->save();
	}

	public function canAuthorize(): bool {
		return $this->supports( 'computop_authorize' );
	}


	public function capture( \WC_Order $order ) {
		$captureRequestData = new CardCaptureRequestData();
		$this->addCaptureData( $captureRequestData, $order );
		$captureRequestData->PayID = $order->get_transaction_id();
		if ( Main::isSandbox() ) {
			$order->add_order_note( 'Capture Request: ' . "\n\n" . print_r( $captureRequestData->toArray(), true ) );
		}
		$response = $this->sdkGateway->capture( $captureRequestData );
		$order->add_order_note( 'Capture Result: ' . print_r( $response->responseArray, true ) );
		if ( $response->responseArray['Data']['Status'] === Response::STATUS_OK ) {
			$this->setOrderCaptured( $order, $response->responseObject->PayID );
		}
	}

	abstract public function create_sdk_gateway(): AbstractSdkGateway;

	public function get_config(): Config {
		$config                = new Config();
		$config->merchantId    = get_option( 'computop_merchant_id' );
		$config->encryptionKey = get_option( 'computop_encryption_key' );
		$config->hashKey       = get_option( 'computop_hash_key' );
		return $config;
	}

	public function needs_setup() {
		return false;
	}

	public function is_enabled() {
		return $this->enabled === 'yes';
	}

	public function is_available() {
		$isAvailable = parent::is_available();
		if ( $isAvailable && ! empty( $this->allowedCurrencies ) ) {
			$isAvailable = in_array( get_woocommerce_currency(), $this->allowedCurrencies );
		}
		if ( $isAvailable && ! empty( $this->allowedCountries ) ) {
			$country = Util::getNonceCheckedPostValue( 'country' );
			if ( ! empty( $country ) && ! in_array( $country, $this->allowedCountries ) ) {
				$isAvailable = false;
			}
		}
		return $isAvailable;
	}


	public function process_payment( $order_id ) {
		$requestData = new ( static::SDK_GATEWAY_REQUEST_DATA_CLASS )();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		$this->logRequestData( $requestData );
		$url    = $this->sdkGateway->getUrl( $requestData );
		$return = array(
			'result'   => 'success',
			'redirect' => $url,
		);
		return $return;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$requestData = new BaseCreditRequestData();
		$order       = wc_get_order( $order_id );
		$this->addCreditData( $requestData, $order, $amount, $reason );

		$response = $this->sdkGateway->refund( $requestData );
		$order->add_order_note( 'Refund Result: ' . print_r( $response->responseArray, true ) );
		if ( in_array( $response->responseArray['Data']['Status'], array( Response::STATUS_OK, Response::STATUS_PENDING ), true ) ) {
			return true;
		} else {
			$errorText  = $response->responseArray['Data']['Description'] ? $response->responseArray['Data']['Description'] : '';
			$errorText .= ( $errorText ? ' | ' : '' ) . ( $response->responseArray['Data']['errortext'] ? $response->responseArray['Data']['errortext'] : '' );

			return new \WP_Error(
				'cpt-refund-error',
				$errorText
			);
		}
	}

	public function has_fields() {
		return ! empty( $this->get_description() );
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
	}

	protected function enqueue_style() {
		wp_enqueue_style( 'computop-payments', COMPUTOP_PLUGIN_URL . '/assets/css/shop.css' );
	}


	protected function getPaymentPageUrl( $optionId, $title, $content ): ?string {
		$pageId = get_option( $optionId );
		if ( $pageId ) {
			$page = get_post( $pageId );
			if ( $page instanceof \WP_Post ) {
				if ( $page->post_status !== 'publish' ) {
					$page->post_status = 'publish';
					wp_update_post( $page );
				}
				return get_permalink( $page );
			}
		}

		$page = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( is_numeric( $page ) ) {
			update_option( $optionId, $page );
			return get_permalink( $page );
		}
		return null;
	}

	public function fetchPaymentStatus( string $payId, string $transId ): InquireResponse {
		$config                         = $this->get_config();
		$inquireRequestData             = new InquireRequestData();
		$inquireRequestData->MerchantId = $config->merchantId;
		$inquireRequestData->PayID      = $payId;
		$inquireRequestData->TransID    = $transId;
		return $this->sdkGateway->inquire( $inquireRequestData )->responseObject;
	}

	public static function getDefaultErrorMessage(): string {
		return __( 'An error occurred while processing your payment. Please try again or use a different payment method.', 'computop-payments' );
	}

	protected function logRequestData( AbstractRequestData $requestData ) {
		Main::getInstance()->getLogger()->debug( 'Request Data ' . get_class( $requestData ), array( 'requestData' => $requestData->toArray() ) );
	}
}
