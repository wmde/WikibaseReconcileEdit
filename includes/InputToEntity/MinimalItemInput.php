<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use ValueParsers\ParserOptions;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\ValueParserFactory;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
class MinimalItemInput {

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $propertyDataTypeLookup;

	/**
	 * @var ValueParserFactory
	 */
	private $valueParserFactory;

	/**
	 * @var ReconciliationService
	 */
	private $reconciliationService;

	/**
	 * @var PropertyLabelResolver
	 */
	private $propertyLabelResolver;

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup,
		ValueParserFactory $valueParserFactory,
		ReconciliationService $reconciliationService,
		PropertyLabelResolver $propertyLabelResolver
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->valueParserFactory = $valueParserFactory;
		$this->reconciliationService = $reconciliationService;
		$this->propertyLabelResolver = $propertyLabelResolver;
	}

	/**
	 * @param array $inputEntity
	 * @param PropertyId $reconcileUrlProperty
	 * @return array( Item, ReconciliationServiceItem[] )
	 */
	public function getItem( array $inputEntity, PropertyId $reconcileUrlProperty ) : array {
		$item = new Item();
		$otherItems = [];

		if ( array_key_exists( 'labels', $inputEntity ) ) {
			foreach ( $inputEntity['labels'] as $lang => $label ) {
				$item->getLabels()->setTextForLanguage( $lang, $label );
			}
		}
		if ( array_key_exists( 'descriptions', $inputEntity ) ) {
			foreach ( $inputEntity['descriptions'] as $lang => $label ) {
				$item->getDescriptions()->setTextForLanguage( $lang, $label );
			}
		}
		if ( array_key_exists( 'aliases', $inputEntity ) ) {
			foreach ( $inputEntity['aliases'] as $lang => $aliases ) {
				if ( $item->getAliasGroups()->hasGroupForLanguage( $lang ) ) {
					$aliases = array_unique( array_merge( $aliases, $item->getAliasGroups()->getByLanguage( $lang ) ) );
				}
				$item->getAliasGroups()->setAliasesForLanguage( $lang, $aliases );
			}
		}

		if ( array_key_exists( 'sitelinks', $inputEntity ) ) {
			foreach ( $inputEntity['sitelinks'] as $siteId => $pageName ) {
				$item->getSiteLinkList()->setSiteLink( new SiteLink( $siteId, $pageName ) );
			}
		}

		if ( array_key_exists( 'statements', $inputEntity ) ) {
			foreach ( $inputEntity['statements'] as $statementDetails ) {
				if ( !array_key_exists( 'property', $statementDetails ) ) {
					throw new ReconciliationException(
						MessageValue::new(
							'wikibasereconcileedit-statements-missing-keys' )
							->textParams( 'property' )
					);
				} elseif ( !array_key_exists( 'value', $statementDetails ) ) {
					throw new ReconciliationException(
						MessageValue::new(
							'wikibasereconcileedit-statements-missing-keys' )
							->textParams( 'value' )
					);
				}
				$propertyId = $this->getPropertyId( $statementDetails['property'] );

				try {
					[ $dataValue, $reconciliationServiceItems ] = $this->getDataValue(
						$propertyId,
						$statementDetails['value'],
						$reconcileUrlProperty
					);
				} catch ( PropertyDataTypeLookupException $exception ) {
					throw new ReconciliationException(
						MessageValue::new(
							'wikibasereconcileedit-property-datatype-lookup-error' )
							->textParams( $statementDetails['property'] )
					);
				}

				$item->getStatements()->addNewStatement(
					new PropertyValueSnak( $propertyId, $dataValue )
				);
				foreach ( $reconciliationServiceItems as $otherItem ) {
					/** @var ReconciliationServiceItem $otherItem */
					$otherItems[$otherItem->getItem()->getId()->getSerialization()] = $otherItem;
				}
			}
		}

		return [ $item, array_values( $otherItems ) ];
	}

	/**
	 * @param string $propertyId
	 * @return PropertyId
	 */
	public function getPropertyId( string $propertyId ): PropertyId {
		try {
			return new PropertyId( $propertyId );
		} catch ( \InvalidArgumentException $exception ) {
			return $this->getPropertyIdByLabel( $propertyId );
		}
	}

	/**
	 * @param string $label
	 * @return PropertyId
	 * @throws ReconciliationException
	 */
	public function getPropertyIdByLabel( string $label ): PropertyId {
		$entityIds = $this->propertyLabelResolver->getPropertyIdsForLabels( [ $label ] );

		if ( empty( $entityIds ) ) {
			throw new ReconciliationException(
				MessageValue::new( 'wikibasereconcileedit-property-not-found' )
				->textParams( $label ) );
		}

		/** @var PropertyId $propertyId */
		$propertyId = $entityIds[ $label ];
		return $propertyId;
	}

	/**
	 * @param PropertyId $id
	 * @param string $value
	 * @return array( DataValue, ReconciliationServiceItem[] )
	 */
	private function getDataValue(
		PropertyId $id,
		string $value,
		PropertyId $reconcileUrlProperty
	) : array {
		$name = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $id );
		if ( $name === 'wikibase-item' && wfParseUrl( $value ) !== false ) {
			$item = $this->reconciliationService
				->getOrCreateItemByStatementUrl( $reconcileUrlProperty, $value );

			return [ new EntityIdValue( $item->getItem()->getId() ), [ $item ] ];
		}
		// TODO add specific options?
		$parser = $this->valueParserFactory->newParser( $name, new ParserOptions );
		$parseResult = $parser->parse( $value );
		return [ $parseResult, [] ];
	}

}
