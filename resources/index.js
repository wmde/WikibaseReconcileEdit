/**
 * Module for SpecialWikibaseReconcileEdit
 *
 * @license GPL-2.0-or-later
 *
 */
( function () {
	'use strict';
	var $entity = $( "[name='entity']" );
	var $reconcile = $( "[name='reconcile']" );
	var $token = $( "[name='token']" );

	$( '#mw-content-text' ).prepend( "<pre id='response'></pre>" );
	var $response = $( '#response' );
	var $submit = $( "#mw-content-text form [type='submit']" );
	var route = mw.config.get( 'wgScriptPath' ) + '/rest.php/wikibase-reconcile-edit/v0/edit';

	$( function () {
		$submit.on(
			'click', function ( event ) {
				event.preventDefault();
				var data = {
					entity: JSON.parse( $entity.val() ),
					reconcile: JSON.parse( $reconcile.val() ),
					token: $token.val()
				};
				$response.text( 'loading ...' );
				$.ajax( {
					type: 'POST',
					url: route,
					data: JSON.stringify( data ),
					success: function ( res ) {
						$response.text( JSON.stringify( res, null, 2 ) );
					},
					error: function ( request, status, error ) {
						var json;
						try {
							json = JSON.parse( request.responseText );
						} catch ( error ) {
							json = {};
						}
						if ( status === 'error' && !json.httpCode ) {
							$response.text( 'Internal server error. See the console for more detail.' );
							/* eslint-disable-next-line no-console */
							console.error( request.responseText );
						} else {
							$response.text( JSON.stringify( json, null, 2 ) );
						}
					},
					contentType: 'application/json',
					dataType: 'json'
				} );
			}
		);
	} );
}() );
