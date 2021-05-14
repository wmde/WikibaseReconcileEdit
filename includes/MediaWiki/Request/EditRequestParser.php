<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

/**
 * @license GPL-2.0-or-later
 */
class EditRequestParser {

	public function parseRequestInterface( RequestInterface $request ): EditRequest {
		return new EditRequest(
			json_decode(
				$request->getPostParams()['reconcile'],
				true
			) ?: null,
			json_decode(
				$request->getPostParams()['entity'],
				true
			) ?: null
		);
	}

}
