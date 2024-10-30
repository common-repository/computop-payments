jQuery(
	function () {
		jQuery( 'form.woocommerce-checkout' ).on(
			'checkout_place_order_success',
			function (e, data) {

				if (data.method && data.method === 'cptAmazonPay' && data.paymentData) {
					const amazonPayCptButton = amazon.Pay.renderButton(
						'#cpt-amazon-pay-button',
						{
							merchantId: cpt_amazonPay.merchantId,
							sandbox: cpt_amazonPay.sandbox === 'yes',
							ledgerCurrency: cpt_amazonPay.ledgerCurrency,
							checkoutLanguage: cpt_amazonPay.checkoutLanguage,
							productType: 'PayAndShip',
							placement: 'Cart',
							buttonColor: 'Gold'
						}
					);

					amazonPayCptButton.initCheckout(
						{
							createCheckoutSessionConfig: {
								payloadJSON: data.paymentData.apButtonPayload,
								signature: data.paymentData.apButtonSignature,
								publicKeyId: data.paymentData.apButtonPublicKeyId
							}
						}
					);
				}
			}
		);
	}
)