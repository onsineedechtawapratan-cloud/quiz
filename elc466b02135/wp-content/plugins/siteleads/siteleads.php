<?php

/**
 * Plugin Name: SiteLeads
 * Description: Capture more leads automatically with a multi-channel contact widget and a free AI assistant that works 24/7.
 * Author: ExtendThemes
 * Author URI: https://extendthemes.com
 * Version: 1.1.5
 * License: GPL3+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: siteleads
 * Requires PHP: 7.4
 * Requires at least: 6.8
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// skip loading free version if the Pro version is active
if ( ! function_exists( 'siteleads_is_free_and_pro_already_active' ) ) {

	function siteleads_is_free_and_pro_already_active( $base_path ) {
		$free_plugin_slug = 'siteleads';
		$pro_plugin_slug  = "{$free_plugin_slug}-pro";

		$current_slug = dirname( plugin_basename( $base_path ) );

		// if we are not in the free plugin, return false
		if ( $current_slug !== $free_plugin_slug ) {
			return false;
		}

		$active_plugins = get_option( 'active_plugins' );

		// check if pro plugin is active
		foreach ( $active_plugins as $plugin ) {
			$active_plugin_slug = dirname( $plugin );
			if ( $active_plugin_slug === $pro_plugin_slug ) {
				return true;
			}
		}

		return false;
	}
}

if ( siteleads_is_free_and_pro_already_active( __FILE__ ) ) {
	return;
}


if ( defined( 'SITELEADS_VERSION' ) ) {
	return;
}


define( 'SITELEADS_VERSION', '1.1.5' );
define( 'SITELEADS_BUILD_NUMBER', '136' );

define( 'SITELEADS_ROOT_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SITELEADS_ENTRY_FILE', __FILE__ );
define( 'SITELEADS_ROOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITELEADS_LOGO_PATH', SITELEADS_ROOT_DIR . '/assets/icons/logo-icon.svg' );
define( 'SITELEADS_LOGO_URL', SITELEADS_ROOT_URL . '/assets/icons/logo-icon.svg' );
define( 'SITELEADS_LOGO_SVG', file_get_contents( SITELEADS_LOGO_PATH ) );

if ( ! defined( 'SITELEADS_DASHBOARD_ROOT_URL' ) ) {
	define( 'SITELEADS_DASHBOARD_ROOT_URL', 'https://cloud.siteleads.ai' );
}

if ( ! defined( 'SITELEADS_WEBSITE_URL' ) ) {
	define( 'SITELEADS_WEBSITE_URL', 'https://siteleads.ai' );
}

if ( ! function_exists( 'siteleads_url' ) ) {
	function siteleads_url( $path = '' ) {
		return plugins_url( $path, __FILE__ );
	}
}

global $siteleads_autoloader;
$siteleads_autoloader = require_once __DIR__ . '/vendor/autoload.php';


SiteLeads\Bootstrap::load();
