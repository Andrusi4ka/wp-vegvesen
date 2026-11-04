<?php
/**
 * Runs when the plugin is deleted under Plugins (not on deactivation or update):
 * removes its settings, the cached Vegvesen answers and the update checker's state.
 *
 * Kept: the result page once it has been edited (it is your content then), and your own
 * files in wp-content/uploads/bv-logos and bv-bodies, and images in the media library.
 *
 * @package WP_Vegvesen
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// All in a function of its own: nothing left in the global scope.
( function () {
	$uninstall_site = function () {
		global $wpdb;

		$settings = get_option( 'bv_settings' );

		// The result page the plugin created on activation – only while it is still exactly as created.
		// Once someone has built a layout on it, it stays (shortcodes left in it show as text).
		$page_id = is_array( $settings ) ? (int) ( $settings['result_page'] ?? 0 ) : 0;
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( $page && 'page' === $page->post_type
			&& '[bv_search layout="hero"] [bv_card]' === preg_replace( '/\s+/', ' ', trim( $page->post_content ) ) ) {
			wp_delete_post( $page_id, true );
		}

		delete_option( 'bv_settings' );
		delete_option( 'bv_logo_gen' );
		delete_option( 'bv_field_icons' ); // Icons chosen per field on the Variables page.
		delete_option( 'bv_day_count' );   // Requests to Vegvesen today (daily limit).
		delete_option( 'bv_quota_alert' ); // "Searches stopped today" notice.
		delete_option( 'bv_version' );      // "What's new": the version last run
		delete_option( 'bv_updated_from' ); // and the one updated from.

		// Cached Vegvesen answers (bv_<md5>), search limits per IP (bv_rl_<md5>) and brand logos (bv_logo_<md5>).
		// The exact key shapes, so other plugins' transients that start with "bv_" are left alone.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup.
			"DELETE FROM {$wpdb->options} WHERE option_name REGEXP '^_transient_(timeout_)?bv_(rl_|logo_)?[0-9a-f]{32}$'"
		);

		// The pretty /vehicle/AB12345/ address rule: WordPress rebuilds the rules without it.
		delete_option( 'rewrite_rules' );
	};

	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
			switch_to_blog( $site_id );
			$uninstall_site();
			restore_current_blog();
		}
	} else {
		$uninstall_site();
	}

	// "What's new": the version each user has seen (users are shared by a network's sites).
	delete_metadata( 'user', 0, 'bv_seen_version', '', true );

	// Last update check (Plugin Update Checker).
	delete_site_option( 'external_updates-wp-vegvesen' );
} )();
