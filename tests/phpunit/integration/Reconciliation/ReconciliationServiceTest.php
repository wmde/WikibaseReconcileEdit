<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Integration\Reconciliation;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevision;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService
 * @license GPL-2.0-or-later
 */
class ReconciliationServiceTest extends MediaWikiIntegrationTestCase {

	public function testRemembersRevisionAfterUpdateFromEntityRevision(): void {
		$reconciliationService = WikibaseReconcileEditServices::getReconciliationService();
		$propertyId = new PropertyId( 'P1' );
		$url = 'https://example.com/' . random_int( 0, PHP_INT_MAX );

		// create a new item
		$reconciliationServiceItem = $reconciliationService->getOrCreateItemByStatementUrl(
			$propertyId,
			$url
		);
		$this->assertFalse( $reconciliationServiceItem->getRevision() );

		// pretend it was saved
		$item = $reconciliationServiceItem->getItem()->copy();
		$item->setLabel( 'en', 'test label' );
		$revisionId = 1234;
		$entityRevision = new EntityRevision(
			$item,
			$revisionId
		);

		// set the savedItem on the ReconciliationServiceItem
		$reconciliationServiceItem->updateFromEntityRevision( $entityRevision );

		// get it again from the ReconciliationService
		$reconciliationServiceItem = $reconciliationService->getOrCreateItemByStatementUrl(
			$propertyId,
			$url
		);

		// assert that we got the saved item again
		$this->assertSame( $item, $reconciliationServiceItem->getItem() );
		$this->assertSame( $revisionId, $reconciliationServiceItem->getRevision() );
	}

	public function testRemembersRevisionAfterFinish(): void {
		$reconciliationService = WikibaseReconcileEditServices::getReconciliationService();
		$itemReconciler = WikibaseReconcileEditServices::getItemReconciler();
		$propertyId = new PropertyId( 'P1' );
		$url = 'https://example.com/' . random_int( 0, PHP_INT_MAX );

		// create and reconcile a new item
		$reconciledItem = $itemReconciler->reconcileItem(
			FluidItem::init()
				->withLabel( 'en', 'test label' )
				->withStringValue( $propertyId->getSerialization(), $url )
				->item(),
			$propertyId
		);
		$this->assertFalse( $reconciledItem->getBaseRevisionId() );

		// pretend it was saved
		$revisionId = 1234;
		$entityRevision = new EntityRevision(
			$reconciledItem->getItem()->copy(),
			$revisionId
		);

		// set the savedItem on the ReconciledItem
		$reconciledItem->finish( $entityRevision );

		// get the ReconciliationServiceItem from the ReconciliationService
		$reconciliationServiceItem = $reconciliationService->getOrCreateItemByStatementUrl(
			$propertyId,
			$url
		);

		// assert that it matches the saved item
		$this->assertSame( 'test label',
			$reconciliationServiceItem->getItem()->getLabels()->getByLanguage( 'en' )->getText() );
		$this->assertSame( $revisionId, $reconciliationServiceItem->getRevision() );
	}

}
