jQuery(document).ready(function( $ ){		
	function spwc_admin_show_hide_fields(){
		if($('#spwc_admin_options_enable_stopforumspam_check').prop("checked")) {
			$( '.spwc-show-field-for-stopforumspam' ).show('slow');
		} else {
			$( '.spwc-show-field-for-stopforumspam' ).hide('slow');
		}
	}
	spwc_admin_show_hide_fields();
	
	$('.form-table').on( "change", "input[type=checkbox]", function() {
		spwc_admin_show_hide_fields();
	});
});