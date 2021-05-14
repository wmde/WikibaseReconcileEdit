<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

/**
 * @license GPL-2.0-or-later
 */
class EditRequest {

	/** @var array|null */
	private $reconcile;

	/** @var array|null */
	private $entity;

	public function __construct( ?array $reconcile, ?array $entity ) {
		$this->reconcile = $reconcile;
		$this->entity = $entity;
	}

	public function reconcile() : ?array {
		return $this->reconcile;
	}

	public function entity() : ?array {
		return $this->entity;
	}

}
