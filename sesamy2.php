<?php
/**
 * Plugin Name:       Sesamy2
 * Plugin URI:        https://sesamy.com
 * Description:       Add Sesamy functionality (sesamy.com) to your WordPress website.
 * Version:           0.1.0
 * Requires at least: 4.9
 * Requires PHP:      7.2
 * Author:            Sesamy
 * Author URI:        https://sesamy.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       sesamy2
 *
 * @link              https://sesamy.com
 * @package           Sesamy2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Useful global constants.
define( 'SESAMY_PLUGIN_VERSION', '1.0.0' );
define( 'SESAMY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SESAMY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SESAMY_PLUGIN_INC', SESAMY_PLUGIN_PATH . 'src/' );
define( 'SESAMY_PLUGIN_DIST_URL', SESAMY_PLUGIN_URL . 'dist/' );
define( 'SESAMY_PLUGIN_DIST_PATH', SESAMY_PLUGIN_PATH . 'dist/' );

$is_local_env = in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
$is_local_url = strpos( home_url(), '.test' ) || strpos( home_url(), '.local' );
$is_local     = $is_local_env || $is_local_url;

if ( $is_local && file_exists( __DIR__ . '/dist/fast-refresh.php' ) ) {
	require_once __DIR__ . '/dist/fast-refresh.php';

	if ( function_exists( 'TenUpToolkit\set_dist_url_path' ) ) {
		TenUpToolkit\set_dist_url_path( basename( __DIR__ ), SESAMY_PLUGIN_DIST_URL, SESAMY_PLUGIN_DIST_PATH );
	}
}

// Bail if Composer autoloader is not found.
if ( ! file_exists( SESAMY_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	throw new \Exception(
		'Vendor autoload file not found. Please run `composer install`.'
	);
}

require_once SESAMY_PLUGIN_PATH . 'vendor/autoload.php';

$plugin_core = new \SesamyPlugin\PluginCore();

// Activation/Deactivation.
register_activation_hook( __FILE__, [ $plugin_core, 'activate' ] );
register_deactivation_hook( __FILE__, [ $plugin_core, 'deactivate' ] );

// Bootstrap.
$plugin_core->setup();
