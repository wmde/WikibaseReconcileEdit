<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikimedia\Message\MessageValue;

/**
 * Service for reconciling an input item against the database.
 *
 * @license GPL-2.0-or-later
 */
class ItemReconciler {

	/** @var ReconciliationService */
	private $reconciliationService;

	/** @var SimplePutStrategy */
	private $simplePutStrategy;

	public function __construct(
		ReconciliationService $reconciliationService,
		SimplePutStrategy $simplePutStrategy
	) {
		$this->reconciliationService = $reconciliationService;
		$this->simplePutStrategy = $simplePutStrategy;
	}

	/**
	 * Get the reconciliation URL from the input item,
	 * look it up in the reconciliation service,
	 * and apply the input item to the returned item.
	 *
	 * @throws ReconciliationException
	 */
	public function reconcileItem(
		Item $inputItem,
		PropertyId $reconcileUrlProperty
	): ReconciledItem {
		// Validate Entity
		// Don't support references, qualifiers
		foreach ( $inputItem->getStatements()->toArray() as $statement ) {
			if ( $statement->getQualifiers()->count() !== 0 || $statement->getReferences()->count() !== 0 ) {
				throw new ReconciliationException(
					MessageValue::new( 'wikibasereconcileedit-editendpoint-qualifiers-references-not-supported' ),
				);
			}
		}
		// Check for our reconciliation value
		$reconciliationStatements = $inputItem->getStatements()->getByPropertyId( $reconcileUrlProperty );
		if ( $reconciliationStatements->count() !== 1 ) {
			throw new ReconciliationException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-reconciliation-property-missing-in-statements' )
			);
		}
		$reconciliationStatement = $reconciliationStatements->toArray()[0];
		if ( !$reconciliationStatement->getMainSnak() instanceof PropertyValueSnak ) {
			throw new ReconciliationException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconciliation-statement-type' )
			);
		}

		/** @var PropertyValueSnak $reconciliationMainSnak */
		$reconciliationMainSnak = $reconciliationStatement->getMainSnak();
		/** @var StringValue $reconciliationDataValue */
		$reconciliationDataValue = $reconciliationMainSnak->getDataValue();
		$reconciliationUrl = $reconciliationDataValue->getValue();

		$reconciliationServiceItem = $this->reconciliationService->getOrCreateItemByStatementUrl(
			$reconcileUrlProperty,
			$reconciliationUrl
		);

		// And make the edit
		$toSave = $this->simplePutStrategy->apply( $reconciliationServiceItem->getItem(), $inputItem );
		return new ReconciledItem(
			$toSave,
			$reconciliationServiceItem
		);
	}

}
