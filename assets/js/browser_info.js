setInterval(
	function () {
		const container = document.getElementById( 'cpt-browser-info-container' );
		if ( ! container) {
			return;
		}
		if (container.querySelector( '#cpt-browser-info-field-container' )) {
			return;
		}

		const javaScriptEnabled = true;
		const javaEnabled       = navigator.javaEnabled();
		const screenHeight      = screen.height;
		const screenWidth       = screen.width;
		const colorDepth        = screen.colorDepth;
		const timeZoneOffset    = (new Date()).getTimezoneOffset();

		const browserInfoFieldContainer         = document.createElement( 'div' );
		browserInfoFieldContainer.id            = 'cpt-browser-info-field-container';
		browserInfoFieldContainer.style.display = 'none';

		const inputFieldCreator = (name, value) => {
			const input         = document.createElement( 'input' );
			input.type          = 'hidden';
			input.name          = name;
			input.value         = value;
			return input;

		}
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[javaScriptEnabled]', javaScriptEnabled ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[javaEnabled]', javaEnabled ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[screenHeight]', screenHeight ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[screenWidth]', screenWidth ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[colorDepth]', colorDepth ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[timeZoneOffset]', timeZoneOffset ) );
		browserInfoFieldContainer.appendChild( inputFieldCreator( 'cptBrowserInfo[language]', navigator.language ) );

		container.appendChild( browserInfoFieldContainer );

	},
	300
);