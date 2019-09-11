<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SPWC_Settings {

	private static $instance;

	public static function init() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	function actions_filters() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_init', array( $this, 'settings_save' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( SPWC_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( is_multisite() ) {
			$same_settings = apply_filters( 'spwc_same_settings_for_all_sites', false );
		} else {
			$same_settings = false;
		}
		if ( $same_settings ) {
			add_action( 'network_admin_menu', array( $this, 'menu_page' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'menu_page' ) );
		}

	}
	
	function admin_enqueue_scripts() {
		wp_register_script( 'spwc-admin', SPWC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SPWC_PLUGIN_VERSION, true );
	}

	function admin_init() {
		register_setting( 'spwc_admin_options', 'spwc_admin_options', array( $this, 'options_sanitize' ) );
		foreach ( $this->get_sections() as $section_id => $section ) {
			add_settings_section( $section_id, $section['section_title'], ! empty( $section['section_callback'] ) ? $section['section_callback'] : null, 'spwc_admin_options' );
		}
		foreach ( $this->get_fields() as $field_id => $field ) {
			$args = wp_parse_args(
				$field, array(
					'id'         => $field_id,
					'label'      => '',
					'cb_label'   => '',
					'type'       => 'text',
					'class'      => 'regular-text',
					'section_id' => 'general',
					'desc'       => '',
					'std'        => '',
				)
			);
			add_settings_field( $args['id'], $args['label'], ! empty( $args['callback'] ) ? $args['callback'] : [ $this, 'callback' ], 'spwc_admin_options', $args['section_id'], $args );
		}
	}

	function get_sections() {
		$sections = array(
			'general' => array(
				'section_title'    => '',
			),
		);
		return apply_filters( 'spwc_settings_sections', $sections );
	}

	function get_fields() {
		$score_values = [];
		for ( $i = 1; $i <= 100; $i++ ) {
			$score_values[ "$i" ] = number_format_i18n( $i );
		}
		$fields = array(
			'enable_js_check'      => array(
				'label'      => __( 'JavaScript Check', 'spam-protection-without-captcha' ),
				'section_id' => 'general',
				'type'       => 'checkbox',
				'std'        => 1,
				'class'      => 'checkbox',
				'cb_label'   => __( 'Enable JavaScript Check?', 'spam-protection-without-captcha' ),
			),
			'enable_cookie_check'      => array(
				'label'      => __( 'Cookie Check', 'spam-protection-without-captcha' ),
				'type'       => 'checkbox',
				'std'        => 1,
				'class'      => 'checkbox',
				'cb_label'   => __( 'Enable Cookie Check?', 'spam-protection-without-captcha' ),
			),
			'enable_stopforumspam_check'      => array(
				'label'      => __( 'stopforumspam Check', 'spam-protection-without-captcha' ),
				'type'       => 'checkbox',
				'class'      => 'checkbox',
				'cb_label'   => __( 'Check ip against stopforumspam database?', 'spam-protection-without-captcha' ),
				'desc'       => __( 'Use with caution! May return false positive.', 'spam-protection-without-captcha' ),
			),
			'allow_tor'      => array(
				'label'      => __( 'TOR network?', 'spam-protection-without-captcha' ),
				'type'       => 'checkbox',
				'std'        => 1,
				'class'      => 'checkbox hidden spwc-show-field-for-stopforumspam',
				'cb_label'   => __( 'Allow TOR network?', 'spam-protection-without-captcha' ),
			),
			'score'              => array(
				'label'      => __( 'Confidence Score', 'spam-protection-without-captcha' ),
				'type'       => 'select',
				'class'      => 'regular hidden spwc-show-field-for-stopforumspam',
				'std'        => '50',
				'options'    => $score_values,
				'desc'       => __( 'Lower means more sensitive', 'spam-protection-without-captcha' ),
			),
			'whitelisted_ips'              => array(
				'label'      => __( 'Whitelisted IPs', 'spam-protection-without-captcha' ),
				'type'       => 'textarea',
				'class'      => 'regular hidden spwc-show-field-for-stopforumspam',
				'desc'       => __( 'One per line', 'spam-protection-without-captcha' ),
			),
			'disable_check_for_loggedin'      => array(
				'label'      => __( 'Disable for logged in', 'spam-protection-without-captcha' ),
				'type'       => 'checkbox',
				'std'        => 1,
				'class'      => 'checkbox',
				'cb_label'   => __( 'Disable check for logged in users?', 'spam-protection-without-captcha' ),
			),
		);
		
		return apply_filters( 'spwc_settings_fields', $fields );
	}

	function callback( $field ) {
		$attrib = '';
		if ( ! empty( $field['required'] ) ) {
			$attrib .= ' required = "required"';
		}
		if ( ! empty( $field['readonly'] ) ) {
			$attrib .= ' readonly = "readonly"';
		}
		if ( ! empty( $field['disabled'] ) ) {
			$attrib .= ' disabled = "disabled"';
		}
		if ( ! empty( $field['minlength'] ) ) {
			$attrib .= ' minlength = "' . absint( $field['minlength'] ) . '"';
		}
		if ( ! empty( $field['maxlength'] ) ) {
			$attrib .= ' maxlength = "' . absint( $field['maxlength'] ) . '"';
		}

		$value = spwc_get_option( $field['id'], $field['std'] );

		switch ( $field['type'] ) {
			case 'text':
			case 'email':
			case 'url':
			case 'number':
			case 'hidden':
			case 'submit':
				printf(
					'<input type="%1$s" id="spwc_admin_options_%2$s" class="%3$s" name="spwc_admin_options[%4$s]" placeholder="%5$s" value="%6$s"%7$s />',
					esc_attr( $field['type'] ),
					esc_attr( $field['id'] ),
					esc_attr( $field['class'] ),
					esc_attr( $field['id'] ),
					isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '',
					esc_attr( $value ),
					$attrib
				);
				break;
			case 'textarea':
					printf( '<textarea id="spwc_admin_options_%1$s" class="%2$s" name="spwc_admin_options[%3$s]" placeholder="%4$s" %5$s >%6$s</textarea>',
						esc_attr( $field['id'] ),
						esc_attr( $field['class'] ),
						esc_attr( $field['id'] ),
						isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '',
						$attrib,
						esc_textarea( $value )
					);
					break;
			case 'checkbox':
				printf( '<input type="hidden" name="spwc_admin_options[%s]" value="" />', esc_attr( $field['id'] ) );
				printf(
					'<label><input type="%1$s" id="spwc_admin_options_%2$s" class="%3$s" name="spwc_admin_options[%4$s]" value="%5$s"%6$s /> %7$s</label>',
					'checkbox',
					esc_attr( $field['id'] ),
					esc_attr( $field['class'] ),
					esc_attr( $field['id'] ),
					'1',
					checked( $value, '1', false ),
					esc_attr( $field['cb_label'] )
				);
				break;
			case 'multicheck':
				printf( '<input type="hidden" name="spwc_admin_options[%s][]" value="" />', esc_attr( $field['id'] ) );
				foreach ( $field['options'] as $key => $label ) {
					printf(
						'<label><input type="%1$s" id="spwc_admin_options_%2$s_%5$s" class="%3$s" name="spwc_admin_options[%4$s][]" value="%5$s"%6$s /> %7$s</label><br>',
						'checkbox',
						esc_attr( $field['id'] ),
						esc_attr( $field['class'] ),
						esc_attr( $field['id'] ),
						esc_attr( $key ),
						checked( in_array( $key, (array) $value ), true, false ),
						esc_attr( $label )
					);
				}
				break;
			case 'select':
				printf(
					'<select id="spwc_admin_options_%1$s" class="%2$s" name="spwc_admin_options[%1$s]">',
					esc_attr( $field['id'] ),
					esc_attr( $field['class'] ),
					esc_attr( $field['id'] )
				);
				foreach ( $field['options'] as $key => $label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $key ),
						selected( $value, $key, false ),
						esc_attr( $label )
					);
				}
				printf( '</select>' );
				break;
			case 'html':
				echo $field['std'];
				break;

			default:
				printf( __( 'No hook defined for %s', 'spam-protection-without-captcha' ), esc_html( $field['type'] ) );
				break;
		}
		if ( ! empty( $field['desc'] ) ) {
			printf( '<p class="description">%s</p>', $field['desc'] );
		}
	}

	function options_sanitize( $value ) {
		if ( ! $value || ! is_array( $value ) ) {
			return $value;
		}
		$fields = $this->get_fields();

		foreach ( $value as $option_slug => $option_value ) {
			if ( isset( $fields[ $option_slug ] ) && ! empty( $fields[ $option_slug ]['sanitize_callback'] ) ) {
				$value[ $option_slug ] = call_user_func( $fields[ $option_slug ]['sanitize_callback'], $option_value );
			}
		}
		return $value;
	}

	function menu_page() {
		add_options_page( __( 'Spam Protection Without Captcha', 'spam-protection-without-captcha' ), __( 'Spam Protection Without Captcha', 'spam-protection-without-captcha' ), 'manage_options', 'spwc-admin-settings', array( $this, 'admin_settings' ) );

	}
	
	function settings_save() {
		if ( current_user_can( 'manage_options' ) && isset( $_POST['spwc_admin_options'] ) && isset( $_POST['action'] ) && 'update' === $_POST['action'] && isset( $_GET['page'] ) && 'spwc-admin-settings' === $_GET['page'] ) {
			check_admin_referer( 'spwc_admin_options-options' );

			$value = wp_unslash( $_POST['spwc_admin_options'] );
			if ( ! is_array( $value ) ) {
				$value = [];
			}
			spwc_update_option( $value );
			
			wp_safe_redirect( admin_url( 'options-general.php?page=spwc-admin-settings&updated=true' ) );
			exit;
		}
	}

	function admin_settings() {
		wp_enqueue_script( 'spwc-admin' );
		?>
		<div class="wrap">
			<div id="poststuff">
				<h2><?php _e( 'Advanced noCaptcha & invisible captcha Settings', 'spam-protection-without-captcha' ); ?></h2>
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div id="tab_container">
							<?php settings_errors( 'spwc_admin_options' ); ?>
							<form method="post" action="<?php echo esc_attr( admin_url( 'options-general.php?page=spwc-admin-settings' ) ); ?>">
								<?php
								settings_fields( 'spwc_admin_options' );
								do_settings_sections( 'spwc_admin_options' );
								do_action( 'spwc_admin_setting_form' );
								submit_button();
								?>
							</form>
						</div><!-- #tab_container-->
					</div><!-- #post-body-content-->
					<div id="postbox-container-1" class="postbox-container">
						<?php echo $this->spwc_admin_sidebar(); ?>
					</div><!-- #postbox-container-1 -->
				</div><!-- #post-body -->
				<br class="clear" />
			</div><!-- #poststuff -->
		</div><!-- .wrap -->
		<?php
	}

	function spwc_admin_sidebar() {
			$return = '<div class="postbox">
					<h3 class="hndle" style="text-align: center;">
						<span>' . __( 'Plugin Author', 'spam-protection-without-captcha' ) . '</span>
					</h3>

					<div class="inside">
						<div style="text-align: center; margin: auto">
						<strong>Shamim Hasan</strong><br />
						Know php, MySql, css, javascript, html. Expert in WordPress. <br /><br />
								
						You can hire for plugin customization, build custom plugin or any kind of WordPress job via <br> <a
								href="https://www.shamimsplugins.com/contact-us/"><strong>Contact Form</strong></a>
					</div>
				</div>
			</div>';
		return $return;
	}

	function add_settings_link( $links ) {
		// add settings link in plugins page
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=spwc-admin-settings' ) . '">' . __( 'Settings', 'spam-protection-without-captcha' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}


} //END CLASS

add_action( 'wp_loaded', array( SPWC_Settings::init(), 'actions_filters' ) );
