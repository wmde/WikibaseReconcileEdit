<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationItem;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use ValueParsers\ParserOptions;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\ValueParserFactory;

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

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup,
		ValueParserFactory $valueParserFactory,
		ReconciliationService $reconciliationService
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->valueParserFactory = $valueParserFactory;
		$this->reconciliationService = $reconciliationService;
	}

	/**
	 * @param array $inputEntity
	 * @param PropertyId $reconcileUrlProperty
	 * @return array( Item, ReconciliationItem[] )
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
				if (
					!array_key_exists( 'property', $statementDetails ) ||
					!array_key_exists( 'value', $statementDetails )
				) {
					die( 'statements must have property and value keys' );
				}
				$propertyId = new PropertyId( $statementDetails['property'] );
				[ $dataValue, $reconciliationItems ] = $this->getDataValue(
					$propertyId,
					$statementDetails['value'],
					$reconcileUrlProperty
				);
				$item->getStatements()->addNewStatement(
					new PropertyValueSnak( $propertyId, $dataValue )
				);
				foreach ( $reconciliationItems as $otherItem ) {
					/** @var ReconciliationItem $otherItem */
					$otherItems[$otherItem->getItem()->getId()->getSerialization()] = $otherItem;
				}
			}
		}

		return [ $item, array_values( $otherItems ) ];
	}

	/**
	 * @param PropertyId $id
	 * @param string $value
	 * @return array( DataValue, ReconciliationItem[] )
	 */
	private function getDataValue(
		PropertyId $id,
		string $value,
		PropertyId $reconcileUrlProperty
	) : array {
		$name = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $id );
		if ( $name === 'wikibase-item' && wfParseUrl( $value ) !== false ) {
			$reconciliationItem = $this->reconciliationService
				->getOrCreateItemByStatementUrl( $reconcileUrlProperty, $value );

			return [ new EntityIdValue( $reconciliationItem->getItem()->getId() ), [ $reconciliationItem ] ];
		}
		// TODO add specific options?
		$parser = $this->valueParserFactory->newParser( $name, new ParserOptions );
		$parseResult = $parser->parse( $value );
		return [ $parseResult, [] ];
	}

}
