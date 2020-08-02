<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SPWC_Hooks' ) ) {
	class SPWC_Hooks {

		private static $instance;

		public static function init() {
			if ( ! self::$instance instanceof self ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		function hooks() {
			if ( is_user_logged_in() && spwc_get_option( 'disable_check_for_loggedin', true ) ) {
				return;
			}
			add_action( 'wp_enqueue_scripts', [ $this, 'register_script' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'register_script' ] );

			add_filter( 'spwc_verify', [ $this, 'verify_callback' ] );

			//Login
			if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
				add_action( 'login_form', [ $this, 'form_field' ] );
				add_filter( 'login_form_middle', [ $this, 'form_field' ] );
				add_action( 'woocommerce_login_form', [ $this, 'form_field' ] );
				add_filter( 'authenticate', [ $this, 'login_verify' ], 999 );
			}

			// Registration
			add_action( 'register_form', [ $this, 'form_field' ] );
			add_action( 'woocommerce_register_form', [ $this, 'form_field' ] );
			add_filter( 'registration_errors', [ $this, 'registration_verify' ], 10, 3 );
			add_filter( 'woocommerce_registration_errors', [ $this, 'wc_registration_verify' ], 10, 3 );

			//Lost Password
			add_action( 'lostpassword_form', [ $this, 'form_field' ] );
			add_action( 'woocommerce_lostpassword_form', [ $this, 'form_field' ] );
			add_action( 'lostpassword_post', [ $this, 'lostpassword_verify' ] );

			//Reset Password
			add_action( 'resetpass_form', [ $this, 'form_field' ] );
			add_action( 'woocommerce_resetpassword_form', [ $this, 'form_field' ] );
			add_filter( 'validate_password_reset', [ $this, 'reset_password_verify' ], 10, 2 );

			//Comment
			if ( ! is_admin() || ! current_user_can( 'moderate_comments' ) ) {

				add_action( 'comment_form', [ $this, 'form_field' ] );

				if ( version_compare( get_bloginfo( 'version' ), '4.9.0', '>=' ) ) {
					add_filter( 'pre_comment_approved', [ $this, 'comment_verify_490' ] );
				} else {
					add_filter( 'preprocess_comment', [ $this, 'comment_verify' ] );
				}
			}

			//Buddypress registration
			add_action( 'bp_before_registration_submit_buttons', [ $this, 'bp_form_field' ] );
			add_action( 'bp_signup_validate', [ $this, 'bp_registration_verify' ] );

			//WooCommerce checkout
			add_action( 'woocommerce_checkout_after_order_review', [ $this, 'form_field' ] );
			add_action( 'woocommerce_after_checkout_validation', [ $this, 'wc_checkout_verify' ], 10, 2 );

			//Mustisite user and blog signup
			if ( is_multisite() ) {
				add_action( 'signup_extra_fields', [ $this, 'ms_form_field' ] );
				add_filter( 'wpmu_validate_user_signup', [ $this, 'ms_user_verify' ] );
				
				add_action( 'signup_blogform', [ $this, 'ms_form_field' ] );
				add_filter( 'wpmu_validate_blog_signup', [ $this, 'ms_blog_verify' ] );
			}

			//Contact Form 7
			add_filter( 'wpcf7_form_elements', [ $this, 'wpcf7_form_field' ] );
			add_filter( 'wpcf7_validate', [ $this, 'wpcf7_verify' ] );
				
			//BBpress new topic
			add_action( 'bbp_theme_before_topic_form_submit_wrapper', [ $this, 'form_field' ] );
			add_action( 'bbp_new_topic_pre_extras', [ $this, 'bbp_new_verify' ] );

			//BBpress reply to topic
			add_action( 'bbp_theme_before_reply_form_submit_wrapper', [ $this, 'form_field' ] );
			add_action( 'bbp_new_reply_pre_extras', [ $this, 'bbp_reply_verify' ], 10, 2 );

			add_action( 'init', [ $this, 'set_cookie' ], 20 );

			do_action( 'spwc_hooks_added', $this );
		}

		function token( $action = '' ) {
			$tick = ceil( time() / HOUR_IN_SECONDS );
			$spwc_token = spwc_get_option( 'token' );
			if( !$spwc_token ){
				$spwc_token = wp_generate_password( 64, true, true );
				spwc_update_option( 'token', $spwc_token );
			}
			return substr( wp_hash( $tick . '|' . $action . '|' . $spwc_token, 'nonce' ), -12, 10 );
		}

		function token_verify( $token, $action = '' ){
			$token = (string) $token;
			if( ! $token ){
				return false;
			}
			$spwc_token = spwc_get_option( 'token' );
			if( !$spwc_token ){
				return false;
			}
			$tick = ceil( time() / HOUR_IN_SECONDS );

			$expected = substr( wp_hash( $tick . '|' . $action . '|' . $spwc_token, 'nonce' ), -12, 10 );
			if ( hash_equals( $expected, $token ) ) {
				//Created in this hour
				return true;
			}
			$expected = substr( wp_hash( ( $tick - 1 ) . '|' . $action . '|' . $spwc_token, 'nonce' ), -12, 10 );
			if ( hash_equals( $expected, $token ) ) {
				//Created in previous hour
				return true;
			}
			return false;
		}

		function register_script() {
			wp_register_script( 'spwc_script', SPWC_PLUGIN_URL . 'assets/js/spwc_script.js', [], SPWC_PLUGIN_VERSION, true );
			wp_localize_script( 'spwc_script', 'spwc_script',
				[
					'nonce_value'   => $this->token( 'nonce' ),
					'cookie_value'  => $this->token( 'cookie' ),
					'enable_cookie' => (bool) spwc_get_option( 'enable_cookie', true ),
				]
			);
		}

		function set_cookie(){
			if( ! spwc_get_option( 'enable_cookie', true ) ){
				return;
			}
			$cookie = $this->token( 'cookie2' );
			if( isset( $_COOKIE['spwc_cookie2'] ) && $cookie == $_COOKIE['spwc_cookie2'] ){
				return;
			}
			setcookie( 'spwc_cookie2', $cookie, 0, '/', COOKIE_DOMAIN, false, true );
		}

		function form_field( $return = '' ) {
			wp_enqueue_script( 'spwc_script' );
			return $return;
		}

		function ms_form_field( $errors ) {
			if ( $errmsg = $errors->get_error_message( 'spwc_error' ) ) {
				echo '<p class="error">' . $errmsg . '</p>';
			}
			$this->form_field();
		}

		function bp_form_field() {
			do_action( 'bp_spwc_error_errors' );
			$this->form_field();
		}

		function wpcf7_form_field( $fields ) {
			$this->form_field();
			return $fields . '<span class="wpcf7-form-control-wrap spwc_nonce"></span>';
		}

		function verify_callback() {
			static $last_verify = null;

			if ( is_user_logged_in() && spwc_get_option( 'disable_check_for_loggedin', true ) ) {
				return true;
			}
			if ( null !== $last_verify ) {
				return $last_verify;
			}

			if ( spwc_get_option( 'enable_js_check', true ) ) {
				$response = isset( $_POST['spwc_nonce'] ) ? $_POST['spwc_nonce'] : '';
				if ( ! $this->token_verify( $response, 'nonce' ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'javaScript check failed. Is your javaScript enabled?', 'spam-protection-without-captcha' ) );
					return $last_verify;
				}
			}

			if ( spwc_get_option( 'enable_cookie_check', true ) ) {
				$response = isset( $_COOKIE['spwc_cookie'] ) ? $_COOKIE['spwc_cookie'] : '';
				$response2 = isset( $_COOKIE['spwc_cookie2'] ) ? $_COOKIE['spwc_cookie2'] : '';
				if ( ! $this->token_verify( $response, 'cookie' ) || ! $this->token_verify( $response2, 'cookie2' ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'Cookie check failed. Is your browser cookie enabled?', 'spam-protection-without-captcha' ) );
					return $last_verify;
				}
			}

			if ( spwc_get_option( 'enable_stopforumspam_check', false ) ) {
				$ip = $_SERVER['REMOTE_ADDR'];
				if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'ip check failed.', 'spam-protection-without-captcha' ) );
					return $last_verify;
				}
				if ( in_array( $ip, explode( '\n', spwc_get_option( 'whitelisted_ips' ) ), true ) ) {
					$last_verify = true;
					return $last_verify;
				}

				$url = 'http://api.stopforumspam.org/api';

				// make a POST request to the stopforumspam Server
				$request = wp_remote_post(
					$url, array(
						'timeout' => 10,
						'body'    => array(
							'ip'         => $ip,
							'json'       => true,
							'confidence' => true,
						),
					)
				);

				// get the request response body
				$request_body = wp_remote_retrieve_body( $request );
				if ( ! $request_body ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'ip check failed.', 'spam-protection-without-captcha' ) );
					return $last_verify;
				}

				$result = json_decode( $request_body, true );
				if ( ! isset( $result['ip'] ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'ip check failed.', 'spam-protection-without-captcha' ) );
					return $last_verify;
				} elseif ( ! spwc_get_option( 'allow_tor', true ) && ! empty( $result['ip']['torexit'] ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'TOR networks are not allowed.', 'spam-protection-without-captcha' ) );
					return $last_verify;
				} elseif ( $result['ip']['appears'] && absint( $result['ip']['confidence'] ) >= absint( spwc_get_option( 'score', 50 ) ) ) {
					$last_verify = new WP_Error( 'spwc_error', __( 'your ip is in spam database.', 'spam-protection-without-captcha' ) );
					return $last_verify;
				}
			}
			$last_verify = true;
			return true;

		}

		function verify() {
			return apply_filters( 'spwc_verify', true );
		}

		function login_verify( $user ) {
			if ( $user instanceof WP_User ) {
				$verify = $this->verify();
				if ( is_wp_error( $verify ) ) {
					return $verify;
				}
			}
			return $user;
		}

		function registration_verify( $errors, $sanitized_user_login, $user_email ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$errors->add( 'spwc_error', $verify->get_error_message() );
			}
			return $errors;
		}

		function wc_registration_verify( $errors, $sanitized_user_login, $user_email ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$errors->add( 'spwc_error', $verify->get_error_message() );
			}
			return $errors;
		}

		function wc_checkout_verify( $data, $errors ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$errors->add( 'spwc_error', $verify->get_error_message() );
			}
		}

		function bp_registration_verify() {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				buddypress()->signup->errors['spwc_error'] = $verify->get_error_message();
			}
		}

		function lostpassword_verify( $errors ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$errors->add( 'spwc_error', $verify->get_error_message() );
			}
		}


		function reset_password_verify( $errors, $user ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$errors->add( 'spwc_error', $verify->get_error_message() );
			}
		}

		function comment_verify( $commentdata ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				wp_die(
					'<p>' . $verify->get_error_message() . '</p>', __( 'Comment Submission Failure' ), [
						'response'  => 403,
						'back_link' => true,
					]
				);
			}
			return $commentdata;
		}

		function comment_verify_490( $approved ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				return new WP_Error( 'spwc_error', $verify->get_error_message(), 403 );
			}
			return $approved;
		}

		function ms_user_verify( $result ) {
			if ( isset( $_POST['stage'] ) && 'validate-user-signup' === $_POST['stage'] ) {
				$verify = $this->verify();
				if ( is_wp_error( $verify ) ) {
					$result['errors']->add( 'spwc_error', $verify->get_error_message() );
				}
			}
			return $result;
		}
		
		function ms_blog_verify( $result ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$result['errors']->add( 'spwc_error', $verify->get_error_message() );
			}
			return $result;
		}

		function wpcf7_verify( $result ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				$result->invalidate( ['name' => 'spwc_nonce'], $verify->get_error_message() );
			}
			return $result;
		}

		function bbp_new_verify( $forum_id ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				bbp_add_error( 'spwc_error', $verify->get_error_message() );
			}
		}

		function bbp_reply_verify( $topic_id, $forum_id ) {
			$verify = $this->verify();
			if ( is_wp_error( $verify ) ) {
				bbp_add_error( 'spwc_error', $verify->get_error_message() );
			}
		}

	} //END CLASS
} //ENDIF

add_action( 'init', [ SPWC_Hooks::init(), 'hooks' ] );

