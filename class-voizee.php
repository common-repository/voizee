<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

class Voizee {
	public function __construct() {
		$this->voizee_host = "https://app.voizee.com";
		$voizee_script     = get_option( "voizee_widget_script");
		if ( ! empty( $voizee_script ) ) {
			add_action( 'wp_head', [ &$this, 'print_widget_script' ], 10 );
		}
		add_action( 'init', [ &$this, 'form_init' ] );
		add_action( 'admin_menu', [ &$this, 'attach_voizee_dashboard' ] );
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

    function get_widget_script() {
        return get_option( "voizee_widget_script" );
    }

	function form_init() {
		if ( ( $this->cf7_enabled() ) && ( $this->cf7_active() ) ) {
			add_action( 'wpcf7_before_send_mail', [ &$this, 'submit_cf7' ], 10, 2 );
		}
		if ( ( $this->gf_enabled() ) && ( $this->gf_active() ) ) {
			add_action( 'gform_after_submission', [ &$this, 'submit_gf' ], 10, 2 );
		}
	}

	function print_widget_script() {
		if ( ! is_admin() ) {
            wp_register_script(
                'voizee_widget_script',
                '//widget.voizee.com/t/' . esc_attr( get_option( 'voizee_widget_script' ) ),
                array(),
                '1.0.0',
                array(
                    'in_footer' => true,
                    'strategy'  => 'async',
                )
            );
            wp_enqueue_script( 'voizee_widget_script' );
        }
	}

	/**
	 * Send the CF7 submission to Voizee.
	 *
	 * @param WPCF7_ContactForm $form The current contact form.
	 * @param bool $abort Whether to abort the sending or not.
	 */
	function submit_cf7( $form, &$abort ) {
		// If the submission is due to be aborted, don't continue with the Voizee submission.
		if ( true === $abort ) {
			return;
		}

		$this->cf7_log( "Submitting..." );

		$title      = $form->title();
		$entry      = $form->form_scan_shortcode();
		$dataObject = WPCF7_Submission::get_instance();
		$data       = $dataObject->get_posted_data();
		$form_id    = 'wpcf7-' . $dataObject->get_contact_form()->id();

		$fields    = [];
		$labels    = [];
		$sublabels = [];

		foreach ( $entry as $field ) {
			if ( $field["basetype"] == "tel" ) {
				$phone = $data[ $field["name"] ];
			} elseif ( $field["basetype"] == "intl_tel" ) {
				$intl_check = $data[ $field["name"] ];
				$intl_regex = "/^\+(?:[0-9]?){6,14}[0-9]$/";
				if ( preg_match( $intl_regex, $intl_check ) ) {
					$phone = $intl_check;
				}
			} elseif ( $field["basetype"] == "email" ) {
				$email = $data[ $field["name"] ];
			} elseif ( $field["name"] == "your-name" ) {
				$name = $data[ $field["name"] ];
			} elseif ( in_array( $field["basetype"], [ "checkbox", "radio" ] ) ) {
				$fields[ $field["name"] ] = $data[ $field["name"] ];
			} elseif ( $field["basetype"] == "quiz" ) {
				$hash = $data[ "_wpcf7_quiz_answer_" . $field["name"] ];
				foreach ( $field["raw_values"] as $answer ) {
					$answer_pos = strpos( $answer, "|" );
					if ( $answer_pos !== false ) {
						if (
							$hash == wp_hash( wpcf7_canonicalize( substr( $answer, $answer_pos + 1 ) ),
								'wpcf7_quiz' )
						) {
							$fields[ $field["name"] ] = $data[ $field["name"] ];
							$labels[ $field["name"] ] = substr( $answer, 0, $answer_pos );
							break;
						}
					}
				}
			} elseif (
				$field["name"] != ""
				&& in_array( $field["basetype"],
					[ "text", "textarea", "select", "url", "number", "date" ] )
			) {
				$fields[ $field["name"] ] = $data[ $field["name"] ];
			}
		}

		$this->cf7_log( "Title: " . $title . ", Name: " . $name . ", Phone: " . $phone . ", Email: " . $email );

		$fr_data = [
			"type"      => "Contact Form 7",
			"id"        => $form_id,
			"title"     => $title,
			"name"      => $name,
			"phone"     => $phone,
			"email"     => $email,
			"fields"    => $fields,
			"labels"    => $labels,
			"sublabels" => $sublabels,
		];

		/**
		 * Allow other plugins to programmatically add/remove the data.
		 *
		 * @param array $fr_data The current form fields.
		 * @param \WPCF7_ContactForm $form The current Contact Form 7 instance.
		 *
		 * @return array
		 */
		$fr_data = apply_filters( 'voizee_cf7_data', $fr_data, $form );

		$this->send_to_voizee( $fr_data );
	}

	function submit_gf( $entry, $form ) {
		$this->gf_log( "Submitting..." );

		if ( $entry["form_id"] != $form["id"] ) {
			return;
		}
		if ( ! $form["is_active"] ) {
			return;
		}

		$country_code = "";
		$custom       = [];
		foreach ( $form["fields"] as $field ) {
			if ( $field["type"] == "name" ) {
				if ( ! isset( $name ) ) {
					$name = trim( $entry[ $field["id"] . ".3" ] . " " . $entry[ $field["id"] . ".6" ] );
				}
			} elseif ( $field["type"] == "phone" ) {
				if ( ! isset( $phone ) ) {
					$phone = $entry[ $field["id"] ];
				}
				if ( $field["phoneFormat"] == "standard" ) {
					$country_code = "1";
				}
			} elseif ( $field["type"] == "email" ) {
				$email = $entry[ $field["id"] ];
			} elseif ( isset( $field["id"] ) && is_int( $field["id"] ) ) {
				$custom[ $field["id"] ] = $field;
			}
		}

		$this->gf_log( "Title: " . $form["title"] . ", Name: " . $name . ", Phone: " . $phone . ", Email: " . $email );

		// phone numbers are required for Voizee
		if ( ! isset( $phone ) || strlen( $phone ) <= 0 ) {
			$this->gf_log( "No phone number set" );

			return;
		}

		$fields    = [];
		$labels    = [];
		$sublabels = [];

		foreach ( $entry as $field => $value ) {
			$id = intval( $field );
			if ( ! isset( $custom[ $id ] ) ) {
				continue;
			}

			$field    = $custom[ $id ];
			$sublabel = null;

			// file uploads are not supported
			if ( $field["type"] == "fileupload" ) {
				continue;
			}

			if ( $field["type"] == "checkbox" ) {
				// checkboxes use separate "12.1" "12.2" IDs for each input in a list with ID = 12, but process all of them together
				unset( $custom[ $id ] );

				$new_value = [];
				$sublabel  = [];
				foreach ( $field["inputs"] as $index => $checkbox ) {
					if (
						isset( $entry[ $checkbox["id"] ] )
						&& $entry[ $checkbox["id"] ] == $field["choices"][ $index ]["value"]
					) {
						$new_value[] = $entry[ $checkbox["id"] ];
						$sublabel[]  = $checkbox["label"];
					}
				}
				$value = $new_value;

			} elseif ( $field["type"] == "list" ) {
				$value = unserialize( $value );
				if ( ! $value || count( $value ) == 0 ) {
					continue;
				}

				$sublabel = [];
				foreach ( $value[0] as $label => $ignore ) {
					$sublabel[] = $label;
				}

				$new_value = [];
				foreach ( $value as $index => $row ) {
					$new_row = [];
					foreach ( $row as $label ) {
						$new_row[] = $label;
					}
					$new_value[] = $new_row;
				}
				$value = $new_value;

			} elseif ( isset( $field["choices"] ) && is_array( $field["choices"] ) ) {
				// convert the value into an array
				$new_value    = [];
				$pos          = 0;
				$value_length = strlen( $value );
				$sublabel     = [];

				while ( $pos < $value_length ) {
					$best        = null;
					$best_length = 0;

					foreach ( $field["choices"] as $choice ) {
						$choice_length = strlen( $choice["value"] );
						if (
							$choice_length <= $value_length - $pos
							&& substr_compare( $value,
								$choice["value"],
								$pos,
								$choice_length ) == 0
						) {
							if ( ! $best || $choice_length >= $best_length ) {
								$best        = $choice;
								$best_length = $choice_length;
							}
						}
					}
					if ( $best ) {
						$new_value[] = $best["value"];
						$sublabel[]  = $best["text"];

						$pos += $best_length;
					} elseif (
						$pos == 0 && $field["type"] == "radio" && $field["enableOtherChoice"]
						&& $field["enableChoiceValue"]
					) {
						$new_value = $value;
						break;
					}

					// move pos up to past the next comma
					$new_pos = strpos( $value, ",", $pos );
					if ( $new_pos === false ) {
						break;
					}
					$pos = $new_pos + 1;
				}

				$value = $new_value;

			} elseif ( ! is_string( $value ) ) {
				continue;
			}

			$fields[ "field_" . $id ] = $value;
			$labels[ "field_" . $id ] = $field["label"];
			if ( $sublabel ) {
				$sublabels[ "field_" . $id ] = $sublabel;
			}
		}

		$to_voizee_form_data = [
			"type"         => "Gravity Forms",
			"id"           => $form["id"],
			"title"        => $form["title"],
			"name"         => $name,
			"country_code" => $country_code,
			"phone"        => $phone,
			"email"        => $email,
			"fields"       => $fields,
			"labels"       => $labels,
			"sublabels"    => $sublabels,
		];

		/**
		 * Allow other plugins to programmatically add/remove Form Voizee data.
		 *
		 * @param array $to_voizee_form_data The current form fields.
		 * @param array $entry The Entry object.
		 * @param array $form The Form object.
		 *
		 * @return array
		 */
		$to_voizee_form_data = apply_filters( 'voizee_gf_data', $to_voizee_form_data, $entry, $form );

		$this->send_to_voizee( $to_voizee_form_data );
	}

	public function send_to_voizee( $form ) {
		$enabled = [
			"Gravity Forms"  => $this->gf_enabled(),
			"Contact Form 7" => $this->cf7_enabled(),
		];
		if ( ! ( $form && isset( $form["type"] ) && isset( $enabled[ $form["type"] ] )
		         && $enabled[ $form["type"] ] )
		) {
			$this->form_log( $form["type"], "Form integration is not enabled" );

			return;
		}

		// phone numbers are required for Voizee
		if ( ! isset( $form["phone"] ) || strlen( $form["phone"] ) <= 0 ) {
			$this->form_log( $form["type"], "No phone number set" );

			return;
		}

        $data = [];

        if ( $this->get_widget_script() ) {
            $data["widget_code"] = $this->get_widget_script();
        } else {
            $this->form_log( $form["type"], "No widget code" );

            return;
        }

        $data["customer_phone_number"] = $form["phone"];

		if ( isset( $form["country_code"] ) ) {
			$data["country_code"] = $form["country_code"];
		}

		if ( isset( $form["name"] ) && strlen( $form["name"] ) > 3 ) {
            $data["full_name"] = $form["name"];
		}
		if ( isset( $form["email"] ) && strlen( $form["email"] ) > 5 ) {
            $data["email"] = $form["email"];
		}

        $data['db_id'] = isset( $_COOKIE["widgetvoizeecom"] ) ? sanitize_text_field( wp_unslash( $_COOKIE["widgetvoizeecom"] ) ) : '';
        $data['url'] = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

        $request_args = array(
            'headers'     => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => get_option( 'voizee_api_key' ),
            ),
            'body'        => wp_json_encode( $data ),
            'timeout'     => 30,
            'redirection' => 1,
            'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        );

        $response = wp_safe_remote_post( $this->voizee_host . '/api/v1/create/callbackrequest', $request_args );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $this->form_log( $form["type"], "Form POST submission returned error: " . $error_message );
        } else {
            $output = wp_remote_retrieve_body( $response );
            $this->form_log( $form['type'], 'Form POST submission returned: ' . $output );
        }
	}

