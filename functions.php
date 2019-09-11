<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Before all hooks.
add_action( 'after_setup_theme', 'spwc_plugin_update' );

function spwc_plugin_update() {
	$prev_version = spwc_get_option( 'version', '1.1' );
	if ( version_compare( $prev_version, SPWC_PLUGIN_VERSION, '<' ) ) {
		do_action( 'spwc_plugin_update', $prev_version );
		spwc_update_option( 'version', SPWC_PLUGIN_VERSION );
	}
}

function spwc_get_option( $option, $default = '', $section = 'spwc_admin_options' ) {

	if ( is_multisite() ) {
		$same_settings = apply_filters( 'spwc_same_settings_for_all_sites', false );
	} else {
		$same_settings = false;
	}
	if ( $same_settings ) {
		$options = get_site_option( $section );
	} else {
		$options = get_option( $section );
	}

	if ( isset( $options[ $option ] ) ) {
		$value      = $options[ $option ];
		$is_default = false;
	} else {
		$value      = $default;
		$is_default = true;
	}
	return apply_filters( 'spwc_get_option', $value, $option, $default, $is_default );
}

function spwc_update_option( $options, $value = '', $section = 'spwc_admin_options' ) {

	if ( $options && ! is_array( $options ) ) {
		$options = array(
			$options => $value,
		);
	}
	if ( ! is_array( $options ) ) {
		return false;
	}

	if ( is_multisite() ) {
		$same_settings = apply_filters( 'spwc_same_settings_for_all_sites', false );
	} else {
		$same_settings = false;
	}
	if ( $same_settings ) {
		update_site_option( $section, wp_parse_args( $options, get_site_option( $section ) ) );
	} else {
		update_option( $section, wp_parse_args( $options, get_option( $section ) ) );
	}

	return true;
}



