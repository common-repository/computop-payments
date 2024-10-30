<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Controllers\ReturnController;
use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\EasyCreditGateway;
use ComputopSdk\Struct\RequestData\AbstractRequestData;
use ComputopSdk\Struct\RequestData\EasyCreditRequestData;
use ComputopSdk\Struct\ResponseData\Response;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EasyCredit extends AbstractGateway {


	public const GATEWAY_ID                     = 'computop_easycredit';
	public const OPTION_EASYCREDIT_PAGE_ID      = 'computop_easycredit_page_id';
	public const SDK_GATEWAY_REQUEST_DATA_CLASS = EasyCreditRequestData::class;
	public $id                                  = self::GATEWAY_ID;
	public $icon                                = COMPUTOP_PLUGIN_URL . '/assets/img/easy-credit.svg';
	public $minOrderValue                       = 200;

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop EasyCredit', 'computop-payments' );
		$this->supports[]   = 'computop_authorize';
	}

	public function is_available() {
		if ( is_admin() ) {
			return true;
		}
		$isAvailable = parent::is_available();
		if ( ! $isAvailable ) {
			return false;
		}
		return $this->get_order_total() >= $this->minOrderValue;
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new EasyCreditGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop EasyCredit', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'EasyCredit', 'computop-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'computop-payments' ),
					'default'     => '',
				),
			)
		);
	}

	public function separateStreetNumber( EasyCreditRequestData $requestData ) {
		foreach ( array( 'bd', 'sd' ) as $prefix ) {
			$street = trim( $requestData->{$prefix . 'Street'} );
			if ( preg_match( '/[0-9]+\s*[a-z]{0,2}$/', $street, $matches ) ) {
				$streetNumber                        = $matches[0];
				$street                              = trim( substr( $street, 0, -strlen( $streetNumber ) ) );
				$streetNumber                        = trim( $streetNumber );
				$requestData->{$prefix . 'Street'}   = $street;
				$requestData->{$prefix . 'StreetNr'} = $streetNumber;
			} else {
				$requestData->{$prefix . 'StreetNr'} = 0;
			}
		}
	}

	public function process_payment( $order_id ) {
		/** @var EasyCreditRequestData $requestData */
		$requestData = new ( static::SDK_GATEWAY_REQUEST_DATA_CLASS )();
		$order       = wc_get_order( $order_id );
		$this->addOrderData( $requestData, $order );
		$this->addAddressData( $requestData, $order );
		$this->separateStreetNumber( $requestData );

		if ( is_user_logged_in() ) {
			$user                          = wp_get_current_user();
			$requestData->CustomerLoggedIn = EasyCreditRequestData::CUSTOMER_LOGGED_IN_TRUE;
			$requestData->CustomerSince    = gmdate( 'Y-m-d', strtotime( $user->user_registered ) );
			$requestData->NumberOrders     = (int) wc_get_customer_order_count( $user->ID );
		} else {
			$requestData->CustomerLoggedIn = EasyCreditRequestData::CUSTOMER_LOGGED_IN_FALSE;
			$requestData->NumberOrders     = 0;
		}
		$requestData->NumberArticles = $order->get_item_count();
		$requestData->EventToken     = EasyCreditRequestData::EVENT_TOKEN_INT;
		$requestData->Email          = $order->get_billing_email();
		$requestData->Salutation     = EasyCreditRequestData::SALUTATION_DIVERSE;
		$this->logRequestData( $requestData );
		$url    = $this->sdkGateway->getUrl( $requestData );
		$return = array(
			'result'   => 'success',
			'redirect' => $url,
		);
		return $return;
	}

	public function confirm() {
		$returnController        = new ReturnController();
		$responseData            = $returnController->getResponseData();
		$order                   = $returnController->getOrderFromResponse( $responseData );
		$requestData             = new EasyCreditRequestData();
		$requestData->MerchantId = $responseData->mid;
		$requestData->PayID      = $responseData->PayID;
		$requestData->TransID    = $responseData->TransID;
		$requestData->Amount     = round( $order->get_total() * 100 );
		$requestData->Currency   = $order->get_currency();
		$requestData->EventToken = EasyCreditRequestData::EVENT_TOKEN_CON;
		$requestData->Capture    = AbstractRequestData::CAPTURE_MODE_MANUAL;
		$infoResponse            = $this->sdkGateway->postData( $requestData, EasyCreditGateway::METHOD_DIRECT );
		return $infoResponse->responseObject->Status === Response::STATUS_OK;
	}


	public function getConfirmationUrl( $data ): ?string {
		$url = $this->getPaymentPageUrl(
			self::OPTION_EASYCREDIT_PAGE_ID,
			__( 'EasyCredit Payment Confirmation', 'computop-payments' ),
			'[computop_easycredit_confirmation]'
		);
		return add_query_arg( 'Data', $data, $url );
	}

	public function renderConfirmationHtml(): string {
		try {
			$returnController = new ReturnController();
			$responseData     = $returnController->getResponseData();
			$order            = $returnController->getOrderFromResponse( $responseData );
			if ( $responseData->Status === Response::STATUS_AUTHORIZE_REQUEST ) {
				$requestData             = new EasyCreditRequestData();
				$requestData->MerchantId = $responseData->mid;
				$requestData->PayID      = $responseData->PayID;
				$requestData->TransID    = $responseData->TransID;
				$requestData->Amount     = round( $order->get_total() * 100 );
				$requestData->Currency   = $order->get_currency();
				$requestData->EventToken = EasyCreditRequestData::EVENT_TOKEN_GET;
				$infoResponse            = $this->sdkGateway->postData( $requestData, EasyCreditGateway::METHOD_DIRECT );
				if ( empty( $infoResponse->responseObject->rawArray['financing'] ) ) {
					throw new \Exception( 'No financing information found' );
				}
				$infoData = json_decode( base64_decode( $infoResponse->responseObject->rawArray['financing'] ), true );
				if ( empty( $infoData ) ) {
					throw new \Exception( 'No confirmation url found' );
				}

				return $this->buildDecisionHtml( $infoData, $order, isset( $_GET['Data'] ) ? sanitize_text_field( wp_unslash( $_GET['Data'] ) ) : '' );

			}
		} catch ( \Exception $e ) {
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
		return '';
	}

	public function buildDecisionHtml( array $infoData, \WC_Order $order, string $originalData ): string {
		$this->enqueue_style();
		$decision                     = $infoData['decision'];
		$urlPreContractualInformation = $decision['urlPreContractualInformation'];
		$interest                     = $decision['interest'];
		$confirmationUrl              = add_query_arg( 'Data', $originalData, WC()->api_request_url( 'computop_success' ) );
		$confirmationUrl              = add_query_arg( 'Confirm', '1', $confirmationUrl );

		$html = '<div class="computop-easycredit-decision">
                    <h2>' . __( 'Your payment plan', 'computop-payments' ) . '</h2>
                    <p>' .
			$decision['numberOfInstallments'] . ' ' . __( 'installments of', 'computop-payments' ) . ' ' .
			wc_price( $decision['installment'], array( 'currency' => $order->get_currency() ) ) .
			' (' . __( 'Last installment', 'computop-payments' ) . ': ' .
			wc_price( $decision['lastInstallment'], array( 'currency' => $order->get_currency() ) ) . ')</p>
                    <p><a href="' . $urlPreContractualInformation . '" target="_blank">' . __( 'Pre-contractual information', 'computop-payments' ) . '</a></p>
                    
                    <h2>' . __( 'Your order', 'computop-payments' ) . '</h2>
                    <table>
                        ';
		/** @var WC_Order_Item_Product $item */
		foreach ( $order->get_items() as $item ) {
			$html .= '<tr>
                            <th>' . $item->get_quantity() . ' &times; ' . $item->get_name() . '</th>
                            <td>' . wc_price( $item->get_total() + $item->get_total_tax(), array( 'currency' => $order->get_currency() ) ) . '</td>
                        </tr>';
		}

		$totalRows = $order->get_order_item_totals();
		foreach ( $totalRows as $totalRowKey => $totalRow ) {
			if ( $totalRowKey === 'order_total' ) {
				continue;
			}
			$html .= '<tr class="total-line">
                            <th>' . $totalRow['label'] . '</th>
                            <td>' . $totalRow['value'] . '</td>
                        </tr>';
		}
		$html .= '<tr class="total-line">
                            <th>' . __( 'Interest', 'computop-payments' ) . ':</th>
                            <td>' . wc_price( $interest, array( 'currency' => $order->get_currency() ) ) . '</td>
                        </tr>';
		$html .= '<tr class="total-line">
                            <th>' . __( 'Total', 'computop-payments' ) . ':</th>
                            <td>' . wc_price( $interest + $order->get_total(), array( 'currency' => $order->get_currency() ) ) . '</td>
                        </tr>';
		$html .= '</table>
                  <a href="' . $confirmationUrl . '" class="computop-button">' . __( 'Confirm order', 'computop-payments' ) . '</a>
                </div>';
		return $html;
	}
}
