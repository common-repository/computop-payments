<?php

namespace ComputopPayments\Controllers;

use ComputopPayments\Main;
use ComputopSdk\Services\EncryptionService;
use ComputopSdk\Struct\ResponseData\Response;

abstract class AbstractDataInteractionController {


	public function getOrderFromResponse( Response $response ): ?\WC_Order {
		$order = null;
		if ( ! empty( $response->TransID ) ) {
			$orderId = str_replace( Main::TRANSACTION_PREFIX, '', $response->TransID );
			if ( ! empty( $orderId ) ) {
				$order = wc_get_order( $orderId );
				if ( empty( $order ) ) {
					$order = null;
				}
			}
		}
		return $order;
	}

	public function getResponseData(): ?Response {
		$responseData = null;
		try {
			$encryptionService = new EncryptionService( Main::getInstance()->getConfig() );
			// no nonce check because data is posted from external source
			$data = wp_kses( wp_unslash( $_POST['Data'] ?? $_GET['Data'] ?? '' ), array() );
			if ( ! empty( $data ) ) {
				$dataString = $encryptionService->decrypt( $data );
				if ( empty( $dataString ) ) {
					throw new \Exception( 'Could not decrypt Data' );
				}
				$responseData = Response::createFromResponseString( $dataString );
			}
		} catch ( \Exception $e ) {
			Main::getInstance()->getLogger()->error(
				'AbstractDataInteractionController::getResponseData()',
				array(
					'exception' => $e->getMessage(),
					'trace'     => $e->getTraceAsString(),
				)
			);
		}
		return $responseData;
	}
}
