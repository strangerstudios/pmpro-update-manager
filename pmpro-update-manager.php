<?php
/**
 * Plugin Name: Paid Memberships Pro - Update Manager
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/update-manager/
 * Description: Manage all Paid Memberships Pro ecosystem Add Ons downloads and updates.
 * Version: 0.1
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-update-manager
 * Domain Path: /languages
 * License: GPL-3.0
 */

define( 'PMPROUM_BASE_FILE', __FILE__ );
define( 'PMPROUM_BASENAME', plugin_basename( __FILE__ ) );
define( 'PMPROUM_DIR', dirname( __FILE__ ) );
define( 'PMPROUM_VERSION', '0.1' );

/**
 * Some of the code in this library was borrowed from the TGM Updater class by Thomas Griffin. (https://github.com/thomasgriffin/TGM-Updater)
 */

/**
 * Setup plugins api filters
 *
 * @since 0.1
 */
function pmproum_setupAddonUpdateInfo() {
	// Only load this stuff in the admin dashboard.
	if ( ! is_admin() ) {
		return;
	}
	
	// Make sure we define the license server in case PMPro is not loaded.
	if ( ! defined( 'PMPRO_LICENSE_SERVER' ) ) {
		define('PMPRO_LICENSE_SERVER', 'https://license.paidmembershipspro.com/v2/');
	}
	
	add_filter( 'plugins_api', 'pmproum_plugins_api', 10, 3 );
	add_filter( 'pre_set_site_transient_update_plugins', 'pmproum_update_plugins_filter' );
	add_filter( 'http_request_args', 'pmproum_http_request_args_for_addons', 10, 2 );
}
add_action( 'init', 'pmproum_setupAddonUpdateInfo', 10 );

/**
 * Infuse plugin update details when WordPress runs its update checker.
 *
 * @since 0.1
 *
 * @param object $value  The WordPress update object.
 * @return object $value Amended WordPress update object on success, default if object is empty.
 */
function pmproum_update_plugins_filter( $value ) {

	// If no update object exists, return early.
	if ( empty( $value ) ) {
		return $value;
	}

    // If PMPro is not active, load some functions we need.
    if ( ! function_exists( 'pmpro_getAddons' ) ) {
		require_once( PMPROUM_DIR . '/includes/addons.php' );
    }

	// Get Add On information
	$addons = pmpro_getAddons();

	// No Add Ons?
	if ( empty( $addons ) ) {
		return $value;
	}

	// Check Add Ons
	foreach ( $addons as $addon ) {
		// Skip for wordpress.org plugins
		if ( empty( $addon['License'] ) || $addon['License'] == 'wordpress.org' ) {
			continue;
		}

		// Get data for plugin
		$plugin_file = $addon['Slug'] . '/' . $addon['Slug'] . '.php';
		$plugin_file_abs = WP_PLUGIN_DIR . '/' . $plugin_file;

		// Couldn't find plugin? Skip
		if ( ! file_exists( $plugin_file_abs ) ) {
			continue;
		} else {
			$plugin_data = get_plugin_data( $plugin_file_abs, false, true );
		}

		// Compare versions
		if ( version_compare( $plugin_data['Version'], $addon['Version'], '<' ) ) {
			$value->response[ $plugin_file ] = pmpro_getPluginAPIObjectFromAddon( $addon );
			$value->response[ $plugin_file ]->new_version = $addon['Version'];
			if ( function_exists( 'pmpro_get_addon_icon' ) ) {
				$value->response[ $plugin_file ]->icons = array( 'default' => esc_url( pmpro_get_addon_icon( $addon['Slug'] ) ) );
			}
		} else {
			$value->no_update[ $plugin_file ] = pmpro_getPluginAPIObjectFromAddon( $addon );
		}
	}

	// Return the update object.
	return $value;
}

/**
 * Disables SSL verification to prevent download package failures.
 *
 * @since 0.1
 *
 * @param array  $args  Array of request args.
 * @param string $url  The URL to be pinged.
 * @return array $args Amended array of request args.
 */
function pmproum_http_request_args_for_addons( $args, $url ) {
	// If this is an SSL request and we are performing an upgrade routine, disable SSL verification.
	if ( strpos( $url, 'https://' ) !== false && strpos( $url, PMPRO_LICENSE_SERVER ) !== false && strpos( $url, 'download' ) !== false ) {
		$args['sslverify'] = false;
	}

	return $args;
}

/**
 * Setup plugin updaters
 *
 * @since  0.1
 */
function pmproum_plugins_api( $api, $action = '', $args = null ) {
	// Not even looking for plugin information? Or not given slug?
	if ( 'plugin_information' != $action || empty( $args->slug ) ) {
		return $api;
	}

    // If PMPro is not active, load some functions we need.
    if ( ! function_exists( 'pmpro_getAddonBySlug' ) ) {
        require_once( PMPROUM_DIR . '/includes/addons.php' );
    }

	// get addon information
	$addon = pmpro_getAddonBySlug( $args->slug );

	// no addons?
	if ( empty( $addon ) ) {
		return $api;
	}

	// handled by wordpress.org?
	if ( empty( $addon['License'] ) || $addon['License'] == 'wordpress.org' ) {
		return $api;
	}

	// Create a new stdClass object and populate it with our plugin information.
	$api = pmpro_getPluginAPIObjectFromAddon( $addon );
	return $api;
}
