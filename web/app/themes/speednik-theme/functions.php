<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 *
 * @package Timber 2.*
 */

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/class-starter-theme.php';

use Timber\Timber;
use function Env\env;

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = array( 'templates', 'views', 'modules' );

new Starter_Theme();

/**
 * Define theme slug globally.
 */
if ( ! defined( 'STARTER_THEME_SLUG' ) ) {
	// Replace the slug name to the theme name.
	define( 'STARTER_THEME_SLUG', 'starter_theme' );
}

/**
 * Define theme version.
 */
if ( ! defined( 'STARTER_THEME_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( 'STARTER_THEME_VERSION', '4.0' );
}

if ( ! class_exists( 'Timber\Timber' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="error"><p>Timber not found. Please ensure Timber is installed correctly via Composer.</p></div>';
		}
	);
	return;
}

/**
 * Include Json_Manifest class
 */
require_once 'src/class-starter-theme-json-manifest.php';

/**
 * Function starter_theme_asset_path
 *
 * @param  string $filename - name of file in the asset folder to fetxh.
 * @return $newpath
 */
function starter_theme_asset_path( $filename ) {
	$dist_path = get_template_directory_uri() . '/dist/';
	$directory = dirname( $filename ) . '/';
	$file      = basename( $filename );
	static $manifest;

	if ( empty( $manifest ) ) {
		$manifest_path = get_template_directory() . '/manifest.json';
		$manifest      = new Starter_Theme_Json_Manifest( $manifest_path );
	}

	if ( $manifest->get() !== null ) {
		if ( array_key_exists( $file, $manifest->get() ) ) {
			$newpath = $dist_path . $directory . $manifest->get()[ $file ];
		} else {
			$newpath = $dist_path . $directory . $file;
		}
	} else {
		$newpath = $dist_path . $directory . $file;
	}

	return $newpath;
}

/**
 * Theme assets
 */
