<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use Wikibase\DataModel\Entity\Item;

/**
 * A reconciled item, returned by {@link ItemReconciler}.
 *
 * This class contains a modified item and the base revision ID
 * that should be used when saving it.
 * It should not be confused with {@link ReconciliationServiceItem},
 * which contains a not-yet-reconciled item with its revision ID.
 *
 * @license GPL-2.0-or-later
 */
class ReconciledItem {

	/** @var Item */
	private $item;

	/** @var int|false */
	private $baseRevisionId;

	/**
	 * @param Item $item
	 * @param int|false $baseRevisionId
	 */
	public function __construct(
		Item $item,
		$baseRevisionId
	) {
		$this->item = $item;
		$this->baseRevisionId = $baseRevisionId;
	}

	/**
	 * Get the item data, with any modifications specified in the input item.
	 */
	public function getItem(): Item {
		return $this->item;
	}

	/**
	 * Get the base revision ID for the edit.
	 * @return false|int
	 */
	public function getBaseRevisionId() {
		return $this->baseRevisionId;
	}

	public function isNew(): bool {
		return $this->baseRevisionId === false;
	}

}
