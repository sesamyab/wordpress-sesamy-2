<?php
/**
 * Release Notice module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use function SesamyPlugin\Releases\get_releases;
use function SesamyPlugin\Releases\is_update_available;

/**
 * Release Notice module.
 *
 * @package Sesamy2
 */
class ReleaseNotice {
	/**
	 * Initialize the Assets module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register();
		return $instance;
	}

	/**
	 * Register any hooks and filters.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', [ $this, 'sesamy_settings_notices' ] );
	}


	/**
	 * Display admin notices on the Sesamy settings page.
	 *
	 * @return void
	 */
	public function sesamy_settings_notices() {
		$screen = get_current_screen();

		// Only show on sesamy settings page.
		if ( ! $screen || 'toplevel_page_sesamy' !== $screen->id ) {
			return;
		}

		if ( is_update_available() ) {
			$releases = get_releases();

			$latest_version = is_array( $releases ) && ! empty( $releases ) ? $releases[0] : null;
			$latest_tag     = str_replace( 'v', '', $latest_version['tag_name'] );
			$release_url    = $latest_version['html_url'];
			$docs_url       = 'https://docs.sesamy.com/products/7KJN7PiwkXdWXks753aJt6/downloading-latest-version-and-installning/4zbFtvfEGEvW6bsvDZRVF2';
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong>Update Available!</strong> A new version of Sesamy is available. You have version <?php echo esc_html( SESAMY_PLUGIN_VERSION ); ?> installed,
					<a href="<?php echo esc_url( $release_url ); ?>" target="_blank">
						<strong>update to <?php echo esc_html( $latest_tag ); ?></strong>
					</a><br />
					Visit <a href="<?php echo esc_url( $docs_url ); ?>" target="_blank">our documentation</a> for instructions on how to update.
				</p>
			</div>
			<?php
		}
	}
}
