<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use DataValues\DataValue;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use ValueParsers\ParserOptions;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\ValueParserFactory;
use Wikibase\Repo\WikibaseRepo;

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
	 * @param PropertyDataTypeLookup|null $propertyDataTypeLookup
	 * @param ValueParserFactory|null $valueParserFactory
	 */
	public function __construct(
		?PropertyDataTypeLookup $propertyDataTypeLookup = null,
		?ValueParserFactory $valueParserFactory = null
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->valueParserFactory = $valueParserFactory;
		if ( !$this->propertyDataTypeLookup ) {
			$this->propertyDataTypeLookup = WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup();
		}
		if ( !$this->valueParserFactory ) {
			// In modern Wikibase (Dec 2020) this is a static method, but call on the object for backwards compatability
			$this->valueParserFactory = WikibaseRepo::getDefaultInstance()->getValueParserFactory();
		}
	}

	/**
	 * @param EditRequest $request
	 * @return Item
	 */
	public function getItem( EditRequest $request ) : Item {
		$inputEntity = $request->entity();

		$item = new Item();

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
				$dataValue = $this->getDataValue(
					$propertyId,
					$statementDetails['value']
				);
				$item->getStatements()->addNewStatement(
					new PropertyValueSnak( $propertyId, $dataValue )
				);
			}
		}

		return $item;
	}

	/**
	 * @param PropertyId $id
	 * @param string $value
	 * @return DataValue
	 */
	private function getDataValue( PropertyId $id, string $value ) : DataValue {
		// TODO this code is copied mainly from ParseValue api module
		$name = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $id );
		// TODO add specific options?
		$parser = $this->valueParserFactory->newParser( $name, new ParserOptions );
		$parseResult = $parser->parse( $value );
		if ( !$parseResult instanceof DataValue ) {
			die( 'Failed to parse statement value' );
		}
		return $parseResult;
	}

}
