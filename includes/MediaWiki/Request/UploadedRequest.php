<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

class UploadedRequest implements Request {

	public function input( RequestInterface $request ) : string {
		return file_get_contents( $this->getRequest()->getUploadedFiles()['input']['file'] );
	}

	public function schema( RequestInterface $request ) : string {
		return file_get_contents( $this->getRequest()->getUploadedFiles()['schema']['file'] );
	}

}
