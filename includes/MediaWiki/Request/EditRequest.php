<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationItem;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;

/**
 * @license GPL-2.0-or-later
 */
class EditRequest {

	/** @var PropertyId */
	private $reconcilePropertyId;

	/** @var Item */
	private $entity;

	/** @var ReconciliationItem[] */
	private $otherItems;

	/**
	 * @param PropertyId $reconcilePropertyId Must have the datatype "url".
	 * @param Item $entity
	 * @param ReconciliationItem[] $otherItems Other items that need to be created,
	 * since their URLs were referenced in $entity but they did not exist yet.
	 */
	public function __construct(
		PropertyId $reconcilePropertyId,
		Item $entity,
		array $otherItems = []
	) {
		$this->reconcilePropertyId = $reconcilePropertyId;
		$this->entity = $entity;
		$this->otherItems = $otherItems;
	}

	/**
	 * The property ID that should be used for reconciliation.
	 *
	 * The property is guaranteed to have the datatype "url".
	 */
	public function reconcilePropertyId(): PropertyId {
		return $this->reconcilePropertyId;
	}

	public function entity(): Item {
		return $this->entity;
	}

	/**
	 * @return ReconciliationItem[]
	 */
	public function otherItems(): array {
		return $this->otherItems;
	}

}