	function has_api_key() {
		$api_key = get_option( "voizee_api_key" );

		return ! empty( $api_key );
	}

	function activate_msg() {
		return 'Enter your Voizee API key on the <a href="' . site_url()
		       . '/wp-admin/options-general.php?page=voizee">Settings page</a> to get started.';
	}

	function invalid_key_msg() {
		return 'Invalid API key. Please check your <a href="' . site_url()
		       . '/wp-admin/options-general.php?page=voizee">account settings</a> and try again.';
	}

	function unavailable_msg() {
		return 'Voizee data temporarily unavailable. Please try again later.';
	}

	public function attach_voizee_dashboard() {
		if ( current_user_can( 'manage_options' ) && $this->dashboard_enabled() ) {
			add_action( 'wp_dashboard_setup', [ &$this, 'install_dashboard_widget' ] );
		}
	}

	function install_dashboard_widget() {
		wp_add_dashboard_widget( "voizee_dash", "Voizee", [ &$this, 'admin_dashboard_plugin' ] );
	}

	/**
	 * Display a snapshot of recent calls, sms, chats aggregated statistic
	 */
	function admin_dashboard_plugin() {
		if ( ! $this->has_api_key() ) {
            echo wp_kses(
                $this->activate_msg(),
                array(
                    'a' => array(
                        'href' => array(),
                    ),
                )
            );
        } else {
			$voizee_stats_cache         = 'voizee_stats_cache';
			$voizee_stats_cache_timeout = 10 * MINUTE_IN_SECONDS;
			$stats                      = get_transient( $voizee_stats_cache );
			if ( $stats === false ) {
				$stats = $this->get_stats();
				if ( is_numeric( $stats ) ) {
					if ( $stats === 401 || $stats === 403 ) {
                        echo wp_kses(
                            $this->invalid_key_msg(),
                            array(
                                'a' => array(
                                    'href' => array(),
                                ),
                            )
                        );
					} else {
						echo esc_html( $this->unavailable_msg() );
					}
				} else {
					set_transient( $voizee_stats_cache, $stats, $voizee_stats_cache_timeout );
				}
			}
			$dates = [];
			for ( $count = 0; $count <= 30; ++ $count ) {
                $dates[] = gmdate( 'Y-m-d', strtotime( '-' . $count . ' days' ) );
            }

			?>
            <div class="voizee-dash"
                 data-dates='<?php
			     echo esc_attr( wp_json_encode( $dates ) ); ?>'
                 data-today="<?php
			     echo esc_attr( gmdate( 'Y-m-d' ) ) ?>"
                 data-start="<?php
			     echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"
                 data-stats='<?php
			     echo esc_attr( wp_json_encode( $stats ) ) ?>'>
            </div>
            <div style="height:250px;padding-bottom:10px">
                <canvas id="voizee-stat" width="400" height="200"></canvas>
            </div>
            <h3 class="voizee-stat total_calls">Total Calls: <span id="voizee_total_calls"></span></h3>
            <h3 class="voizee-stat total_unique_calls">Total Callers: <span id="voizee_total_unique_calls"></span></h3>
            <h3 class="voizee-stat average_call_length">Average Call Time: <span id="voizee_average_call_length"></span></h3>
            <h3 class="voizee-stat top_call_source">Top Call Source: <span id="voizee_top_call_source"></span></h3>
			<?php
		}
	}

