<?php
/**
 * Class Starter_Theme File.
 *
 * This file defines the Starter_Theme class, extending Timber\Site to provide
 * custom theme functionality and integration with Timber for the WordPress theme.
 *
 * @package WordPress
 * @subpackage Timber
 */

use Timber\Site;
use Timber\Timber;
use Timber\PostQuery;

/**
 * Starter_Theme Class.
 *
 * Extends Timber\Site to provide custom theme functionality
 * and integration with Timber.
 */
class Starter_Theme extends Site {
	/**
	 * Constructor.
	 *
	 * Initializes the theme's functions and hooks.
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		add_filter( 'timber/context', array( $this, 'add_to_context' ) );
		add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
		add_filter( 'timber/twig/environment/options', array( $this, 'update_twig_environment_options' ) );

		parent::__construct();
	}

	/**
	 * Registers custom post types.
	 */
	public function register_post_types() {
		// Register custom post types here.
	}

	/**
	 * Registers custom taxonomies.
	 */
	public function register_taxonomies() {
		// Register custom taxonomies here.
	}

	/**
	 * Adds custom variables to the Timber context.
	 *
	 * @param array $context The current Timber context.
	 * @return array The modified context.
	 */
	public function add_to_context( $context ) {
		$context['foo']      = 'bar';
		$context['stuff']    = 'I am a value set in your functions.php file';
		$context['notes']    = 'These values are available every time you call Timber::context();';
		$context['menu']     = Timber::get_menu();
		$context['site']     = $this;
		$context['options']  = get_fields( 'option' );
		$context['ajax_url'] = admin_url( 'admin-ajax.php' );

		$theme_settings['general'] = get_field( 'general', 'option' );

		return $context;
	}

	/**
	 * Adds theme supports.
	 */
	public function theme_supports() {
		// Theme supports like post thumbnails, HTML5, and feed links are added here.
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );

		add_theme_support(
			'html5',
			array(
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
			)
		);

		add_theme_support(
			'post-formats',
			array(
				'aside',
				'image',
				'video',
				'quote',
				'link',
				'gallery',
				'audio',
			)
		);

		add_theme_support( 'menus' );
	}

	/**
	 * Custom Twig filter example.
	 *
	 * @param string $text The text to be filtered.
	 * @return string The filtered text.
	 */
	public function myfoo( $text ) {
		$text .= ' bar!';
		return $text;
	}

	/**
	 * Retrieves posts of a specific post type.
	 *
	 * @param string $post_type The post type to query.
	 * @return PostQuery The query result.
	 */
	public function get_posts( $post_type ) {
		global $paged;

		if ( ! isset( $paged ) || ! $paged ) {
		  $paged = 1; //phpcs:ignore
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'paged'          => $paged,
		);

		$posts = new PostQuery( $args );

		return $posts;
	}

	/**
	 * Adds custom functions to Twig.
	 *
	 * @param Twig\Environment $twig The Twig environment.
	 * @return Twig\Environment The modified Twig environment.
	 */
	public function add_to_twig( $twig ) {
		$twig->addFilter( new Twig\TwigFilter( 'myfoo', array( $this, 'myfoo' ) ) );

		return $twig;
	}

	/**
	 * Updates the Twig environment options.
	 *
	 * @param array $options The current Twig environment options.
	 * @return array The updated Twig environment options.
	 */
	public function update_twig_environment_options( $options ) {
		// Modify Twig environment options here.

		return $options;
	}
}
