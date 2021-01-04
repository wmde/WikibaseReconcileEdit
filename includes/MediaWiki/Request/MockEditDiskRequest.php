<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

class MockEditDiskRequest {

	public function entity( RequestInterface $request ) : string {
		return file_get_contents( __DIR__ . '/../../../data/edit/entity.json' );
	}

	public function reconcile( RequestInterface $request ) : string {
		return file_get_contents( __DIR__ . '/../../../data/edit/reconcile.json' );
	}

}
