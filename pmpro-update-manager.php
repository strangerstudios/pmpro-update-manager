<?php
/**
 * Plugin Name: Paid Memberships Pro - Update Manager
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/update-manager/
 * Description: Manage downloads and updates for all official Paid Memberships Pro Add Ons, themes, and translation files.
 * Version: 0.2.2
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-update-manager
 * Domain Path: /languages
 * License: GPL-3.0
 */

define( 'PMPROUM_BASE_FILE', __FILE__ );
define( 'PMPROUM_BASENAME', plugin_basename( __FILE__ ) );
define( 'PMPROUM_DIR', dirname( __FILE__ ) );
define( 'PMPROUM_VERSION', '0.2.2' );

// Includes
require_once( PMPROUM_DIR . '/includes/theme-update-manager.php' );
require_once( PMPROUM_DIR . '/includes/glotpress-helper.php' );
require_once( PMPROUM_DIR . '/classes/class-pmproum-addons.php' );

/**
 * Some of the code in this library was borrowed from the TGM Updater class by Thomas Griffin. (https://github.com/thomasgriffin/TGM-Updater)
 */


/**
 * Setup PMProUM_AddOns class and license functions.
 *
 * @since 0.1
 */
function pmproum_init() {
	// Set up the license functions if needed.
	if ( ! defined( 'PMPRO_LICENSE_SERVER' ) ) {
		require_once( PMPROUM_DIR . '/includes/license.php' );
	}


	// Set up the AddOns class and hooks.
	PMProUM_AddOns::instance();
}
add_action( 'init', 'pmproum_init' );

/**
 * Handle translation updates from our own translation server.
 * Note: This only needs to run when products are active, to save server resources.
 * @since 0.2
 */
function pmproum_check_for_translations() {

	// Unhook any product we know that is loading translations, for now it's only PMPro Core and Memberlite.
	remove_action( 'admin_init', 'pmpro_check_for_translations', 10 );
	remove_action( 'admin_init', 'memberlite_check_for_translations', 10 );

	// Run it only on a PMPro page in the admin.
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	// Only run this check when we're in the PMPro Page or plugins/update page to save some resources.
	$is_pmpro_admin = ! empty( $_REQUEST['page'] ) && strpos( $_REQUEST['page'], 'pmpro' ) !== false;
	$is_update_or_plugins_page = strpos( $_SERVER['REQUEST_URI'], 'update-core.php' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'plugins.php' ) !== false;
	if ( ! $is_pmpro_admin && ! $is_update_or_plugins_page ) {
		return;
	}

	// Get our themes and Add Ons.
	$pmpro_add_ons = PMProUM_AddOns::instance()->get_addons();
	$pmpro_themes = pmproum_get_themes();

	// Join the themes and Add On JSON into a products array so we can loop through and get active products to update translations for.
	$pmpro_products = array_merge( $pmpro_add_ons, $pmpro_themes );

	// Loop through all active products and see if they have translations available.
	foreach( $pmpro_products as $product ) {

		// Figure out if we're looking for a theme or plugin.
		$product_type = isset( $product['plugin'] ) ? 'plugin' : 'theme';

		// If the product is a plugin, let's check to see if it exists in the WordPress install.
		if ( $product_type === 'plugin' && ! in_array( $product['plugin'], (array) get_option( 'active_plugins', array() ) ) ) {
			continue;
		}
		
		// Check if the theme exists and active, if not, skip it.
		if ( $product_type === 'theme' ) {
			$theme = wp_get_theme();
			
			// Get active theme slug and compare to the slug of the JSON, if it's not the same let's bail.
			if ( is_wp_error( $theme ) || $theme->get_template() !== $product['Slug'] ) {
				continue;
			}
		}

		// Get the product slug so we can pass it to Traduttore.
		$product_slug = $product['Slug'];

		// This uses the Traduttore plugin to check for translations for locales etc.
		PMProUM\Required\Traduttore_Registry\add_project(
			$product_type,
			$product_slug,
			'https://translate.strangerstudios.com/api/translations/' . $product_slug
		);
	}

}
add_action( 'admin_init', 'pmproum_check_for_translations', 5 ); // PMPro core runs this on priority 10.

/**
 * Function to add links to the plugin row meta.
 * @since 0.2.2
 */
function pmproum_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-update-manager.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/update-manager/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-update-manager' ) . '">' . esc_html__( 'Docs', 'pmpro-update-manager' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-update-manager' ) . '">' . esc_html__( 'Support', 'pmpro-update-manager' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmproum_plugin_row_meta', 10, 2 );
