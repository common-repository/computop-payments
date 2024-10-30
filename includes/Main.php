<?php

namespace ComputopPayments;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use ComputopPayments\Controllers\AdminController;
use ComputopPayments\Controllers\ReturnController;
use ComputopPayments\Controllers\WebhookController;
use ComputopPayments\Gateways\AmazonPay;
use ComputopPayments\Gateways\Blocks\IdealBlock;
use ComputopPayments\Gateways\Card;
use ComputopPayments\Gateways\DirectDebit;
use ComputopPayments\Gateways\EasyCredit;
use ComputopPayments\Gateways\Giropay;
use ComputopPayments\Gateways\Ideal;
use ComputopPayments\Gateways\Klarna;
use ComputopPayments\Gateways\Paypal;
use ComputopPayments\Services\LogService;
use ComputopSdk\Struct\Config\Config;
use ComputopSdk\Struct\RequestData\PaypalRequestData;

class Main {

	public const SETTINGS_ORDER_STATUS_AUTHORIZED      = 'computop_authorized_order_status';
	public const SETTINGS_CAPTURE_TRIGGER_ORDER_STATUS = 'computop_capture_trigger_order_status';
	public const ORDER_STATUS_AUTHORIZED               = 'wc-cpt-authorized';

	public const ORDER_META_IS_AUTHORIZED = 'computop_is_authorized';
	public const ORDER_META_IS_CAPTURED   = 'computop_is_captured';

	public const URL_SLUG_ADMIN_CAPTURE       = 'computop_capture';
	public const URL_SLUG_ADMIN_IDEAL_ISSUERS = 'computop_ideal_issuers';
	public const TRANSACTION_PREFIX           = 'WOOCCPTTX';
	public static $instance;
	protected ?LogService $logger = null;

	public static function getInstance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function isSandbox(): bool {
		return defined( 'COMPUTOP_SANDBOX' ) && COMPUTOP_SANDBOX === 'yes';
	}

	public function getLogger() {
		if ( empty( $this->logger ) ) {
			$this->logger = new LogService();
		}
		return $this->logger;
	}

	public function init(): void {
		$this->registerEvents();
		$this->registerOrderStatus();
	}

	public function registerEvents(): void {
		add_filter( 'woocommerce_get_settings_checkout', array( $this, 'addGlobalSettings' ), 10, 3 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'addPaymentGateways' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( COMPUTOP_PLUGIN_PATH . 'computop-payments.php' ), array( $this, 'addPluginSettingsLink' ) );
		add_action( 'woocommerce_api_computop_success', array( ( new ReturnController() ), 'successAction' ) );
		add_action( 'woocommerce_api_computop_express_checkout_success', array( ( new ReturnController() ), 'expressCheckoutSuccessAction' ) );
		add_action( 'woocommerce_api_computop_success_iframe', array( ( new ReturnController() ), 'successActionIframe' ) );
		add_action( 'woocommerce_api_computop_failure', array( ( new ReturnController() ), 'failureAction' ) );
		add_action( 'woocommerce_api_computop_failure_iframe', array( ( new ReturnController() ), 'failureActionIframe' ) );
		add_action( 'woocommerce_api_computop_notify', array( ( new WebhookController() ), 'notifyAction' ) );

		add_action( 'woocommerce_api_' . self::URL_SLUG_ADMIN_CAPTURE, array( ( new AdminController() ), 'captureAction' ) );
		add_action( 'woocommerce_api_' . self::URL_SLUG_ADMIN_IDEAL_ISSUERS, array( ( new AdminController() ), 'getIdealIssuersAction' ) );

		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'addOrderStatusesForPaymentComplete' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handleStatusChange' ), 10, 4 );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'addCaptureButton' ) );

		add_action( 'woocommerce_blocks_loaded', array( $this, 'addCheckoutBlocks' ) );

		add_shortcode( 'computop_cc_iframe', array( new Card(), 'renderIframeHtml' ) );
		add_shortcode( 'computop_cc_local', array( new Card(), 'renderLocalHtml' ) );
		add_shortcode( 'computop_easycredit_confirmation', array( new EasyCredit(), 'renderConfirmationHtml' ) );
		add_action(
			'wp_enqueue_scripts',
			function () {
				$paypalGateway = Util::getPaymentGateway( Paypal::GATEWAY_ID );
				if ( $paypalGateway->get_option( 'express_checkout_enabled' ) === 'yes' ) {
					wp_register_script( 'woocommerce_computop_paypal', COMPUTOP_PLUGIN_URL . '/assets/js/paypal.js' );
					$encryptedData = $paypalGateway->getExpressCheckoutData();
					wp_localize_script(
						'woocommerce_computop_paypal',
						'cpt_paypal',
						array(
							'mid'       => $encryptedData['MerchantID'],
							'len'       => $encryptedData['Len'],
							'data'      => $encryptedData['Data'],
							'client_id' => $paypalGateway->get_option( 'express_checkout_client_id' ),
							'currency'  => get_woocommerce_currency(),
						)
					);
					wp_enqueue_script( 'woocommerce_computop_paypal' );
				}
			}
		);
	}

