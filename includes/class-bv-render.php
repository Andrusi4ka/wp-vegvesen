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

	public static function silhouette() {
		return '<svg class="bv-silhouette" viewBox="0 0 240 100" aria-hidden="true"><path fill="currentColor" d="M12 72v-14c0-6 4-10 10-11l46-6 26-19c4-3 9-4 14-4h80c7 0 12 3 16 8l18 22c6 1 10 6 10 12v12c0 3-2 5-5 5h-17a22 22 0 0 0-43 0H75a22 22 0 0 0-43 0H17c-3 0-5-2-5-5z"/><path fill="#fff" opacity=".55" d="M76 42 99 26c3-2 6-3 9-3h30v19zM145 23h36c5 0 9 2 12 6l12 13h-60z"/><circle cx="53.5" cy="75" r="17" fill="currentColor"/><circle cx="53.5" cy="75" r="8" fill="#fff" opacity=".6"/><circle cx="187" cy="75" r="17" fill="currentColor"/><circle cx="187" cy="75" r="8" fill="#fff" opacity=".6"/></svg>';
	}
}

add_action( 'wp_footer', array( 'BV_Render', 'footer_notes' ) );
