<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

class MockInputDiskRequest implements InputRequest {

	public function input( RequestInterface $request ) : string {
		return file_get_contents( __DIR__ . '/../../../data/csv/1.csv' );
	}

	public function schema( RequestInterface $request ) : string {
		return file_get_contents( __DIR__ . '/../../../data/csv/schema.json' );
	}

}
