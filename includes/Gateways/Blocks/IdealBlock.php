<?php

namespace ComputopPayments\Gateways\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use ComputopPayments\Gateways\Ideal;


class IdealBlock extends AbstractPaymentMethodType {

	private Ideal $gateway;
	protected $name = Ideal::GATEWAY_ID;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_computop_ideal_settings', array() );
		$this->gateway  = new Ideal();
	}

	public function is_active(): bool {
		return true; // TODO
	}

	public function get_payment_method_script_handles() {
		$scriptPath = '/assets/js/frontend_blocks.js';
		$scriptUrl  = COMPUTOP_PLUGIN_URL . $scriptPath;

		wp_register_script(
			'computop-payments-blocks',
			$scriptUrl,
			array(),
			COMPUTOP_VERSION,
			true
		);
		return array( 'computop-payments-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway->get_title(),
			'description' => $this->gateway->get_description(),
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
