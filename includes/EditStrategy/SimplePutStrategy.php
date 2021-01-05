<?php

namespace MediaWiki\Extension\OnOrProt\EditStrategy;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementList;

class SimplePutStrategy {

	/**
	 * @param Item $base The base Item to be used
	 * @param Item $submitted The submitted data to be applied to the base
	 * @return Item the $base with $submitted applied per the strategy
	 */
	public function apply( Item $base, Item $submitted ) : Item {
		$base = $this->applyLabels( $base, $submitted );
		$base = $this->applyDescriptions( $base, $submitted );
		$base = $this->applyAliases( $base, $submitted );
		return $this->applyStatements( $base, $submitted );
	}

	/**
	 * @param Item $base
	 * @param Item $submitted
	 * @return Item
	 */
	private function applyLabels( Item $base, Item $submitted ) : Item {
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
			foreach ( $existingStatementsByPropertyId[$inputPropertyIdString] as $existingStatement ) {
				if ( $existingStatement->getMainSnak()->getDataValue()->equals( $inputMainSnak->getDataValue() ) ) {
					// continue out of the 2 foreach loops, as we don't need to add this statement
					$statementsToKeep[] = $existingStatement;
					continue 2;
				}
			}
			$statementsToAdd[] = $inputStatement;
		}

		// Add fresh guids to new statements
		$guidGenerator = new GuidGenerator();
		foreach ( $statementsToAdd as $statement ) {
			$statement->setGuid( $guidGenerator->newGuid( $base->getId() ) );
		}

		// Set the new statement list
		$base->setStatements( new StatementList( array_merge( $statementsToKeep, $statementsToAdd ) ) );

		return $base;
	}

}
