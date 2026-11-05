<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPBakery elements (category "Vegvesen"). Thin layer over the shortcodes:
 * every element is just a shortcode with a settings form + "Design Options".
 * Loaded only when WPBakery is active, works with any theme.
 */
class BV_WPBakery {

	public static function init() {
		$common = array(
			array( 'type' => 'textfield', 'heading' => __( 'Extra CSS class', 'bruktdeler-vegvesen' ), 'param_name' => 'el_class' ),
			array( 'type' => 'css_editor', 'heading' => 'CSS', 'param_name' => 'css', 'group' => __( 'Design Options', 'bruktdeler-vegvesen' ) ),
		);
		$yes_no     = array( __( 'Yes', 'bruktdeler-vegvesen' ) => '1', __( 'No', 'bruktdeler-vegvesen' ) => '0' );
		$icons      = array_unique( array_merge( array( 'info' => 'info' ), array_combine( BV_Icons::names(), BV_Icons::names() ) ) );
		$fields     = array_flip( BV_Fields::choices() );
		$fields_tip = __( 'Comma separated. Field list: WP Vegvesen → Variables.', 'bruktdeler-vegvesen' );
		$base       = array( 'category' => 'Vegvesen', 'icon' => 'dashicons dashicons-car', 'show_settings_on_create' => true );

		// "EU inspection" tab for the card and the table: icon and button of the "Next EU inspection" row.
		$eu_group  = __( 'EU inspection', 'bruktdeler-vegvesen' );
		$eu_params = array_merge(
			array(
				array(
					'type'        => 'checkbox',
					'heading'     => __( 'Status icon', 'bruktdeler-vegvesen' ),
					'param_name'  => 'eu_icon',
					'group'       => $eu_group,
					'value'       => array( __( 'Show an icon to the left of the date', 'bruktdeler-vegvesen' ) => 'yes' ),
					'settings'    => array( 'default' => 'yes' ),
					'description' => __( 'Check, warning or cross to the left of the date.', 'bruktdeler-vegvesen' ),
				),
			),
			self::eu_button_params( $eu_group )
		);
		// "Title tag" dropdown (H1–H6, p, div, span); the look stays the same, only the HTML tag changes (SEO).
		$tag_param = function ( $name, $heading, $default ) {
			$tags = array();
			foreach ( BV_Render::title_tags() as $tag ) {
				$tags[ strtoupper( $tag ) ] = $tag;
			}
			return array(
				'type'        => 'dropdown',
				'heading'     => $heading,
				'param_name'  => $name,
				'value'       => $tags,
				'std'         => $default,
				/* translators: %s: default HTML tag, e.g. H2. */
				'description' => sprintf( __( 'HTML tag for SEO; the look does not change. Default: %s.', 'bruktdeler-vegvesen' ), strtoupper( $default ) ),
			);
		};

		vc_map( array_merge( $base, array(
			'name'        => __( 'Vehicle search', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_search',
			'description' => __( 'Search field for registration number / VIN', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array( 'type' => 'dropdown', 'heading' => __( 'Layout', 'bruktdeler-vegvesen' ), 'param_name' => 'layout', 'admin_label' => true, 'value' => array(
					__( 'Field only', 'bruktdeler-vegvesen' )         => 'inline',
					__( 'Banner (hero)', 'bruktdeler-vegvesen' )      => 'hero',
					__( 'Compact (for header)', 'bruktdeler-vegvesen' ) => 'compact',
				) ),
				array( 'type' => 'textfield', 'heading' => __( 'Title', 'bruktdeler-vegvesen' ), 'param_name' => 'title' ),
				$tag_param( 'title_tag', __( 'Title tag', 'bruktdeler-vegvesen' ), 'h1' ),
				array( 'type' => 'textfield', 'heading' => __( 'Subtitle', 'bruktdeler-vegvesen' ), 'param_name' => 'subtitle' ),
				array( 'type' => 'attach_image', 'heading' => __( 'Background image (banner)', 'bruktdeler-vegvesen' ), 'param_name' => 'image' ),
				array( 'type' => 'textfield', 'heading' => __( 'Placeholder', 'bruktdeler-vegvesen' ), 'param_name' => 'placeholder', 'description' => __( 'Empty = default text in the site language.', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'textfield', 'heading' => __( 'Button text', 'bruktdeler-vegvesen' ), 'param_name' => 'button', 'description' => __( 'Empty = default text in the site language.', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'dropdown', 'heading' => __( 'Message under the field', 'bruktdeler-vegvesen' ), 'param_name' => 'message', 'value' => $yes_no, 'description' => __( 'Shows "not found" or an error right under the field, so it is visible without scrolling.', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'textfield', 'heading' => __( 'Max width', 'bruktdeler-vegvesen' ), 'param_name' => 'max_width', 'description' => __( 'E.g. 600 (px) or 80%. Empty = full width of the column (banner: 820px).', 'bruktdeler-vegvesen' ) ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'Vehicle card (full)', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_card',
			'description' => __( 'The whole layout at once', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Tile fields', 'bruktdeler-vegvesen' ), 'param_name' => 'tiles', 'value' => BV_Shortcodes::DEFAULT_TILES, 'description' => $fields_tip ),
				array( 'type' => 'textfield', 'heading' => __( '"General information" fields', 'bruktdeler-vegvesen' ), 'param_name' => 'general', 'value' => BV_Shortcodes::TABLE_GENERAL ),
				array( 'type' => 'textfield', 'heading' => __( '"Important dates" fields', 'bruktdeler-vegvesen' ), 'param_name' => 'dates', 'value' => BV_Shortcodes::TABLE_DATES ),
				array( 'type' => 'textfield', 'heading' => __( '"Environment and consumption" fields', 'bruktdeler-vegvesen' ), 'param_name' => 'miljo', 'value' => BV_Shortcodes::TABLE_MILJO ),
				array( 'type' => 'dropdown', 'heading' => __( 'Print button', 'bruktdeler-vegvesen' ), 'param_name' => 'print', 'value' => $yes_no ),
				array( 'type' => 'dropdown', 'heading' => __( 'Picture', 'bruktdeler-vegvesen' ), 'param_name' => 'image', 'value' => $yes_no ),
				array( 'type' => 'dropdown', 'heading' => __( 'Brand logo', 'bruktdeler-vegvesen' ), 'param_name' => 'logo', 'value' => $yes_no ),
				$tag_param( 'title_tag', __( 'Vehicle name tag', 'bruktdeler-vegvesen' ), 'h2' ),
				$tag_param( 'table_title_tag', __( 'Table titles tag', 'bruktdeler-vegvesen' ), 'h3' ),
			), $eu_params, $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'Vehicle field', 'bruktdeler-vegvesen' ),
			'base'        => 'bv',
			'description' => __( 'A single value (make, weight, date…)', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array( 'type' => 'dropdown', 'heading' => __( 'Field', 'bruktdeler-vegvesen' ), 'param_name' => 'field', 'value' => $fields, 'admin_label' => true ),
				array( 'type' => 'textfield', 'heading' => __( 'Text before', 'bruktdeler-vegvesen' ), 'param_name' => 'before' ),
				array( 'type' => 'textfield', 'heading' => __( 'Text after', 'bruktdeler-vegvesen' ), 'param_name' => 'suffix', 'description' => __( 'E.g. " kg"', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'dropdown', 'heading' => __( 'Standard unit', 'bruktdeler-vegvesen' ), 'param_name' => 'unit', 'value' => array( __( 'No', 'bruktdeler-vegvesen' ) => '0', __( 'Yes (kg, kW, g/km…)', 'bruktdeler-vegvesen' ) => '1' ) ),
				array( 'type' => 'textfield', 'heading' => __( 'Date format', 'bruktdeler-vegvesen' ), 'param_name' => 'format', 'description' => __( 'E.g. d.m.Y or j M Y', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'textfield', 'heading' => __( 'If empty', 'bruktdeler-vegvesen' ), 'param_name' => 'empty' ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'   => __( 'Number plate', 'bruktdeler-vegvesen' ),
			'base'   => 'bv_plate',
			'params' => array_merge( array(
				array( 'type' => 'dropdown', 'heading' => __( 'Size', 'bruktdeler-vegvesen' ), 'param_name' => 'size', 'value' => array(
					__( 'Normal', 'bruktdeler-vegvesen' ) => 'normal',
					__( 'Small', 'bruktdeler-vegvesen' )  => 'small',
					__( 'Large', 'bruktdeler-vegvesen' )  => 'large',
				) ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'Search status', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_status',
			'description' => __( '"Vehicle found" + date', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Text when found', 'bruktdeler-vegvesen' ), 'param_name' => 'found', 'description' => __( 'Empty = default text in the site language.', 'bruktdeler-vegvesen' ) ),
				array( 'type' => 'dropdown', 'heading' => __( 'Show last updated date', 'bruktdeler-vegvesen' ), 'param_name' => 'updated', 'value' => $yes_no ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'EU inspection button', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_eu_button',
			'description' => __( 'Shown only when the inspection deadline is close or passed', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array(
					'type'       => 'checkbox',
					'heading'    => __( 'Status icon', 'bruktdeler-vegvesen' ),
					'param_name' => 'eu_icon',
					'value'      => array( __( 'Show an icon next to the button', 'bruktdeler-vegvesen' ) => 'yes' ),
				),
			), self::eu_button_params( '' ), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'Brand logo', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_logo',
			'description' => __( 'Logo of the make (own upload "Make logo" or the logo set)', 'bruktdeler-vegvesen' ),
			'params'      => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Size (px)', 'bruktdeler-vegvesen' ), 'param_name' => 'size', 'value' => '64' ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'        => __( 'Vehicle picture', 'bruktdeler-vegvesen' ),
			'base'        => 'bv_image',
			'description' => __( 'From the media library by title "Make Model", or a silhouette', 'bruktdeler-vegvesen' ),
			'params'      => $common,
		) ) );

		vc_map( array_merge( $base, array(
			'name'   => __( 'Print button', 'bruktdeler-vegvesen' ),
			'base'   => 'bv_print',
			'params' => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Text', 'bruktdeler-vegvesen' ), 'param_name' => 'label', 'description' => __( 'Empty = default text in the site language.', 'bruktdeler-vegvesen' ) ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'   => __( 'Key figure tiles', 'bruktdeler-vegvesen' ),
			'base'   => 'bv_tiles',
			'params' => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Fields', 'bruktdeler-vegvesen' ), 'param_name' => 'fields', 'value' => BV_Shortcodes::DEFAULT_TILES, 'admin_label' => true, 'description' => $fields_tip ),
				array( 'type' => 'dropdown', 'heading' => __( 'Columns', 'bruktdeler-vegvesen' ), 'param_name' => 'columns', 'value' => array( '4' => '4', '3' => '3', '2' => '2', '1' => '1', '5' => '5', '6' => '6' ) ),
			), $common ),
		) ) );

		vc_map( array_merge( $base, array(
			'name'   => __( 'Field table', 'bruktdeler-vegvesen' ),
			'base'   => 'bv_table',
			'params' => array_merge( array(
				array( 'type' => 'textfield', 'heading' => __( 'Title', 'bruktdeler-vegvesen' ), 'param_name' => 'title', 'admin_label' => true ),
				$tag_param( 'title_tag', __( 'Title tag', 'bruktdeler-vegvesen' ), 'h3' ),
				array( 'type' => 'dropdown', 'heading' => __( 'Icon', 'bruktdeler-vegvesen' ), 'param_name' => 'icon', 'value' => $icons ),
				array( 'type' => 'textfield', 'heading' => __( 'Fields', 'bruktdeler-vegvesen' ), 'param_name' => 'fields', 'value' => BV_Shortcodes::TABLE_GENERAL, 'description' => $fields_tip ),
				array( 'type' => 'dropdown', 'heading' => __( 'Highlight row', 'bruktdeler-vegvesen' ), 'param_name' => 'highlight', 'value' => array_merge( array( __( '— none —', 'bruktdeler-vegvesen' ) => '' ), $fields ) ),
				array( 'type' => 'textfield', 'heading' => __( 'Custom labels', 'bruktdeler-vegvesen' ), 'param_name' => 'labels', 'description' => __( 'E.g. merke:Brand,modell:Model', 'bruktdeler-vegvesen' ) ),
			), $eu_params, $common ),
		) ) );
	}

	/** Button settings of the WoodMart Button element that are reused (renamed with the eu_btn_ prefix). */
	const WOODMART_BUTTON_PARAMS = array(
		'title', 'link', 'custom_attributes',
		'style', 'shape', 'size', 'color',
		'bg_color', 'color_scheme', 'custom_color_scheme',
		'bg_color_hover', 'color_scheme_hover', 'custom_color_scheme_hover',
	);

	/**
	 * Settings of the EU inspection button: when to show it, plus the settings of the theme's Button element
	 * (WoodMart: title, link, custom attributes, style, shape, size, colours, typography), and a CSS class.
	 * With WoodMart the fields are the theme's own (same look and options as its Button element, the theme
	 * generates the CSS for colours and typography); with another theme a simple set is used.
	 *
	 * @param string $group Tab name ('' = General).
	 */
	public static function eu_button_params( $group ) {
		$g      = $group ? array( 'group' => $group ) : array();
		$params = array(
			array(
				'type'        => 'dropdown',
				'heading'     => __( 'Show the button', 'bruktdeler-vegvesen' ),
				'param_name'  => 'eu_show',
				'value'       => array(
					__( 'When close or overdue', 'bruktdeler-vegvesen' )           => 'snart,utlopt',
					__( 'Only when overdue', 'bruktdeler-vegvesen' )               => 'utlopt',
					__( 'Only when the deadline is close', 'bruktdeler-vegvesen' ) => 'snart',
					__( 'Never', 'bruktdeler-vegvesen' )                           => 'none',
				),
				'description' => __( 'The button appears to the left of the status icon. Without a link it is a button for scripts: give it a CSS class (the registration number is in data-regnr).', 'bruktdeler-vegvesen' ),
			) + $g,
		);
		$class = array(
			'type'        => 'textfield',
			'heading'     => __( 'Button CSS class', 'bruktdeler-vegvesen' ),
			'param_name'  => 'eu_btn_class',
			'description' => __( 'Added to the button itself, e.g. to open a popup or run a script on click.', 'bruktdeler-vegvesen' ),
			'edit_field_class' => 'vc_col-sm-6 vc_column',
		) + $g;
		$link_tip = __( '{regnr} in the link is replaced with the registration number, e.g. /kontakt/?regnr={regnr}', 'bruktdeler-vegvesen' );

		if ( function_exists( 'woodmart_get_button_shortcode_params' ) && function_exists( 'woodmart_shortcode_button' ) ) {
			// WoodMart: the theme generates CSS for its colour/typography fields from "woodmart_css_id".
			$params[] = array( 'type' => 'woodmart_css_id', 'param_name' => 'woodmart_css_id' );
			$params[] = array( 'type' => 'woodmart_title_divider', 'title' => __( 'Button', 'bruktdeler-vegvesen' ), 'param_name' => 'eu_btn_divider' ) + $g;

			$style_added = false;
			foreach ( (array) woodmart_get_button_shortcode_params() as $p ) {
				$name = is_array( $p ) ? ( $p['param_name'] ?? '' ) : '';
				if ( ! $name || ( ! in_array( $name, self::WOODMART_BUTTON_PARAMS, true ) && 0 !== strpos( $name, 'typography_' ) ) ) {
					continue;
				}
				if ( 'style' === $name && ! $style_added ) {
					$params[]    = $class; // The class goes right after title, link and custom attributes.
					$params[]    = array( 'type' => 'woodmart_title_divider', 'title' => __( 'Button style', 'bruktdeler-vegvesen' ), 'param_name' => 'eu_btn_style_divider' ) + $g;
					$style_added = true;
				}
				$p['param_name'] = 'eu_btn_' . $name;
				unset( $p['holder'] );
				if ( isset( $p['dependency']['element'] ) ) {
					$p['dependency']['element'] = 'eu_btn_' . $p['dependency']['element'];
				}
				if ( isset( $p['std'] ) ) { // WPBakery 9: defaults live in settings['default'].
					$p['settings']['default'] = $p['std'];
					unset( $p['std'] );
				}
				if ( 'link' === $name ) {
					$p['description'] = $link_tip;
				}
				if ( $group ) {
					$p['group'] = $group;
				} else {
					unset( $p['group'] );
				}
				$params[] = $p;
			}
			if ( ! $style_added ) {
				$params[] = $class;
			}
			return $params;
		}

		// Another theme: a simple button.
		return array_merge( $params, array(
			array( 'type' => 'textfield', 'heading' => __( 'Title', 'bruktdeler-vegvesen' ), 'param_name' => 'eu_btn_title', 'edit_field_class' => 'vc_col-sm-6 vc_column' ) + $g,
			array( 'type' => 'vc_link', 'heading' => __( 'Link', 'bruktdeler-vegvesen' ), 'param_name' => 'eu_btn_link', 'description' => $link_tip, 'edit_field_class' => 'vc_col-sm-6 vc_column' ) + $g,
			array( 'type' => 'textfield', 'heading' => __( 'Custom attributes', 'bruktdeler-vegvesen' ), 'param_name' => 'eu_btn_custom_attributes', 'description' => __( 'key|value pairs separated by commas, e.g. data-popup|contact', 'bruktdeler-vegvesen' ), 'edit_field_class' => 'vc_col-sm-6 vc_column' ) + $g,
			$class,
		) );
	}
}
