(function(){
	for ( var i = 0; i < document.forms.length; i++ ) {
		var form = document.forms[i];
		if ( 'get' === form.method ) {
			continue;
		}
		var field = document.createElement( "INPUT" );
		field.setAttribute( "type", "hidden" );
		field.setAttribute( "name", "spwc_nonce" );
		field.setAttribute( "value", spwc_script.nonce_value );
		form.appendChild( field );
	}

	if ( spwc_script.enable_cookie ) {
		document.cookie = 'spwc_cookie=' + spwc_script.cookie_value + '; path=/;';
	}
})();