<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

/**
 * EditRequest sourced from test data stored in the data directory
 */
class MockEditDiskRequest implements EditRequest {

	public function entity() : ?array {
		return json_decode(
			file_get_contents( __DIR__ . '/../../../data/edit/entity-normal.json' ),
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
