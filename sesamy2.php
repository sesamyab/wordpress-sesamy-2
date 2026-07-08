<?php
/**
 * Plugin Name:       Sesamy2
 * Plugin URI:        https://sesamy.com
 * Description:       Add Sesamy functionality (sesamy.com) to your WordPress website.
 * Version:           1.3.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
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

// Guard against the plugin file being loaded more than once in a single
// request (e.g. when the plugin is present both standalone and as a Composer
// dependency of the site). Re-entry would redefine the constants below and,
// worse, bootstrap the plugin twice — registering every hook a second time.
if ( defined( 'SESAMY_PLUGIN_VERSION' ) ) {
	return;
}

// Useful global constants.
define( 'SESAMY_PLUGIN_VERSION', '1.3.0' );
// Pinned version of `@sesamy/sesamy-js`. Kept in sync with
// `package.json` by `update-plugin-version.js` on precommit. The bootstrap
// loader is bundled at this version and the same value is emitted in the
// `<script id="sesamy-js">` config so the script-host chain (auth0-plugin,
// capsule-plugin, sesamy-components, core) loads in lockstep.
define( 'SESAMY_JS_VERSION', '1.120.2' );
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

// Load the Composer autoloader. When the plugin is installed standalone (the
// zip distribution) it ships its own `vendor/`. When installed as a Composer
// dependency of the site, the classes are registered in the site's root
// autoloader, which is loaded before plugins run.
if ( file_exists( SESAMY_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once SESAMY_PLUGIN_PATH . 'vendor/autoload.php';
}

if ( ! class_exists( \SesamyPlugin\PluginCore::class ) ) {
	throw new \Exception(
		'Sesamy dependencies not found. Run `composer install` in the plugin directory, or install the plugin via Composer.'
	);
}

$plugin_core = new \SesamyPlugin\PluginCore();

// Activation/Deactivation.
register_activation_hook( __FILE__, [ $plugin_core, 'activate' ] );
register_deactivation_hook( __FILE__, [ $plugin_core, 'deactivate' ] );

// Bootstrap.
$plugin_core->setup();
