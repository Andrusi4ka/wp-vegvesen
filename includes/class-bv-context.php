<?php
defined( 'ABSPATH' ) || exit;

/**
 * Works out which vehicle the current page shows.
 * Pretty URL: /kjoretoy/AB12345/  — fallback: ?regnr=AB12345 on any page.
 * The API is called at most once per page, all shortcodes share the result.
 */
class BV_Context {

	private static $cache = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'bv_regnr';
			return $vars;
		} );
		add_action( 'template_redirect', array( __CLASS__, 'pretty_redirect' ) );
		// The front page setting decides whether pretty URLs are possible – rebuild rules when it changes.
		foreach ( array( 'show_on_front', 'page_on_front' ) as $opt ) {
			add_action( 'update_option_' . $opt, function () {
				delete_option( 'rewrite_rules' );
			} );
		}
		// The pretty URL follows the result page's address: rebuild rules when its slug or parent changes.
		add_action( 'post_updated', function ( $post_id, $after, $before ) {
			if ( (int) $post_id === (int) BV_Settings::result_page_id()
				&& ( $after->post_name !== $before->post_name || (int) $after->post_parent !== (int) $before->post_parent ) ) {
				delete_option( 'rewrite_rules' );
			}
		}, 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'cache_control' ), 20 ); // After pretty_redirect().
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'visibility_css' ), 5 );
	}

	/** Pretty URLs (/page/AB12345/) need permalinks and a page that is not the front page. */
	private static function pretty_enabled() {
		$id = BV_Settings::result_page_id();
		return $id && get_option( 'permalink_structure' ) && ! self::is_front( $id );
	}

	private static function is_front( $id ) {
		return 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $id;
	}

	public static function add_rewrite() {
		$id = BV_Settings::result_page_id();
		if ( ! $id || self::is_front( $id ) ) {
			return;
		}
		$uri = get_page_uri( $id );
		if ( $uri ) {
			add_rewrite_rule( '^' . preg_quote( $uri, '#' ) . '/([^/]+)/?$', 'index.php?page_id=' . $id . '&bv_regnr=$matches[1]', 'top' );
		}
	}

	/** URL of the result page for a given plate (or without plate). */
	public static function url( $regnr = '' ) {
		$id   = BV_Settings::result_page_id();
		$base = $id ? get_permalink( $id ) : home_url( '/' );
		if ( '' === $regnr ) {
			return $base;
		}
		if ( self::pretty_enabled() ) {
			return trailingslashit( $base ) . rawurlencode( $regnr ) . '/';
		}
		return add_query_arg( 'regnr', rawurlencode( $regnr ), $base );
	}

	/** Plate requested in the URL ('' if none). */
	public static function requested() {
		$v = get_query_var( 'bv_regnr' );
		if ( ! $v && isset( $_GET['regnr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$v = sanitize_text_field( wp_unslash( $_GET['regnr'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $v ? rawurldecode( (string) $v ) : '';
	}

	/**
	 * Lookup for the current page, or for an explicit plate.
	 * Without a plate in the URL, editors see the preview vehicle inside page builders (see use_preview()).
	 */
	public static function current( $regnr = '' ) {
		if ( '' === $regnr ) {
			$regnr = self::requested();
		}
		$preview = false;
		if ( '' === $regnr ) {
			if ( self::use_preview() ) {
				$regnr   = BV_Settings::get( 'preview_regnr' );
				$preview = true;
			} else {
				return null;
			}
		}
		if ( ! isset( self::$cache[ $regnr ] ) ) {
			self::$cache[ $regnr ]            = BV_API::lookup( $regnr );
			self::$cache[ $regnr ]['preview'] = $preview;
		}
		return self::$cache[ $regnr ];
	}

	/** ?regnr=ab 12345 on the result page → /kjoretoy/AB12345/ */
	public static function pretty_redirect() {
		$id = BV_Settings::result_page_id();
		if ( ! self::pretty_enabled() || ! is_page( $id ) || ! isset( $_GET['regnr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$q = BV_API::parse_query( sanitize_text_field( wp_unslash( $_GET['regnr'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $q ) {
			wp_safe_redirect( self::url( $q['value'] ), 302 );
			exit;
		}
	}

	public static function title( $parts ) {
		if ( ! self::requested() ) {
			return $parts;
		}
		$l = self::current();
		if ( $l && 'funnet' === $l['status'] ) {
			// implode, not trim(' –'): trim works on bytes and would cut a multibyte letter at the end.
			$parts['title'] = implode( ' – ', array_filter( array( $l['data']['regnr_formatted'] ?? $l['query'], $l['data']['merke_modell'] ?? '' ) ) );
		}
		return $parts;
	}

	/**
	 * Body classes with the search state, used by the visibility classes:
	 * bv-state-found | bv-state-notfound | bv-state-empty.
	 * Not added in the WPBakery front-end editor, so hidden elements stay editable.
	 */
	public static function body_class( $classes ) {
		if ( self::requested() ) {
			$classes[] = 'bv-vehicle-page';
		}
		if ( self::is_builder_preview() ) {
			return $classes;
		}
		$l = self::current();
		if ( ! $l ) {
			$classes[] = 'bv-state-empty';
		} elseif ( 'funnet' === $l['status'] ) {
			$classes[] = 'bv-state-found';
		} else {
			$classes[] = 'bv-state-notfound';
		}
		return $classes;
	}

	/** True inside a page builder / editor preview (WPBakery front-end editor, Elementor, Customizer, block editor). */
	public static function is_builder_preview() {
		// phpcs:disable WordPress.Security.NonceVerification
		$ajax_action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$is = isset( $_GET['vc_editable'] ) || isset( $_GET['vc_action'] )
			|| ( function_exists( 'vc_is_inline' ) && vc_is_inline() )
			|| ( wp_doing_ajax() && 0 === strpos( $ajax_action, 'vc_' ) )   // WPBakery renders elements via AJAX.
			|| isset( $_GET['elementor-preview'] )
			|| ( wp_doing_ajax() && 0 === strpos( $ajax_action, 'elementor' ) )
			|| is_customize_preview()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_GET['context'] ) && 'edit' === $_GET['context'] );
		// phpcs:enable
		return (bool) apply_filters( 'bv_is_builder_preview', $is );
	}

	/**
	 * Should the editor preview vehicle be used? Only for editors, and only in a page builder
	 * or when "?bv_preview=1" is added to the address. On the normal site editors see what visitors see.
	 */
	private static function use_preview() {
		if ( ! current_user_can( 'edit_posts' ) || ! BV_Settings::get( 'preview_regnr' ) ) {
			return false;
		}
		return self::is_builder_preview() || ! empty( $_GET['bv_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Visibility classes for any element (put them in "Extra class name" of a row, column or element):
	 *  bv-if-found     – only when a vehicle is found
	 *  bv-if-notfound  – only when a search found nothing / failed
	 *  bv-if-empty     – only before a search (no number in the address)
	 *  bv-if-searched  – after any search (found or not)
	 * Printed inline in <head> so it works on every page, even without the plugin stylesheet.
	 */
	public static function visibility_css() {
		// In a page builder every block stays visible and editable (no state class is set there).
		if ( self::is_builder_preview() ) {
			return;
		}
		echo "<style id=\"bv-visibility\">body:not(.bv-state-found) .bv-if-found,body:not(.bv-state-notfound) .bv-if-notfound,body:not(.bv-state-empty) .bv-if-empty,body.bv-state-empty .bv-if-searched{display:none!important}</style>\n";
	}

	/**
	 * Page caches (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, a CDN) must not keep a vehicle page
	 * that shows an error, "not found" or demo data: everyone would see it until the cache expires.
	 * A page with a found vehicle may be cached – that even saves requests.
	 */
	public static function cache_control() {
		if ( '' === self::requested() ) {
			return;
		}
		$l = self::current();
		if ( $l && 'funnet' === $l['status'] && empty( $l['demo'] ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // WP Rocket, W3 Total Cache, WP Super Cache and most others.
		}
		do_action( 'litespeed_control_set_nocache', 'WP Vegvesen: no vehicle data to cache' );
		nocache_headers(); // Browsers and CDNs.
	}

	/**
	 * Robots: crawlers should not walk through vehicle pages (every visit could cost an API request).
	 * The result page itself stays open. Only WordPress's own robots.txt – a robots.txt file on the server wins.
	 */
	public static function robots_txt( $output, $public ) {
		$rules = array( 'Disallow: /*?regnr=', 'Disallow: /*&regnr=' );
		$id    = BV_Settings::result_page_id();
		if ( $id && self::pretty_enabled() ) {
			$path    = '/' . trim( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), '/' ) . '/';
			$rules[] = 'Allow: ' . $path . '$';
			$rules[] = 'Disallow: ' . $path;
		}
		return rtrim( $output ) . "\n\n# WP Vegvesen: vehicle pages\nUser-agent: *\n" . implode( "\n", $rules ) . "\n";
	}

	/** Vehicle pages are generated on the fly – keep them out of search engines. */
	public static function robots( $robots ) {
		if ( self::requested() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
}
