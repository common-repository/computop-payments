<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\CreditCardPayNowGateway;
use ComputopSdk\Gateways\CreditCardPaySslGateway;
use ComputopSdk\Struct\RequestData\AbstractRequestData;
use ComputopSdk\Struct\RequestData\CreditCardPayPayNowRequestData;
use ComputopSdk\Struct\RequestData\CreditCardPaySslRequestData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Card extends AbstractGateway {


	public const GATEWAY_ID = 'computop_card';

	public const DISPLAY_MODE_HPP    = 'hpp';
	public const DISPLAY_MODE_IFRAME = 'iframe';
	public const DISPLAY_MODE_LOCAL  = 'local';

	public const OPTION_CC_IFRAME_PAGE_ID       = 'computop_cc_iframe_page_id';
	public const OPTION_CC_LOCAL_PAGE_ID        = 'computop_cc_local_page_id';
	public const SDK_GATEWAY_REQUEST_DATA_CLASS = CreditCardPaySslRequestData::class;
	public $id                                  = self::GATEWAY_ID;
	public $icon                                = COMPUTOP_PLUGIN_URL . '/assets/img/cc.svg';
	public $supports                            = array(
		'products',
		'refunds',
		'computop_authorize',
	);

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop Credit Card', 'computop-payments' );
	}


	public function has_fields() {
		if ( $this->get_option( 'display_mode' ) === self::DISPLAY_MODE_LOCAL ) {
			return true;
		}
		return parent::has_fields();
	}

	public function payment_fields() {
		parent::payment_fields();
		if ( $this->get_option( 'display_mode' ) === self::DISPLAY_MODE_LOCAL ) {
			wp_enqueue_script( 'computop_browser_info', COMPUTOP_PLUGIN_URL . '/assets/js/browser_info.js' );
		}
		if ( empty( $this->get_description() ) ) {
			echo '<style>.payment_box.payment_method_computop_card{display:none !important;}</style>';
		}
		echo '<div id="cpt-browser-info-container" style="display: none;">
                <input type="hidden" name="computop-browser-info-nonce" value="' . esc_attr( wp_create_nonce( 'computop-browser-info-nonce' ) ) . '"/>
              </div>';
	}


	public function create_sdk_gateway(): AbstractSdkGateway {
		return new CreditCardPaySslGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'                                => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop Credit Card', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                                  => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'Credit Card', 'computop-payments' ),
				),
				'description'                            => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
				'display_mode'                           => array(
					'title'       => __( 'Display Mode', 'computop-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => self::DISPLAY_MODE_HPP,
					'options'     => array(
						self::DISPLAY_MODE_HPP    => __( 'Payment Page', 'computop-payments' ),
						self::DISPLAY_MODE_IFRAME => __( 'iFrame', 'computop-payments' ),
						self::DISPLAY_MODE_LOCAL  => __( 'Silent Mode', 'computop-payments' ),
					),
				),
				'page_template'                          => array(
					'title'       => __( 'Custom form template', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'Leave empty for custom template', 'computop-payments' ),
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
			)
		);
	}

	/**
	 * @param CreditCardPaySslRequestData|CreditCardPayPayNowRequestData $requestData
	 * @param \WC_Order                                                  $order
	 * @return void
	 */
	protected function addOrderData( AbstractRequestData $requestData, \WC_Order $order ) {
		parent::addOrderData( $requestData, $order );
		$requestData->billToCustomer = array(
			'consumer' => array(
				'firstName' => $order->get_billing_first_name(),
				'lastName'  => $order->get_billing_last_name(),
			),
			'email'    => $order->get_billing_email(),
		);
		if ( Main::isSandbox() ) {
			$requestData->OrderDesc = 'Test:0000';
		}
		$requestData->Capture = $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE );
		if ( $this->get_option( 'page_template' ) && property_exists( $requestData, 'Template' ) ) {
			$requestData->Template = $this->get_option( 'page_template' );
		}
	}


	public function process_payment( $order_id ) {
		switch ( $this->get_option( 'display_mode' ) ) {
			case self::DISPLAY_MODE_HPP:
				$requestData = new CreditCardPaySslRequestData();
				$order       = wc_get_order( $order_id );
				$this->addOrderData( $requestData, $order );
				$this->logRequestData( $requestData );
				$url = $this->sdkGateway->getUrl( $requestData );
				break;
			case self::DISPLAY_MODE_IFRAME:
				$url = $this->getIframeUrl();
				break;
			case self::DISPLAY_MODE_LOCAL:
				if ( ! isset( $_POST['computop-browser-info-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['computop-browser-info-nonce'] ) ), 'computop-browser-info-nonce' ) ) {
					throw new \Exception( esc_html( static::getDefaultErrorMessage() ) );
				}
				if ( isset( $_POST['cptBrowserInfo'] ) && is_array( $_POST['cptBrowserInfo'] ) ) {
					$browserInfo = array();
					// ignore warning, because we sanitize within the loop
					// @codingStandardsIgnoreLine
					// phpcs:ignore
					foreach ( $_POST['cptBrowserInfo'] as $key => $value ) {
						$browserInfo[ sanitize_text_field( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
					}
					WC()->session->set( 'cptBrowserInfo', $browserInfo );
				}
				// no break
			default:
				$url = $this->getLocalPageUrl();
				break;
		}
		return array(
			'result'   => 'success',
			'redirect' => $url,
		);
	}

	public function getIframeUrl(): ?string {
		return $this->getPaymentPageUrl(
			self::OPTION_CC_IFRAME_PAGE_ID,
			__( 'Credit Card Payment Page', 'computop-payments' ),
			'[computop_cc_iframe]'
		);
	}

	public function getLocalPageUrl(): ?string {
		return $this->getPaymentPageUrl(
			self::OPTION_CC_LOCAL_PAGE_ID,
			__( 'Credit Card Payment Page', 'computop-payments' ),
			'[computop_cc_local]'
		);
	}

	public function renderIframeHtml() {
		if ( empty( WC()->session ) ) {
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
		$requestData = new CreditCardPaySslRequestData();

		$orderId = WC()->session->get( 'order_awaiting_payment' );
		if ( empty( $orderId ) ) {
			// redirect to checkout
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = wc_get_order( $orderId );
		$this->addOrderData( $requestData, $order );
		$requestData->URLSuccess = WC()->api_request_url( 'computop_success_iframe' );
		$requestData->URLFailure = WC()->api_request_url( 'computop_failure_iframe' );
		$this->logRequestData( $requestData );
		$url = $this->sdkGateway->getUrl( $requestData );
		return '<div style="display:flex; justify-content: center;">
                    <iframe src="' . $url . '" width="600" style="max-width: 100%; margin: auto; overflow: hidden;" height="800" frameborder="0" scrolling="no"></iframe>
                </div>';
	}

	public function renderLocalHtml() {
		if ( empty( WC()->session ) ) {
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
		$this->enqueue_style();
		$requestData = new CreditCardPayPayNowRequestData();
		// get id of order awaiting payment
		$orderId = WC()->session->get( 'order_awaiting_payment' );
		if ( empty( $orderId ) ) {
			// redirect to checkout
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = wc_get_order( $orderId );
		$this->addOrderData( $requestData, $order );
		$browserInfo              = WC()->session->get( 'cptBrowserInfo', array() );
		$requestData->browserInfo =
			array(
				'javaScriptEnabled' => ( $browserInfo['javaScriptEnabled'] ?? false ) === 'true',
				'javaEnabled'       => ( $browserInfo['javaEnabled'] ?? false ) === 'true',
				'screenWidth'       => (int) ( $browserInfo['screenWidth'] ?? 0 ),
				'screenHeight'      => (int) ( $browserInfo['screenHeight'] ?? 0 ),
				'colorDepth'        => (int) ( $browserInfo['colorDepth'] ?? 24 ),
				'timeZoneOffset'    => $browserInfo['timeZoneOffset'] ?? '0',
				'language'          => $browserInfo['language'] ?? 'de',
				'acceptHeaders'     => isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '',
				'ipAddress'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'userAgent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			);
		$config                   = Main::getInstance()->getConfig();
		$sdkGateway               = new CreditCardPayNowGateway( $config );
		$this->logRequestData( $requestData );
		$encryptedData = $sdkGateway->getEncryptedRequestData( $requestData );

		$expiryYearsOptions = '';
		$currentYear        = gmdate( 'Y' );
		for ( $i = 0; $i < 10; $i++ ) {
			$year                = $currentYear + $i;
			$expiryYearsOptions .= '<option value="' . $year . '">' . $year . '</option>';
		}
		return '<div style="display:flex; justify-content: center;">
                    <script>
                        function setExpiryDate() {
                            var expiryMonth = document.getElementsByName("expiryMonth")[0].value;
                            var expiryYear = document.getElementsByName("expiryYear")[0].value;
                            document.getElementsByName("expiryDate")[0].value = expiryYear + expiryMonth;
                        }
                    </script>
                    <form name="credit-card-form" action="' . CreditCardPayNowGateway::PAYGATE_URL . CreditCardPayNowGateway::METHOD . '" method="post" class="computop-credit-card-form">
    <input type="hidden" name="MerchantID" value="' . $requestData->MerchantId . '">
    <input type="hidden" name="Len" value="' . $encryptedData['Len'] . '">
    <input type="hidden" name="Data" value="' . $encryptedData['Data'] . '">
    <input type="hidden" name="expiryDate" value="">

    <div class="form-field">
        <label for="brand">' . __( 'Card brand', 'computop-payments' ) . ':</label>
        <select name="brand" id="brand">
            <option value="VISA">VISA</option>
            <option value="MasterCard">MASTERCARD</option>
        </select>
    </div>
    
    <div class="form-field">
        <label for="cardholder">' . __( 'Card holder', 'computop-payments' ) . ':</label>
        <input type="text" name="cardholder" id="cardholder">
    </div>

    <div class="form-field">
        <label for="number">' . __( 'Card number', 'computop-payments' ) . ':</label>
        <input type="text" name="number" id="number">
    </div>

    <div class="form-field">
        <label for="expiryMonth">' . __( 'Expiry date', 'computop-payments' ) . ':</label>
        <select name="expiryMonth" id="expiryMonth" onchange="setExpiryDate()">
            <option></option>
                            <option value="01">01</option>
                            <option value="02">02</option>
                            <option value="03">03</option>
                            <option value="04">04</option>
                            <option value="05">05</option>
                            <option value="06">06</option>
                            <option value="07">07</option>
                            <option value="08">08</option>
                            <option value="09">09</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
        </select>
        <select name="expiryYear" id="expiryYear" onchange="setExpiryDate()">
            ' . $expiryYearsOptions . '
        </select>
    </div>

    <div class="form-field">
        <label for="securityCode">' . __( 'CVV', 'computop-payments' ) . ':</label>
        <input type="text" name="securityCode" id="securityCode">
    </div>

    <div class="form-field">
        <button type="submit" class="button button-primary">' . __( 'Pay', 'computop-payments' ) . '</button>
    </div>
</form>
                </div>';
	}
}
