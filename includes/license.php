<?php
/*
	This file handles the support licensing control for Paid Memberships Pro
	and PMPro addons.

	How it works:
	- All source code and resource files bundled with this plugin are licensed under the GPLv2 license unless otherwise noted (e.g. included third-party libraries).
	- An additional "support license" can be purchased at https://www.paidmembershipspro.com/pricing/
	  which will simultaneous support the development of this plugin and also give you access to support forums and documentation.
	- Once your license has been purchased, visit Settings --> PMPro License in your WP dashboard to enter your license.
	- Once the license is activated all "nags" will be disabled in the dashboard and member links will be added where appropriate.
    - This plugin will function 100% even if the support license is not installed.
    - If no support license is detected on this site, prompts will show in the admin to encourage you to purchase one.
	- You can override these prompts by setting the PMPRO_LICENSE_NAG constant to false.
*/

/*
	Developers, add this line to your wp-config.php to remove PMPro license nags even if no license has been purchased.

	define('PMPRO_LICENSE_NAG', false);	//consider purchasing a license at https://www.paidmembershipspro.com/pricing/
*/

/*
	Constants
*/
if ( ! defined( 'PMPRO_LICENSE_SERVER' ) ) {
	define( 'PMPRO_LICENSE_SERVER', 'https://license.paidmembershipspro.com/v2/' );
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