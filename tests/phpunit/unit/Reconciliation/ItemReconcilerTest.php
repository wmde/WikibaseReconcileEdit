<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\Reconciliation;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler
 * @license GPL-2.0-or-later
 */
class ItemReconcilerTest extends MediaWikiUnitTestCase {

	private const RECONCILE_URL_PROPERTY = 'P1';
	private const STRING_PROPERTY = 'P2';

	public function testReconcileItem() {
		$url = 'https://entity.test/1';
		$reconcileUrlProperty = new PropertyId( self::RECONCILE_URL_PROPERTY );
		$urlSnak = new PropertyValueSnak(
			$reconcileUrlProperty,
			new StringValue( $url )
		);
		$otherSnak = new PropertyValueSnak(
			new PropertyId( self::STRING_PROPERTY ),
			new StringValue( 'a string' )
		);
		$itemId = new ItemId( 'Q1' );

		$inputItem = new Item( null, null, null, new StatementList(
			new Statement( $urlSnak ),
			new Statement( $otherSnak )
		) );
		$originalItem = new Item( $itemId, null, null, new StatementList(
			new Statement( $urlSnak ),
		) );
		$saveItem = new Item( $itemId, null, null, new StatementList(
			new Statement( $urlSnak ),
			new Statement( $otherSnak ),
		) );
		$baseRevisionId = 1234;

		$reconciliationService = $this->createMock( ReconciliationService::class );
		$reconciliationService->method( 'getOrCreateItemByStatementUrl' )
			->with( $reconcileUrlProperty, $url )
			->willReturn( new ReconciliationServiceItem( $originalItem, $baseRevisionId ) );
		$simplePutStrategy = $this->createMock( SimplePutStrategy::class );
		$simplePutStrategy->method( 'apply' )
			->with( $originalItem, $inputItem )
			->willReturn( $saveItem );
		$itemReconciler = new ItemReconciler(
			$reconciliationService,
			$simplePutStrategy
		);

		$reconciledItem = $itemReconciler->reconcileItem(
			$inputItem,
			$reconcileUrlProperty
		);

		$this->assertSame( $saveItem, $reconciledItem->getItem() );
		$this->assertSame( $baseRevisionId, $reconciledItem->getBaseRevisionId() );
	}

	/** @dataProvider provideInvalidItem */
	public function testReconcileItem_invalid(
		Item $invalidInputItem,
		string $expectedMessageKey
	) {
		$reconciliationService = $this->createMock( ReconciliationService::class );
		$reconciliationService->expects( $this->never() )
			->method( $this->anything() );
		$simplePutStrategy = $this->createMock( SimplePutStrategy::class );
		$simplePutStrategy->expects( $this->never() )
			->method( $this->anything() );
		$itemReconciler = new ItemReconciler(
			$reconciliationService,
			$simplePutStrategy
		);

		try {
			$itemReconciler->reconcileItem(
				$invalidInputItem,
				new PropertyId( self::RECONCILE_URL_PROPERTY )
			);
			$this->fail( 'Expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( $expectedMessageKey, $rex->getMessageValue()->getKey() );
		}
	}

	public function provideInvalidItem(): iterable {
		$urlPropertyId = new PropertyId( self::RECONCILE_URL_PROPERTY );
		$urlSnak = new PropertyValueSnak(
			$urlPropertyId,
			new StringValue( 'https://example.com/' )
		);
		$stringSnak = new PropertyValueSnak(
			new PropertyId( self::STRING_PROPERTY ),
			new StringValue( 'a string' )
		);

		yield 'has qualifier on reconciliation statement' => [
			new Item( null, null, null, new StatementList(
				new Statement(
					$urlSnak,
					new SnakList( [ $stringSnak ] )
				)
			) ),
			'wikibasereconcileedit-qualifiers-references-not-supported',
		];

		yield 'has reference on reconciliation statement' => [
			new Item( null, null, null, new StatementList(
				new Statement(
					$urlSnak,
					null,
					new ReferenceList( [
						new Reference( new SnakList( [ $stringSnak ] ) ),
					] )
				)
			) ),
			'wikibasereconcileedit-qualifiers-references-not-supported',
		];

		yield 'has qualifier on other statement' => [
			new Item( null, null, null, new StatementList(
				new Statement( $urlSnak ),
				new Statement(
					$stringSnak,
					new SnakList( [ $stringSnak ] )
				)
			) ),
			'wikibasereconcileedit-qualifiers-references-not-supported',
		];

		yield 'has reference on other statement' => [
			new Item( null, null, null, new StatementList(
				new Statement( $urlSnak ),
				new Statement(
					$stringSnak,
					null,
					new ReferenceList( [
						new Reference( new SnakList( [ $stringSnak ] ) ),
					] )
				)
			) ),
			'wikibasereconcileedit-qualifiers-references-not-supported',
		];

		yield 'no statements' => [
			new Item(),
			'wikibasereconcileedit-reconciliation-property-missing-in-statements',
		];

		yield 'no reconciliation statement' => [
			new Item( null, null, null, new StatementList(
				new Statement( $stringSnak )
			) ),
			'wikibasereconcileedit-reconciliation-property-missing-in-statements',
		];

		yield 'several reconciliation statements' => [
			new Item( null, null, null, new StatementList(
				new Statement( $urlSnak ),
				new Statement( $urlSnak ),
			) ),
			'wikibasereconcileedit-reconciliation-property-missing-in-statements',
		];

		yield 'reconciliation statement with somevalue main snak' => [
			new Item( null, null, null, new StatementList(
				new Statement( new PropertySomeValueSnak( $urlPropertyId ) )
			) ),
			'wikibasereconcileedit-invalid-reconciliation-statement-type',
		];

		yield 'reconciliation statement with novalue main snak' => [
			new Item( null, null, null, new StatementList(
				new Statement( new PropertyNoValueSnak( $urlPropertyId ) )
			) ),
			'wikibasereconcileedit-invalid-reconciliation-statement-type',
		];
	}

}
