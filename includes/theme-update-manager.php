<?php
// This file is to add support for updating our themes in the event they are deactivated.

/**
 * Setup themes api filters
 * @since TBD
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
 * @since  TBD
 */
function pmproum_theme_get_update_info() {
	// Check if forcing a pull from the server.
	$update_info = get_option( 'pmproum_theme_update_info', false );
	$update_info_timestamp = get_option( 'pmproum_theme_update_info_timestamp', 0 );

	// Query the server if we do not have the local $update_info or we force checking for an update.
	if ( empty( $update_info ) || ! empty( $_REQUEST['force-check'] ) || current_time('timestamp') > $update_info_timestamp + 86400 ) {
		/**
         * Filter to change the timeout for this wp_remote_get() request for updates.
         * @since TBD
         * @param int $timeout The number of seconds before the request times out
         */
        $timeout = apply_filters( 'pmproum_theme_get_update_info_timeout', 5 );
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
* @since TBD
* @param object $value  The WordPress update object.
* @return object $value Amended WordPress update object on success, default if object is empty.
*/
function pmproum_update_themes_filter( $value ) {

	// If no update object exists, return early.
	if ( empty( $value ) ) {
		return $value;
	}

	// Get the update JSON for Stranger Studios themes
	$update_info = pmproum_theme_get_update_info();

	// No info found, let's bail.
	if ( empty( $update_info ) ) {
		return $value;
	}

    // Get our themes and figure out if we need to try and serve an update.
	/**
	 * Filter to add themes to the list of themes that should be updated. (Useful to let themes use update code without the need of bundling it in.)
	 * @since TBD
	 * @param array $themes An array of theme slugs that should be updated.
	 */
    $our_themes = apply_filters( 'pmproum_owned_themes', array( 'memberlite' ) );
    
	// Used to build an array of data to return to the transients
    $theme_update_info = array();

    // Loop through $our_themes to see if we need to serve an update.
    foreach ( $our_themes as $theme_slug ) {

		// Get data for the theme and make sure it is found in WordPress.
        $theme_data = wp_get_theme( $theme_slug );

		// If the theme is not found, skip it.
        if ( is_wp_error( $theme_data ) ) {
            continue;
        }

        // Find the theme update data in the update info array.
        $find_theme = array_search( $theme_slug, array_column( $update_info, 'Slug' ) );

        // If the theme update data is found, adjust $update_info to be specifically for memberlite.
        if ( $find_theme !== false ) {
            $theme_update_info = $update_info[$find_theme];
        } else {
            continue;
        }   

        // Compare versions and build the response array for each of our themes.
        if ( ! empty( $theme_update_info['License'] ) && version_compare( $theme_data['Version'], $theme_update_info['Version'], '<' ) ){
            $value->response[$theme_update_info['Slug']] = array(
                'theme' => $theme_update_info['Slug'],
                'new_version' => $theme_update_info['Version'],
                'url' => $theme_update_info['ThemeURI'],
                'package' => $theme_update_info['Download']
            );
        }
    }

    return $value;
}
