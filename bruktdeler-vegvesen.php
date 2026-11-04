<?php
/**
 * Plugin Name:       WP Vegvesen
 * Plugin URI:        https://github.com/Andrusi4ka/wp-vegvesen
 * Description:       Vehicle lookup in Statens vegvesen by registration number or VIN. Provides variables (shortcodes and tokens), ready-made blocks and WPBakery elements that follow the theme's styling.
 * Version:           1.4.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Relevant AS
 * Author URI:        https://relevant.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bruktdeler-vegvesen
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/wp-vegvesen
 */

// "Update URI": updates come from this plugin's GitHub releases (bv_updates()),
// never from a wordpress.org plugin that happens to share the folder name.

defined( 'ABSPATH' ) || exit;

define( 'BV_VERSION', '1.4.0' );
define( 'BV_REPOSITORY', 'https://github.com/Andrusi4ka/wp-vegvesen/' );
define( 'BV_FILE', __FILE__ );
define( 'BV_DIR', plugin_dir_path( __FILE__ ) );
define( 'BV_URL', plugin_dir_url( __FILE__ ) );

require_once BV_DIR . 'includes/class-bv-settings.php';
require_once BV_DIR . 'includes/class-bv-icons.php';
require_once BV_DIR . 'includes/class-bv-fields.php';
require_once BV_DIR . 'includes/class-bv-api.php';
require_once BV_DIR . 'includes/class-bv-context.php';
require_once BV_DIR . 'includes/class-bv-render.php';
require_once BV_DIR . 'includes/class-bv-shortcodes.php';
require_once BV_DIR . 'includes/class-bv-rest.php';

if ( is_admin() ) {
	require_once BV_DIR . 'includes/class-bv-admin.php';
	BV_Admin::init();
}

// English is the source language; translations live in /languages (Norwegian bokmål included).
add_action( 'init', function () {
	load_plugin_textdomain( 'bruktdeler-vegvesen', false, dirname( plugin_basename( BV_FILE ) ) . '/languages' );
}, 0 );

/**
 * Updates from GitHub: a new release (tag "vX.Y.Z" with the wp-vegvesen.zip built by
 * .github/workflows/release.yml) shows up under Plugins like any other update.
 * "View details" is filled from readme.txt (description, installation, FAQ, changelog).
 */
function bv_updates() {
	$library = BV_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	// Only where WordPress looks for updates: the admin and its cron.
	if ( ( ! is_admin() && ! wp_doing_cron() ) || ! file_exists( $library ) ) {
		return;
	}
	require_once $library;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( BV_REPOSITORY, BV_FILE, 'wp-vegvesen' );
	// The release's zip, which holds just the plugin (not the repository's dev files).
	$checker->getVcsApi()->enableReleaseAssets( '/wp-vegvesen\.zip($|[?&#])/i' );
}
add_action( 'plugins_loaded', 'bv_updates' );

BV_Context::init();
BV_Shortcodes::init();
BV_Rest::init();

add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'bv', BV_URL . 'assets/css/bv.css', array(), BV_VERSION );
	wp_register_script( 'bv', BV_URL . 'assets/js/bv.js', array(), BV_VERSION, true );
	// Scroll to the result after a search (only when a number is in the address, never inside page builders).
	wp_add_inline_script( 'bv', 'window.bvConfig = ' . wp_json_encode( array(
		'scroll' => BV_Settings::get( 'scroll' ) && '' !== BV_Context::requested() && ! BV_Context::is_builder_preview(),
		'offset' => (string) BV_Settings::get( 'scroll_offset' ),
	) ) . ';', 'before' );

	$color = BV_Settings::get( 'primary_color' );
	if ( $color ) {
		wp_add_inline_style( 'bv', '.bv{--bv-primary:' . esc_attr( $color ) . ';}' );
	}
} );

// WPBakery elements (loaded only when WPBakery is active – works with any theme).
add_action( 'vc_before_init', function () {
	require_once BV_DIR . 'includes/integrations/class-bv-wpbakery.php';
	BV_WPBakery::init();
} );

register_activation_hook( __FILE__, function () {
	BV_Settings::maybe_create_result_page();
	BV_Context::add_rewrite();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Hjelpefunksjon for temaer/utviklere: hent normaliserte data for gjeldende kjøretøy.
 *
 * @param string|null $field Feltnøkkel, eller null for alle felt.
 * @return mixed
 */
function bv_vehicle( $field = null ) {
	$lookup = BV_Context::current();
	if ( ! $lookup || 'funnet' !== $lookup['status'] ) {
		return null;
	}
	return null === $field ? $lookup['data'] : ( $lookup['data'][ $field ] ?? null );
}
