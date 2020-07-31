<?php
/**
 * Tests for HM Post Cloner.
 *
 * @package HM Post Cloner.
 * @author Human Made Limited
 */

namespace Post_Cloner_Tests;

use Post_Cloner;
use WP_UnitTest_Factory;

/**
 * Class HMTestPostCloner
 */
class TestPostCloner extends PostCloner_TestCase {
	/**
	 * Holds the test post ID.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * Holds the duplicated test post ID.
	 *
	 * @var int
	 */
	private static $copied_id = 0;

	/**
	 * Post information array.
	 *
	 * @var array
	 */
	private static $post_data = [];

	/**
	 * Holds the test taxo terms.
	 *
	 * @var array
	 */
	private static $term_ids = [];

	/**
	 * Holds the test post ID.
	 *
	 * @var int
	 */
	private static $editor_id = 0;

	/**
	 * Holds the test categories.
	 *
	 * @var array
	 */
	private static $cats = [];

	/**
	 * Instance of the class we are testing.
	 *
	 * @var \Post_Cloner
	 */
	private static $instance = null;

	/**
	 * Setup data for the test suite.
	 *
	 * @param WP_UnitTest_Factory $factory WP factory for creating objects.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Create a test post.
		self::$post_id = $factory->post->create(
			[
				'post_author'  => get_current_user_id(),
				'post_title'   => 'My test post',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => 'Monotonectally brand bleeding-edge total linkage before interactive imperatives. Monotonectally facilitate ethical methods of empowerment rather than high-quality quality vectors. Uniquely whiteboard backend relationships vis-a-vis 24/365.',
				'post_excerpt' => 'short summary',
			]
		);

		self::$editor_id = $factory->user->create( [
			'role' => 'editor',
		] );

		self::$cats = $factory->category->create_many( 2 );

		// Create a custom taxonomy.
		register_taxonomy( 'my-custom-taxo', 'post' );
		self::$term_ids = $factory->term->create_many(
			3,
			[
				'taxonomy' => 'my-custom-taxo',
			]
		);

		wp_set_object_terms( self::$post_id, self::$cats, 'category' );
		wp_set_object_terms( self::$post_id, self::$term_ids, 'my-custom-taxo' );

		// Create custom metadata.
		add_post_meta( self::$post_id, 'teaser_kicker', 'my kicker', true );
		add_post_meta( self::$post_id, 'teaser_headline', 'a random headline', true );
		add_post_meta( self::$post_id, 'image', 187, true );

		self::$post_data = get_post( self::$post_id, ARRAY_A );

		self::$instance = new Post_Cloner\Cloner();

		$old_data = Post_Cloner\clean_keys( self::$post_data, [ 'ID', 'guid' ] );
		self::$copied_id  = self::invokeMethod( self::$instance, 'create_copy', [ $old_data ] );
	}

	/**
	 * Clean up after a test.
	 */
	public static function wpTearDownAfterClass() {
		_unregister_taxonomy( 'my-custom-taxo' );
	}

	/**
	 * Verifies that the cloned post is an exact copy of the original.
	 */
	public function test_new_post_is_exact_copy() {
		// Setup proper conditions to allow for cloning.
		set_current_screen( 'edit.php' );
		wp_set_current_user( self::$editor_id );

		// Clone post and fetch post data.
		$new_post_id = self::$instance->clone_post( self::$post_id );
		$new_post_info = get_post( $new_post_id );

		// Test that the ID is different.
		$this->assertNotEquals( $new_post_id, self::$post_id );

		// Test that the content and title match specifically.
		$this->assertEquals( get_the_title( $new_post_id ), get_the_title( self::$post_id ) );
		$this->assertEquals( $new_post_info->post_content, self::$post_data['post_content'] );

		// Test that all other data in the post object matches with the
		// exception of intentionally different pieces.
		$keys_to_clean = [
			'ID',                // ID and guid intentionally changed.
			'guid',
			'post_date',
			'post_date_gmt',     // New post created at different time than old post.
			'post_modified',
			'post_modified_gmt',
			'post_status',       // New post is always draft.
			'ancestors',         // See above note.
		];

		// We add a unique identifier to the end of the post name.
		self::$post_data['post_name'] = self::$post_data['post_name'] . '-cloned';

		$this->assertEquals(
			Post_Cloner\clean_keys( get_post( $new_post_id, ARRAY_A ), $keys_to_clean ),
			Post_Cloner\clean_keys( self::$post_data, $keys_to_clean )
		);

		// Test that all post meta match.
		$new_post_meta      = get_post_meta( $new_post_id );
		$original_meta_data = get_post_meta( self::$post_id );
		$this->assertEquals( 1, $new_post_meta['post_cloned'][0] );

		$keys_to_clean = [
			'post_cloned',       // Only new post should have cloned meta.
			'post_cloned_from',  // Only new post should have cloned meta.
			'_edit_lock',        // Edit_lock and edit_last are stripped.
			'_edit_last',
			'apple_news_api_id', // All apple news data is stripped.
			'apple_news_api_created_at',
			'apple_news_api_modified_at',
			'apple_news_api_share_url',
			'apple_news_api_revision',
			'apple_news_is_preview',
			'apple_news_pullquote',
			'apple_news_pullquote_position',
			'apple_news_sections',
		];

		$this->assertEquals(
			Post_Cloner\clean_keys( $new_post_meta, $keys_to_clean ),
			Post_Cloner\clean_keys( $original_meta_data, $keys_to_clean )
		);

		// Test that all taxonomy/term data match.
		$this->assertEquals(
			wp_get_object_terms( $new_post_id, get_object_taxonomies( $new_post_info ) ),
			wp_get_object_terms( self::$post_id, get_object_taxonomies( get_post( self::$post_id ) ) )
		);
	}

