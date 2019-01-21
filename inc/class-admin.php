<?php
/**
 * Handles the admin UI and redirection of cloning a post.
 *
 * @package HM Post Cloner.
 */

namespace Post_Cloner;

use WP_Post;

/**
 * Class Admin
 */
final class Admin {
	/**
	 * Access to plugin definitions.
	 *
	 * @var Plugin
	 * @access private
	 */
	private $plugin;

	/**
	 * Easy way to access all of our defined paths & info.
	 *
	 * @var object
	 * @access private
	 */
	private $definitions;

	/**
	 * Run hooks that the class relies on.
	 */
	public function hooks() {
		$this->definitions = $this->plugin->get_definitions();

		// Set to 25 to run after any other boxes have been added.
		add_action( 'post_submitbox_misc_actions', [ $this, 'custom_button' ], 25 );
		add_filter( 'post_row_actions', [ $this, 'list_row_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'list_row_action' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'clone_post' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'load_styles' ], 10, 1 );
		add_action( 'admin_notices', [ $this, 'clone_failed_message' ] );
		add_filter( 'display_post_states', [ $this, 'post_state' ], 10, 2 );

		// Load up editor scripts.
		add_action( 'enqueue_block_editor_assets', [ $this, 'load_gutenberg_resources' ] );
	}

	/**
	 * Set a reference to the main plugin instance.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 * @return Admin
	 */
	public function set_plugin( $plugin ) {
		$this->plugin = $plugin;
		return $this;
	}

	/**
	 * Add our CSS stylesheet to the post edit screen to prettify our button.
	 *
	 * @param string $page Page name for the current sceen.
	 */
	public function load_styles( $page ) {
		// Only load styles on the edit screen.
		if ( 'post.php' !== $page ) {
			return;
		}

		wp_enqueue_style( 'post_cloner_styles', $this->definitions->assets_url . '/clone-posts.css', [], $this->definitions->version );
	}

	/**
	 * Load up Gutenberg resources for the post edit screen.
	 */
	public function load_gutenberg_resources() {
		if ( ! isset( $_GET['post'] ) ) { // WPCS: CSRF ok.
			return;
		}

		$post_id = absint( $_GET['post'] );

		// No need for our resources if this post type isn't clonable at all.
		if ( ! is_post_type_clonable( get_post_type( $post_id ) ) ) {
			return;
		}

		// If in Gutenberg context, our custom CSS is no longer useful.
		wp_dequeue_style( 'post_cloner_styles' );

		// Enqueue our script.
		wp_enqueue_script(
			'post-cloner-gutenberg',
			$this->definitions->assets_url . '/js/gutenberg.js',
			[ 'wp-blocks', 'wp-element', 'wp-url', 'wp-components', 'wp-editor' ],
			$this->definitions->version
		);

		$post_type = get_post_type_object( get_post( $post_id )->post_type );

		// Load up data about said post.
		wp_localize_script(
			'post-cloner-gutenberg',
			'postCloner',
			[
				'userCanClone'   => can_user_clone(),
				'postIsClonable' => is_post_clonable( $post_id ),
				'cloneLink'      => $this->clone_post_link( $post_id ),
				'postTypelabels' => $post_type->labels,
			]
		);
	}

	/**
	 * Add a new button to the post publish box for cloning a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return null
	 */
	public function custom_button( WP_Post $post ) {
		/**
		 * Check if the post type is whitelisted to be clonable and we're not
		 * trying to clone a trashed/pending review post.
		 */
		if ( ! is_post_clonable( $post ) ) {
			return null;
		}

		// User is not allowed to clone posts - bail.
		if ( ! can_user_clone() ) {
			return null;
		}

		// Assemble the clone button text.
		$post_type = get_post_type_object( $post->post_type );
		$text = sprintf( '%1$s %2$s',
			__( 'Clone', 'post-cloner' ),
			$post_type->labels->singular_name
		);

		?>
		<div class="clone-actions misc-pub-section">
			<a href="<?php echo esc_url( $this->clone_post_link( $post->ID ) ); ?>" id="clone_post" class="button"><?php echo esc_html( $text ); ?></a>
		</div>
		<?php
	}

	/**
	 * Add a custom action link for cloning to the posts list row for a post.
	 *
	 * @param array   $actions Existing actions for the post.
	 * @param WP_Post $post Post object.
	 * @return array  Actions for the post row.
	 */
	public function list_row_action( array $actions, WP_Post $post ) {
		// Check if the post type is whitelisted to be clonable and we're not
		// trying to clone a trashed/pending review post.
		if ( ! is_post_clonable( $post ) ) {
			return $actions;
		}

		// User is not allowed to clone posts - bail.
		if ( ! can_user_clone() ) {
			return $actions;
		}

		// Add our custom action.
		$actions = array_merge( $actions, [
			'clone' => sprintf( '<a href="%1$s">%2$s</a>',
				esc_url( $this->clone_post_link( $post->ID ) ),
				esc_html__( 'Clone', 'post-cloner' )
			),
		] );

		return $actions;
	}

	/**
	 * Clone our post when requested. Only runs in the admin.
	 *
	 * Runs a series of check that we are passed correct information,
	 * have the correct privileges, and have a post ID and then clones
	 * the post and redirects to the new edit page.
	 */
	public function clone_post() {
		$pid   = ( isset( $_GET['clone_post_id'] ) ) ? absint( $_GET['clone_post_id'] ) : '';
		$nonce = ( isset( $_GET['clone_post'] ) ) ? sanitize_text_field( wp_unslash( $_GET['clone_post'] ) ) : '';

		// Missing post ID or nonce - this is not our request.
		if ( empty( $nonce ) || empty( $pid ) ) {
			return null;
		}

		// Nonce exists but cannot be verified - bail.
		if ( ! wp_verify_nonce( $nonce, 'clone_post' ) ) {
			wp_die( esc_html__( 'Your session has expired. Please try again.', 'post-cloner' ) );
		}

		// User is not allowed to clone posts - bail.
		if ( ! can_user_clone() ) {
			wp_die( esc_html__( 'You do not have permission to clone posts.', 'post-cloner' ) );
		}

		// Clone our post.
		$post_id = ( new Cloner() )->clone_post( $pid );

		if ( ! is_wp_error( $post_id ) ) {
			$edit_link = admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' );
			wp_redirect( $edit_link );
			exit;
		} else {
			// Get the old post URL and add a failed attribute.
			$link = admin_url( 'post.php?post=' . absint( $pid ) . '&action=edit&clone_failed=failed' );
			wp_redirect( $link );
			exit;
		}
	}

	/**
	 * Display an error notice if the clone failed.
	 */
	public function clone_failed_message() {
		if ( ! isset( $_GET['clone_failed'] ) || 'failed' !== $_GET['clone_failed'] ) {
			return false;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'This post could not be cloned. Please try again.', 'post-cloner' )
		);
	}

	/**
	 * Add a post state to cloned posts to indiciate that they're cloned.
	 *
	 * @param array   $post_states Existing post states on post.
	 * @param WP_Post $post Post object.
	 * @return array (Potentially) modified post states
	 */
	public function post_state( $post_states, $post = null ) {
		// Fetch current post if none is properly passed in.
		if ( is_null( $post ) ) {
			$post = get_post();
		}

		if ( is_post_cloned( $post->ID ) ) {
			$post_states[] = esc_html__( 'Cloned', 'post-cloner' );
		}

		return $post_states;
	}

	/**
	 * Build a link to clone posts.
	 *
	 * @param int $post_id Post ID.
	 * @return string Link to clone a particular post.
	 */
	private function clone_post_link( $post_id ) {
		return wp_nonce_url( admin_url( 'admin.php?clone_post_id=' . absint( $post_id ) ), 'clone_post', 'clone_post' );
	}
}
