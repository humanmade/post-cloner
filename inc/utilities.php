<?php
/**
 * Utility methods related to post cloning.
 *
 * @package HM Post Cloner.
 */

namespace Post_Cloner;

/**
 * Combine post_type and post_status checks into one.
 *
 * Checks whether a post has valid post type and status
 * to be clonable.
 *
 * @param object|int $post Post object or ID.
 * @return bool Clonable or not.
 */
function is_post_clonable( $post ) {
	if ( is_int( $post ) ) {
		$status  = get_post_status( $post );
		$type    = get_post_type( $post );
		$post_id = $post;
	} else {
		$status  = $post->post_status;
		$type    = $post->post_type;
		$post_id = $post->ID;
	}

	$clonable = ( is_post_type_clonable( $type ) && is_post_status_clonable( $status ) );

	/**
	 * Override clonable status of a single post if desired.
	 *
	 * @param bool $clonable Current clonable status.
	 * @param int $post_id ID of post that we're checking.
	 */
	return apply_filters( 'post_cloner_override_single_post', $clonable, $post_id );
}

/**
 * Check whether a post type is whitelisted to be cloned.
 *
 * @param string $post_type Post type slug.
 * @return bool Clonable or not.
 */
function is_post_type_clonable( $post_type ) {
	/**
	 * Post types that are eligible for cloning.
	 *
	 * @param array Post types.
	 */
	$types = apply_filters( 'post_cloner_clonable_post_types', [ 'post' ] );

	return ( in_array( $post_type, $types ) );
}

/**
 * Check whether a post type status is clonable.
 *
 * @param string $post_status Post status slug.
 * @return bool Clonable or not.
 */
function is_post_status_clonable( $post_status ) {
	/**
	 * Post statuses that are eligible for cloning.
	 *
	 * @param array Post statuses.
	 */
	$statuses = apply_filters( 'post_cloner_clonable_statuses', [ 'publish', 'draft', 'pending' ] );

	return ( in_array( $post_status, $statuses ) );
}

/**
 * Verify that the current user is allowed to clone posts.
 *
 * Checks that:
 * Current user is logged in.
 * Is in the admin area.
 * Current user has the proper capability to clone posts.
 *
 * @return bool whether or not a user can clone posts.
 */
function can_user_clone() {
	/**
	 * Minimum capability that a user must have to clone a post.
	 *
	 * Defaults to 'publish_posts' to give authors and up access to
	 * the capability.
	 *
	 * @param string Capability.
	 */
	$permission_level = apply_filters( 'post_cloner_permission_level', 'publish_posts' );

	return ( is_admin() && current_user_can( $permission_level ) );
}

/**
 * Check whether a post is cloned or not.
 *
 * @param int $post_id Post ID.
 * @return bool whather a post is cloned or not.
 */
function is_post_cloned( $post_id ) {
	return ( get_post_meta( $post_id, 'post_cloned', true ) );
}

/**
 * Remove array items that match an array of keys.
 *
 * @param array $array Original array.
 * @param array $keys Keys to remove.
 * @param array $patterns Optional. Regex patterns to search for in keys to remove.
 * @return array Cleaned array.
 */
function clean_keys( array $array, array $keys, array $patterns = [] ) {
	// Remove specifically identified keys.
	foreach ( $keys as $key ) {
		if ( isset( $array[ $key ] ) ) {
			unset( $array[ $key ] );
		}
	}

	// Remove keys matching a regex string.
	foreach ( $array as $meta_key => $meta_value ) {
		foreach ( $patterns as $pattern ) {
			if ( ! empty( preg_match( $pattern, $meta_key ) ) ) {
				unset( $array[ $meta_key ] );
			}
		}
	}

	return $array;
}
