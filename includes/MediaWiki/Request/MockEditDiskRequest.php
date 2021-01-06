<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

/**
 * EditRequest sourced from test data stored in the data directory
 */
class MockEditDiskRequest implements EditRequest {

	/**
	 * @var string
	 */
	private $entityFile;

	/**
	 * @var string
	 */
	private $reconcileFile;

	/**
	 * @param string|null $entityFile
	 * @param string|null $reconcileFile
	 */
	public function __construct(
		?string $entityFile = null,
		?string $reconcileFile = null
		) {
			$this->entityFile = $entityFile;
			$this->reconcileFile = $reconcileFile;
			if ( !$this->entityFile ) {
				$this->entityFile = __DIR__ . '/../../../data/edit/entity-normal.json';
			}
			if ( !$this->reconcileFile ) {
				$this->reconcileFile = __DIR__ . '/../../../data/edit/reconcile.json';

			}
	}

	public function entity() : ?array {
		return json_decode(
			file_get_contents( $this->entityFile ),
			true
		) ?: null;
	}

	public function reconcile() : ?array {
		return json_decode(
			file_get_contents( $this->reconcileFile ),
			true
		) ?: null;
	}

}
