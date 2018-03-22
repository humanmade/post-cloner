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
class TestPostClonerUtilities extends PostCloner_TestCase {

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Setup data for the test suite.
	 *
	 * @param WP_UnitTest_Factory $factory WP factory for creating objects.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( [
			'role' => 'editor',
		] );
		self::$admin_id = $factory->user->create( [
			'role' => 'administrator',
		] );
		self::$subscriber_id = $factory->user->create( [
			'role' => 'subscriber',
		] );
	}

	/**
	 * Test is_post_type_clonable().
	 */
	function test_is_post_type_clonable() {
		// Check that post returns true.
		$this->assertTrue( Post_Cloner\is_post_type_clonable( 'post' ) );

		// Check that various other post types return false.
		$this->assertFalse( Post_Cloner\is_post_type_clonable( 'attachment' ) );
		$this->assertFalse( Post_Cloner\is_post_type_clonable( 'video' ) );
	}

	/**
	 * Test is_post_status_clonable().
	 */
	function test_is_post_status_clonable() {
		// Check that draft, publish, and pending return true.
		$this->assertTrue( Post_Cloner\is_post_status_clonable( 'draft' ) );
		$this->assertTrue( Post_Cloner\is_post_status_clonable( 'publish' ) );
		$this->assertTrue( Post_Cloner\is_post_status_clonable( 'pending' ) );

		// Check that trashed returns false.
		$this->assertFalse( Post_Cloner\is_post_status_clonable( 'trashed' ) );

	}

	/**
	 * Test can_user_clone().
	 */
	function test_can_user_clone() {
		// User is logged out, should return false.
		$this->assertFalse( Post_Cloner\can_user_clone() );

		// Set an admin user to logged in.
		wp_set_current_user( self::$admin_id );

		// User is logged in, but not in the admin screen - should return false.
		$this->assertFalse( Post_Cloner\can_user_clone() );

		// Switch to an admin screen.
		set_current_screen( 'edit.php' );

		// User is logged in and in admin section - everything a-OK.
		$this->assertTrue( Post_Cloner\can_user_clone() );

		// Editor should also have access to cloning.
		wp_set_current_user( self::$editor_id );
		$this->assertTrue( Post_Cloner\can_user_clone() );

		// User in the admin area but has incorrect privileges.
		wp_set_current_user( self::$subscriber_id );
		$this->assertFalse( Post_Cloner\can_user_clone() );
	}

	/**
	 * Test clean_keys().
	 */
	function test_clean_keys_passed_key() {
		$array = [
			'this'    => '0',
			'key'     => '1',
			'should'  => '2',
			'be'      => '3',
			'removed' => '4',
		];

		$keys_to_clean = [
			'this',
			'should',
			'be',
		];

		$bad_set_of_keys = [
			'keys',
			'do',
			'not',
			'exist',
		];

		// Check that the keys we want gone have been removed.
		$this->assertEquals(
			[
				'key'     => '1',
				'removed' => '4',
			],
			Post_Cloner\clean_keys( $array, $keys_to_clean )
		);

		// Check that we get the full array back if we pass in no keys to remove.
		$this->assertEquals( $array, Post_Cloner\clean_keys( $array, [] ) );

		// Check that we get the full array back if we pass a set of keys that don't exist.
		$this->assertEquals( $array, Post_Cloner\clean_keys( $array, $bad_set_of_keys ) );
	}

	/**
	 * Test clean_keys() Regex cleaning.
	 */
	function test_clean_keys_by_regex() {
		$array = [
			'this'                       => '0',
			'key'                        => '1',
			'should'                     => '2',
			'be'                         => '3',
			'removed'                    => '4',
			'apple_news_api_id'          => '5',
			'apple_news_api_created_at'  => '6',
			'apple_news_api_modified_at' => '7',
			'apple_news_api_share_url'   => '8',
			'apple_news_api_revision'    => '9',
			'apple_news_sections'        => '10',
		];

		$regex = [
			'/^apple_news/',
		];

		$clean_array = [
			'this'                       => '0',
			'key'                        => '1',
			'should'                     => '2',
			'be'                         => '3',
			'removed'                    => '4',
		];

		// Check that the keys we want gone have been removed.
		$this->assertEquals( $clean_array, Post_Cloner\clean_keys( $array, [], $regex ) );
	}
}
