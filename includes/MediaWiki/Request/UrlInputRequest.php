<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;
use RuntimeException;

class UrlRequest implements InputRequest {

	public function input( RequestInterface $request ) : string {
		// TODO implement getting a URL that was submitted by the user
		throw new RuntimeException( 'Not yet implemented!' );
	}

	public function schema( RequestInterface $request ) : string {
		// TODO implement getting a URL that was submitted by the user
		throw new RuntimeException( 'Not yet implemented!' );
	}

}
