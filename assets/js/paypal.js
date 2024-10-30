const CptPayPal = {
	interval: null,
	intervalCounter: 0,
	attemptToPlaceButton: function () {
		const container = document.querySelector( '.wc-block-cart__submit-container' );

		if ( ! container) {
			return false;
		}
		if (document.getElementById( 'paypal-button-container' )) {
			return false;
		}
		const button = document.createElement( 'div' );
		button.id    = 'paypal-button-container';
		container.appendChild( button );
		return true;
	},

	init: function () {
		if ( ! CptPayPal.attemptToPlaceButton()) {
			return false;
		}
		const script = document.createElement( 'script' );
		script.src   = 'https://www.paypal.com/sdk/js?client-id=' + cpt_paypal.client_id + '&currency=' + cpt_paypal.currency + '&disable-funding=giropay,sofort,sepa,card&intent=authorize';
		script.async = true;
		document.body.appendChild( script );
		script.addEventListener(
			'load',
			function () {
				console.log( 'PayPal script loaded' );
				const mid  = cpt_paypal.mid;
				const len  = cpt_paypal.len;
				const data = cpt_paypal.data;
				let payId;
				if ( ! mid || ! len || ! data) {
					return;
				}
				const params = new URLSearchParams(
					{
						MerchantID: mid,
						Len: len,
						Data: data
					}
				);

				paypal.Buttons(
					{
						// Call your server to set up the transaction
						createOrder: function (data, actions) {
							return fetch(
								'https://www.computop-paygate.com/ExternalServices/paypalorders.aspx',
								{
									method: 'POST',
									body: params
								}
							).then(
								function (res) {
									return res.text();
								}
							).then(
								function (orderData) {
									let qData = new URLSearchParams( orderData )
									payId     = qData.get( 'PayID' );
									return qData.get( 'orderid' );
								}
							);
						},
						// Call cbPayPal.aspx for continue sequence
						onApprove: function (data, actions) {
							var rd = "MerchantId=" + mid + "&PayId=" + payId + "&OrderId=" + data.orderID;
							// Build an invisible form and directly submit it
							const form         = document.createElement( 'form' );
							form.method        = 'POST';
							form.action        = 'https://www.computop-paygate.com/cbPayPal.aspx?rd=' + window.btoa( rd );
							form.style.display = 'none';
							// Add form to body
							document.body.appendChild( form );
							// Submit form
							form.submit();
						},
						onCancel: function (data, actions) {
							var rd = "MerchantId=" + mid + "&PayId=" + payid + "&OrderId=" + data.orderID;
							// Build an invisible form and directly submit it
							const form         = document.createElement( 'form' );
							form.method        = 'POST';
							form.action        = "https://www.computop-paygate.com/cbPayPal.aspx?rd=" + window.btoa( rd ) + "&ua=cancel&token=" + data.orderID;
							form.style.display = 'none';
							// Add form to body
							document.body.appendChild( form );
							// Submit form
							form.submit();
						}
					}
				).render( '#paypal-button-container' );
			}
		);
		return true;
	}
}

CptPayPal.interval = setInterval(
	function () {
		CptPayPal.intervalCounter++;
		if (CptPayPal.init() || CptPayPal.intervalCounter > 10) {
			clearInterval( CptPayPal.interval );
		}
	},
	500
);