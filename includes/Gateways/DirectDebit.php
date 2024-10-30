<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\DirectDebitGateway;
use ComputopSdk\Struct\CaptureRequestData\DirectDebitCaptureRequestData;
use ComputopSdk\Struct\RequestData\DirectDebitRequestData;
use ComputopSdk\Struct\ResponseData\Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DirectDebit extends AbstractGateway {



	public const GATEWAY_ID = 'computop_directdebit';
	public $id              = self::GATEWAY_ID;
	public $icon            = COMPUTOP_PLUGIN_URL . '/assets/img/direct-debit.svg';
	public $supports        = array(
		'products',
		'refunds',
		'computop_authorize',
	);


	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop Direct Debit', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new DirectDebitGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'                                => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop Direct Debit', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'                                  => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'Direct Debit', 'computop-payments' ),
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
			)
		);
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

		echo '<div id="computop-direct-debit-form">
                <input type="hidden" name="computop-direct-debit-nonce" value="' . esc_attr( wp_create_nonce( 'computop-direct-debit-nonce' ) ) . '"/>
                <div>
                    <label for="computop-direct-debit-iban">' . esc_html( __( 'IBAN', 'computop-payments' ) ) . '</label>
                    <input type="text" id="computop-direct-debit-iban" name="computop-direct-debit-iban" placeholder="DE12 3456 7890 1234 5678 90" />
                </div>
                <div>
                    <label for="computop-direct-debit-account-owner">' . esc_html( __( 'Account Owner', 'computop-payments' ) ) . '</label>
                    <input type="text" id="computop-direct-debit-account-owner" name="computop-direct-debit-account-owner" placeholder="" />
                </div>
              </div>';
	}


	public function process_payment( $order_id ) {
		$requestData = new DirectDebitRequestData();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		if ( ! isset( $_POST['computop-direct-debit-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['computop-direct-debit-nonce'] ) ), 'computop-direct-debit-nonce' ) ) {
			throw new \Exception( esc_html( static::getDefaultErrorMessage() ) );
		}
		$requestData->AccOwner  = sanitize_text_field( wp_unslash( $_POST['computop-direct-debit-account-owner'] ?? '' ) );
		$requestData->IBAN      = sanitize_text_field( wp_unslash( $_POST['computop-direct-debit-iban'] ?? '' ) );
		$requestData->MandateID = uniqid();
		$requestData->DtOfSgntr = gmdate( 'd.m.Y' );
		$requestData->Capture   = $this->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE );
		$this->logRequestData( $requestData );
		$clientResponse = $this->sdkGateway->postData( $requestData );
		if ( $clientResponse->responseObject && $clientResponse->responseObject->Status === Response::STATUS_OK ) {
			if ( $requestData->Capture === 'MANUAL' ) {
				$this->setOrderAuthorized( $order, $clientResponse->responseObject->PayID );
			} else {
				$order->payment_complete( $clientResponse->responseObject->PayID );
			}

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			);
		}

		$errorMessage = '';
		if ( $clientResponse->responseObject && $clientResponse->responseObject->Description ) {
			$errorMessage = $clientResponse->responseObject->Description;
		} elseif ( $clientResponse->responseObject && $clientResponse->responseObject->errortext ) {
			$errorMessage = $clientResponse->responseObject->errortext;
		}

		throw new \Exception( esc_html( $errorMessage ) );
	}

	public function capture( \WC_Order $order ) {
		$captureRequestData = new DirectDebitCaptureRequestData();
		$this->addCaptureData( $captureRequestData, $order );
		$captureRequestData->PayID = $order->get_transaction_id();
		if ( Main::isSandbox() ) {
			$order->add_order_note( 'Capture Request: ' . print_r( $captureRequestData->toArray(), true ) );
		}
		$response = $this->sdkGateway->capture( $captureRequestData );
		$order->add_order_note( 'Capture Result: ' . print_r( $response->responseArray, true ) );
		$this->setOrderCaptured( $order, $response->responseObject->PayID );
	}
}
