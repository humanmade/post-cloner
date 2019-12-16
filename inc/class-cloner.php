<?php
/**
 * Handles cloning of a post
 *
 * @package HM Post Cloner.
 */

namespace Post_Cloner;

/**
 * Class Cloner
 */
class Cloner {

	/**
	 * Original post ID.
	 *
	 * @var \int
	 */
	private $orig_id;

	/**
	 * Duplicate a post into another, new post.
	 *
	 * @param int $post_id post ID to be duplicated.
	 * @return int/WP_Error New post ID or error information.
	 */
	public function clone_post( $post_id ) {
		// Check if the post type is whitelisted to be clonable by status and type.
		if ( ! is_post_clonable( $post_id ) ) {
			return false;
		}

		// Check that the current user is allowed to clone posts.
		if ( ! can_user_clone() ) {
			return false;
		}

		$this->orig_id = $post_id;

		// Get our original post object.
		$original_post = get_post( $this->orig_id, ARRAY_A );

		// Create the repeat post as a copy of the original, but ignore some fields.
		$duplicate_post = $this->clean_post( $original_post );

		/**
		 * Parent that the new post should have.
		 *
		 * @param int $parent Parent - defaults to original post ID.
		 */
		$parent = apply_filters( 'post_cloner_cloned_parent', $original_post['ID'] );

		// Set the post_parent.
		$duplicate_post['post_parent'] = $parent;

		/**
		 * Status that the new post should have.
		 *
		 * @param string $status Status - defaults to draft.
		 */
		$status = apply_filters( 'post_cloner_cloned_status', 'draft' );

		// Set the post status.
		$duplicate_post['post_status'] = $status;

		/**
		 * String to append onto the end of post name to prevent collisions.
		 *
		 * Defaults to '-cloned'. If an empty string is passed through the filter
		 * then the rewrites won't take effect and nothing will be appended to the
		 * post name.
		 *
		 * @param string String to append to the end of a cloned post's name.
		 */
		$name_append = apply_filters( 'post_cloner_name_append', '-cloned' );

		// Add cloned to new post status to target for rewrites.
		$duplicate_post['post_name'] = $original_post['post_name'] . $name_append;

		// Make the new post.
		$new_post_id = $this->create_copy( $duplicate_post );

		// If we encounter an error skip straight to pushing that through.
		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		// Mirror any post_meta.
		$this->copy_meta_data( $new_post_id, $this->get_meta_data( $this->orig_id ) );

		// Mirror any term relationships.
		$this->copy_taxonomy_relationships( $new_post_id, $this->get_taxonomies( $original_post ) );

		return $new_post_id;
	}

	/**
	 * Get meta data related to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Metadata
	 */
	protected function get_meta_data( $post_id ) {
		$post_meta = get_post_meta( $post_id );

		$keys_to_remove = [
			'_edit_lock',
			'_edit_last',
		];

		/**
		 * Keys of metadata that should not be passed through to the new post.
		 *
		 * @param array $keys_to_remove Metadata keys.
		 */
		$keys = apply_filters( 'post_cloner_meta_keys_to_remove', $keys_to_remove );

		/**
		 * Regex patterns to look for when cleaning meta keys.
		 */
		$patterns = apply_filters( 'post_cloner_meta_patterns_to_remove', [] );

		return clean_keys( $post_meta, $keys, $patterns );
	}

	/**
	 * Gets all term objects from all taxonomies assigned to the post object.
	 *
	 * @param array $post_object The post object to retrieve the taxonomies for.
	 * @return array|WP_Error
	 */
	protected function get_taxonomies( array $post_object ) {
		$taxonomies = get_object_taxonomies( $post_object['post_type'] );
		$terms = [];

		foreach ( $taxonomies as $tax ) {
			$the_terms = get_the_terms( $post_object['ID'], $tax );
			if ( ! empty( $the_terms ) ) {
				$terms[ $tax ] = wp_list_pluck( $the_terms, 'term_id' );
			}
		}

		return $terms;
	}

	/**
	 * Create a post with a post object fed in.
	 *
	 * @param array $post_obj Post data.
	 * @return int|WP_Error Post ID | WP Error data
	 */
	protected function create_copy( $post_obj ) {
		return wp_insert_post( wp_slash( $post_obj ), true );
	}

	/**
	 * Duplicate the metadata from an original post onto another post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $post_meta Post metadata.
	 * @return bool false if post ID or metadata is missing.
	 */
	protected function copy_meta_data( $post_id, $post_meta ) {
		if ( empty( $post_id ) || empty( $post_meta ) ) {
			return false;
		}

		// Add cloned metadata.
		$post_meta['post_cloned']      = [ true ];
		$post_meta['post_cloned_from'] = [ $this->orig_id ];

		/**
		 * Filter the post meta data assigned to the new post.
		 *
		 * @param array $post_meta All post meta to assign to the new post.
		 */
		$post_meta = apply_filters( 'post_cloner_meta_data', $post_meta );

		// Loop through the meta items and update on our new post.
		foreach ( $post_meta as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta( $post_id, $key, $value );
			}
		}

		/**
		 * Fires when post meta has been copied to cloned post.
		 *
		 * @param array $post_id Post ID of cloned post.
		 */
		do_action( 'post_cloner_after_meta_data_copy', $post_id );
	}

	/**
	 * Duplicate taxonomy relationships on to a new post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $taxonomies array Taxonomy and term data.
	 */
	protected function copy_taxonomy_relationships( $post_id, $taxonomies ) {
		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( $terms ) {
				wp_set_object_terms( $post_id, $terms, $taxonomy );
			}
		}
	}

	/**
	 * Clean several unnecessary indexes from a post object.
	 *
	 * We don't want several of the post information pieces
	 * to be carried through to our new post so we will clean those
	 * out here and return the cleaned post info array.
	 *
	 * @param array $post original post information.
	 * @return array Cleaned post information
	 */
	protected function clean_post( $post ) {
		$keys_to_remove = [
			'ID',
			'guid',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
		];

		/**
		 * Keys of post object data that should not be passed through to the new post.
		 *
		 * @param array $keys_to_remove WP_Post array keys.
		 */
		$keys = apply_filters( 'post_cloner_post_keys_to_remove', $keys_to_remove );

		return clean_keys( $post, $keys );
	}
}
