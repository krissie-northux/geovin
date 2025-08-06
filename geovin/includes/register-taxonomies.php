<?php

namespace Geovin;


/*
 * Create a custom taxonomies for post types
 */
add_action( 'init', __NAMESPACE__ . '\register_taxonomies', 0 );
 

/**
 * Register custom taxonomies for the Geovin plugin.
 * This function registers a 'collections' taxonomy for products.
 *
 * @return void
 */
function register_taxonomies() {
	$labels = array(
		'name' => _x( 'Collections', 'taxonomy general name' ),
		'singular_name' => _x( 'Collection', 'taxonomy singular name' ),
		'search_items' =>  __( 'Search Collections' ),
		'all_items' => __( 'All Collections' ),
		'parent_item' => __( 'Parent Collection' ),
		'parent_item_colon' => __( 'Parent Collection:' ),
		'edit_item' => __( 'Edit Collection' ), 
		'update_item' => __( 'Update Collection' ),
		'add_new_item' => __( 'Add New Collection' ),
		'new_item_name' => __( 'New Collection Name' ),
		'menu_name' => __( 'Collections' ),
	);

	register_taxonomy('collections',array('product'), array(
		'hierarchical' => true,
		'labels' => $labels,
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array( 'slug' => 'collection' ),
	));
}