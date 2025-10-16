<?php
// This file is to add support for updating our themes in the event they are deactivated.

/**
 * Setup themes api filters
 * @since 0.2
*/
function pmproum_theme_setup_update_info() {
	add_filter( 'pre_set_site_transient_update_themes', 'pmproum_update_themes_filter' );
}
add_action( 'admin_init', 'pmproum_theme_setup_update_info', 99 );

/**
 * Get theme update information from the PMPro server.
 * @since  0.2
 */
function pmproum_get_themes() {	
	// Check if forcing a pull from the server.
	$update_info = get_option( 'pmproum_theme_update_info', array() );
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

		// make sure we have at least an array to pass back
		if ( empty( $remote_info ) ) {
			$update_info = array();
		}

		// Test response.
		if ( is_wp_error( $remote_info ) || ! isset( $remote_info['response'] ) || empty( $remote_info['response'] ) || $remote_info['response']['code'] != 200 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$error_response = is_array( $remote_info ) && isset( $remote_info['response'] ) ? print_r( $remote_info['response'], true ) : 'No response data';
				error_log( 'PMPro Update Manager: Error retrieving theme update information from the PMPro license server. Response: ' . $error_response );
			}
		} else {
			// Update update_infos in cache.
			$update_info = json_decode( wp_remote_retrieve_body( $remote_info ), true );
			
			// If the data isn't an array, bail.
			if ( empty( $update_info ) || ! is_array( $update_info ) ) {
				return array();
			}

		}

		// Save timestamp of last update
		update_option( 'pmproum_theme_update_info', $update_info, false );
		update_option( 'pmproum_theme_update_info_timestamp', current_time( 'timestamp' ), false);
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
