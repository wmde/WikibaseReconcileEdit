<?php

namespace MediaWiki\Extension\OnOrProt\Wikibase;

use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;

class FluidItem {

	/**
	 * @var Item
	 */
	private $item;

	/**
	 * @param string|null $idString
	 */
	public function __construct( ?string $idString = null ) {
		if ( $idString ) {
			$this->item = new Item( new ItemId( $idString ) );
		} else {
			$this->item = new Item();
		}
	}

	public static function init() : self {
		return new self();
	}

	/**
	 * @param string|null $idString
	 * @return self
	 */
	public static function withId( ?string $idString = null ) : self {
		return new self( $idString );
	}

	/**
	 * @return Item the build Item
	 */
	public function item() : Item {
		return $this->item;
	}

	/**
	 * @param string $languageCode
	 * @param string $value
	 * @return self
	 */
	public function withLabel( string $languageCode, string $value ) : self {
		$this->item->setLabel( $languageCode, $value );
		return $this;
	}

	/**
	 * @param string $languageCode
	 * @param string $value
	 * @return self
	 */
	public function withDescription( string $languageCode, string $value ) : self {
		$this->item->setDescription( $languageCode, $value );
		return $this;
	}

	/**
	 * @param string $languageCode
	 * @param string $value
	 * @return self
	 */
	public function withAlias( string $languageCode, string $value ) : self {
		if ( $this->item->getAliasGroups()->hasGroupForLanguage( $languageCode ) ) {
			$baseAliases = $this->item->getAliasGroups()->getByLanguage( $languageCode )->getAliases();
		} else {
			$baseAliases = [];
		}
		$this->item->getAliasGroups()->setAliasesForLanguage(
			$languageCode,
			array_unique( array_merge(
				[ $value ],
				$baseAliases
				) )
			);
		return $this;
	}

	/**
	 * @param string $propertyIdString
	 * @param string $value
	 * @param string|null $guid
	 * @return self
	 */
	public function withStringValue( string $propertyIdString, string $value, ?string $guid = null ) : self {
		$this->item->getStatements()->addNewStatement(
			new PropertyValueSnak( new PropertyId( $propertyIdString ), new StringValue( $value ) ),
			null,
			null,
			$guid
		);
		return $this;
	}

}
