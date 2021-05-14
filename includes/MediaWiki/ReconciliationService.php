<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use DataValues\StringValue;
use TitleFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
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

	/** @var EntityRevisionLookup */
	private $entityRevisionLookup;

	/** @var IdGenerator */
	private $idGenerator;

	/** @var ExternalLinks */
	private $externalLinks;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var ReconciliationItem[][] */
	private $reconciliationItems = [];

	public function __construct(
		EntityIdLookup $entityIdLookup,
		EntityRevisionLookup $entityRevisionLookup,
		IdGenerator $idGenerator,
		ExternalLinks $externalLinks,
		TitleFactory $titleFactory
	) {
		$this->entityIdLookup = $entityIdLookup;
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
	public function getOrCreateItemByStatementUrl(
		PropertyID $reconcileUrlProperty,
		string $reconciliationUrl
	) : ReconciliationItem {
		// return the same item if we already reconciled it
		$propertyId = $reconcileUrlProperty->getSerialization();
		if ( array_key_exists( $propertyId, $this->reconciliationItems )
			 && array_key_exists( $reconciliationUrl, $this->reconciliationItems[$propertyId] ) ) {
			return $this->reconciliationItems[$propertyId][$reconciliationUrl];
		}

		// Find Items that use the URL
		$externalLinkIds = $this->externalLinks->pageIdsContainingUrl( $reconciliationUrl );
		$itemIdsThatReferenceTheUrl = $this->getItemIdsFromPageIds( $externalLinkIds );

		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = [];
		foreach ( $itemIdsThatReferenceTheUrl as $itemId ) {
			$entityRevision = $this->entityRevisionLookup->getEntityRevision( $itemId );
			/** @var Item $item */
			$item = $entityRevision->getEntity();

			foreach ( $item->getStatements()->getByPropertyId( $reconcileUrlProperty )->toArray() as $statement ) {
				if ( !$statement->getMainSnak() instanceof PropertyValueSnak ) {
					continue;
				}
				/** @var PropertyValueSnak $mainSnak */
				$mainSnak = $statement->getMainSnak();

				$urlOfStatement = $mainSnak->getDataValue()->getValue();
				if ( $urlOfStatement === $reconciliationUrl ) {
					$itemsThatReferenceTheUrlInCorrectStatement[] = new ReconciliationItem(
						$item,
						$entityRevision->getRevisionId()
					);
				}
			}
		}

		// If we have more than one item matches, something is wrong and we can't edit
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) > 1 ) {
			die( 'Matched multiple Items during reconciliation :(' );
		}

		$reconciliationItem = null;

		// Get our base
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) === 1 ) {
			$reconciliationItem = $itemsThatReferenceTheUrlInCorrectStatement[0];
		} else {
			$base = new Item();
			// XXX: this is a bit evil, but needed to work around the fact we want to mint statement guids
			$base->setId( ItemId::newFromNumber( $this->idGenerator->getNewId( 'wikibase-item' ) ) );
			$reconciliationItem = new ReconciliationItem( $base, false );

			// if this is a new item, we need to put the reconciliation statement in it
			$reconciliationItem->getItem()->getStatements()->addNewStatement(
				new PropertyValueSnak( $reconcileUrlProperty, new StringValue( $reconciliationUrl ) )
			);
		}

		$this->reconciliationItems[$propertyId][$reconciliationUrl] = $reconciliationItem;
		return $reconciliationItem;
	}

}
