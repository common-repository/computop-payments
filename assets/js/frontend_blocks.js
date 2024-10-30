var settings = wc.wcSettings.getSetting( 'computop_ideal_data' );
wc.wcBlocksRegistry.registerPaymentMethod(
	{
		name: 'computop_ideal',
		label: settings.title,
		content: window.wp.element.createElement( 'Content', settings.title ),
		edit: null,
		canMakePayment: () => true,
		ariaLabel: settings.title,
		supports: {
			features: settings.supports
		}

	}
)