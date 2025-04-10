<?php

$parallax = new Advanced_Ads_Pro_Module_Parallax();

if ( ! $parallax->enabled_placement_exists() ) {
	return;
}

if ( is_admin() && ! wp_doing_ajax() ) {
	new Advanced_Ads_Pro_Module_Parallax_Admin_UI( $parallax );

	return;
}

new Advanced_Ads_Pro_Module_Parallax_Frontend( $parallax );
