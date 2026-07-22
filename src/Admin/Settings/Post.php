<?php
/**
 * Post Settings module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\Settings;

use function SesamyPlugin\Helpers\get_enabled_post_types;
use function SesamyPlugin\Helpers\get_post_locking_term;
use function SesamyPlugin\Helpers\is_config_valid;
use function SesamyPlugin\Helpers\is_post_locked;

/**
 * Post Settings module.
 *
 * @package Sesamy2
 */
class Post {
	/**
	 * Initialize the module.
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
			add_action( 'quick_edit_custom_box', [ $this, 'add_quick_edit_field' ], 10, 2 );
			add_action( 'save_post', [ $this, 'save_quick_edit_data' ] );
			add_action( 'bulk_edit_custom_box', [ $this, 'add_bulk_edit_field' ], 10, 2 );
			add_action( 'save_post', [ $this, 'save_bulk_edit_data' ] );
			add_action( 'add_meta_boxes', [ $this, 'sesamy_meta_box' ] );
			add_action( 'save_post', [ $this, 'save_meta_box_postdata' ] );

			$enabled_post_types = get_enabled_post_types();
			if ( $enabled_post_types ) {
				foreach ( $enabled_post_types as $post_type ) {
					add_filter( 'manage_' . $post_type . '_posts_columns', [ $this, 'add_custom_column' ] );
					add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'populate_custom_column' ], 10, 2 );
				}
			}
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

				register_post_meta(
					$post_type,
					'_sesamy_locked_content_redirect_url',
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
			$is_meta_locked  = (bool) get_post_meta( $post_id, '_sesamy_locked', true );
			$locking_term    = get_post_locking_term( $post_id );
			$is_locked       = is_post_locked( $post_id );
			$single_purchase = get_post_meta( $post_id, '_sesamy_enable_single_purchase', true );

			// `column-sesamy_locked` must reflect the per-post meta only — the
			// quick-edit checkbox is populated from this class (admin.js), and a
			// term- or filter-driven lock must never round-trip into
			// `_sesamy_locked`.
			$value = '';
			if ( $is_meta_locked ) {
				$value = '<div class="column-sesamy_locked"><span class="dashicons dashicons-lock"></span> Locked</div>';
			} elseif ( null !== $locking_term ) {
				$term     = get_term( $locking_term['term_id'], $locking_term['taxonomy'] );
				$taxonomy = get_taxonomy( $locking_term['taxonomy'] );
				$source   = $term instanceof \WP_Term
					? sprintf( '%s: %s', $taxonomy ? $taxonomy->labels->singular_name : $locking_term['taxonomy'], $term->name )
					: $locking_term['taxonomy'];
				$value    = '<div class="column-sesamy_term_locked"><span class="dashicons dashicons-lock"></span> Locked (' . esc_html( $source ) . ')</div>';
			} elseif ( $is_locked ) {
				// Locked via the `sesamy_is_post_locked` filter.
				$value = '<div class="column-sesamy_term_locked"><span class="dashicons dashicons-lock"></span> Locked (filter)</div>';
			}
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

	/**
	 * Adds a meta box to the legacy classic post editor.
	 *
	 * @return void
	 */
	public function sesamy_meta_box() {
		if ( ! is_config_valid() ) {
			return;
		}

		$meta_box_view = new \SesamyPlugin\Admin\View\MetaBox();
		$screens       = get_enabled_post_types();

		add_meta_box(
			'sesamy_meta_box',
			'Sesamy',
			[ $meta_box_view, 'sesamy_meta_box_html' ],
			$screens,
			'side',
			'high',
			[
				'__back_compat_meta_box' => true,
			]
		);
	}

	/**
	 * Saves the meta box data for the post.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function save_meta_box_postdata( $post_id ) {
		// Check if this is an autosave or a revision.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// if our nonce isn't there, or we can't verify it, bail.
		if ( ! isset( $_POST['post_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['post_meta_box_nonce'] ) ), 'sesamy_post_meta_box_nonce' ) ) {
			return;
		}
		// Check if the current user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_locked = isset( $_POST['sesamy_meta_box_locked'] ) ? 1 : 0;
		update_post_meta( $post_id, '_sesamy_locked', $is_locked );
		$single_purchase_enabled = isset( $_POST['sesamy_meta_box_enable_single_purchase'] ) ? 1 : 0;
		update_post_meta( $post_id, '_sesamy_enable_single_purchase', $single_purchase_enabled );
		$price = isset( $_POST['sesamy_meta_box_price'] ) ? sanitize_text_field( wp_unslash( $_POST['sesamy_meta_box_price'] ) ) : '';
		update_post_meta( $post_id, '_sesamy_price', $price );
		$custom_paywall_url = isset( $_POST['sesamy_meta_box_custom_paywall_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sesamy_meta_box_custom_paywall_url'] ) ) : '';
		update_post_meta( $post_id, '_sesamy_custom_paywall_url', $custom_paywall_url );
		$locked_content_redirect_url = isset( $_POST['sesamy_meta_box_locked_content_redirect_url'] ) ? sanitize_text_field( wp_unslash( $_POST['sesamy_meta_box_locked_content_redirect_url'] ) ) : '';
		update_post_meta( $post_id, '_sesamy_locked_content_redirect_url', $locked_content_redirect_url );
	}
}
