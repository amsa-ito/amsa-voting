<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://amsa.org.au
 * @since             1.0.0
 * @package           Amsa_Voting
 *
 * @wordpress-plugin
 * Plugin Name:       AMSA Voting
 * Plugin URI:        https://amsa.org.au
 * Description:       To faciliate voting process of National Councils at AMSA
 * Version:           2.0.0
 * Author:            Steven Zhang & Harrison Liu
 * Author URI:        https://amsa.org.au/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       amsa-voting
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AMSA_VOTING_VERSION', '2.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-amsa-voting-activator.php
 */
function activate_amsa_voting() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amsa-voting-activator.php';
	Amsa_Voting_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-amsa-voting-deactivator.php
 */
function deactivate_amsa_voting() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-amsa-voting-deactivator.php';
	Amsa_Voting_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_amsa_voting' );
register_deactivation_hook( __FILE__, 'deactivate_amsa_voting' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-amsa-voting.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_amsa_voting() {

	$plugin = new Amsa_Voting();
	$plugin->run();

}
run_amsa_voting();

function amsa_check_dependencies() {
    // Check for WooCommerce Memberships
    if ( ! function_exists( 'wc_memberships' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'AMSA Voting requires WooCommerce Memberships to be active. Plugin deactivated.' );
    }

}
add_action( 'admin_init', 'amsa_check_dependencies' );

