<?php
/**
 * One-time data migrations, run when the stored schema version is behind.
 *
 * Separate from `SESAMY_PLUGIN_VERSION` on purpose: the plugin version is
 * bumped on every release, while this counter only moves when a release needs
 * to *change stored state or remote registrations*. Comparing against the
 * plugin version would re-enter this code on every release for no reason.
 *
 * Adding a migration: bump `SCHEMA_VERSION` and add a matching
 * `migrate_to_<n>()` returning `true` on success (including "nothing to do")
 * and `false` when it should be retried later.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Core;

use SesamyPlugin\Capsule\Registration;

/**
 * Schema/migration runner.
 */
class Upgrade {

	/**
	 * Current schema version. Bump when adding a `migrate_to_<n>()`.
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * Option holding the last schema version we successfully migrated to.
	 * Autoloaded — it's read on every request that reaches `maybe_run()`.
	 */
	private const VERSION_OPTION = 'sesamy_schema_version';

	/**
	 * Transient set when a migration fails, to bound retries. Migrations can
	 * make outbound HTTP calls, so a permanently failing one must not fire on
	 * every request.
	 */
	private const RETRY_TRANSIENT = 'sesamy_schema_upgrade_retry';

	/**
	 * Back-off between retries after a failed migration, in seconds (1 hour).
	 * Literal rather than `HOUR_IN_SECONDS` so this class carries no core
	 * constant dependency.
	 */
	private const RETRY_BACKOFF = 3600;

	/**
	 * Initialize the module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		$instance->register_hooks();
		return $instance;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Runs on `init` rather than `plugins_loaded`: migrations can surface
	 * translated error strings, and calling `__()` before `init` trips the
	 * `_doing_it_wrong` notice WordPress 6.7 added for early translation
	 * loading. Priority 20 puts us after `PluginCore::init()` (priority 8) has
	 * booted the modules a migration may call into.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'maybe_run' ], 20 );
	}

	/**
	 * Run pending migrations, if any, and if this is a request where it's
	 * appropriate to do outbound work.
	 *
	 * @return void
	 */
	public function maybe_run() {
		if ( ! self::is_pending() ) {
			return;
		}
		if ( ! self::is_suitable_request() ) {
			return;
		}
		if ( get_transient( self::RETRY_TRANSIENT ) ) {
			return;
		}
		static::run();
	}

	/**
	 * Whether the stored schema version is behind the current one.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		return self::stored_version() < self::SCHEMA_VERSION;
	}

	/**
	 * Run every migration between the stored version and the current one,
	 * recording progress after each so a failure halfway through isn't
	 * repeated from the start.
	 *
	 * Idempotent: with nothing pending, this returns immediately without
	 * touching options.
	 *
	 * @return bool True when the install is fully migrated.
	 */
	public static function run() {
		$from = self::stored_version();
		if ( $from >= self::SCHEMA_VERSION ) {
			return true;
		}

		for ( $version = $from + 1; $version <= self::SCHEMA_VERSION; $version++ ) {
			$method = 'migrate_to_' . $version;
			// A version number with no migration behind it is fine — the
			// counter is allowed to skip, e.g. when a migration is withdrawn.
			if ( method_exists( static::class, $method ) && ! static::$method() ) {
				set_transient( self::RETRY_TRANSIENT, 1, self::RETRY_BACKOFF );
				return false;
			}
			update_option( self::VERSION_OPTION, $version, true );
		}

		delete_transient( self::RETRY_TRANSIENT );
		return true;
	}

	// ---------------------------------------------------------------------
	// Migrations
	// ---------------------------------------------------------------------

	/**
	 * Re-register this site's domain with a pinned signing-key PEM.
	 *
	 * Installs that registered before this release pointed the api-proxy at
	 * our `jwksUri`, which makes every reader's unlock depend on a synchronous
	 * fetch of this site's own origin — and fail closed when an edge WAF or
	 * rate limit answers it with a 403. Flipping `Registration::pick_mode()`
	 * only helps sites that register from now on, so push the key once for
	 * everyone already out there.
	 *
	 * Only the domain we auto-registered is touched; domains an admin added by
	 * hand keep whatever mode they were given.
	 *
	 * @return bool False to retry later (leaves the schema version untouched).
	 */
	protected static function migrate_to_1() {
		$result = Registration::repin_auto_domain();

		if ( is_wp_error( $result ) ) {
			error_log( '[sesamy] Re-registering the publisher domain with a pinned PEM failed, will retry: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
		return true;
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * The schema version this install last migrated to. `0` for installs that
	 * predate this runner.
	 *
	 * @return int
	 */
	private static function stored_version() {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Whether this is a request we're willing to do migration work on.
	 *
	 * Migrations may issue outbound HTTP, and the whole point of this release
	 * is to keep blocking network calls out of the reader's path — so confine
	 * them to admin, cron, and WP-CLI requests. Cron alone is enough to reach
	 * a site nobody logs into: WordPress spawns it off front-end traffic.
	 *
	 * @return bool
	 */
	private static function is_suitable_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return is_admin() || wp_doing_cron();
	}
}
