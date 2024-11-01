<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Voizee_Options {
	public function __construct() {
		$this->voizee_host = "https://app.voizee.com";
		add_action( 'admin_init', [ &$this, 'init_plugin' ] );
        add_action( 'admin_init', [ &$this, 'handle_voizee_form_submission' ] );
		add_action( 'admin_menu', [ &$this, 'create_voizee_options' ] );
	}

	function init_plugin() {
        register_setting( "voizee", "voizee_api_key", array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( "voizee", "voizee_widget_script", array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( "voizee", "voizee_api_dashboard_enabled", array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( "voizee", "voizee_api_cf7_enabled", array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( "voizee", "voizee_api_gf_enabled", array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( "voizee", "voizee_api_cf7_logs", array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ) );

        register_setting( "voizee", "voizee_api_gf_logs", array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ) );
	}

	function dashboard_enabled() {
		return get_option( 'voizee_api_dashboard_enabled', true );
	}

	function cf7_enabled() {
		return get_option( 'voizee_api_cf7_enabled', true );
	}

	function cf7_active() {
		return is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
	}

	function gf_enabled() {
		return get_option( 'voizee_api_gf_enabled', true );
	}

	function gf_active() {
		return is_plugin_active( 'gravityforms/gravityforms.php' );
	}

	function has_api_key() {
		$api_key = get_option( "voizee_api_key" );

		return ! empty( $api_key );
	}

	public function create_voizee_options() {
		if ( current_user_can( 'manage_options' ) ) {
			$hook = add_options_page(
				'Voizee',
				'Voizee',
				'administrator',
				'voizee',
				[ &$this, 'create_voizee_settings_page' ]
			);

            add_action( 'admin_enqueue_scripts', function( $current_hook ) use ( $hook ) {
                if ( $current_hook === $hook ) {
                    wp_enqueue_script(
                        'voizee_form_logs_script',
                        plugins_url( 'js/voizee-forms-log.js', __FILE__ ),
                        array( 'jquery' ),
                        '1.0.0',
                        true
                    );
                }
            });
		}
	}

	function create_voizee_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}
		$has_api_key = $this->has_api_key();

		?>
        <div class="wrap">
			<?php
			if ( isset( $_GET['savedata'] ) ) {
				if ( $_GET['savedata'] == true && !count( get_settings_errors('voizee_widget_script') )) {
					echo '<div id="message" class="updated"><p>Settings saved</p></div>';
				}
			}
			?>
            <a href="https://voizee.com" target="_blank" id="voizee_logo"></a>
            <form method="POST" name="voizee_form" action="options-general.php?page=voizee&savedata=true">

				<?php
				wp_nonce_field( 'update_voizee_options_a', '_vzeo_nonce' ); ?>
                <input type="hidden" name="voizee___ous" id="voizee___ous" />

                <div class="voizee_field">
                    <h3>Account <small>enter your account details to use this plugin</small></h3>
					<?php
					if ( ! $has_api_key ) { ?>
                    <div class="voizee_card">
                        <p style="margin-bottom:5px">Enter your <b>Voizee account details</b> below to get started.</p>
                        <p><b>Don't have an account?</b> <b><a target="_blank" href="https://app.voizee.com/user/register?utm_source=wp_plugin">Sign
                                    up</a></b> now &mdash; it only takes a few minutes.</p>
						<?php
						} else { ?>
                        <div class="voizee_card">
							<?php
							} ?>
                            <div class="voizee_field">
                                <strong><label for="voizee_api_key"><?php
                                        esc_html_e( 'API Key', 'voizee' ); ?></label></strong><br />
                                <input class="regular-text" type="text" id="voizee_api_key" name="voizee_api_key"
                                       value="<?php
								       echo esc_attr( get_option( 'voizee_api_key' ) ); ?>" autocomplete="off" />
                            </div>
                            <span class="hint">This can be found in the API Integration section of your <a
                                    target="_blank" href="https://app.voizee.com/integrations?utm_source=wp_plugin">account Integrations section</a>.</span>

                            <footer>
                                <input type="submit" class="voizee_button callout" value="<?php
                                esc_html_e( 'Save Changes', 'voizee' ) ?>" />
                            </footer>
                        </div>
                    </div>
                    <div class="voizee_field"<?php
					if ( $has_api_key ) { ?> style="display:none"<?php
					} ?>>
                        <h3>Widget Embed Code <small>Manually install the widget embed code on this website</small></h3>
                        <div class="voizee_card">
                            <p>If you do not wish to use the API, you may manually enter your website's <a
                                    target="_blank" href="https://app.voizee.com">widget code</a> below.</p>
                            <p>Please use the hash from the 'Code embed for your site' snippet. The hash is the unique identifier found after <code>/t/</code> in the script URL. For example, <code>cgfb7eced97db20f8cdcdd2e3cc74cd2230d7d6b</code></p>
                            <div class="voizee_field">
                                <input type="text" id="voizee_widget_script" class="regular-text"
                                       name="voizee_widget_script" value="<?php
								echo esc_attr( htmlspecialchars( stripslashes( get_option( 'voizee_widget_script' ) ) ) ); ?>"
                                       style="width:100%;text-align:center;padding:7px;font-size:13px;max-width:400px">
                            </div>
                            <footer>
                                <input type="submit" class="voizee_button callout" value="<?php
                                esc_html_e( 'Save Changes', 'voizee' ) ?>" />
                            </footer>
                        </div>
                    </div>
                    <div class="voizee_field"<?php
					if ( ! $has_api_key ) { ?> style="display:none"<?php
					} ?>>
                        <h3>Settings <small>customize the behavior of this plugin</small></h3>
                        <div class="voizee_card">
                            <div class="voizee_field">
                                <label><input type="checkbox" id="voizee_api_dashboard_enabled"
                                              name="voizee_api_dashboard_enabled" value='1' <?php
									echo esc_attr( checked( 1, $this->dashboard_enabled(), false ) ) ?>/>Show <b>call statistics</b>
                                    in the WordPress Dashboard</label>
                                <span class="hint">Displays a simple widget in your <a href="/wp-admin/index.php">WordPress Dashboard</a> showing call volume by day for the last 30 days.</span>
                            </div>
                            <footer>
                                <input type="submit" class="voizee_button callout" value="<?php
                                esc_html_e( 'Save Changes', 'voizee' ) ?>" />
                            </footer>
                        </div>
                    </div>
                    <div class="voizee_field"<?php
					if ( ! $has_api_key ) { ?> style="display:none"<?php
					} ?>>
                        <h3>Integrations <small>send your WordPress forms into Voizee automatically</small></h3>
                        <div class="voizee_card">
                            <p>These integrations do not require <i>any extra setup</i> &mdash; simply create a form
                                with a phone number field and Voizee will create
                                an instant call back request in your Voizee account automatically.</p>
                            <div class="voizee_field">
                                <label><input type="checkbox" id="voizee_api_cf7_enabled" name="voizee_api_cf7_enabled"
                                              value='1' <?php
									echo esc_attr( checked( 1, $this->cf7_enabled(), false ) ) ?>/>Enable <b>Contact Form 7</b>
                                    integration</label>
                                <span class="hint">Contact Form 7 uses a simple markup structure to embed forms anywhere on your WordPress website.</span>
                                <span class="hint"><?php
									if ( ! $this->cf7_active() ) {
										?><a class="voizee_btn" href="https://wordpress.org/plugins/contact-form-7/">Install</a><?php
									} else { ?><a class="voizee_btn" href="/wp-admin/admin.php?page=wpcf7">Settings</a>
                                        <a class="voizee_btn" href="https://wordpress.org/plugins/contact-form-7/">Website</a><?php
									} ?> <a class="voizee_btn" href="http://contactform7.com/support/">Support</a><?php
									if ( $this->cf7_active() ) { ?> <a class="voizee_btn" id="voizee_cf7-logs-btn"
                                                                       href="#">Logs <span>&#9662;</span></a><?php
									} ?></span>
                            </div>
							<?php
							if ( $this->cf7_enabled() ) : ?>
                                <div class="voizee_field">
                                    <div class="notice notice-info is-dismissible notice-info-grey"><p>Note: It is
                                            required to use a form that captures a telephone number (input type="tel")
                                            in order for Contact Form 7 to integrate properly with Voizee. For more
                                            information, see <a
                                                href="https://voizee.com/docs-category/integrations/"
                                                target="_blank">Using the Voizee WordPress Plugin</a></p></div>
                                </div>
                                <div class="voizee_field">
                                    <div class="notice notice-info is-dismissible notice-info-grey"><p>Note: If you will
                                            request international (non-U.S.) phone numbers with your Contact Form 7
                                            forms, we recommend using the plugin <a
                                                href="https://wordpress.org/plugins/international-telephone-input-for-contact-form-7/"
                                                target="_blank">International Telephone Input for Contact Form 7</a> to
                                            avoid possible formatting issues with Voizee. Both [tel] and [intl_tel] are
                                            now supported as phone inputs.</p></div>
                                </div>
							<?php
							endif; ?>
                            <div class="voizee_field" id="voizee_cf7-logs-list" style="display:none">
                                <div class="voizee_list" data-logs="<?php
								echo esc_attr( htmlspecialchars( get_option( "voizee_api_cf7_logs" ) ) ) ?>"></div>
                            </div>
                            <div class="voizee_field">
                                <label><input type="checkbox" id="voizee_api_gf_enabled" name="voizee_api_gf_enabled"
                                              value='1' <?php
									echo esc_attr( checked( 1, $this->gf_enabled(), false ) ) ?>/>Enable <b>Gravity Forms</b>
                                    integration</label>
                                <span class="hint">Gravity Forms are created using a drag-and-drop editor with support for over 30 input types.</span>
                                <span class="hint"><?php
									if ( ! $this->gf_active() ) {
										?><a class="voizee_btn" href="http://www.gravityforms.com/">Install</a><?php
									} else {
										?><a class="voizee_btn" href="/wp-admin/admin.php?page=gf_settings">Settings</a>
                                        <a class="voizee_btn" href="https://www.gravityforms.com/">Website</a><?php
									} ?> <a class="voizee_btn" href="https://www.gravityhelp.com/support/">Support</a><?php
									if ( $this->gf_active() ) {
										?> <a class="voizee_btn" id="voizee_gf-logs-btn"
                                              href="#">Logs <span>&#9662;</span></a><?php
									} ?></span>
                            </div>
							<?php
							if ( $this->gf_enabled() ) : ?>
                                <div class="voizee_field">
                                    <div class="notice notice-info is-dismissible notice-info-grey"><p>Note: It is
                                            required to use a form that captures a telephone number (input type="tel")
                                            in order for Gravity Forms to integrate properly with Voizee. For
                                            more information, see <a
                                                href="https://voizee.com/docs-category/integrations/"
                                                target="_blank">Using the Voizee WordPress Plugin</a></p></div>
                                </div>
							<?php
							endif; ?>
                            <div class="voizee_field" id="voizee_gf-logs-list" style="display:none">
                                <div class="voizee_list" data-logs="<?php
								echo esc_attr( htmlspecialchars( get_option( "voizee_api_gf_logs" ) ) ) ?>"></div>
                            </div>
                            <footer>
                                <input type="submit" class="voizee_button callout" value="<?php
                                esc_html_e( 'Save Changes', 'voizee' ) ?>" />
                            </footer>
                        </div>
                    </div>
            </form>
        </div>
		<?php
	}

    function invalid_key_msg() {
        return 'Invalid API key. Please check your <a href="' . site_url()
            . '/wp-admin/options-general.php?page=voizee">account settings</a> and try again.';
    }

    function unavailable_msg() {
        return 'Voizee data temporarily unavailable. Please try again later.';
    }

    function inappropriate_api_key_msg() {
        return 'Please use another API KEY with only one widget assigned to it.';
    }

	function update_voizee_options() {
		if ( check_admin_referer( 'update_voizee_options_a', '_vzeo_nonce' ) ) {

			$voizee_widget_code = '';
			if ( !empty( $_POST['voizee_api_key'] ) ) {
				$voizee_api_key = sanitize_text_field( wp_unslash( $_POST['voizee_api_key'] ) );
                // fetch widget code by API
                $result = $this->get_widget_code($voizee_api_key);
                if ( is_numeric( $result ) ) {
                    if ( $result === 401 || $result === 403 ) {
                        $error_msg = $this->invalid_key_msg();
                    } elseif ( $result === 422 ) {
                        $error_msg = $this->inappropriate_api_key_msg();
                    } else {
                        $error_msg = $this->unavailable_msg();
                    }
                    if ( !empty($error_msg) ) {
                        add_settings_error(
                            'voizee_widget_script',
                            'voizee_widget_script',
                            $error_msg,
                            'error'
                        );
                    }
                } elseif ( empty($result['token']) ) {
                    add_settings_error(
                        'voizee_widget_script',
                        'voizee_widget_script',
                        $this->unavailable_msg(),
                        'error'
                    );
                } else {
                    $voizee_widget_code = $result['token'];
                    update_option( 'voizee_api_key', $voizee_api_key );
                }
			} elseif ( $this->has_api_key() ) {
                update_option( 'voizee_api_key', '' );
            }

            $voizee_widget_script = isset( $_POST['voizee_widget_script'] ) ? trim(
                sanitize_text_field( wp_unslash( $_POST['voizee_widget_script'] ) )
            ) : '';


            if ( ! empty( $voizee_widget_script ) ) {
                update_option( 'voizee_widget_script', $voizee_widget_script );
            } elseif ( $voizee_widget_code ) {
                $voizee_widget_code = esc_attr( $voizee_widget_code );
                update_option( 'voizee_widget_script', $voizee_widget_code );
            }

			$voizee_api_dashboard_enabled = '';
			if ( isset( $_POST['voizee_api_dashboard_enabled'] ) ) {
				$voizee_api_dashboard_enabled = (int) $_POST['voizee_api_dashboard_enabled'] ?: '';
			}
			update_option( 'voizee_api_dashboard_enabled', $voizee_api_dashboard_enabled );

			$voizee_api_cf7_enabled = '';
			if ( isset( $_POST['voizee_api_cf7_enabled'] ) ) {
				$voizee_api_cf7_enabled = (int) $_POST['voizee_api_cf7_enabled'] ?: '';
			}
			update_option( 'voizee_api_cf7_enabled', $voizee_api_cf7_enabled );

			$voizee_api_gf_enabled = '';
			if ( isset( $_POST['voizee_api_gf_enabled'] ) ) {
				$voizee_api_gf_enabled = (int) $_POST['voizee_api_gf_enabled'] ?: '';
			}
			update_option( 'voizee_api_gf_enabled', $voizee_api_gf_enabled );

		}
	}

    public function handle_voizee_form_submission() {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['voizee___ous'] ) ) {
            $this->process_voizee_options_submission();
        }
    }

    function process_voizee_options_submission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        if ( ! isset( $_POST['_vzeo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_vzeo_nonce'] ) ), 'update_voizee_options_a' ) ) {
            wp_die( 'Nonce verification failed' );
        }

        $this->update_voizee_options();
    }


    /**
     * Get Voizee widget code
     */
    function get_widget_code($voizee_api_key) {
        $request_args = array(
            'headers'     => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => $voizee_api_key,
            ),
            'body'        => wp_json_encode(['url' => site_url()]),
            'timeout'     => 30,
        );

        $response = wp_safe_remote_post( $this->voizee_host . '/api/v1/get-widget-code', $request_args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code === 200 ) {
            $body = wp_remote_retrieve_body( $response );

            return json_decode( $body, true );
        }

        return $http_code;
    }
}

$create__voizeeoptions = new Voizee_Options();
