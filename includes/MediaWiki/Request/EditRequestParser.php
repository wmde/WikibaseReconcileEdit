<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestInterface;
use Wikibase\DataModel\Entity\PropertyId;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
class EditRequestParser {

	public const VERSION_KEY = "wikibasereconcileedit-version";

	private const SUPPORTED_RECONCILE_VERSIONS = [ '0.0.1' ];

	public function parseRequestInterface( RequestInterface $request ): EditRequest {
		$reconcile = json_decode(
			$request->getPostParams()['reconcile'],
			true
		);
		if ( !is_array( $reconcile ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-json' ),
				400
			);
		}

		if (
			!array_key_exists( self::VERSION_KEY, $reconcile ) ||
			!in_array( $reconcile[self::VERSION_KEY], self::SUPPORTED_RECONCILE_VERSIONS )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version' )
					->textListParams( self::SUPPORTED_RECONCILE_VERSIONS )
					->numParams( count( self::SUPPORTED_RECONCILE_VERSIONS ) ),
				400
			);
		}

		if (
			!array_key_exists( 'urlReconcile', $reconcile ) ||
			!preg_match( PropertyId::PATTERN, $reconcile['urlReconcile'] )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid' )
					->textParams( $reconcile[self::VERSION_KEY], 'urlReconcile' ),
				400
			);
		}
		$reconcilePropertyId = new PropertyId( $reconcile['urlReconcile'] );

		$entity = json_decode(
			$request->getPostParams()['entity'],
			true
		) ?: null;

		return new EditRequest(
			$reconcilePropertyId,
			$entity
		);
	}

}