function starter_theme_assets() {
	wp_enqueue_style( 'uikit', get_template_directory_uri() . '/node_modules/uikit/dist/css/uikit.min.css', array(), '3.19.1' );
	wp_enqueue_style( 'sage', starter_theme_asset_path( 'css/app.min.css' ), array(), STARTER_THEME_VERSION );

	if ( is_single() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_enqueue_script( 'uikit', get_template_directory_uri() . '/node_modules/uikit/dist/js/uikit.min.js', array(), '3.19.1', true );
	wp_enqueue_script( 'uikit-icons', get_template_directory_uri() . '/node_modules/uikit/dist/js/uikit-icons.min.js', array(), '3.19.1', true );
	wp_enqueue_script( 'sage', starter_theme_asset_path( 'scripts/app.min.js' ), array( 'jquery' ), STARTER_THEME_VERSION, true );

	// Shortcut path.
	$vendor_path = get_template_directory_uri() . '/assets/vendor/';

	// Slick JS.
	wp_enqueue_script( 'slick-js', $vendor_path . 'slick/slick.min.js', array( 'jquery' ), '1.8.1', true );
	wp_enqueue_style( 'slick-css', $vendor_path . 'slick/slick.css', array(), '1.8.1' );
	wp_enqueue_style( 'slick-theme-css', $vendor_path . 'slick/slick-theme.css', array(), '1.8.1' );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\starter_theme_assets', 100 );

/**
 * Modifies the HTML script tag for a specific script to include the type attribute set to "module".
 * This is done to allow the use of ES6 modules in the script identified by the handle 'sage'.
 *
 * @param string $tag    The `<script>` tag for the enqueued script.
 * @param string $handle The script's registered handle.
 * @param string $src    The script's source URL.
 *
 * @return string Modified script tag with the type attribute set to "module" for the 'sage' handle.
 */
function add_type_attribute( $tag, $handle, $src ) {
	if ( 'sage' === $handle ) {
		$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'add_type_attribute', 10, 3 );

// allow shortcodes in ACF text fields.
add_filter( 'acf/format_value/type=text', 'do_shortcode' );

/**
 * Undocumented function
 *
 * @param  string $menu_name - menu name to add to Timber\Menu.
 * @return array Array of Timber\MenuItem objects.
 */
// phpcs:ignore
function get_menu($menu_name) {
	return Timber::get_menu( $menu_name );
}

/**
 * Add filter to excerpt
 *
 * @return $length
 */
function starter_theme_excerpt_length() {
	return 12;
}
add_filter( 'excerpt_length', 'starter_theme_excerpt_length' );

/**
 * Get current Year
 *
 * @return $year
 */
function starter_theme_get_year() {
	$year = gmdate( 'Y' );

	return $year;
}
add_shortcode( 'year', 'starter_theme_get_year' );

/**
 * Function starter_theme_enqueue_foundation_in_admin
 *
 * @return void
 */
function starter_theme_enqueue_foundation_in_admin() {
	$current_screen = get_current_screen();

	if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
		// check if we're on a Gutenberg Page.
		wp_enqueue_style( 'main', starter_theme_asset_path( 'css/app.min.css' ), array(), STARTER_THEME_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'starter_theme_enqueue_foundation_in_admin' );

/**
 * Function starter_theme_add_default_value_to_image_field
 *
 * @param  string $field - field name.
 * @return void
 */
function starter_theme_add_default_value_to_image_field( $field ) {
	acf_render_field_setting(
		$field,
		array(
			'label'        => 'Default Image',
			'instructions' => 'Appears when creating a new post',
			'type'         => 'image',
			'name'         => 'default_value',
		)
	);
}
add_action( 'acf/render_field_settings/type=image', 'starter_theme_add_default_value_to_image_field', 10, 1 );

/**
 * Add type module filter
 *
 * @param  string $tag    - script tags to return.
 * @param  string $handle - name handler.
 * @param  string $src    - source file.
 * @return $tag
 */
function starter_theme_add_type_attribute( $tag, $handle, $src ) {
	// if not your script, do nothing and return original $tag.
	if ( 'sage/js' !== $handle ) {
		return $tag;
	}

	// change the script tag by adding type="module" and return it.
  // phpcs:ignore
  $tag = '<script type="module" src="' . esc_url($src) . '"></script>';

	return $tag;
}
add_filter( 'script_loader_tag', 'starter_theme_add_type_attribute', 10, 3 );


/**
 * Function to add preview images to custom module blocks
 *
 * @param array $args - args for adding preview images via example.
 */
function offshorly_modules_preview_imgs( $args ) {
	// Get image from module folder.
	$module_name        = str_replace( 'acf/', '', $args['name'] );
	$module_preview_img = '/modules/' . $module_name . '/module-preview.png';

	// If image exists, load it as the block's hover preview.
	if ( file_exists( get_template_directory() . $module_preview_img ) ) {

		$args['example'] = array(
			'attributes' => array(
				'mode' => 'preview',
				'data' => array(
					'module_preview_img' => get_template_directory_uri() . $module_preview_img,
				),
			),
		);
	}

	return $args;
}
add_filter( 'acf/register_block_type_args', 'offshorly_modules_preview_imgs' );

/**
 * Adding a "Custom Blocks" category at the top of block options
 *
 * @param array $categories - Array of categories for block types.
 */
function offshorly_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'flex-custom-blocks',
			'title' => 'Flex Custom Blocks',
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'offshorly_block_category', 10, 2 );

// phpcs:disable
/**
 * Prevent update notification for plugin
 * Uncomment this function after deployment. 
 */

// function disable_plugin_updates( $value ) {

// 	$pluginsToDisable = [
// 			'timber-library/timber.php'
// 	];

// 	if ( isset($value) && is_object($value) ) {
// 			foreach ($pluginsToDisable as $plugin) {
// 					if ( isset( $value->response[$plugin] ) ) {
// 							unset( $value->response[$plugin] );
// 					}
// 			}
// 	}
// 	return $value;
// }
// add_filter( 'site_transient_update_plugins', 'disable_plugin_updates' );

// phpcs:enable

/**
 * Function to prevent non-offshorly user to edit ACF options
 */
function my_acf_show_admin() {
	if ( defined( 'WP_ENV' ) && WP_ENV === 'production' ) {
		$current_user = wp_get_current_user();
		if ( 'dev@offshorly.com' === $current_user->user_email ) {
			return true;
		} else {
			return false;
		}
	} else {
		return current_user_can( 'manage_options' );
	}
}
add_filter( 'acf/settings/show_admin', 'my_acf_show_admin' );

function render_group_caption_shortcode($atts) {
    // Extract attributes with defaults
    $atts = shortcode_atts([
        'caption' => '',
        'type'    => '1',
        'images'  => ''
    ], $atts);
    
    // Decode the caption
    $caption = urldecode($atts['caption']);
    
    // Split images into an array
    $images = array_filter(array_map('trim', explode(',', $atts['images'])));
    
    if (empty($images)) {
        return ''; // No images provided, return empty
    }
    
    // Start output buffering
    ob_start();
    ?>

    <figure>
        <div class="uk-grid uk-child-width-1-1 uk-child-width-1-<?php echo esc_attr($atts['type']); ?>@m group-images" uk-grid>
            <?php foreach ($images as $image): ?>
                <img src="<?php echo esc_url($image); ?>" alt="">
            <?php endforeach; ?>
        </div>
        <figcaption class="uk-padding uk-border-rounded uk-background-muted uk-margin-top uk-text-italic"><?php echo esc_html($caption); ?></figcaption>
    </figure>

    <?php
    return ob_get_clean();
}
add_shortcode('group_caption', 'render_group_caption_shortcode');