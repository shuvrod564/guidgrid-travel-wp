<?php
/**
 * Customizer: branding, colors, header, footer.
 *
 * Deep booking/payment settings live on the "Tour Settings" admin page
 * (single tg_settings option). The customizer covers visual branding.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_customize_register' ) ) {
	/**
	 * Register customizer settings.
	 *
	 * @param WP_Customize_Manager $wp_customize Manager.
	 * @return void
	 */
	function tg_customize_register( WP_Customize_Manager $wp_customize ) {
		$settings = tg_settings();

		$wp_customize->add_section(
			'tg_branding',
			array(
				'title'    => __( 'GuideGrid: Branding & Colors', 'guidegrid-travel' ),
				'priority' => 30,
			)
		);

		$color_settings = array(
			'primary_color'   => array(
				'label' => __( 'Primary Color', 'guidegrid-travel' ),
				'default' => '#0B6E69',
			),
			'secondary_color' => array(
				'label' => __( 'Secondary Color', 'guidegrid-travel' ),
				'default' => '#F4A261',
			),
			'accent_color'    => array(
				'label' => __( 'Accent Color', 'guidegrid-travel' ),
				'default' => '#2A9D8F',
			),
		);

		foreach ( $color_settings as $key => $cfg ) {
			$wp_customize->add_setting(
				'tg_settings[' . $key . ']',
				array(
					'default'           => $cfg['default'],
					'type'              => 'option',
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);
			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					'tg_' . $key,
					array(
						'label'    => $cfg['label'],
						'section'  => 'tg_branding',
						'settings' => 'tg_settings[' . $key . ']',
					)
				)
			);
		}

		$text_settings = array(
			'footer_about'     => array(
				'label' => __( 'Footer About Text', 'guidegrid-travel' ),
				'type'  => 'textarea',
			),
			'contact_address'  => array(
				'label' => __( 'Contact Address', 'guidegrid-travel' ),
				'type'  => 'text',
			),
			'contact_phone'    => array(
				'label' => __( 'Contact Phone', 'guidegrid-travel' ),
				'type'  => 'text',
			),
			'contact_email'    => array(
				'label' => __( 'Contact Email', 'guidegrid-travel' ),
				'type'  => 'email',
			),
			'contact_hours'    => array(
				'label' => __( 'Office Hours', 'guidegrid-travel' ),
				'type'  => 'text',
			),
			'social_facebook'  => array(
				'label' => __( 'Facebook URL', 'guidegrid-travel' ),
				'type'  => 'url',
			),
			'social_instagram' => array(
				'label' => __( 'Instagram URL', 'guidegrid-travel' ),
				'type'  => 'url',
			),
			'social_x'         => array(
				'label' => __( 'X (Twitter) URL', 'guidegrid-travel' ),
				'type'  => 'url',
			),
			'social_youtube'   => array(
				'label' => __( 'YouTube URL', 'guidegrid-travel' ),
				'type'  => 'url',
			),
			'header_cta_text'  => array(
				'label' => __( 'Header CTA Text', 'guidegrid-travel' ),
				'type'  => 'text',
			),
			'header_cta_url'   => array(
				'label' => __( 'Header CTA URL', 'guidegrid-travel' ),
				'type'  => 'url',
			),
			'footer_copyright' => array(
				'label' => __( 'Footer Copyright Text', 'guidegrid-travel' ),
				'type'  => 'text',
			),
		);

		foreach ( $text_settings as $key => $cfg ) {
			$existing = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
			$sanitize = 'sanitize_text_field';
			if ( 'url' === $cfg['type'] ) {
				$sanitize = 'esc_url_raw';
			} elseif ( 'email' === $cfg['type'] ) {
				$sanitize = 'sanitize_email';
			} elseif ( 'textarea' === $cfg['type'] ) {
				$sanitize = 'sanitize_textarea_field';
			}
			$wp_customize->add_setting(
				'tg_settings[' . $key . ']',
				array(
					'default'           => $existing,
					'type'              => 'option',
					'sanitize_callback' => $sanitize,
				)
			);
			$wp_customize->add_control(
				$tg_id = 'tg_' . $key,
				array(
					'label'    => $cfg['label'],
					'section'  => 'tg_branding',
					'settings' => 'tg_settings[' . $key . ']',
					'type'     => $cfg['type'],
				)
			);
		}

		$bool_settings = array(
			'sticky_header'       => __( 'Sticky Header', 'guidegrid-travel' ),
			'show_header_search'  => __( 'Show Header Search', 'guidegrid-travel' ),
			'show_header_wishlist' => __( 'Show Wishlist Icon', 'guidegrid-travel' ),
			'show_header_account' => __( 'Show Account Icon', 'guidegrid-travel' ),
			'show_header_cta'     => __( 'Show Header CTA Button', 'guidegrid-travel' ),
		);

		foreach ( $bool_settings as $key => $label ) {
			$wp_customize->add_setting(
				'tg_settings[' . $key . ']',
				array(
					'default'           => isset( $settings[ $key ] ) ? (bool) $settings[ $key ] : true,
					'type'              => 'option',
					'sanitize_callback' => 'tg_sanitize_checkbox',
				)
			);
			$wp_customize->add_control(
				'tg_' . $key,
				array(
					'label'    => $label,
					'section'  => 'tg_branding',
					'settings' => 'tg_settings[' . $key . ']',
					'type'     => 'checkbox',
				)
			);
		}

		$wp_customize->add_setting(
			'tg_settings[footer_logo_id]',
			array(
				'default'           => absint( $settings['footer_logo_id'] ?? 0 ),
				'type'              => 'option',
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				'tg_footer_logo',
				array(
					'label'       => __( 'Footer Logo', 'guidegrid-travel' ),
					'description' => __( 'Upload a separate transparent or light-colored logo for the dark footer.', 'guidegrid-travel' ),
					'section'     => 'tg_branding',
					'settings'    => 'tg_settings[footer_logo_id]',
					'mime_type'   => 'image',
				)
			)
		);
	}
}
add_action( 'customize_register', 'tg_customize_register' );

if ( ! function_exists( 'tg_sanitize_checkbox' ) ) {
	/**
	 * Checkbox sanitizer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function tg_sanitize_checkbox( $value ) {
		return empty( $value ) ? 0 : 1;
	}
}
