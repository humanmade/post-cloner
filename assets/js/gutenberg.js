/* global postCloner, wp */
/* eslint no-var: off */

/**
 * WordPress React element creation entry point.
 */
var el = wp.element.createElement;

/**
 * Get the content of the status filler.
 *
 * @returns {Function} createElement instance.
 */
function getContent() {
	return el(
		'a',
		{
			className: 'components-button is-button is-large is-default',
			href: postCloner.cloneLink,
		},
		wp.i18n.__( 'Clone ' + postCloner.postTypelabels.singular_name )
	);
}

/**
 * Display a custom status fill element at the bottom of the G'berg post status box.
 *
 * @returns {Function} createElement instance.
 */
function DisplayPostCloningStatus() {
	// If user cannot clone, or post is not clonable, do nothing.
	if ( ! postCloner.userCanClone || ! postCloner.postIsClonable ) {
		return;
	}

	return el(
		wp.editPost.PluginPostStatusInfo,
		{
			className: 'post-cloning-actions',
		},
		getContent()
	);
}

/**
 * Initialize functionality and register custom plugin.
 */
window.addEventListener( 'load', function () {
	wp.plugins.registerPlugin(
		'post-cloning',
		{
			render: DisplayPostCloningStatus,
		}
	);
} );
