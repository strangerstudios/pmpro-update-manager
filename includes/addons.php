<?php
/**
 * Some common functions required to deal with addons.
 * Copies of these functions are included in core PMPro as well.
 */

if ( ! function_exists( 'pmpro_getAddons' ) ) {
    /**
     * Get addon information from PMPro server.
     *
     * @since  1.8.5
     */
    function pmpro_getAddons() {
        // check if forcing a pull from the server
        $addons = get_option( 'pmpro_addons', array() );
        $addons_timestamp = get_option( 'pmpro_addons_timestamp', 0 );

        // if no addons locally, we need to hit the server
        if ( empty( $addons ) || ! empty( $_REQUEST['force-check'] ) || current_time( 'timestamp' ) > $addons_timestamp + 86400 ) {
            /**
             * Filter to change the timeout for this wp_remote_get() request.
             *
             * @since 1.8.5.1
             *
             * @param int $timeout The number of seconds before the request times out
             */
            $timeout = apply_filters( 'pmpro_get_addons_timeout', 5 );

            // get em
            $remote_addons = wp_remote_get( PMPRO_LICENSE_SERVER . 'addons/', $timeout );

            // make sure we have at least an array to pass back
            if ( empty( $addons ) ) {
                $addons = array();
            }

            // test response
            if ( is_wp_error( $remote_addons ) ) {
                // Error. We're quiet. The code in PMPro core shows an error message.
            } elseif ( ! empty( $remote_addons ) && $remote_addons['response']['code'] == 200 ) {
                // update addons in cache
                $addons = json_decode( wp_remote_retrieve_body( $remote_addons ), true );
                foreach ( $addons as $key => $value ) {
                    $addons[$key]['ShortName'] = trim( str_replace( array( 'Add On', 'Paid Memberships Pro - ' ), '', $addons[$key]['Title'] ) );
                }
                // Alphabetize the list by ShortName.
                $short_names = array_column( $addons, 'ShortName' );
                array_multisort( $short_names, SORT_ASC, SORT_STRING | SORT_FLAG_CASE, $addons );

                delete_option( 'pmpro_addons' );
                add_option( 'pmpro_addons', $addons, null, 'no' );
            }

            // save timestamp of last update
            delete_option( 'pmpro_addons_timestamp' );
            add_option( 'pmpro_addons_timestamp', current_time( 'timestamp' ), null, 'no' );
        }

        return $addons;
    }
}

if ( ! function_exists( 'pmpro_getAddonBySlug' ) ) {
    /**
     * Find a PMPro addon by slug.
     *
     * @since 1.8.5
     *
     * @param object $slug  The identifying slug for the addon (typically the directory name)
     * @return object $addon containing plugin information or false if not found
     */
    function pmpro_getAddonBySlug( $slug ) {
        $addons = pmpro_getAddons();

        if ( empty( $addons ) ) {
            return false;
        }

        foreach ( $addons as $addon ) {
            if ( $addon['Slug'] == $slug ) {
                return $addon;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'pmpro_getPluginAPIObjectFromAddon' ) ) {
    /**
     * Convert the format from the pmpro_getAddons function to that needed for plugins_api
     *
     * @since  1.8.5
     */
    function pmpro_getPluginAPIObjectFromAddon( $addon ) {
        $api                        = new stdClass();

        if ( empty( $addon ) ) {
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
                /* translators: %s is the license type */
                $api->upgrade_notice = sprintf( __( 'Important: This plugin requires a valid PMPro %s license key to update.', 'pmpro-update-manager' ), ucwords( $addon['License'] ) );
            }
        }	

        return $api;
    }
}

if ( ! function_exists( 'pmpro_license_type_is_premium' ) ) {
    /**
     * Check if a license type is "premium"
     * @since 2.7.4
     * @param string $type The license type for an add on for license key.
     * @return bool True if the type is for a paid PMPro membership, false if not.
     */
    function pmpro_license_type_is_premium( $type ) {
        $premium_types = pmpro_license_get_premium_types();
        return in_array( strtolower( $type ), $premium_types, true );
    }
}

if ( ! function_exists( 'pmpro_license_get_premium_types' ) ) {
    /**
     * Get array of premium license types.
     * @since 2.7.4
     * @return array Premium types.
     */
    function pmpro_license_get_premium_types() {
        return array( 'standard', 'plus', 'builder' );
    }
}

if ( ! function_exists( 'pmpro_license_isValid' ) ) {
    /**
     * Check if a license key is valid.
     * We're returning false all the time here.
     * If PMPro were active, the function there
     * would be used instead and really check the key.
     */
    function pmpro_license_isValid( $key = NULL, $type = NULL, $force = false ) {
        return false;
    }
}