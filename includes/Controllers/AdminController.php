<?php

namespace ComputopPayments\Controllers;

use ComputopPayments\Gateways\Ideal;
use ComputopPayments\Util;

class AdminController {



	public function captureAction() {
		header( 'Content-Type: application/json' );
		if ( empty( $_POST['wp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_nonce'] ) ), 'computop-capture' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Not allowed (nonce)',
				)
			);
			exit;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) || empty( $_POST['order_id'] ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Not allowed',
				)
			);
			exit;
		}
		$order = wc_get_order( (int) $_POST['order_id'] );
		if ( ! $order ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Order not found',
				)
			);
			exit;
		}
		$paymentMethod = $order->get_payment_method();
		try {
			$gateway = Util::getPaymentGateway( $paymentMethod );
		} catch ( \Exception $e ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				)
			);
			exit;
		}
		if ( ! $gateway->canAuthorize() ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Payment method does not support capture',
				)
			);
			exit;
		}
		if ( ! Util::orderCanBeCaptured( $order ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Order cannot be captured',
				)
			);
			exit;
		}
		$gateway->capture( $order );
		echo wp_json_encode(
			array(
				'success' => true,
				'message' => 'Order captured',
			)
		);
		exit;
	}

	public function getIdealIssuersAction() {
		header( 'Content-Type: application/json' );
		if ( ! current_user_can( 'manage_options' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Not allowed',
				)
			);
			exit;
		}
		try {
			$gateway = Util::getPaymentGateway( Ideal::GATEWAY_ID );
		} catch ( \Exception $e ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				)
			);
			exit;
		}
		$issuers = $gateway->getIssuers( true );
		echo wp_json_encode(
			array(
				'success' => true,
				'html'    => implode(
					'<br>',
					array_map(
						function ( $issuer ) {
							return $issuer['name'] . ' (' . $issuer['id'] . ')';
						},
						$issuers
					)
				),
			)
		);
		exit;
	}
}
