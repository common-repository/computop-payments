document.addEventListener(
	'DOMContentLoaded',
	function () {
		const getIdealIssuersButton = document.querySelector( '.computop-get-ideal-issuers' );
		if (getIdealIssuersButton) {
			getIdealIssuersButton.addEventListener(
				'click',
				function (e) {
					const statusResponse     = document.getElementById( 'computop-ideal-issuers-status' );
					statusResponse.innerHTML = 'LOADING...';
					e.preventDefault();
					fetch(
						getIdealIssuersButton.getAttribute( 'href' ),
						{
							method: 'GET'
						}
					).then(
						function (response) {
							return response.json();
						}
					).then(
						function (data) {
							statusResponse.innerHTML = '';
							if (data.success) {
								statusResponse.innerHTML = data.html;
							} else {
								alert( data.message );
							}
						}
					);
				}
			);
		}
	}
);
jQuery(
	function () {
		const orderItemBox = jQuery( '#woocommerce-order-items' );
		if (orderItemBox.length) {
			orderItemBox.on(
				'click',
				'.computop-capture',
				function (e) {
					const captureButton = e.target;
					e.preventDefault();
					const formData = new FormData();
					formData.append( 'action', 'capture' );
					formData.append( 'order_id', captureButton.getAttribute( 'data-order-id' ) );
					formData.append( 'wp_nonce', captureButton.getAttribute( 'data-nonce' ) );
					fetch(
						captureButton.getAttribute( 'href' ),
						{
							method: 'POST',
							body: formData
						}
					).then(
						function (response) {
							return response.json();
						}
					).then(
						function (data) {
							if (data.success) {
								location.reload();
							} else {
								alert( data.message );
							}
						}
					);
				}
			);
		}
	}
);
