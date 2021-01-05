<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

class UrlInputEditRequest implements EditRequest {

	private $request;

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
