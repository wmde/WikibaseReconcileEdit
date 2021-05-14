<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestInterface;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
class EditRequestParser {

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

		$entity = json_decode(
			$request->getPostParams()['entity'],
			true
		) ?: null;

		return new EditRequest(
			$reconcile,
			$entity
		);
	}

}
