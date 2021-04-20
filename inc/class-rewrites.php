<?php
/**
 * Handles cloned post URL cleaning.
 *
 * @package HM Post Cloner.
 */

namespace Post_Cloner;

/**
 * Class Rewrites
 */
final class Rewrites {
	/**
	 * URL modifier that we need to search for.
	 *
	 * @var string
	 * @access private
	 */
	private $search_string;

	/**
	 * Run hooks that the class relies on.
	 */
	public function hooks() {
		$this->search_string = apply_filters( 'post_cloner_name_append', '-cloned' );

		// Add the ability to turn these filters off if an empty string is passed through the filter.
		if ( ! empty( $this->search_string ) ) {
			add_filter( 'post_link', [ $this, 'strip_cloned_from_url' ], 10, 2 );
			add_filter( 'get_sample_permalink_html', [ $this, 'strip_cloned_from_url_preview' ], 10, 2 );
		}
	}

	/**
	 * Remove cloned text from URL for a cloned post.
	 *
	 * This only works when a post is prepended with the ID in the URL string.
	 *
	 * @param string      $permalink Full permalink.
	 * @param WP_Post|int $post post object or ID.
	 * @return string Potentially modified permalink
	 */
	public function strip_cloned_from_url( $permalink, $post ) {
		if ( is_post_cloned( $post->ID ) && strip_cloned( $permalink, $post ) ) {
			$permalink = preg_replace( '/(' . $this->search_string . ')\/$/', '/', trailingslashit( $permalink ), 1 );
		}

		return $permalink;
	}

	/**
	 * Remove cloned string from URL preview.
	 *
	 * @param string $output Full permalink.
	 * @param int    $post_id Post ID.
	 * @return string Potentially modified permalink.
	 */
	public function strip_cloned_from_url_preview( $output, $post_id ) {
		if ( is_post_cloned( $post_id ) ) {
			$output = preg_replace( '/(' . $this->search_string . ')/', '', $output, 1 );
		}

		return $output;
	}
}
