<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Store\EntityRevision;
use Wikimedia\Assert\Assert;

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

	/**
	 * Replace the item and revision ID with those of the given entity revision.
	 *
	 * This should be used after the caller has saved the reconciliation service item.
	 * The entity revision is assumed to be based on the previous {@link #getItem item};
	 * in particular, the caller must ensure that the entity is an Item,
	 * and that it has the same URL statement
	 * (i.e. it is correct for a {@link ReconciliationService} to still return
	 * this {@link ReconciliationServiceItem} instance for the same URL).
	 */
	public function updateFromEntityRevision( EntityRevision $entityRevision ): void {
		$item = $entityRevision->getEntity();
		Assert::parameterType( Item::class, $item, '$entityRevision->getEntity()' );
		$this->item = $item;
		$this->revision = $entityRevision->getRevisionId();
	}

}
