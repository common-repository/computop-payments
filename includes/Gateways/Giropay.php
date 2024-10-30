<?php

namespace ComputopPayments\Gateways;

use ComputopPayments\Main;
use ComputopSdk\Gateways\AbstractGateway as AbstractSdkGateway;
use ComputopSdk\Gateways\GiropayGateway;
use ComputopSdk\Struct\RequestData\GiropayRequestData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Giropay extends AbstractGateway {

	public const GATEWAY_ID                     = 'computop_giropay';
	public const SDK_GATEWAY_REQUEST_DATA_CLASS = GiropayRequestData::class;
	public $id                                  = self::GATEWAY_ID;
	public $icon                                = COMPUTOP_PLUGIN_URL . '/assets/img/giropay.svg';

	public function __construct() {
		parent::__construct();
		$this->method_title = __( 'Computop giropay', 'computop-payments' );
	}

	public function create_sdk_gateway(): AbstractSdkGateway {
		return new GiropayGateway( Main::getInstance()->getConfig() );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_computop_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'computop-payments' ),
					'label'       => __( 'Enable Computop giropay', 'computop-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'computop-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'computop-payments' ),
					'default'     => __( 'giropay', 'computop-payments' ),
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
}
