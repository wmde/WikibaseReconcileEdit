<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use Wikibase\DataModel\Entity\Item;

/**
 * An item found by the {@link ReconciliationService}, together with its revision ID.
 *
 * This class contains an item as it was found in the database,
 * or a newly created item if no existing item was found,
 * in which case the revision ID is false.
 * It should not be confused with {@link ReconciledItem},
 * which includes modifications made to the item based on the input.
 *
 * @license GPL-2.0-or-later
 */
class ReconciliationServiceItem {

	/** @var Item */
	private $item;

	/** @var int|false */
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
	 * The revision from which the item was loaded,
	 * or false if the item is new.
	 *
	 * @return int|false
	 */
	public function getRevision() {
		return $this->revision;
	}

}
