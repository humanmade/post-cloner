<?php
/**
 * Plugin Name: Post Cloner
 * Description: Allows users to clone posts.
 * Version: 1.0.0
 * Text Domain: post-cloner
 * Author: Human Made Limited
 * Author URI: https://hmn.md
 *
 * @package HM Post Cloner
 */

namespace Post_Cloner;

/**
 * @TODO should we assign the author of the new post to the person creating it?
 * @TODO Should there be some kind of indication that a post is cloned?
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/inc/class-cloner.php';
require_once __DIR__ . '/inc/class-plugin.php';
require_once __DIR__ . '/inc/class-admin.php';
require_once __DIR__ . '/inc/class-rewrites.php';
require_once __DIR__ . '/inc/utilities.php';

/**
 * Retrieve the plugin instance.
 *
 * @return object Plugin
 */
function plugin() {
	static $instance;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}


// Load the plugin.
plugin();

// Set our definitions for later use.
plugin()->set_definitions(
	(object) [
		'basename'   => plugin_basename( __FILE__ ),
		'directory'  => plugin_dir_path( __FILE__ ),
		'file'       => __FILE__,
		'slug'       => 'post-cloner',
		'url'        => plugin_dir_url( __FILE__ ),
		'assets_url' => plugin_dir_url( __FILE__ ) . 'assets',
		'version'    => '1.0.0',
	]
);

// Register hooks.
plugin()->register_hooks( new Admin() )
		->register_hooks( new Rewrites() );
