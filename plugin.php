<?php
/*
Plugin Name: Spam Protection Without Captcha
Plugin URI: https://www.shamimsplugins.com/
Description: Protect Login, Register, Lost & Reset Password, Comment, woocommerce, CF7, bbpress, BuddyPress forms. Also can implement in any other form easily.
Version: 1.1
Author: Shamim Hasan
Author URI: https://www.shamimsplugins.com/contact-us/
Text Domain: spam-protection-without-captcha
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SPWC {

	private static $instance;

	private function __construct() {
		$this->constants();
		$this->includes();
	}

	public static function init() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function constants() {
		define( 'SPWC_PLUGIN_VERSION', '1.1' );
		define( 'SPWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'SPWC_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
		define( 'SPWC_PLUGIN_FILE', __FILE__ );
	}

	private function includes() {
		require_once SPWC_PLUGIN_DIR . 'functions.php';
		require_once SPWC_PLUGIN_DIR . 'spwc-hooks.php';
		if ( is_admin() ) {
			require_once SPWC_PLUGIN_DIR . 'admin/settings.php';
		}
	}
} //END Class

SPWC::init();
