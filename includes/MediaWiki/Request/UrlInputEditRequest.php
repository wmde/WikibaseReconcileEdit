<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

/**
 * EditRequest sourced from POSTed request data
 */
class UrlInputEditRequest implements EditRequest {

	/**
	 * @var RequestInterface
	 */
	private $request;

	/**
	 * @param RequestInterface $request
	 */
	public function __construct( RequestInterface $request ) {
		$this->request = $request;
	}

	public function entity() : ?array {
		return json_decode(
			$this->request->getPostParams()['entity'],
			true
		) ?: null;
	}

	public function reconcile() : ?array {
		return json_decode(
			$this->request->getPostParams()['reconcile'],
			true
		) ?: null;
	}

}
