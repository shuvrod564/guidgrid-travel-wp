<?php
/**
 * Theme setup: supports, menus, image sizes.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tg_setup' ) ) {
	/**
	 * Register theme supports, nav menus and image sizes.
	 *
	 * @return void
	 */
	function tg_setup() {
		load_theme_textdomain( 'guidegrid-travel', TG_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array(
			'height'      => 88,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		) );
		add_theme_support( 'custom-background' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );

		add_image_size( 'tg-card', 800, 600, true );
		add_image_size( 'tg-hero', 1600, 900, true );
		add_image_size( 'tg-square', 600, 600, true );
		add_image_size( 'tg-gallery', 1200, 800, true );

		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'guidegrid-travel' ),
				'footer'  => __( 'Footer Menu', 'guidegrid-travel' ),
			)
		);
	}
}
add_action( 'after_setup_theme', 'tg_setup' );

if ( ! function_exists( 'tg_default_menu' ) ) {
	/**
	 * Fallback primary menu built from core pages when none is assigned.
	 *
	 * @return void
	 */
	function tg_default_menu() {
		$items   = array();
		$home    = get_option( 'show_on_front' ) === 'page' ? (int) get_option( 'page_on_front' ) : 0;
		$pages   = array(
			'home',
			'tours',
			'destinations',
			'travel-guides',
			'blog',
			'about',
			'contact',
		);

		$items[] = array(
			'title'     => __( 'Home', 'guidegrid-travel' ),
			'url'       => home_url( '/' ),
		);

		$archives = array(
			'tours'          => array(
				'label' => __( 'Tours', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'tour' ),
			),
			'destinations'   => array(
				'label' => __( 'Destinations', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'destination' ),
			),
			'travel-guides'  => array(
				'label' => __( 'Travel Guides', 'guidegrid-travel' ),
				'url'   => get_post_type_archive_link( 'travel_guide' ),
			),
		);

		foreach ( $archives as $arch ) {
			if ( $arch['url'] ) {
				$items[] = array(
					'title' => $arch['label'],
					'url'   => $arch['url'],
				);
			}
		}

		if ( get_option( 'blog_page_id' ) || ! get_option( 'page_on_front' ) ) {
			$blog_url = get_permalink( (int) get_option( 'blog_page_id' ) );
			if ( ! $blog_url ) {
				$blog_url = home_url( '/blog/' );
			}
			$items[] = array(
				'title' => __( 'Blog', 'guidegrid-travel' ),
				'url'   => $blog_url,
			);
		}

		foreach ( array( 'about', 'contact' ) as $slug ) {
			$id = tg_page_id_by_path( $slug );
			if ( $id ) {
				$items[] = array(
					'title' => get_the_title( $id ),
					'url'   => get_permalink( $id ),
				);
			}
		}

		echo '<ul class="tg-menu">';
		foreach ( $items as $item ) {
			echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></li>';
		}
		echo '</ul>';
	}
}
