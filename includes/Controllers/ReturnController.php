<?php

namespace ComputopPayments\Controllers;

use ComputopPayments\Gateways\AbstractGateway;
use ComputopPayments\Gateways\EasyCredit;
use ComputopPayments\Gateways\Klarna;
use ComputopPayments\Main;
use ComputopPayments\Util;
use ComputopSdk\Struct\ResponseData\Response;

class ReturnController extends AbstractDataInteractionController {




	public function successAction() {
		wp_redirect( $this->successActionHandler() );
		exit;
	}

	public function successActionIframe() {
		echo '<script>window.top.location.href = "' . esc_url( $this->successActionHandler(), null, '' ) . '";</script>';
		exit;
	}

	public function expressCheckoutSuccessAction(): string {
		$responseData = $this->getResponseData();
		Main::getInstance()->getLogger()->debug( 'ReturnController::expressCheckoutSuccessHandler()', array( 'responseData' => $responseData ) );
		if ( empty( $responseData ) || empty( $responseData->rawArray['AddrZip'] ) ) {
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
		wp_redirect( $this->handleExpressCheckoutReturn( $responseData ) );
		die;
	}

	/**
	 * returns a redirect URL
	 *
	 * @return string
	 */
	protected function successActionHandler(): string {
		$responseData = $this->getResponseData();
		Main::getInstance()->getLogger()->debug( 'ReturnController::successActionHandler()', array( 'responseData' => $responseData ) );
		if ( empty( $responseData ) ) {
			// do redirect to check out page
			return wc_get_checkout_url();
		}

		$order = $this->getOrderFromResponse( $responseData );
		if ( empty( $order ) ) {
			// do redirect to check out page
			return wc_get_checkout_url();
		}
		if ( Main::isSandbox() ) {
			$order->add_order_note( 'Computop Response: ' . print_r( $responseData->rawArray, true ) );
		}
		if ( $order->get_payment_method() === EasyCredit::GATEWAY_ID ) {
			$easyCreditGateway = new EasyCredit();
			if ( ! empty( $_GET['Confirm'] ) ) {
				// confirm action called
				if ( $easyCreditGateway->confirm() ) {
					$easyCreditGateway->setOrderAuthorized( $order, $responseData->PayID );
					return $order->get_checkout_order_received_url();
				} else {
					return wc_get_checkout_url();
				}
			}
			if ( ! empty( $_GET['Data'] ) ) {
				// redirect to confirmation page
				return $easyCreditGateway->getConfirmationUrl( sanitize_text_field( wp_unslash( $_GET['Data'] ) ) );
			} else {
				return wc_get_checkout_url();
			}
		}

		if ( $responseData->Status === Response::STATUS_OK || $responseData->Status === Response::STATUS_AUTHORIZE_REQUEST || $responseData->Status === Response::STATUS_AUTHORIZED ) {
			$order->add_order_note(
				sprintf(
					'Computop Payment: %s',
					$responseData->Status
				)
			);

			try {
				$paymentGateway = Util::getPaymentGateway( $order->get_payment_method() );
				if ( $paymentGateway instanceof Klarna ) {
					try {
						$paymentGateway->createKlarnaOrder( $order, $responseData->PayID, $responseData->rawArray['TokenExt'] );
					} catch ( \Exception $e ) {
						wp_redirect( wc_get_checkout_url() );
						exit;
					}
				}
				// $order->add_order_note('Inquiry from success handler:'.print_r([$responseData->PayID, $responseData->TransID, $paymentGateway->fetchPaymentStatus($responseData->PayID, $responseData->TransID)], true));
				if ( $paymentGateway->canAuthorize() && ( $paymentGateway->get_option( AbstractGateway::OPTION_KEY_CAPTURE_MODE ) === 'MANUAL' || $paymentGateway instanceof Klarna ) ) {
					$paymentGateway->setOrderAuthorized( $order, $responseData->PayID );
				} else {
					$order->payment_complete( $responseData->PayID );
				}
			} catch ( \Exception $e ) {
				$order->add_order_note(
					sprintf(
						'Computop Payment Success Page Exception: %s',
						$e->getMessage()
					)
				);
			}
		}
		return $order->get_checkout_order_received_url();
	}

	public function failureAction() {
		Main::getInstance()->getLogger()->debug(
			'ReturnController::failureAction()'
		);
		$this->failureActionHandler();
		wp_redirect( wc_get_checkout_url() );
		exit;
	}

	public function failureActionIframe() {
		Main::getInstance()->getLogger()->debug(
			'ReturnController::failureActionIframe()'
		);
		$this->failureActionHandler();
		echo '<script>window.top.location.href = "' . esc_url( add_query_arg( 'paymenterrorcpt', '1', wc_get_checkout_url() ), null, '' ) . '";</script>';
		exit;
	}

	protected function failureActionHandler() {
		$responseData = $this->getResponseData();
		Main::getInstance()->getLogger()->warning( 'ReturnController::failureActionHandler()', array( 'responseData' => $responseData ) );
		if ( $responseData->errortext ) {
			wc_add_notice( $responseData->errortext, 'error' );
		} else {
			wc_add_notice( AbstractGateway::getDefaultErrorMessage(), 'error' );
		}
		if ( Main::isSandbox() ) {
			wc_add_notice( '<pre>' . print_r( $responseData, true ) . '</pre>', 'error' );
		}
	}

	protected function handleExpressCheckoutReturn( Response $responseData ) {
		// set address name in checkout session
		$customer = WC()->customer;
		if ( $customer ) {
			$customer->set_billing_first_name( $responseData->rawArray['firstname'] );
			$customer->set_billing_last_name( $responseData->rawArray['lastname'] );
			$customer->set_billing_address_1( $responseData->rawArray['AddrStreet'] );
			$customer->set_billing_postcode( $responseData->rawArray['AddrZip'] );
			$customer->set_billing_city( $responseData->rawArray['AddrCity'] );
			$customer->set_billing_country( $responseData->rawArray['AddrCountryCode'] );
			$customer->set_billing_email( $responseData->rawArray['e-mail'] );
			$customer->save();
			// set payment method
			WC()->session->set( 'chosen_payment_method', Paypal::GATEWAY_ID );
			WC()->session->set( 'computop_paypal_data', $responseData->rawArray );
		}

		return wc_get_checkout_url();
	}
}
