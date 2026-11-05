<?php
defined( 'ABSPATH' ) || exit;

/** Template loading and small render helpers. */
class BV_Render {

	private static $notes = array();

	/**
	 * Loads a template. A theme can override it by copying it to
	 * {theme}/bruktdeler-vegvesen/{name}.php (works with any theme, incl. child themes).
	 */
	public static function template( $name, $vars = array() ) {
		$file = locate_template( 'bruktdeler-vegvesen/' . $name . '.php' );
		if ( ! $file ) {
			$file = BV_DIR . 'templates/' . $name . '.php';
		}
		$file = apply_filters( 'bv_template', $file, $name, $vars );

		wp_enqueue_style( 'bv' );
		wp_enqueue_script( 'bv' );

		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $file;
		return ob_get_clean();
	}

	/** Wrapper classes incl. WPBakery "Design Options" and extra class. */
	public static function classes( $base, $atts ) {
		$classes = array( $base );
		if ( ! empty( $atts['el_class'] ) ) {
			$classes[] = $atts['el_class'];
		}
		if ( ! empty( $atts['css'] ) && function_exists( 'vc_shortcode_custom_css_class' ) ) {
			$classes[] = vc_shortcode_custom_css_class( $atts['css'], ' ' );
		}
		return esc_attr( trim( implode( ' ', $classes ) ) );
	}

	public static function value( $lookup, $key, $opts = array() ) {
		if ( ! $lookup || ! isset( $lookup['data'][ $key ] ) ) {
			return '';
		}
		return BV_Fields::format( $key, $lookup['data'][ $key ], $opts );
	}

	/** Remember that demo/preview data was shown, so editors get a hint in the footer. */
	public static function note( $lookup ) {
		if ( ! $lookup || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! empty( $lookup['demo'] ) ) {
			self::$notes['demo'] = __( 'Demo mode: sample data, not from Vegvesen.', 'bruktdeler-vegvesen' );
		}
		if ( ! empty( $lookup['preview'] ) ) {
			/* translators: %s: registration number. */
			self::$notes['preview'] = sprintf( __( 'Editor preview: %s', 'bruktdeler-vegvesen' ), $lookup['query'] );
		}
	}

