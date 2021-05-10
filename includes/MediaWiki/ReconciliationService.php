<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use TitleFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\IdGenerator;

/**
 * Service for reconciling items based on url statements
 */
class ReconciliationService {

	/** @var EntityIdLookup */
	private $entityIdLookup;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var EntityRevisionLookup */
	private $entityRevisionLookup;

	/** @var IdGenerator */
	private $idGenerator;

	/** @var ExternalLinks */
	private $externalLinks;

	/** @var TitleFactory */
	private $titleFactory;

	public function __construct(
		EntityIdLookup $entityIdLookup,
		EntityLookup $entityLookup,
		EntityRevisionLookup $entityRevisionLookup,
		IdGenerator $idGenerator,
		ExternalLinks $externalLinks,
		TitleFactory $titleFactory
	) {
		$this->entityIdLookup = $entityIdLookup;
		$this->entityLookup = $entityLookup;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->idGenerator = $idGenerator;
		$this->externalLinks = $externalLinks;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @param int[] $pageIds
	 * @return ItemId[]
	 */
	private function getItemIdsFromPageIds( array $pageIds ) : array {
		$titles = $this->titleFactory->newFromIDs( $pageIds );

		if ( empty( $titles ) ) {
			return [];
		}

		$entityIds = $this->entityIdLookup->getEntityIds( $titles );
		$itemIds = [];
		foreach ( $entityIds as $entityId ) {
			if ( $entityId instanceof ItemId ) {
				$itemIds[] = $entityId;
			}
		}
		return $itemIds;
	}

	/**
	 * Finds existing item or returns a new one if no matching item is found.
	 * Lookup is matching on items with statement ($reconcileUrlProperty) set to the $reconciliationUrl
	 *
	 * @param PropertyId $reconcileUrlProperty
	 * @param string $reconciliationUrl
	 * @return ReconciliationItem
	 * @throws \Exception
	 */
	public function getItemByStatementUrl(
		PropertyID $reconcileUrlProperty,
		string $reconciliationUrl
	) : ReconciliationItem {
		// Find Items that use the URL
		$itemIdsThatReferenceTheUrl = $this->getItemIdsFromPageIds(
			$this->externalLinks->pageIdsContainingUrl( $reconciliationUrl )
		);

		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = [];
		foreach ( $itemIdsThatReferenceTheUrl as $itemId ) {
			/** @var Item $item */
			$item = $this->entityLookup->getEntity( $itemId );

			foreach ( $item->getStatements()->getByPropertyId( $reconcileUrlProperty )->toArray() as $statement ) {
				if ( !$statement->getMainSnak() instanceof PropertyValueSnak ) {
					continue;
				}
				/** @var PropertyValueSnak $mainSnak */
				$mainSnak = $statement->getMainSnak();

				$urlOfStatement = $mainSnak->getDataValue()->getValue();
				if ( $urlOfStatement === $reconciliationUrl ) {
					$itemsThatReferenceTheUrlInCorrectStatement[] = $item;
				}
			}
		}

		// If we have more than one item matches, something is wrong and we can't edit
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) > 1 ) {
			die( 'Matched multiple Items during reconciliation :(' );
		}

		// Get our base
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) === 1 ) {
			$base = $itemsThatReferenceTheUrlInCorrectStatement[0];
			// XXX: This bit is so annoying...
			$baseRevId = $this->entityRevisionLookup
				->getLatestRevisionId( $base->getId() )
				->onConcreteRevision( function ( $revId ) {
					return $revId;
				} )
				->onRedirect( function () {
					throw new \RuntimeException();
				} )
				->onNonexistentEntity( function () {
					throw new \RuntimeException();
				} )
				->map();
		} else {
			$base = new Item();
			$baseRevId = false;
			// XXX: this is a bit evil, but needed to work around the fact we want to mint statement guids
			$base->setId( ItemId::newFromNumber( $this->idGenerator->getNewId( 'wikibase-item' ) ) );
		}

		return new ReconciliationItem( $base, $baseRevId );
	}

}