	/**
	 * Test get_meta_data().
	 */
	public function test_get_meta_data() {
		$original_meta_data = get_post_meta( self::$post_id );

		$meta_data_to_check = self::invokeMethod( self::$instance, 'get_meta_data', [ self::$post_id ] );

		// Manually unset the fields that we expect to be missing.
		unset( $original_meta_data['ID'] );
		unset( $original_meta_data['guid'] );

		$this->assertEquals( $original_meta_data, $meta_data_to_check );

	}

	/**
	 * Test get_taxonomies().
	 */
	public function test_get_taxonomies() {
		$expected_data = [
			'my-custom-taxo' => self::$term_ids,
			'category'       => self::$cats,
		];

		$taxonomies = self::invokeMethod( self::$instance, 'get_taxonomies', [ self::$post_data ] );

		$this->assertEquals( $expected_data, $taxonomies );
	}

	/**
	 * Test create_copy().
	 */
	public function test_create_copy() {
		// Check that we got an ID back.
		$this->assertInternalType( 'int', self::$copied_id );

		// Check that the post type is the same as the original post.
		$new_post_data = get_post( self::$copied_id, ARRAY_A );
		$this->assertEquals( $new_post_data['post_type'], self::$post_data['post_type'] );
	}

	/**
	 * Test copy_meta_data().
	 */
	public function test_copy_meta_data() {
		self::invokeMethod( self::$instance, 'copy_meta_data', [ self::$copied_id, get_post_meta( self::$post_id ) ] );

		// Check that the post metadata matches - minus the post_cloned item we're adding.
		$this->assertEquals(
			Post_Cloner\clean_keys( get_post_meta( self::$copied_id ), [ 'post_cloned', 'post_cloned_from', '_pingme', '_encloseme' ] ),
			Post_Cloner\clean_keys( get_post_meta( self::$post_id ), [ 'post_cloned', 'post_cloned_from', '_pingme', '_encloseme' ] )
		);
	}

	/**
	 * Test copy_taxonomy_relationships().
	 */
	public function test_copy_taxonomy_relationships() {
		$taxonomies = self::invokeMethod( self::$instance, 'get_taxonomies', [ self::$post_data ] );
		self::invokeMethod( self::$instance, 'copy_taxonomy_relationships', [ self::$copied_id, $taxonomies ] );

		$this->assertEquals(
			wp_get_object_terms( self::$copied_id, get_object_taxonomies( get_post( self::$copied_id ) ) ),
			wp_get_object_terms( self::$post_id, get_object_taxonomies( get_post( self::$post_id ) ) )
		);
	}

	/**
	 * Test clean_post().
	 */
	public function test_clean_post() {
		$original_post = self::$post_data;
		$modified_post = $original_post;

		// Manually unset the fields that we expect to be missing.
		unset( $modified_post['ID'] );
		unset( $modified_post['guid'] );
		unset( $modified_post['post_date_gmt'] );
		unset( $modified_post['post_modified'] );
		unset( $modified_post['post_modified_gmt'] );

		$post_data_to_check = self::invokeMethod( self::$instance, 'clean_post', [ $original_post ] );

		// Check that the array matches the modified one perfectly.
		$this->assertEquals( $modified_post, $post_data_to_check );
	}
}
