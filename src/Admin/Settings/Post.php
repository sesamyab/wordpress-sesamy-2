<?php
/**
 * Post Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\is_config_valid;

/**
 * Post Settings module.
 *
 * @package Sesamy2
 */
class Post {
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
		if ( is_config_valid() ) {
			add_action( 'init', [ $this, 'register_slot_fill_meta' ] );
			add_filter( 'manage_post_posts_columns', [ $this, 'add_custom_column' ] );
			add_action( 'manage_post_posts_custom_column', [ $this, 'populate_custom_column' ], 10, 2 );
			add_action( 'quick_edit_custom_box', [ $this, 'add_quick_edit_field' ], 10, 2 );
			add_action( 'save_post', [ $this, 'save_quick_edit_data' ], 10, 1 );
			add_action( 'bulk_edit_custom_box', [ $this, 'add_bulk_edit_field' ], 10, 2 );
			add_action( 'save_post', [ $this, 'save_bulk_edit_data' ], 10, 1 );
		}
	}

	/**
	 * Registers the `display-mode` post meta for use in the SlotFill lesson.
	 *
	 * @return void
	 */
	public function register_slot_fill_meta() {
		$enabled_post_types = get_enabled_post_types();

		if ( $enabled_post_types ) {
			foreach ( $enabled_post_types as $post_type ) {
				register_post_meta(
					$post_type,
					'_sesamy_locked',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'default'       => false,
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_access_level',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'string',
						'auth_callback' => '__return_true',
						'default'       => 'entitlement',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_enable_single_purchase',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'boolean',
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_price',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'number',
						'auth_callback' => '__return_true',
					]
				);

				register_post_meta(
					$post_type,
					'_sesamy_custom_paywall_url',
					[
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => 'string',
						'auth_callback' => '__return_true',
					]
				);
			}
		}
	}

	/**
	 * Adds a custom column to the post list table.
	 *
	 * @param array $columns An array of column names.
	 * @return array Modified array of column names.
	 */
	public function add_custom_column( $columns ) {
		$columns['sesamy'] = 'Sesamy';
		return $columns;
	}

	/**
	 * Populates the custom column in the post list table.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The ID of the post.
	 * @return void
	 */
	public function populate_custom_column( $column_name, $post_id ) {
		if ( 'sesamy' === $column_name ) {
			$is_locked       = get_post_meta( $post_id, '_sesamy_locked', true );
			$single_purchase = get_post_meta( $post_id, '_sesamy_enable_single_purchase', true );

			$value  = $is_locked ? '<div class="column-sesamy_locked"><span class="dashicons dashicons-lock"></span> Locked</div>' : '';
			$value .= $is_locked && $single_purchase ? '<div class="column-sesamy_single_purchase"><span class="dashicons dashicons-money-alt"></span> Single Purchase</div>' : '';
			echo wp_kses_post( $value );
		}
	}

	/**
	 * Adds a quick edit field to the post list table.
	 *
	 * @param string $column_name The name of the column.
	 * @param string $post_type   The post type.
	 * @return void
	 */
	public function add_quick_edit_field( $column_name, $post_type ) {
		$enabled_post_types = get_enabled_post_types();
		if ( 'sesamy' !== $column_name || ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right" style="margin-top:10px;">
			<div class="inline-edit-col">
				<strong>Sesamy</strong>
				<div class="inline-edit-group wp-clearfix">
					<label class="alignleft">
						<input type="checkbox" name="_sesamy_locked" />
						<span class="checkbox-title">Locked</span>
					</label>
					<label class="alignleft">
						<input type="checkbox" name="_sesamy_enable_single_purchase" />
						<span class="checkbox-title">Single purchase</span>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Saves the quick edit data for the post.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function save_quick_edit_data( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['_inline_edit'] ) ) {
			return;
		}
		check_admin_referer( 'inlineeditnonce', '_inline_edit' );

		$locked = isset( $_POST['_sesamy_locked'] ) ? 1 : 0;
		update_post_meta( $post_id, '_sesamy_locked', $locked );
		$single_purchase = isset( $_POST['_sesamy_enable_single_purchase'] ) ? 1 : 0;
		update_post_meta( $post_id, '_sesamy_enable_single_purchase', $single_purchase );
	}

	/**
	 * Adds a bulk edit field to the post list table.
	 *
	 * @param string $column_name The name of the column.
	 * @param string $post_type   The post type.
	 * @return void
	 */
	public function add_bulk_edit_field( $column_name, $post_type ) {
		$enabled_post_types = get_enabled_post_types();
		if ( 'sesamy' !== $column_name || ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}
		wp_nonce_field( 'sesamy_bulk_edit_action', 'sesamy_bulk_edit_nonce' );
		?>
		<fieldset class="inline-edit-col-right sesamy-bulk-edit">
			<div class="inline-edit-legend">Sesamy</div>
			<div class="inline-edit-col">
				<label class="inline-edit-sesamy-locked wp-clearfix">
					<span class="title">Locked</span>
					<select name="sesamy_locked">
						<option value="-1">— No Change —</option>
						<option value="1">Locked</option>
						<option value="0">Not Locked</option>
					</select>
				</label>
				<label class="inline-edit-sesamy-single-purchase wp-clearfix">
					<span class="title">Single Purchase</span>
					<select name="sesamy_single_purchase">
						<option value="-1">— No Change —</option>
						<option value="1">Enabled</option>
						<option value="0">Disabled</option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Saves the bulk edit data for the post.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function save_bulk_edit_data( $post_id ) {
		// Verify nonce
		if ( ! isset( $_GET['sesamy_bulk_edit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['sesamy_bulk_edit_nonce'] ) ), 'sesamy_bulk_edit_action' ) ) {
			return;
		}
		// Check user capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_GET['sesamy_locked'] ) && '-1' !== $_GET['sesamy_locked'] ) {
			$is_locked = sanitize_text_field( wp_unslash( $_GET['sesamy_locked'] ) ) === '1';
			update_post_meta( $post_id, '_sesamy_locked', $is_locked );
		}
		if ( isset( $_GET['sesamy_single_purchase'] ) && '-1' !== $_GET['sesamy_single_purchase'] ) {
			$single_purchase = sanitize_text_field( wp_unslash( $_GET['sesamy_single_purchase'] ) ) === '1';
			update_post_meta( $post_id, '_sesamy_enable_single_purchase', $single_purchase );
		}
	}
}
