<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use Wikibase\DataModel\Entity\Item;

/**
 * Class that contains the item and revision found by the reconciliation service
 * @see ReconciliationService
 * @license GPL-2.0-or-later
 */
class ReconciliationItem {

	/** @var Item */
	private $item;

	/** @var int|bool */
	private $revision;

	public function __construct( Item $item, $revision ) {
		$this->item = $item;
		$this->revision = $revision;
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item {
		return $this->item;
	}

	/**
	 * @return bool|int
	 */
	public function getRevision() {
		return $this->revision;
	}

}
