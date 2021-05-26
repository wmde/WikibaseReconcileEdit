<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use TitleFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\IdGenerator;
use Wikimedia\Message\MessageValue;

/**
 * Service for reconciling items based on url statements
 * @license GPL-2.0-or-later
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

	/**
	 * Per-request cache for previously returned results
	 *
	 * Example:
	 *  [ 'P1' => [ 'http://reconciled-url' => ReconciliationServiceItem ] ]
	 *
	 * @var ReconciliationServiceItem[][]
	 */
	private $items = [];

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
	 * @return ReconciliationServiceItem
	 * @throws ReconciliationException
	 * @throws \Wikibase\Lib\Store\StorageException
	 */
	public function getOrCreateItemByStatementUrl(
		PropertyID $reconcileUrlProperty,
		string $reconciliationUrl
	) : ReconciliationServiceItem {
		// return the same item if we already reconciled it
		$propertyId = $reconcileUrlProperty->getSerialization();
		if ( array_key_exists( $propertyId, $this->items )
			 && array_key_exists( $reconciliationUrl, $this->items[$propertyId] ) ) {
			return $this->items[$propertyId][$reconciliationUrl];
		}

		// Find Items that use the URL
		$externalLinkIds = $this->externalLinks->pageIdsContainingUrl( $reconciliationUrl );
		$itemIdsThatReferenceTheUrl = $this->getItemIdsFromPageIds( $externalLinkIds );

		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = [];
		$idsOfItemsThatReferenceTheUrlInCorrectStatement = [];
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
					$itemsThatReferenceTheUrlInCorrectStatement[] = new ReconciliationServiceItem(
						$item,
						$entityRevision->getRevisionId()
					);
					array_push( $idsOfItemsThatReferenceTheUrlInCorrectStatement, $item->getId()->serialize() );
				}
			}
		}

		$numberItemsThatReferenceTheUrlInCorrectStatement = count( $itemsThatReferenceTheUrlInCorrectStatement );
		// If we have more than one item matches, something is wrong and we can't edit
		if ( $numberItemsThatReferenceTheUrlInCorrectStatement > 1 ) {
			throw new ReconciliationException(
				MessageValue::new( 'wikibasereconcileedit-reconciliationservice-matched-multiple-items' )
					->textListParams( $idsOfItemsThatReferenceTheUrlInCorrectStatement )
					->numParams( $numberItemsThatReferenceTheUrlInCorrectStatement ),
			);
		}

		$reconciliationServiceItem = null;

		// Get our base
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) === 1 ) {
			$reconciliationServiceItem = $itemsThatReferenceTheUrlInCorrectStatement[0];
		} else {
			$base = new Item();
			// XXX: this is a bit evil, but needed to work around the fact we want to mint statement guids
			$base->setId( ItemId::newFromNumber( $this->idGenerator->getNewId( 'wikibase-item' ) ) );
			$reconciliationServiceItem = new ReconciliationServiceItem( $base, false );

			// if this is a new item, we need to put the reconciliation statement in it
			$reconciliationServiceItem->getItem()->getStatements()->addNewStatement(
				new PropertyValueSnak( $reconcileUrlProperty, new StringValue( $reconciliationUrl ) )
			);
		}

		$this->items[$propertyId][$reconciliationUrl] = $reconciliationServiceItem;
		return $reconciliationServiceItem;
	}

}
