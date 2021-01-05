<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

class MockEditDiskRequest implements EditRequest {

	public function entity() : ?array {
		return json_decode(
			file_get_contents( __DIR__ . '/../../../data/edit/entity.json' ),
			true
		) ?: null;
	}

	public function reconcile() : ?array {
		return json_decode(
			file_get_contents( __DIR__ . '/../../../data/edit/reconcile.json' ),
			true
		) ?: null;
	}

}
