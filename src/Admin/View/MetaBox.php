<?php
/**
 * MetaBox module.
 *
 * @package Sesamy2
 */

namespace SesamyPlugin\Admin\View;

use function SesamyPlugin\Helpers\get_post_locking_term;

/**
 * MetaBox module.
 *
 * @package Sesamy2
 */
class MetaBox {
	/**
	 * Initialize the module.
	 *
	 * @return self
	 */
	public static function init() {
		$instance = new self();
		return $instance;
	}

	/**
	 * Renders the HTML for the Sesamy meta box.
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function sesamy_meta_box_html( $post ) {
		// We'll use this nonce field later on when saving.
		wp_nonce_field( 'sesamy_post_meta_box_nonce', 'post_meta_box_nonce' );

		$is_meta_locked = (bool) get_post_meta( $post->ID, '_sesamy_locked', true );
		$locking_term   = get_post_locking_term( $post->ID );
		$is_locked      = $is_meta_locked || null !== $locking_term;

		echo '<div class="misc-pub-section">';
		if ( null !== $locking_term ) {
			// Term-locked: the checkbox is display-only (checked + disabled) and
			// the rule never writes `_sesamy_locked`. Disabled inputs don't
			// submit, so a hidden field preserves the underlying per-post toggle
			// across saves (`save_meta_box_postdata` keys off isset).
			if ( $is_meta_locked ) {
				echo '<input type="hidden" name="sesamy_meta_box_locked" value="1" />';
			}
			echo '<label><input value="1" type="checkbox" id="sesamy_meta_box_locked" checked disabled /> ' . esc_html( __( 'Lock this article', 'sesamy' ) ) . '</label>';

			$term          = get_term( $locking_term['term_id'], $locking_term['taxonomy'] );
			$taxonomy      = get_taxonomy( $locking_term['taxonomy'] );
			$taxonomy_name = $taxonomy ? strtolower( $taxonomy->labels->singular_name ) : $locking_term['taxonomy'];
			$term_name     = $term instanceof \WP_Term ? $term->name : (string) $locking_term['term_id'];
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: 1: taxonomy singular name (e.g. category), 2: term name. */
					__( 'Locked automatically by %1$s “%2$s”. Manage under Settings → Sesamy.', 'sesamy' ),
					$taxonomy_name,
					$term_name
				)
			) . '</p>';
		} else {
			echo '<label><input value="1" type="checkbox" name="sesamy_meta_box_locked" id="sesamy_meta_box_locked" ' . checked( $is_meta_locked, true, false ) . ' /> ' . esc_html( __( 'Lock this article', 'sesamy' ) ) . '</label>';
		}
		echo '</div>';

		if ( $is_locked ) {
			$single_purchase_enabled = get_post_meta( $post->ID, '_sesamy_enable_single_purchase', true );
			echo '<div class="misc-pub-section">';
			echo '<label><input value="1" type="checkbox" name="sesamy_meta_box_enable_single_purchase" id="sesamy_meta_box_enable_single_purchase" ' . checked( $single_purchase_enabled, true, false ) . ' /> ' . esc_html( __( 'Enable single-purchase', 'sesamy' ) ) . '</label>';
			echo '</div>';
			if ( $single_purchase_enabled ) {
				echo '<div class="misc-pub-section">';
				echo '<label for="sesamy_meta_box_price">' . esc_html( __( 'Price', 'sesamy' ) ) . '</label><br />';
				echo '<input type="text" style="width:100%;max-width:250px;" name="sesamy_meta_box_price" id="sesamy_meta_box_price" value="' . esc_attr( get_post_meta( $post->ID, '_sesamy_price', true ) ) . '" />';
				echo '</div>';
			}
			echo '<div class="misc-pub-section">';
			echo '<label for="sesamy_meta_box_custom_paywall_url">' . esc_html( __( 'Custom Paywall URL', 'sesamy' ) ) . '</label><br />';
			echo '<input type="text" style="width:100%;max-width:250px;" name="sesamy_meta_box_custom_paywall_url" id="sesamy_meta_box_custom_paywall_url" value="' . esc_attr( get_post_meta( $post->ID, '_sesamy_custom_paywall_url', true ) ) . '" />';
			echo '</div>';
			echo '<div class="misc-pub-section">';
			echo '<label for="sesamy_meta_box_locked_content_redirect_url">' . esc_html( __( 'Locked content redirect URL', 'sesamy' ) ) . '</label><br />';
			echo '<input type="text" style="width:100%;max-width:250px;" name="sesamy_meta_box_locked_content_redirect_url" id="sesamy_meta_box_locked_content_redirect_url" value="' . esc_attr( get_post_meta( $post->ID, '_sesamy_locked_content_redirect_url', true ) ) . '" />';
			echo '</div>';
		}
	}
}
