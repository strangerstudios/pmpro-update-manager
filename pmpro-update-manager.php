<?php
/**
 * Plugin Name: Paid Memberships Pro - Update Manager
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-update-manager/
 * Description: Manage plugin updates for PMPro Add Ons.
 * Version: 0.1
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-update-manager
 * Domain Path: /languages
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
    // If PMPro < 3.1 is running, it will handle updates itself.
	if ( defined( 'PMPRO_VERSION' ) && version_compare( PMPRO_VERSION, '3.1', '<' ) ) {
		return;
	}
	
	add_filter( 'plugins_api', 'pmproum_plugins_api', 10, 3 );
	add_filter( 'pre_set_site_transient_update_plugins', 'pmproum_update_plugins_filter' );
	add_filter( 'http_request_args', 'pmproum_http_request_args_for_addons', 10, 2 );
}
add_action( 'init', 'pmproum_setupAddonUpdateInfo' );

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

    // If PMPro is not active, bail.
    if ( ! function_exists( 'pmpro_getAddons' ) ) {
        return $value;
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
			$value->response[ $plugin_file ]->icons = array( 'default' => esc_url( pmpro_get_addon_icon( $addon['Slug'] ) ) );
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

    // If PMPro is not active, bail.
    if ( ! function_exists( 'pmpro_getAddonBySlug' ) ) {
        return $api;
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

/**
 * Convert the format from the pmpro_getAddons function to that needed for plugins_api
 *
 * @since  0.1
 */
function pmproum_getPluginAPIObjectFromAddon( $addon ) {
	$api                        = new stdClass();

	if ( empty( $addon ) ) {
		return $api;
	}

    // If PMPro is not active, bail.
    if ( ! function_exists( 'pmpro_license_type_is_premium' ) ) {
        return $api;
    }

	// add info
	$api->name                  = isset( $addon['Name'] ) ? $addon['Name'] : '';
	$api->slug                  = isset( $addon['Slug'] ) ? $addon['Slug'] : '';
	$api->plugin                = isset( $addon['plugin'] ) ? $addon['plugin'] : '';
	$api->version               = isset( $addon['Version'] ) ? $addon['Version'] : '';
	$api->author                = isset( $addon['Author'] ) ? $addon['Author'] : '';
	$api->author_profile        = isset( $addon['AuthorURI'] ) ? $addon['AuthorURI'] : '';
	$api->requires              = isset( $addon['Requires'] ) ? $addon['Requires'] : '';
	$api->tested                = isset( $addon['Tested'] ) ? $addon['Tested'] : '';
	$api->last_updated          = isset( $addon['LastUpdated'] ) ? $addon['LastUpdated'] : '';
	$api->homepage              = isset( $addon['URI'] ) ? $addon['URI'] : '';
	// It is against the current wp.org guidelines to override these download locations, but we are okay doing this in our own plugin hosted elsewhere.
	$api->download_link         = isset( $addon['Download'] ) ? $addon['Download'] : '';
	$api->package               = isset( $addon['Download'] ) ? $addon['Download'] : '';

	// add sections
	if ( !empty( $addon['Description'] ) ) {
		$api->sections['description'] = $addon['Description'];
	}
	if ( !empty( $addon['Installation'] ) ) {
		$api->sections['installation'] = $addon['Installation'];
	}
	if ( !empty( $addon['FAQ'] ) ) {
		$api->sections['faq'] = $addon['FAQ'];
	}
	if ( !empty( $addon['Changelog'] ) ) {
		$api->sections['changelog'] = $addon['Changelog'];
	}

	// get license key if one is available
	$key = get_option( 'pmpro_license_key', '' );
	if ( ! empty( $key ) && ! empty( $api->download_link ) ) {
		$api->download_link = add_query_arg( 'key', $key, $api->download_link );
	}
	if ( ! empty( $key ) && ! empty( $api->package ) ) {
		$api->package = add_query_arg( 'key', $key, $api->package );
	}
	
	if ( empty( $api->upgrade_notice ) && pmpro_license_type_is_premium( $addon['License'] ) ) {
		if ( ! pmpro_license_isValid( null, $addon['License'] ) ) {
			$api->upgrade_notice = sprintf( __( 'Important: This plugin requires a valid PMPro %s license key to update.', 'paid-memberships-pro' ), ucwords( $addon['License'] ) );
		}
	}	

	return $api;
}
