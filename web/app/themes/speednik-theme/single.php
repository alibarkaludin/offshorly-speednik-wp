<?php
/**
 * The Template for displaying all single posts
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

use Timber\Timber;

$context         = Timber::context();
$current_post    = Timber::get_post();
$context['post'] = $current_post;


$context['related_posts'] = Timber::get_posts(
	array(
		'post_type'      => $current_post->post_type,
		'posts_per_page' => 3,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post__not_in'   => array( $current_post->ID ),
		'category__in'   => $current_post->terms(
			array(
				'taxonomy' => 'category',
				'fields'   => 'ids',
			)
		),
	)
);



if ( post_password_required( $current_post->ID ) ) {
	Timber::render( 'single-password.twig', $context );
} else {
	Timber::render( array( 'single-' . $current_post->ID . '.twig', 'single-' . $current_post->post_type . '.twig', 'single-' . $current_post->slug . '.twig', 'single.twig' ), $context );
}
