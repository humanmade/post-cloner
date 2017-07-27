<?php
/**
 * Bootstrap and load test files.
 *
 * @package TheSun_Redirector_Plugin
 * @author Human Made Limited
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin files.
 */
function _manually_load_plugin() {
	require_once __DIR__ . '/../inc/class-cloner.php';
	require_once __DIR__ . '/../inc/class-plugin.php';
	require_once __DIR__ . '/../inc/class-admin.php';
	require_once __DIR__ . '/../inc/utilities.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

/**
 * Class TheSunPostCloner_TestCase
 */
class PostCloner_TestCase extends WP_UnitTestCase {
	/**
	 * Call protected/private method of a class.
	 *
	 * @param object &$object Instantiated object that we will run method on.
	 * @param string $methodName Method name to call.
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invokeMethod( &$object, $methodName, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $methodName );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	public function invokeStaticMethod( &$object, $methodName, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $methodName );

		return $method->invokeArgs( $object, $parameters );
	}
}

