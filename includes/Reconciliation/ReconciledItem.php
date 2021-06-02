<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Store\EntityRevision;

/**
 * A reconciled item, returned by {@link ItemReconciler}.
 *
 * This class contains a modified item
 * and the {@link ReconciliationServiceItem} it is based on.
 *
 * @license GPL-2.0-or-later
 */
class ReconciledItem {

	/** @var Item */
	private $item;

	/** @var ReconciliationServiceItem */
	private $reconciliationServiceItem;

	public function __construct(
		Item $item,
		ReconciliationServiceItem $reconciliationServiceItem
	) {
		$this->item = $item;
		$this->reconciliationServiceItem = $reconciliationServiceItem;
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
		return $this->reconciliationServiceItem->getRevision();
	}

	public function isNew(): bool {
		return $this->getBaseRevisionId() === false;
	}

	/**
	 * Record that the reconciled item was saved as the given revision.
	 *
	 * After this call, the {@link ReconciledItem} should be discarded:
	 * it must no longer be used, since it no longer contains unsaved modifications.
	 */
	public function finish( EntityRevision $entityRevision ): void {
		$this->reconciliationServiceItem->updateFromEntityRevision( $entityRevision );
		// clear the item to ensure it’s not reused
		// (if anyone tries, they’ll get a TypeError)
		$this->item = null;
		$this->reconciliationServiceItem = null;
	}

}
