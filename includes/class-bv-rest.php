<?php
defined( 'ABSPATH' ) || exit;

/**
 * GET /wp-json/bv/v1/vehicle/AB12345 → { status, data }
 * For AJAX / future blocks. Same cache and rate limit as the pages; raw API data is not exposed.
 */
class BV_Rest {

	public static function init() {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'bv/v1', '/vehicle/(?P<q>[^/]+)', array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'vehicle' ),
			) );
		} );
	}

	public static function vehicle( WP_REST_Request $req ) {
		$l = BV_API::lookup( rawurldecode( $req['q'] ) );
		$codes = array( 'funnet' => 200, 'ikke_funnet' => 404, 'ugyldig' => 400, 'feil' => 503 );
		return new WP_REST_Response( array(
			'status' => $l['status'],
			'error'  => $l['error'],
			'demo'   => $l['demo'],
			'data'   => 'funnet' === $l['status'] ? $l['data'] : null,
		), $codes[ $l['status'] ] ?? 200 );
	}
}
