<?php
/**
 * Tests for The Sun Post Cloner.
 *
 * @package The Sun Post Cloner.
 * @author Human Made Limited
 */

/**
 * Class TheSunTestPostCloner
 */
class TestPostCloner extends PostCloner_TestCase {
	/**
	 * Holds the test post ID.
	 *
	 * @var int
	 */
	private $post_id = 0;

	/**
	 * Holds the duplicated test post ID.
	 *
	 * @var int
	 */
	private $copied_id = 0;

	/**
	 * Post information array.
	 *
	 * @var array
	 */
	private $post_data = [];

	/**
	 * Holds duplicated post IDs.
	 *
	 * @var array
	 */
	private $post_ids = [];

	/**
	 * Holds the test taxo terms.
	 *
	 * @var array
	 */
	private $term_ids = [];

	/**
	 * Holds the test post ID.
	 *
	 * @var int
	 */
	private $editor_id = 0;

	/**
	 * Holds the test categories.
	 *
	 * @var array
	 */
	private $cats = [];

	/**
	 * Instance of the class we are testing.
	 *
	 * @var TheSun_Post_Cloner
	 */
	private $instance = null;

	/**
	 * Initialization.
	 */
	function setUp() {
		parent::setUp();

		// Create a test post.
		$this->post_id = self::factory()->post->create(
			array(
				'post_author'  => get_current_user_id(),
				'post_title'   => 'My test post',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => 'Monotonectally brand bleeding-edge total linkage before interactive imperatives. Monotonectally facilitate ethical methods of empowerment rather than high-quality quality vectors. Uniquely whiteboard backend relationships vis-a-vis 24/365.',
				'post_excerpt' => 'short summary'
			)
		);

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->cats = self::factory()->category->create_many( 2 );

		// Create a custom taxonomy.
		register_taxonomy( 'my-custom-taxo', 'post' );
		$this->term_ids = self::factory()->term->create_many( 3 , [
			'taxonomy' => 'my-custom-taxo'
		] );

		wp_set_object_terms( $this->post_id, $this->cats, 'category' );
		wp_set_object_terms( $this->post_id, $this->term_ids, 'my-custom-taxo' );

		// Create custom metadata
		add_post_meta( $this->post_id, 'teaser_kicker', 'my kicker', true );
		add_post_meta( $this->post_id, 'teaser_headline', 'a random headline', true );
		add_post_meta( $this->post_id, 'image', 187, true );

		$this->post_data = get_post( $this->post_id, ARRAY_A );

		$this->instance = new Post_Cloner\Cloner();

		$old_data = Post_Cloner\clean_keys( $this->post_data, [ 'ID', 'guid' ] );
		$this->post_ids[] = $this->copied_id = $this->invokeMethod( $this->instance, 'create_copy', array( $old_data ) );
	}

	/**
	 * Clean up after a test.
	 */
	function tearDown() {
		_unregister_taxonomy( 'my-custom-taxo' );
		wp_delete_post( $this->post_id );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id );
		}
		parent::tearDown();
	}

	/**
	 * Verifies that the cloned post is an exact copy of the original.
	 */
	public function test_new_post_is_exact_copy() {

		// Setup proper conditions to allow for cloning
		set_current_screen( 'edit.php' );
		wp_set_current_user( $this->editor_id );

		// Clone post and fetch post data.
		$this->post_ids[] = $new_post_id = $this->instance->clone_post( $this->post_id );
		$new_post_info = get_post( $new_post_id );

		// Test that the ID is different.
		$this->assertNotEquals( $new_post_id, $this->post_id );

		// Test that the content and title match specifically.
		$this->assertEquals( get_the_title( $new_post_id ), get_the_title( $this->post_id ) );
		$this->assertEquals( $new_post_info->post_content, $this->post_data['post_content'] );

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
			'post_parent',       // New post now has original post as parent.
			'ancestors',         // See above note.
		];

		// We add a unique identifier to the end of the post name.
		$this->post_data['post_name'] = $this->post_data['post_name'] . '-cloned';

		$this->assertEquals(
			Post_Cloner\clean_keys( get_post( $new_post_id, ARRAY_A ), $keys_to_clean ),
			Post_Cloner\clean_keys( $this->post_data, $keys_to_clean )
		);

		// Test that all post meta match.
		$new_post_meta      = get_post_meta( $new_post_id );
		$original_meta_data = get_post_meta( $this->post_id );
		$this->assertEquals( 1, $new_post_meta['post_cloned'][0] );

		$keys_to_clean = [
			'post_cloned',       // Only new post should have cloned meta.
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
			wp_get_object_terms( $this->post_id, get_object_taxonomies( get_post( $this->post_id ) ) )
		);
	}

	/**
	 * Test get_meta_data().
	 */
	public function test_get_meta_data() {
		$original_meta_data = get_post_meta( $this->post_id );

		$meta_data_to_check = $this->invokeMethod( $this->instance, 'get_meta_data', array( $this->post_id ) );

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
			'my-custom-taxo' => $this->term_ids,
			'category'       => $this->cats,
		];

		$taxonomies = $this->invokeMethod( $this->instance, 'get_taxonomies', array( $this->post_data ) );

		$this->assertEquals( $expected_data, $taxonomies );
	}

	/**
	 * Test create_copy().
	 */
	public function test_create_copy() {
		// Check that we got an ID back.
		$this->assertInternalType( 'int', $this->copied_id );

		// Check that the post type is the same as the original post.
		$new_post_data = get_post( $this->copied_id, ARRAY_A );
		$this->assertEquals( $new_post_data['post_type'], $this->post_data['post_type'] );
	}

	/**
	 * Test copy_meta_data().
	 */
	public function test_copy_meta_data() {
		$this->invokeMethod( $this->instance, 'copy_meta_data', array( $this->copied_id, get_post_meta( $this->post_id ) ) );

		// Check that the post metadata matches - minus the post_cloned item we're adding.
		$this->assertEquals(
			Post_Cloner\clean_keys( get_post_meta( $this->copied_id ), [ 'post_cloned', '_pingme', '_encloseme' ] ),
			Post_Cloner\clean_keys( get_post_meta( $this->post_id ), [ 'post_cloned', '_pingme', '_encloseme' ] )
		);


	}

	/**
	 * Test copy_taxonomy_relationships().
	 */
	public function test_copy_taxonomy_relationships() {
		$taxonomies = $this->invokeMethod( $this->instance, 'get_taxonomies', array( $this->post_data ) );
		$this->invokeMethod( $this->instance, 'copy_taxonomy_relationships', array( $this->copied_id, $taxonomies ) );

		$this->assertEquals(
			wp_get_object_terms( $this->copied_id, get_object_taxonomies( get_post( $this->copied_id ) ) ),
			wp_get_object_terms( $this->post_id, get_object_taxonomies( get_post( $this->post_id ) ) )
		);
	}

	/**
	 * Test clean_post().
	 */
	public function test_clean_post() {
		$original_post = $this->post_data;
		$modified_post = $original_post;

		// Manually unset the fields that we expect to be missing.
		unset( $modified_post['ID'] );
		unset( $modified_post['guid'] );
		unset( $modified_post['post_date_gmt'] );
		unset( $modified_post['post_modified'] );
		unset( $modified_post['post_modified_gmt'] );

		$post_data_to_check = $this->invokeMethod( $this->instance, 'clean_post', array( $original_post ) );

		// Check that the array matches the modified one perfectly.
		$this->assertEquals( $modified_post, $post_data_to_check );
	}
}
