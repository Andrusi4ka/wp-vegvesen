<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings stored in a single option.
 * The API key can also come from the BV_API_KEY constant in wp-config.php (preferred).
 */
class BV_Settings {

	const OPTION = 'bv_settings';

	public static function defaults() {
		return array(
			'api_key'       => '',
			'endpoint'      => 'https://akfell-datautlevering.atlas.vegvesen.no/enkeltoppslag/kjoretoydata',
			'auth_header'   => 'SVV-Authorization',
			'auth_prefix'   => 'Apikey ',
			'demo_mode'     => 1,
			'result_page'   => 0,
			'cache_hours'   => 24,
			'rate_limit'    => 20,
			'preview_regnr' => 'AB12345',
			'eu_warn_days'  => 60,
			'primary_color' => '',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function api_key() {
		if ( defined( 'BV_API_KEY' ) && BV_API_KEY ) {
			return BV_API_KEY;
		}
		return (string) self::get( 'api_key' );
	}

	/** Demo data is used when demo mode is on or no API key exists. */
	public static function is_demo() {
		return (bool) self::get( 'demo_mode' ) || '' === self::api_key();
	}

	public static function result_page_id() {
		$id = (int) self::get( 'result_page' );
		return ( $id && 'page' === get_post_type( $id ) ) ? $id : 0;
	}

	/** Creates the result page with a ready-made layout on first activation. */
	public static function maybe_create_result_page() {
		if ( self::result_page_id() ) {
			return;
		}
		// Runs on activation, before "init", so load translations here.
		load_plugin_textdomain( 'bruktdeler-vegvesen', false, dirname( plugin_basename( BV_FILE ) ) . '/languages' );
		$title = __( 'Vehicle', 'bruktdeler-vegvesen' );

		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $title ),
			// Title and subtitle of the hero come from translations, so the page follows the site language.
			'post_content' => '[bv_search layout="hero"]' . "\n\n" . '[bv_card]',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			$all                = self::all();
			$all['result_page'] = $id;
			update_option( self::OPTION, $all );
		}
	}
}
