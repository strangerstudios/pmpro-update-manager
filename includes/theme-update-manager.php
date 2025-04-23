<?php
// This file is to add support for updating our themes in the event they are deactivated.

/**
 * Setup themes api filters
 * @since 0.2
*/
function pmproum_theme_setup_update_info() {

	if ( ! defined( 'PMPRO_LICENSE_SERVER' ) ) {
		define('PMPRO_LICENSE_SERVER', 'https://license.paidmembershipspro.com/v2/' );
	}

	add_filter( 'pre_set_site_transient_update_themes', 'pmproum_update_themes_filter' );
}
add_action( 'admin_init', 'pmproum_theme_setup_update_info', 99 );

/**
 * Get theme update information from the PMPro server.
 * @since  0.2
 */
function pmproum_get_themes() {
	// Check if forcing a pull from the server.
	$update_info = get_option( 'pmproum_theme_update_info', false );
	$update_info_timestamp = get_option( 'pmproum_theme_update_info_timestamp', 0 );

	// Query the server if we do not have the local $update_info or we force checking for an update.
	if ( empty( $update_info ) || ! empty( $_REQUEST['force-check'] ) || current_time('timestamp') > $update_info_timestamp + 86400 ) {
        /**
         * Filter to change the timeout for this wp_remote_get() request for updates.
         * @since 0.2
         * @param int $timeout The number of seconds before the request times out
         */
        $timeout = apply_filters( 'pmproum_get_themes_timeout', 5 );
        $remote_info = wp_remote_get( PMPRO_LICENSE_SERVER . 'themes/', $timeout );

		// Test response.
        if ( is_wp_error( $remote_info ) || empty( $remote_info['response'] ) || $remote_info['response']['code'] != '200' ) {
			// Error.
			return new WP_Error( 'connection_error', 'Could not connect to the PMPro License Server to get update information. Try again later.' );
		} else {
			// Update update_infos in cache.
			$update_info = json_decode( wp_remote_retrieve_body( $remote_info ), true );
			delete_option( 'pmproum_theme_update_info' );
			add_option( 'pmproum_theme_update_info', $update_info, NULL, 'no' );
		}

		// Save timestamp of last update
		delete_option( 'pmproum_theme_update_info_timestamp' );
		add_option( 'pmproum_theme_update_info_timestamp', current_time('timestamp'), NULL, 'no' );
	}

	return $update_info;
}

/**
* Infuse theme update details when WordPress runs its update checker.
* @since 0.2
* @param object $value  The WordPress update object.
* @return object $value Amended WordPress update object on success, default if object is empty.
*/
function pmproum_update_themes_filter( $value ) {

	// If no update object exists, return early.
	if ( empty( $value ) ) {
		return $value;
	}

	// Get the update JSON for Stranger Studios themes
	$update_info = pmproum_get_themes();

	// No info found, let's bail.
	if ( empty( $update_info ) ) {
		return $value;
	}

	// Loop through the $update_info array to see if the theme exists, and if it does let's try serve an update. This saves some API calls to our license server.
	foreach ( $update_info as $theme_info ) {
		if ( ! empty( $theme_info['Slug'] ) ) {

			$theme_exists = wp_get_theme( $theme_info['Slug'] );

			// Make sure the theme exists before we try to see if an update is needed.
			if ( $theme_exists->exists() ) {
				// Compare versions and build the response array for each of our themes.
				if ( version_compare( $theme_exists['Version'], $theme_info['Version'], '<' ) ) {
					$value->response[$theme_info['Slug']] = array(
						'theme' => $theme_info['Slug'],
						'new_version' => $theme_info['Version'],
						'url' => $theme_info['ThemeURI'],
						'package' => $theme_info['Download']
					);
				}
			}
		}
    
    }
    return $value;
}
