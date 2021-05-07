<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Wikibase;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use MediaWiki\Rest\LocalizedHttpException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Message\MessageValue;

class ReconciliationService {

	public const VERSION_KEY = "wikibasereconcileedit-version";

	/**
	 * @var \Wikibase\Lib\Store\EntityIdLookup|\Wikibase\Repo\Content\EntityContentFactory
	 */
	private $entityIdLookup;

	/**
	 * @var ExternalLinks
	 */
	private $externalLinks;

	/** @var EntityLookup */
	private $entityLookup;

	/**
	 * @var \Wikibase\Lib\Store\EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	private $newIdGenerator;

	public function __construct(
		ExternalLinks $externalLinks,
		$entityIdLookup,
		$entityLookup,
		$propertyDataTypeLookup,
		$entityRevisionLookup,
		$newIdGenerator
	) {
		$this->externalLinks = $externalLinks;
		$this->entityIdLookup = $entityIdLookup;
		$this->entityLookup = $entityLookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->newIdGenerator = $newIdGenerator;
	}

	public function reconcile( EditRequest $request ): array {
		$inputReconcile = $request->reconcile();
		$supportedReconciliationVersions = [ '0.0.1' ];

		if (
			!array_key_exists( self::VERSION_KEY, $inputReconcile ) ||
			!in_array( $inputReconcile[self::VERSION_KEY], $supportedReconciliationVersions )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version' )
					->textListParams( $supportedReconciliationVersions )
					->numParams( count( $supportedReconciliationVersions ) ),
				400
			);
		}
		if (
			!array_key_exists( 'urlReconcile', $inputReconcile ) ||
			!preg_match( PropertyId::PATTERN, $inputReconcile['urlReconcile'] )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid' )
					->textParams( $inputReconcile[self::VERSION_KEY], 'urlReconcile' ),
				400
			);
		}
		$reconcileUrlProperty = new PropertyId( $inputReconcile['urlReconcile'] );
		// For now this property must be of URL type
		if ( $this->propertyDataTypeLookup->getDataTypeIdForProperty( $reconcileUrlProperty ) !== 'url' ) {
			die( 'urlReconcile property must be of type url' );
		}

		// Get Item from input
		if ( !array_key_exists( self::VERSION_KEY, $request->entity() ) ) {
			die( 'entity input version must be specified in key ' . self::VERSION_KEY );
		}
		$inputEntityVersion = $request->entity()[self::VERSION_KEY];
		if ( $inputEntityVersion === '0.0.1/full' ) {
			$inputEntity = ( new FullWikibaseItemInput )->getItem( $request );
		} elseif ( $inputEntityVersion === '0.0.1/minimal' ) {
			$inputEntity = ( new MinimalItemInput )->getItem( $request );
		} else {
			die( 'unknown entity input version' );
		}

		// Validate Entity
		// Don't support references, qualifiers
		foreach ( $inputEntity->getStatements()->toArray() as $statement ) {
			if ( $statement->getQualifiers()->count() !== 0 || $statement->getReferences()->count() !== 0 ) {
				die( 'Qualifiers and References are not currently supported' );
			}
		}
		// Check for our reconciliation value
		$reconciliationStatements = $inputEntity->getStatements()->getByPropertyId( $reconcileUrlProperty );
		if ( $reconciliationStatements->count() !== 1 ) {
			die( 'Entity must have at least one statement for the reconciliation Property' );
		}
		$reconciliationStatement = $reconciliationStatements->toArray()[0];
		if ( !$reconciliationStatement->getMainSnak() instanceof PropertyValueSnak ) {
			die( 'Reconciliation statement must be of type value ' );
		}
		/** @var PropertyValueSnak $reconciliationMainSnak */
		$reconciliationMainSnak = $reconciliationStatement->getMainSnak();
		/** @var StringValue $reconciliationDataValue */
		$reconciliationDataValue = $reconciliationMainSnak->getDataValue();
		$reconciliationUrl = $reconciliationDataValue->getValue();

		// Find Items that use the URL
		$itemIds = $this->getItemIdsFromPageIds(
			$this->externalLinks->pageIdsContainingUrl( $reconciliationUrl )
		);

		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = $this->getItemsWithPropertyAndUrl(
			$itemIds,
			$reconcileUrlProperty,
			$reconciliationUrl
		);

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
			$base->setId( ItemId::newFromNumber( $this->newIdGenerator->getNewId( 'wikibase-item' ) ) );
		}

		// And make the edit
		return [ $baseRevId, ( new SimplePutStrategy() )->apply( $base, $inputEntity ) ];
	}

	/**
	 * @param int[] $pageIds
	 * @return ItemId[]
	 */
	private function getItemIdsFromPageIds( array $pageIds ) : array {
		$itemIds = [];
		foreach ( $pageIds as $pageId ) {
			$entityId = $this->entityIdLookup->getEntityIdForTitle( Title::newFromID( $pageId ) );
			if ( $entityId && $entityId instanceof ItemId ) {
				$itemIds[] = $entityId;
			}
		}
		return $itemIds;
	}

	/**
	 * @param array $itemIds
	 * @param PropertyId $reconcileUrlProperty
	 * @param string $reconciliationUrl
	 * @return Item[]
	 */
	private function getItemsWithPropertyAndUrl(
		array $itemIds,
		PropertyId $reconcileUrlProperty,
		string $reconciliationUrl
	) : array {
		$items = [];
		foreach ( $itemIds as $itemId ) {
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
					$items[] = $item;
				}
			}
		}

		return $items;
	}
}
