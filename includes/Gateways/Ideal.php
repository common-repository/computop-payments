<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\IdealGateway;
use ComputopSdk\Struct\RequestData\IdealRequestData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ideal extends AbstractGateway {


	public const GATEWAY_ID  = 'computop_ideal';
	public const MODE_DIRECT = 'direct';
	public const MODE_PPRO   = 'ppro';

	public $id   = self::GATEWAY_ID;
	public $icon = COMPUTOP_PLUGIN_URL . '/assets/img/ideal.svg';

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop iDEAL', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new IdealGateway( Main::getInstance()->getConfig() );
	}


	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		$this->enqueue_style();
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
		$issues = $this->getIssuers();
		echo '<div id="computop-ideal-form">
                <input type="hidden" name="computop-ideal-nonce" value="' . esc_attr( wp_create_nonce( 'computop-ideal-nonce' ) ) . '"/>
                <div>
                    <label for="computop-ideal-issuer">' . esc_html( __( 'Your bank', 'computop-payments' ) ) . '</label>
                    <select id="computop-ideal-issuer" name="computop-ideal-issuer" required>';
		foreach ( $issues as $issuer ) {
			echo '<option value="' . esc_attr( $issuer['id'] ) . '">' . esc_html( $issuer['name'] ) . '</option>';
		}
		echo '      </select>
                </div>
                </div>';
	}


	public function get_form_fields() {
		wp_enqueue_script( 'computop_admin_js', COMPUTOP_PLUGIN_URL . '/assets/js/admin.js' );
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop iDEAL', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' =>
						'<a href="' . WC()->api_request_url( Main::URL_SLUG_ADMIN_IDEAL_ISSUERS ) . '" class="button button-primary computop-get-ideal-issuers">' . __( 'Refresh iDEAL Issuers', 'computop-payments' ) . '</a>' .
						'<div id="computop-ideal-issuers-status"></div>',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'iDEAL', 'computop-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
				'mode'        => array(
					'title'       => __( 'Mode', 'computop-payments' ),
					'type'        => 'select',
					'description' => '',
					'default'     => self::MODE_DIRECT,
					'options'     => array(
						self::MODE_DIRECT => __( 'Direct', 'computop-payments' ),
						self::MODE_PPRO   => __( 'PPRO', 'computop-payments' ),
					),
				),
			)
		);
	}

	public function process_payment( $order_id ) {
		if ( ! isset( $_POST['computop-ideal-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['computop-ideal-nonce'] ) ), 'computop-ideal-nonce' ) ) {
			throw new \Exception( esc_html( static::getDefaultErrorMessage() ) );
		}
		$requestData = new IdealRequestData();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		$requestData->IssuerID = sanitize_text_field( wp_unslash( $_POST['computop-ideal-issuer'] ?? '' ) );
		$requestData->BIC      = sanitize_text_field( wp_unslash( $_POST['computop-ideal-issuer'] ?? '' ) );

		if ( $this->get_option( 'mode' ) === self::MODE_PPRO ) {
			$this->addPproData( $requestData, $order );
		}

		$this->logRequestData( $requestData );
		$url    = $this->sdkGateway->getUrl( $requestData );
		$return = array(
			'result'   => 'success',
			'redirect' => $url,
		);
		return $return;
	}

	public function getIssuers( $forceRefresh = false ): array {
		$issuers = get_option( 'computop_ideal_issuers' );
		if ( $forceRefresh || empty( $issuers ) ) {
			$issuersResponse = $this->sdkGateway->getIssuerList();
			if ( $issuersResponse->responseArray && isset( $issuersResponse->responseArray['Data']['IdealIssuerList'] ) ) {
				$issuersRaw = explode( '|', $issuersResponse->responseArray['Data']['IdealIssuerList'] );
				$issuersRaw = array_filter( array_map( 'trim', $issuersRaw ) );
				$issuers    = array();
				foreach ( $issuersRaw as $issuerRaw ) {
					$issuer                = explode( ',', $issuerRaw );
					$issuers[ $issuer[0] ] = array(
						'id'   => $issuer[0],
						'name' => $issuer[1],
					);
				}
				if ( ! empty( $issuers ) ) {
					update_option( 'computop_ideal_issuers', $issuers );
				}
			}
		}
		return empty( $issuers ) ? array() : $issuers;
	}

	protected function addPproData( IdealRequestData $requestData, \WC_Order $order ) {
		$requestData->IssuerID = null;
		if ( get_locale() ) {
			$requestData->Language = substr( strtolower( get_locale() ), 0, 2 );
		}
	}
}
