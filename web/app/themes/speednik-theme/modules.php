<?php
/**
 * Modules Render Template
 *
 * This file serves as the template for rendering modules in the Timber theme.
 * It dynamically includes module-specific Twig templates based on ACF field data,
 * applying context from the Timber framework. It supports rendering both a module
 * preview image and the module's Twig template.
 *
 * @package WordPress
 * @subpackage Timber
 * @since Timber 2.*
 */

// Require the autoload for dependencies.
require __DIR__ . '/vendor/autoload.php';

use Timber\Timber;

// Start of the module render logic.
$module_name      = str_replace( 'acf/', '', $block['name'] );
$context          = Timber::context();
$context['block'] = $block;

$modules = get_fields();
if ( ! empty( $modules['content'] ) ) {
	$context['content'] = $modules['content'];
}

if ( ! empty( $modules['styles'] ) ) {
	$context['styles'] = $modules['styles'];
}

if ( ! empty( $modules['global'] ) ) {
	$context['global'] = $modules['global'];
}

$context['is_preview'] = $is_preview;

if ( isset( $block['data']['module_preview_img'] ) ) {
	// Display module preview image on block hover.
	echo '<img class="previewImg" src="' . esc_html( $block['data']['module_preview_img'] ) . '" style="width:100%; height:auto;">';
} else {
	// Render the twig module template on Editor and Front End.
	Timber::render( 'modules/' . $module_name . '/module.twig', $context );
}
