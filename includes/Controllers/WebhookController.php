<?php

namespace ComputopPayments\Controllers;

use ComputopPayments\Main;
use ComputopPayments\Util;

class WebhookController extends AbstractDataInteractionController {


	public function notifyAction() {
		$responseData = $this->getResponseData();
		Main::getInstance()->getLogger()->debug(
			'WebhookController::notifyAction()',
			array(
				'DATA' => $responseData,
			)
		);

		if ( empty( $responseData ) ) {
			return;
		}

		$order = $this->getOrderFromResponse( $responseData );
		if ( empty( $order ) ) {
			Main::getInstance()->getLogger()->error(
				'Webhook Exception no order',
				array(
					'data' => $responseData,
				)
			);
			return;
		}

		try {
			$paymentGateway = Util::getPaymentGateway( $order->get_payment_method() );
		} catch ( \Exception $e ) {
			Main::getInstance()->getLogger()->error(
				'Webhook Exception trying to get payment gateway',
				array(
					'Exception' => $e->getMessage(),
				)
			);
			return;
		}

		$inquireResponse = $paymentGateway->fetchPaymentStatus( $responseData->PayID, $responseData->TransID );
		if ( Main::isSandbox() ) {
			$order->add_order_note( 'Inquiry from webhook:' . print_r( array( $responseData->PayID, $responseData->TransID, $paymentGateway->fetchPaymentStatus( $responseData->PayID, $responseData->TransID ) ), true ) );
		}
		// $order->add_order_note('Computop Status Webhook: ' . print_r($inquireResponse->toArray(), true));

		if ( (int) $inquireResponse->AmountCap === (int) round( $order->get_total() * 100 ) ) {
			$order->payment_complete( $responseData->PayID );
		} elseif ( (int) $inquireResponse->AmountAuth === (int) round( $order->get_total() * 100 ) ) {
			$paymentGateway->setOrderAuthorized( $order, $responseData->PayID );
		}
	}
}
