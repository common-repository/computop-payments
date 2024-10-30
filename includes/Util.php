<?php

namespace ComputopPayments;

use ComputopPayments\Gateways\AbstractGateway;
use ComputopPayments\Gateways\AmazonPay;
use ComputopPayments\Gateways\Card;
use ComputopPayments\Gateways\DirectDebit;
use ComputopPayments\Gateways\EasyCredit;
use ComputopPayments\Gateways\Giropay;
use ComputopPayments\Gateways\Ideal;
use ComputopPayments\Gateways\Klarna;
use ComputopPayments\Gateways\Paypal;

class Util {
	public const NONCE_NAME               = 'computop_nonce';
	public const COMPUTOP_PAYMENT_METHODS = array(
		Card::GATEWAY_ID        => Card::class,
		DirectDebit::GATEWAY_ID => DirectDebit::class,
		EasyCredit::GATEWAY_ID  => EasyCredit::class,
		Giropay::GATEWAY_ID     => Giropay::class,
		Ideal::GATEWAY_ID       => Ideal::class,
		Klarna::GATEWAY_ID      => Klarna::class,
		Paypal::GATEWAY_ID      => Paypal::class,
		AmazonPay::GATEWAY_ID   => AmazonPay::class,
	);

	public static function getPaymentGateway( string $paymentMethod ): AbstractGateway {
		if ( ! isset( self::COMPUTOP_PAYMENT_METHODS[ $paymentMethod ] ) ) {
			throw new \Exception( 'Not a Computop payment method: ' . esc_html( $paymentMethod ) );
		}
		$class = self::COMPUTOP_PAYMENT_METHODS[ $paymentMethod ];
		return new $class();
	}

	public static function orderCanBeCaptured( \WC_Order $order ): bool {
		$isAuthorized = $order->get_meta( Main::ORDER_META_IS_AUTHORIZED ) === 'yes';
		$isCaptured   = $order->get_meta( Main::ORDER_META_IS_CAPTURED ) === 'yes';
		return $isAuthorized && ! $isCaptured;
	}
	public static function safeCompareAmount( $amount1, $amount2 ): bool {
		return number_format( $amount1, 2 ) === number_format( $amount2, 2 );
	}

	public static function round( $amount, $precision = 2 ): float {
		return round( $amount, $precision );
	}

	public static function getNonceCheckedPostValue( string $key ): ?string {
		if ( ! empty( $_POST[ $key ] ) ) {
			// our own nonce:
			if ( isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_NAME ) ) {
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			// woocommerce nonce:
			if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'update-order-review' ) ) {
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		return null;
	}
}
