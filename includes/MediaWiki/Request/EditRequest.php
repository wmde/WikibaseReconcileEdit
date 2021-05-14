<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @license GPL-2.0-or-later
 */
class EditRequest {

	/** @var PropertyId */
	private $reconcilePropertyId;

	/** @var array|null */
	private $entity;

	/**
	 * @param PropertyId $reconcilePropertyId Must have the datatype "url".
	 * @param array|null $entity
	 */
	public function __construct( PropertyId $reconcilePropertyId, ?array $entity ) {
		$this->reconcilePropertyId = $reconcilePropertyId;
		$this->entity = $entity;
	}

	/**
	 * The property ID that should be used for reconciliation.
	 *
	 * The property is guaranteed to have the datatype "url".
	 */
	public function reconcilePropertyId(): PropertyId {
		return $this->reconcilePropertyId;
	}

	public function entity() : ?array {
		return $this->entity;
	}

}
