<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @license GPL-2.0-or-later
 */
class SimplePutStrategy {

	/**
	 * @var GuidGenerator
	 */
	private $guidGenerator;

	public function __construct( GuidGenerator $guidGenerator ) {
		$this->guidGenerator = $guidGenerator;
	}

	/**
	 * @param Item $base The base Item to be used
	 * @param Item $submitted The submitted data to be applied to the base
	 * @return Item the $base with $submitted applied per the strategy
	 */
	public function apply( Item $base, Item $submitted ) : Item {
		// TODO validate? and die if references, qualifiers are involved?
		$base = $this->applyLabels( $base, $submitted );
		$base = $this->applyDescriptions( $base, $submitted );
		$base = $this->applyAliases( $base, $submitted );
		$base = $this->applySitelinks( $base, $submitted );
		return $this->applyStatements( $base, $submitted );
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applyLabels( Item $base, Item $submitted ) : Item {
		// TODO perhaps things that are overridden should be added as aliases? (probably a strategy configuration)
		$base->getFingerprint()->getLabels()->addAll(
			$submitted->getFingerprint()->getLabels()->getIterator()
		);
		return $base;
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applyDescriptions( Item $base, Item $submitted ) : Item {
		$base->getFingerprint()->getDescriptions()->addAll(
			$submitted->getFingerprint()->getDescriptions()->getIterator()
		);
		return $base;
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applyAliases( Item $base, Item $submitted ) : Item {
		// TODO such merging logic would be good to have in DataModel?
		foreach ( $submitted->getFingerprint()->getAliasGroups()->getIterator() as $inputAliasGroup ) {
			$language = $inputAliasGroup->getLanguageCode();
			$base->getFingerprint()->getAliasGroups()->setAliasesForLanguage(
				$language,
				array_unique( array_merge(
					$base->getFingerprint()->getAliasGroups()->getByLanguage( $language )->getAliases(),
					$submitted->getFingerprint()->getAliasGroups()->getByLanguage( $language )->getAliases()
				) )
			);
		}

		return $base;
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applySitelinks( Item $base, Item $submitted ) : Item {
		$siteLinkList = new SiteLinkList();
		foreach ( $submitted->getSiteLinkList()->getIterator() as $siteLink ) {
			$siteLinkList->setSiteLink( $siteLink );
		}
		$base->setSiteLinkList( $siteLinkList );
		return $base;
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applyStatements( Item $base, Item $submitted ) : Item {
		// Collect existing statements by property id
		$existingStatementsByPropertyId = [];
		foreach ( $base->getStatements()->getIterator() as $statement ) {
			$propertyIdString = $statement->getMainSnak()->getPropertyId()->getSerialization();
			$existingStatementsByPropertyId[$propertyIdString][$statement->getGuid()] = $statement;
		}

		// Figure out which statements need to be kept and added
		$statementsToAdd = [];
		$statementsToKeep = [];
		foreach ( $submitted->getStatements()->getIterator() as $inputStatement ) {
			/** @var PropertyValueSnak $inputMainSnak */
			$inputMainSnak = $inputStatement->getMainSnak();
			$inputPropertyIdString = $inputMainSnak->getPropertyId()->getSerialization();
			// If an input statement value already exists then do nothing...
			if ( array_key_exists( $inputPropertyIdString, $existingStatementsByPropertyId ) ) {
				foreach ( $existingStatementsByPropertyId[$inputPropertyIdString] as $existingStatement ) {
					if ( $existingStatement->getMainSnak()->getDataValue()->equals( $inputMainSnak->getDataValue() ) ) {
						// continue out of the 2 foreach loops, as we don't need to add this statement
						$statementsToKeep[] = $existingStatement;
						continue 2;
					}
				}
			}
			$statementsToAdd[] = $inputStatement;
		}

		// Add fresh guids to new statements
		foreach ( $statementsToAdd as $statement ) {
			$statement->setGuid( $this->guidGenerator->newGuid( $base->getId() ) );
		}

		// Set the new statement list
		$base->setStatements( new StatementList( array_merge( $statementsToKeep, $statementsToAdd ) ) );

		return $base;
	}

}