    /**
     * Get Voizee calls statistic
     */
    function get_stats() {
        $start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $end_date = gmdate( 'Y-m-d', strtotime( '-0 days' ) );

        $url = $this->voizee_host . '/api/v1/get-calls-stats?startDate=' . $start_date . '&endDate=' . $end_date;

        $request_headers = array(
            'Content-Type' => 'application/json',
            'X-API-Key'    => get_option( 'voizee_api_key' ),
        );

        // Set request arguments
        $request_args = array(
            'headers'     => $request_headers,
            'timeout'     => 30,
        );

        $response = wp_safe_remote_get( $url, $request_args );

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

	function cf7_log( $message ) {
		$logs = json_decode( get_option( "voizee_api_cf7_logs" ), true );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}
		while ( count( $logs ) >= 20 ) {
			array_shift( $logs );
		}

		array_push( $logs, [ "message" => $message, "date" => gmdate( "c" ) ] );
		update_option( "voizee_api_cf7_logs", wp_json_encode( $logs ) );
	}

	function gf_log( $message ) {
		$logs = json_decode( get_option( "voizee_api_gf_logs" ), true );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}
		while ( count( $logs ) >= 20 ) {
			array_shift( $logs );
		}

		array_push( $logs, [ "message" => $message, "date" => gmdate( "c" ) ] );
		update_option( "voizee_api_gf_logs", wp_json_encode( $logs ) );
	}

	function form_log( $type, $message ) {
		if ( $type == "Contact Form 7" ) {
			$this->cf7_log( $message );
		} elseif ( $type == "Gravity Forms" ) {
			$this->gf_log( $message );
		}
	}

	function debug( $data ) {
		ob_start();
		var_dump( $data );
		$contents = ob_get_contents();
		ob_end_clean();
		error_log( $contents );
	}
}

function voizee_create() {
	$create_voizee = new Voizee();

	require_once( trailingslashit( __DIR__ ) . 'class-voizee-options.php' );
}

add_action( 'plugins_loaded', "voizee_create" );