	public static function footer_notes() {
		if ( ! self::$notes ) {
			return;
		}
		echo '<div class="bv-editor-note">' . esc_html( implode( ' · ', self::$notes ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=bv-settings' ) ) . '">' . esc_html__( 'Settings', 'bruktdeler-vegvesen' ) . '</a></div>';
	}

	/** Finds a picture: media item titled "Merke Modell", then "Merke". Filter 'bv_vehicle_image' can override. */
	public static function image_url( $data ) {
		$url = apply_filters( 'bv_vehicle_image', '', $data );
		if ( $url ) {
			return $url;
		}
		$candidates = array_filter( array( $data['merke_modell'] ?? '', $data['merke'] ?? '' ) );
		foreach ( $candidates as $title ) {
			$url = self::attachment_by_title( $title, 'large' );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	/** URL of the newest media library image with exactly this title ('' if none). */
	private static function attachment_by_title( $title, $size ) {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'title'          => $title,
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		) );
		return $ids ? (string) wp_get_attachment_image_url( $ids[0], $size ) : '';
	}

	/** HTML tags a title may use (title_tag="h2" etc.). */
	public static function title_tags() {
		return array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );
	}

	/** A safe title tag: the one asked for if allowed, otherwise the default. */
	public static function title_tag( $tag, $default ) {
		$tag = strtolower( trim( (string) $tag ) );
		return in_array( $tag, self::title_tags(), true ) ? $tag : $default;
	}

	/**
	 * Status icon for the EU inspection: ok = check, snart = warning, utlopt = cross.
	 * Returns '' for an unknown status.
	 */
	public static function eu_icon( $status ) {
		$map = array(
			'ok'     => array( 'circle-check', __( 'Approved', 'bruktdeler-vegvesen' ) ),
			'snart'  => array( 'triangle-alert', __( 'Due soon', 'bruktdeler-vegvesen' ) ),
			'utlopt' => array( 'circle-x', __( 'Overdue', 'bruktdeler-vegvesen' ) ),
		);
		if ( ! isset( $map[ $status ] ) ) {
			return '';
		}
		return '<span class="bv-eu-icon bv-eu-icon--' . esc_attr( $status ) . '" role="img" aria-label="' . esc_attr( $map[ $status ][1] ) . '" title="' . esc_attr( $map[ $status ][1] ) . '">' . BV_Icons::get( $map[ $status ][0] ) . '</span>';
	}

	/** Button style settings passed on to the WoodMart Button element (eu_btn_<name> → <name>). */
	const WOODMART_STYLE_KEYS = array( 'style', 'shape', 'size', 'color', 'bg_color', 'color_scheme', 'custom_color_scheme', 'bg_color_hover', 'color_scheme_hover', 'custom_color_scheme_hover' );

	/**
	 * Button next to the EU inspection date (e.g. a link to a contact form). Configured in the element
	 * (tab "EU inspection"): eu_show, eu_btn_title, eu_btn_link, eu_btn_custom_attributes, eu_btn_class
	 * and the WoodMart style settings eu_btn_style, eu_btn_shape, eu_btn_size, eu_btn_color…
	 * Outputs nothing when the deadline is fine (or not in eu_show). Without a link it is still shown and
	 * works as a button for scripts (role="button", reachable with the keyboard) – e.g. via its CSS class.
	 *
	 * @param array $lookup Lookup result.
	 * @param array $a      Element attributes.
	 */
	public static function eu_button( $lookup, $a = array() ) {
		$status = $lookup['data']['eu_status'] ?? '';
		$show   = isset( $a['eu_show'] ) && '' !== $a['eu_show'] ? (string) $a['eu_show'] : 'snart,utlopt';
		if ( ! in_array( $status, array_map( 'trim', explode( ',', $show ) ), true ) ) {
			return '';
		}
		$link    = self::parse_link( (string) ( $a['eu_btn_link'] ?? '' ) );
		$no_link = '' === $link['url'];
		if ( ! $no_link ) {
			$link['url'] = self::link_with_tokens( $link['url'], $lookup );
		}
		$title  = isset( $a['eu_btn_title'] ) && '' !== trim( $a['eu_btn_title'] ) ? $a['eu_btn_title'] : __( 'Contact us', 'bruktdeler-vegvesen' );
		$class  = trim( 'bv-eu-btn bv-eu-btn--' . $status . ' ' . ( $a['eu_btn_class'] ?? '' ) );
		$custom = trim( (string) ( $a['eu_btn_custom_attributes'] ?? '' ) );
		// The registration number for scripts: data-regnr on the button.
		$regnr  = (string) ( $lookup['data']['regnr'] ?? '' );
		if ( '' !== $regnr && false === stripos( $custom, 'data-regnr' ) ) {
			$custom = ltrim( $custom . ',data-regnr|' . $regnr, ',' );
		}
		if ( $no_link && false === stripos( $custom, 'role|' ) ) {
			$custom = ltrim( $custom . ',role|button,tabindex|0', ',' );
		}

		// WoodMart: the theme's own Button element, so it looks and works like any WoodMart button.
		if ( function_exists( 'woodmart_shortcode_button' ) ) {
			$atts = array(
				'title'             => $title,
				'link'              => $no_link ? '' : $link,
				'custom_attributes' => $custom,
				'el_class'          => $class,
				'button_inline'     => 'yes',
				'align'             => 'right',
				'woodmart_css_id'   => (string) ( $a['woodmart_css_id'] ?? '' ),
			);
			foreach ( self::WOODMART_STYLE_KEYS as $k ) {
				if ( isset( $a[ 'eu_btn_' . $k ] ) && '' !== $a[ 'eu_btn_' . $k ] ) {
					$atts[ $k ] = $a[ 'eu_btn_' . $k ];
				}
			}
			return '<span class="bv-eu-btn-wrap">' . woodmart_shortcode_button( $atts ) . '</span>';
		}

		// Another theme: a simple button styled by the plugin.
		$attrs = '';
		foreach ( array_filter( array_map( 'trim', explode( ',', $custom ) ) ) as $pair ) {
			$kv  = array_map( 'trim', explode( '|', $pair, 2 ) );
			$key = strtolower( $kv[0] );
			if ( preg_match( '/^[a-z][a-z0-9_:.-]*$/', $key ) && 0 !== strpos( $key, 'on' ) && ! in_array( $key, array( 'href', 'class', 'style' ), true ) ) {
				$attrs .= ' ' . $key . '="' . esc_attr( $kv[1] ?? '' ) . '"';
			}
		}
		if ( $no_link ) {
			// Without a link: a real <button> for scripts (role/tabindex are not needed on it).
			$attrs = preg_replace( '/ (role|tabindex)="[^"]*"/', '', $attrs );
			return '<button type="button" class="' . esc_attr( $class . ' bv-eu-btn--simple' ) . '"' . $attrs . '>' . esc_html( $title ) . '</button>';
		}
		if ( $link['target'] ) {
			$attrs .= ' target="' . esc_attr( $link['target'] ) . '"';
		}
		$rel = trim( $link['rel'] . ( '_blank' === $link['target'] ? ' noopener' : '' ) );
		if ( $rel ) {
			$attrs .= ' rel="' . esc_attr( $rel ) . '"';
		}
		return '<a class="' . esc_attr( $class . ' bv-eu-btn--simple' ) . '" href="' . esc_url( $link['url'] ) . '"' . $attrs . '>' . esc_html( $title ) . '</a>';
	}

	/** A WPBakery link ("url:…|target:_blank|rel:…") or a plain URL → [ url, title, target, rel ]. */
	public static function parse_link( $raw ) {
		$link = array( 'url' => '', 'title' => '', 'target' => '', 'rel' => '' );
		$raw  = trim( $raw );
		if ( '' === $raw || '||' === $raw ) {
			return $link;
		}
		if ( ! preg_match( '/(^|\|)(url|title|target|rel):/', $raw ) ) {
			$link['url'] = $raw;
			return $link;
		}
		foreach ( explode( '|', $raw ) as $part ) {
			$kv = explode( ':', $part, 2 );
			if ( 2 === count( $kv ) && array_key_exists( $kv[0], $link ) ) {
				$link[ $kv[0] ] = trim( rawurldecode( $kv[1] ) );
			}
		}
		return $link;
	}

	/** Fills {regnr} and {bv:field} in a link with the vehicle's values (URL-encoded), e.g. /kontakt/?regnr={regnr}. */
	public static function link_with_tokens( $url, $lookup ) {
		$data = $lookup['data'] ?? array();
		$url  = str_replace( '{regnr}', rawurlencode( (string) ( $data['regnr'] ?? $lookup['query'] ?? '' ) ), $url );
		return preg_replace_callback( '/\{bv:([a-z0-9_]+)\}/', function ( $m ) use ( $data ) {
			return isset( $data[ $m[1] ] ) ? rawurlencode( BV_Fields::format( $m[1], $data[ $m[1] ] ) ) : '';
		}, $url );
	}

	/** Message for a search that found nothing or failed (search field and [bv_card] notice). */
	public static function status_message( $lookup ) {
		switch ( $lookup['status'] ?? '' ) {
			case 'ikke_funnet':
				/* translators: %s: the registration number or VIN that was searched for. */
				return sprintf( __( 'We found no vehicle with “%s”. Check that the number is correct.', 'bruktdeler-vegvesen' ), $lookup['query'] );
			case 'ugyldig':
				return __( 'Enter a registration number (e.g. AB12345) or a 17-character VIN.', 'bruktdeler-vegvesen' );
			case 'feil':
				return (string) $lookup['error'];
		}
		return '';
	}

	/**
	 * Logo folders, in lookup order:
	 * - custom: wp-content/uploads/bv-logos/ – your own files, survive plugin updates;
	 * - bundled: the logo set shipped with the plugin (assets/logos/).
	 */
	public static function logo_dirs() {
		$u = wp_upload_dir( null, false );
		return array(
			'custom'  => array(
				'path' => trailingslashit( $u['basedir'] ) . 'bv-logos/',
				'url'  => trailingslashit( $u['baseurl'] ) . 'bv-logos/',
			),
			'bundled' => array(
				'path' => BV_DIR . 'assets/logos/',
				'url'  => BV_URL . 'assets/logos/',
			),
		);
	}

	/** File names to try for a make: "Mercedes-Benz" → mercedes-benz, "VW" → volkswagen, "Land Rover" → land-rover. */
	public static function logo_slugs( $merke ) {
		$aliases = apply_filters( 'bv_logo_aliases', array(
			'vw'             => 'volkswagen',
			'mercedes'       => 'mercedes-benz',
			'daimler-benz'   => 'mercedes-benz',
			'citroën'        => 'citroen',
			'škoda'          => 'skoda',
			'rolls-royce'    => 'rolls-royce',
			'ds-automobiles' => 'ds',
			'mini-bmw'       => 'mini',
		) );
		$slug  = sanitize_title( remove_accents( $merke ) );
		$first = strtok( $slug, '-' );
		$slugs = array( $aliases[ $slug ] ?? $slug, $slug, $aliases[ $first ] ?? $first );
		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * Brand logo URL. Order:
	 * 1. filter 'bv_brand_logo';
	 * 2. media library image titled "<Make> logo" (e.g. "Volkswagen logo") – your own upload wins;
	 * 3. your file in wp-content/uploads/bv-logos/<make>.svg|webp|png|jpg;
	 * 4. the logo set bundled with the plugin.
	 */
	public static function logo_url( $merke ) {
		$merke = trim( (string) $merke );
		if ( '' === $merke ) {
			return '';
		}
		$url = apply_filters( 'bv_brand_logo', '', $merke );
		if ( $url ) {
			return $url;
		}
		// Cached per make (a database query and up to 24 file checks otherwise run on every page view).
		// A media upload, edit or delete starts a new cache generation; files added by FTP show within an hour.
		$key    = 'bv_logo_' . md5( BV_VERSION . '|' . (int) get_option( 'bv_logo_gen', 0 ) . '|' . strtolower( $merke ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return (string) $cached['url'];
		}
		$url = self::attachment_by_title( $merke . ' logo', 'medium' );
		if ( '' === $url ) {
			$url = self::logo_file_url( $merke );
		}
		set_transient( $key, array( 'url' => $url ), HOUR_IN_SECONDS );
		return $url;
	}

	/** Logo file for a make: your own folder first, then the bundled set ('' if none). */
	private static function logo_file_url( $merke ) {
		foreach ( self::logo_dirs() as $dir ) {
			foreach ( self::logo_slugs( $merke ) as $slug ) {
				foreach ( array( 'svg', 'webp', 'png', 'jpg' ) as $ext ) {
					if ( file_exists( $dir['path'] . $slug . '.' . $ext ) ) {
						return $dir['url'] . $slug . '.' . $ext;
					}
				}
			}
		}
		return '';
	}

	/** Starts a new logo cache generation (a media item may be a "Make logo"). */
	public static function flush_logo_cache() {
		update_option( 'bv_logo_gen', (int) get_option( 'bv_logo_gen', 0 ) + 1, false );
	}

	/** Number of logo files in a folder ('custom' or 'bundled'), for the settings page. */
	public static function logo_count( $which = 'bundled' ) {
		$dirs  = self::logo_dirs();
		$files = is_dir( $dirs[ $which ]['path'] ) ? scandir( $dirs[ $which ]['path'] ) : array();
		return count( preg_grep( '/\.(svg|webp|png|jpg)$/i', (array) $files ) );
	}

	/**
	 * Silhouette slug for the vehicle's body type (assets/bodies/{slug}.svg).
	 * Uses the Vegvesen body code (kodeverk KAROSSERITYPE), then the vehicle category.
	 * Filter 'bv_body_type' can change the result.
	 */
	public static function body_type( $data ) {
		$code = strtoupper( trim( (string) ( $data['karosseri_kode'] ?? '' ) ) );
		$map  = array(
			'AA' => 'sedan', 'AB' => 'hatchback', 'AC' => 'estate', 'AD' => 'coupe', 'AE' => 'convertible',
			'AF' => 'mpv', 'AG' => 'pickup-double', 'BE' => 'pickup', 'BB' => 'van', 'BA' => 'truck', 'BX' => 'truck',
			'BC' => 'tractor', 'BD' => 'tractor', 'SL' => 'tractor', 'SA' => 'camper', 'SC' => 'van', 'SH' => 'van',
			'SE' => 'trailer', 'SJ' => 'trailer', 'SK' => 'trailer',
		);
		$type = '';
		if ( isset( $map[ $code ] ) ) {
			$type = $map[ $code ];
		} elseif ( 'C' === substr( $code, 0, 1 ) && strlen( $code ) === 2 ) {
			$type = 'bus';
		} elseif ( 'D' === substr( $code, 0, 1 ) && strlen( $code ) === 2 ) {
			$type = 'trailer';
		} else {
			// No usable body code: guess from the category (M1, N1, M3…).
			$cat = strtoupper( (string) ( $data['kategori'] ?? '' ) );
			if ( in_array( substr( $cat, 0, 2 ), array( 'M2', 'M3' ), true ) ) {
				$type = 'bus';
			} elseif ( 'N1' === substr( $cat, 0, 2 ) ) {
				$type = 'van';
			} elseif ( in_array( substr( $cat, 0, 2 ), array( 'N2', 'N3' ), true ) ) {
				$type = 'truck';
			} elseif ( 'O' === substr( $cat, 0, 1 ) ) {
				$type = 'trailer';
			}
		}
		$type = (string) apply_filters( 'bv_body_type', $type ? $type : 'sedan', $data );
		return preg_replace( '/[^a-z0-9-]/', '', $type );
	}

	/** Car colour for the silhouette: the Vegvesen colour, or '' to use the neutral grey. */
	public static function body_color( $data ) {
		return (string) apply_filters( 'bv_body_color', $data['farge_hex'] ?? '', $data );
	}

	/** True for light colours (white, silver, beige…), which need an outline to stand out on a light page. */
	public static function is_light( $hex ) {
		if ( ! preg_match( '/^#?([0-9a-f]{6})$/i', (string) $hex, $m ) ) {
			return false;
		}
		list( $r, $g, $b ) = array_map( 'hexdec', str_split( $m[1], 2 ) );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255 > 0.72;
	}

	/**
	 * Body type silhouette (inline SVG, coloured through CSS "color").
	 * Your own files in wp-content/uploads/bv-bodies/{slug}.svg take priority over the bundled ones.
	 */
	public static function silhouette( $data = array() ) {
		static $cache = array();
		$type = self::body_type( $data );
		if ( ! isset( $cache[ $type ] ) ) {
			$u     = wp_upload_dir();
			$files = array( trailingslashit( $u['basedir'] ) . 'bv-bodies/' . $type . '.svg', BV_DIR . 'assets/bodies/' . $type . '.svg', BV_DIR . 'assets/bodies/sedan.svg' );
			$svg   = '';
			foreach ( $files as $file ) {
				if ( is_readable( $file ) ) {
					$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					break;
				}
			}
			$cache[ $type ] = preg_replace( '/<svg\b/', '<svg class="bv-silhouette bv-silhouette--' . esc_attr( $type ) . '"', trim( $svg ), 1 );
		}
		return $cache[ $type ];
	}
}

add_action( 'wp_footer', array( 'BV_Render', 'footer_notes' ) );
foreach ( array( 'add_attachment', 'edit_attachment', 'delete_attachment' ) as $bv_hook ) {
	add_action( $bv_hook, array( 'BV_Render', 'flush_logo_cache' ) );
}
unset( $bv_hook );