	/**
	 * @param $orderId
	 * @param $statusFrom
	 * @param $statusTo
	 * @param \WC_Order  $order
	 * @return void
	 */
	public function handleStatusChange( $orderId, $statusFrom, $statusTo, $order ) {
		$paymentMethod = $order->get_payment_method();
		try {
			$gateway = Util::getPaymentGateway( $paymentMethod );
		} catch ( \Exception $e ) {
			return;
		}

		if ( ! $gateway->canAuthorize() ) {
			return;
		}

		$captureStatus = get_option( self::SETTINGS_CAPTURE_TRIGGER_ORDER_STATUS );

		if ( $statusTo === $captureStatus || 'wc-' . $statusTo === $captureStatus || $statusTo === 'wc-' . $captureStatus ) {
			$gateway->capture( $order );
		}
	}

	public function addCaptureButton( \WC_Order $order ): void {
		$paymentMethod = $order->get_payment_method();
		try {
			$gateway = Util::getPaymentGateway( $paymentMethod );
		} catch ( \Exception $e ) {
			return;
		}

		if ( ! $gateway->canAuthorize() || ! Util::orderCanBeCaptured( $order ) ) {
			return;
		}

		echo '<a href="' . esc_url( WC()->api_request_url( self::URL_SLUG_ADMIN_CAPTURE ) ) . '" class="button button-primary computop-capture" data-order-id="' . esc_attr( $order->get_id() ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'computop-capture' ) ) . '">' . esc_html( __( 'Capture', 'computop-payments' ) ) . '</a>';
		wp_enqueue_script( 'computop_admin_js', COMPUTOP_PLUGIN_URL . '/assets/js/admin.js', 'jquery' );
	}

	protected function registerOrderStatus(): void {
		add_action(
			'init',
			function () {
				register_post_status(
					self::ORDER_STATUS_AUTHORIZED,
					array(
						'label'                     => __( 'Ready to Capture', 'computop-payments' ),
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
					)
				);
			}
		);

		add_filter(
			'wc_order_statuses',
			function ( $statusList ) {
				$statusList[ self::ORDER_STATUS_AUTHORIZED ] = __( 'Ready to Capture', 'computop-payments' );
				return $statusList;
			}
		);
	}

	public function addOrderStatusesForPaymentComplete( $statuses ): array {
		if ( get_option( self::SETTINGS_ORDER_STATUS_AUTHORIZED ) ) {
			$statuses[] = get_option( self::SETTINGS_ORDER_STATUS_AUTHORIZED );
		}
		return $statuses;
	}

	public function addPluginSettingsLink( $links ): array {
		$settingsLink = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=computop_general' ) . '">' . __( 'Computop settings', 'computop-payments' ) . '</a>';
		array_unshift( $links, $settingsLink );
		return $links;
	}

	public function addGlobalSettings( $settings, $currentSection ): array {
		if ( $currentSection === 'computop_general' ) {
			$settings = array(
				'title'                        => array(
					'type' => 'title',
					'desc' => '',
				),
				'merchant_id'                  => array(
					'title'   => __( 'Merchant ID', 'computop-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'id'      => 'computop_merchant_id',
					'value'   => get_option( 'computop_merchant_id' ),
					'default' => '',
				),
				'encryption_key'               => array(
					'title'   => __( 'Encryption Key', 'computop-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'id'      => 'computop_encryption_key',
					'value'   => get_option( 'computop_encryption_key' ),
					'default' => '',
				),
				'hash_key'                     => array(
					'title'   => __( 'Hash Key', 'computop-payments' ),
					'type'    => 'text',
					'desc'    => '',
					'id'      => 'computop_hash_key',
					'value'   => get_option( 'computop_hash_key' ),
					'default' => '',
				),
				'authorized_order_status'      => array(
					'title'   => __( 'Order status for authorized payments', 'computop-payments' ),
					'label'   => '',
					'type'    => 'select',
					'desc'    => __( 'This status is assigned for orders, that are authorized', 'computop-payments' ),
					'options' => array_merge( array( '' => __( '[Use WooC default status]', 'computop-payments' ) ), wc_get_order_statuses() ),
					'id'      => self::SETTINGS_ORDER_STATUS_AUTHORIZED,
					'value'   => get_option( self::SETTINGS_ORDER_STATUS_AUTHORIZED ),
					'default' => self::ORDER_STATUS_AUTHORIZED,
				),
				'capture_trigger_order_status' => array(
					'title'   => __( 'Order status to trigger capture', 'computop-payments' ),
					'label'   => '',
					'type'    => 'select',
					'desc'    => __( 'When this status is assigned to an order and there is a valid authorization, the capture is triggered', 'computop-payments' ),
					'options' => array_merge( array( '' => __( '[do not capture on status changes]', 'computop-payments' ) ), wc_get_order_statuses() ),
					'id'      => self::SETTINGS_CAPTURE_TRIGGER_ORDER_STATUS,
					'value'   => get_option( self::SETTINGS_CAPTURE_TRIGGER_ORDER_STATUS ),
					'default' => '',
				),
				'sectionend'                   => array(
					'type' => 'sectionend',
				),
			);
		}
		return $settings;
	}

	public function getConfig(): Config {
		$config                = new Config();
		$config->merchantId    = get_option( 'computop_merchant_id' );
		$config->encryptionKey = get_option( 'computop_encryption_key' );
		$config->hashKey       = get_option( 'computop_hash_key' );
		return $config;
	}

	public function addPaymentGateways( $gateways ): array {
		return array_merge( $gateways, array_values( $this->getPaymentGateways() ) );
	}

	public function getPaymentGateways(): array {
		return Util::COMPUTOP_PAYMENT_METHODS;
	}

	public function addCheckoutBlocks() {
		return;
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once COMPUTOP_PLUGIN_PATH . 'includes/Gateways/Blocks/IdealBlock.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new IdealBlock() );
				}
			);
		}
	}
}
