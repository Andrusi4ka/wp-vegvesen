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
			$ids = get_posts( array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			if ( $ids ) {
				return wp_get_attachment_image_url( $ids[0], 'large' );
			}
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
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'title'          => $merke . ' logo',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( $ids ) {
			return wp_get_attachment_image_url( $ids[0], 'medium' );
		}
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
