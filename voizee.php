<?php
/**
 * Plugin Name: Voizee
 * Plugin URI: https://voizee.com/docs/how-to-install-voizees-wordpress-plugin/?utm_source=wp-plugin&utm_medium=voizee-for-wp&utm_campaign=plugins-page
 * Description: Voizee is a powerful communications suite application that offers callbacks, live chat, SMS, and email capabilities, all in one integrated solution
 * Version: 1.0.0
 * Author: Voizee
 * Author URI: https://voizee.com
 * Requires at least: 4.1.0
 * Requires PHP: 5.3
 * License: GPL v3 or later
 * License URI: http://www.gnu.org/licenses/gpl.html
 * Text Domain: voizee
 * Domain Path: /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function voizee_WP_error() {
    echo '<div id="voizee_error" class="update-message notice inline notice-error notice-alt" style="margin:30px 0 10px 0;">';
    echo '<p><strong>Your WordPress version is too old for the Voizee plugin.</strong></p>';
    echo '</div>';
}

function voizee_PHP_error() {
    echo '<div id="voizee_error" class="update-message notice inline notice-error notice-alt" style="margin:30px 0 10px 0;">';
    echo '<p><strong>Your PHP version is too old for the Voizee plugin.</strong></p>';
    echo '</div>';
}

function voizee_enq_voizee_css( $hook ) {
	$hook_voizee = 'settings_page_voizee';
	if ( $hook !== $hook_voizee ) {
		return;
	}

	wp_register_style( 'v_z_e', plugins_url( 'css/v_z_e.css', __FILE__ ), array(), '1.0.0' );
	wp_enqueue_style( 'v_z_e' );
}

function voizee_enq_voizee_js( $hook ) {
	if ( $hook === 'index.php' ) {
		wp_enqueue_script( 'voizee_chart', plugins_url( 'js/chart.min.js', __FILE__ ), array(), '4.4.4', true );
        wp_enqueue_script( 'voizee_dashboard', plugins_url( 'js/voizee-dashboard.js', __FILE__ ), array( 'jquery', 'voizee_chart' ), '1.0.0', true );
	}
}

/**
 * Check requirements
 */
function voizee_requirements() {
	$check_result_fail = false;

	if ( version_compare( $GLOBALS["wp_version"], "4.1.0", "<" ) ) {
		add_action( 'admin_notices', 'voizee_WP_error' );
		$check_result_fail = true;
	}

	if ( version_compare( PHP_VERSION, "5.3", "<" ) ) {
		add_action( 'admin_notices', 'voizee_PHP_error' );
		$check_result_fail = true;
	}

	if ( ! $check_result_fail ) {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', 'voizee_enq_voizee_css' );
			add_action( 'admin_enqueue_scripts', 'voizee_enq_voizee_js' );
		}

		require_once( trailingslashit( __DIR__ ) . 'class-voizee.php' );
	}
}

/**
 * Settings link on plugin page
 */
function voizee_settings_link( $links, $file ) {
	$plugin = plugin_basename( __FILE__ );
	if ( $file !== $plugin ) {
		return $links;
	}

	$settings_link = '<a href="' . admin_url( 'options-general.php?page=voizee' ) . '">' . esc_html( __( 'Settings',
			'voizee' ) ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links', 'voizee_settings_link', 10, 2 );

/**
 * Uninstall cleanup
 */
function voizee_deactivate() {
	$voizee_options = [
		"voizee_api_key",
		"voizee_api_dashboard_enabled",
		"voizee_api_cf7_enabled",
		"voizee_api_gf_enabled",
		"voizee_widget_script",
		"voizee_api_cf7_logs",
		"voizee_api_gf_logs",
	];
	foreach ( $voizee_options as $option ) {
		delete_option( $option );
	}
	delete_transient( 'voizee_stats_cache' );
}

register_uninstall_hook( __FILE__, 'voizee_deactivate' );

/****** run thru requirements ******/
if ( defined( 'ABSPATH' ) ) {
	voizee_requirements();
}
