<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\Reconciliation;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use Title;
use TitleFactory;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\IdGenerator;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService
 * @license GPL-2.0-or-later
 */
class ReconciliationServiceTest extends \MediaWikiUnitTestCase {

	public function testNoExternalLinksFound() {
		$propertyId = new PropertyId( 'P1' );
		$reconcileUrl = "http://www.something-nice";

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromIDs' )
			->with( [] )
			->willReturn( [] );

		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->method( 'getEntityIds' )
			->with( [] )
			->willReturn( [] );

		$entityRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$entityRevisionLookup->expects( $this->never() )
			->method( 'getEntityRevision' );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->expects( $this->once() )
			->method( 'getNewId' )
			->willReturn( 10 );

		$externalLinks = $this->createMock( ExternalLinks::class );
		$externalLinks->expects( $this->once() )
			->method( 'pageIdsContainingUrl' )
			->willReturn( [] );

		$guidGenerator = $this->createMock( GuidGenerator::class );
		$guidGenerator->method( 'newGuid' )
			->willReturnCallback( static function ( EntityId $entityId ) {
				return $entityId->getSerialization() . '$00000000-0000-0000-0000-000000000000';
			} );

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory,
			$guidGenerator,
		);

		$reconciliationServiceItem = $service->getOrCreateItemByStatementUrl( $propertyId, $reconcileUrl );

		$itemId = new ItemId( 'Q10' );

		$expectedItem = new Item( $itemId );
		$expectedItem->setStatements( new StatementList(
			new Statement(
				new PropertyValueSnak(
					$propertyId,
					new StringValue( $reconcileUrl )
				),
				null,
				null,
				$guidGenerator->newGuid( $itemId )
			)
		) );

		$this->assertEquals( $expectedItem, $reconciliationServiceItem->getItem() );
		$this->assertFalse( $reconciliationServiceItem->getRevision() );
	}

	public function reconciliationProvider() {
		$p1 = new PropertyId( 'P1' );
		$someUrl = 'http://some-url';

		yield 'matches on string url' => [
			$p1,
			$someUrl,
			new ItemId( 'Q1' ),
			new StatementList(
				new Statement(
					new PropertyValueSnak(
						$p1,
						new StringValue( $someUrl )
					)
				)
			),
			'itemRevision' => 1234,
			'expectedRevision' => 1234,
		];

		yield 'in external links but does not match on string url' => [
			$p1,
			$someUrl,
			new ItemId( 'Q1' ),
			new StatementList(
				new Statement(
					new PropertyValueSnak(
						$p1,
						new StringValue( 'http://some-other-url' )
					)
				)
			),
			'itemRevision' => 1234,
			'expectedRevision' => false,
		];

		yield 'does not have statements' => [
			$p1,
			$someUrl,
			new ItemId( 'Q1' ),
			new StatementList( [] ),
			'itemRevision' => 1234,
			'expectedRevision' => false,
		];

		$existingItemId = new ItemId( 'Q1' );
		yield 'matches on already reconciled item' => [
			$p1,
			$someUrl,
			$existingItemId,
			new StatementList(
				new Statement(
					new PropertyValueSnak(
						$p1,
						new StringValue( $someUrl )
					)
				)
			),
			'itemRevision' => 1234,
			'expectedRevision' => 5678,
			'previousResults' => [
				$p1->serialize() => [
					$someUrl => new ReconciliationServiceItem( new Item( $existingItemId ), 5678 )
				]
			]
		];
	}

	/**
	 * @dataProvider reconciliationProvider
	 *
	 * @param PropertyId $reconcilePropertyId
	 * @param string $reconcileUrl
	 * @param ItemId $itemId
	 * @param StatementList $itemStatements
	 * @param int $itemRevision revision ID returned by the entity revision lookup
	 * @param int|false $expectedRevision revision ID returned by the service (either $itemRevision or false)
	 * @param ReconciliationServiceItem[][] $previousResults results that the service
	 * is supposed to have returned previously
	 */
	public function testGetItemByStatementUrlFoundSomething(
		PropertyId $reconcilePropertyId,
		string $reconcileUrl,
		ItemId $itemId,
		StatementList $itemStatements,
		int $itemRevision,
		$expectedRevision,
		array $previousResults = []
	) {
		$newItemIDNumeric = 10;
		$newItemID = new ItemId( 'Q' . $newItemIDNumeric );

		$item = new Item( $itemId );
		$item->setStatements( $itemStatements );

		$pageId = 1;

		$externalLinks = $this->createMock( ExternalLinks::class );
		$externalLinks->method( 'pageIdsContainingUrl' )
			->with( $reconcileUrl )
			->willReturn( [ $pageId ] );

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromIDs' )
			->with( [ $pageId ] )
			->willReturn( [ Title::newFromID( $pageId ) ] );

		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->method( 'getEntityIds' )
			->with( [ Title::newFromID( $pageId ) ] )
			->willReturn( [ $itemId ] );

		$entityRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getEntityRevision' )
			->with( $itemId )
			->willReturn( new EntityRevision( $item, $itemRevision ) );

		$idGenerator = $this->createMock( IdGenerator::class );

		// id generator should not be run if revision was found
		if ( !$expectedRevision ) {
			$idGenerator->expects( $this->once() )
				->method( 'getNewId' )
				->willReturn( $newItemIDNumeric );
		} else {
			$idGenerator->expects( $this->never() )
				->method( 'getNewId' );
		}

		$guidGenerator = $this->createMock( GuidGenerator::class );
		$guidGenerator->method( 'newGuid' )
			->willReturnCallback( static function ( EntityId $entityId ) {
				return $entityId->getSerialization() . '$00000000-0000-0000-0000-000000000000';
			} );

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory,
			$guidGenerator
		);

		$wrapper = TestingAccessWrapper::newFromObject( $service );
		$wrapper->items = $previousResults;

		$reconciliationServiceItem = $wrapper->getOrCreateItemByStatementUrl( $reconcilePropertyId, $reconcileUrl );

		$this->assertEquals( $expectedRevision ? $itemId : $newItemID, $reconciliationServiceItem->getItem()->getId() );
		$this->assertEquals( $expectedRevision, $reconciliationServiceItem->getRevision() );
	}

	public function testMatchedMultipleItems() {
		$pageId0 = 0;
		$pageId1 = 1;

		$title0 = $this->createMock( Title::class );
		$title1 = $this->createMock( Title::class );

		$itemId = new ItemId( 'Q10' );

		$propertyId = new PropertyId( 'P1' );
		$reconcileUrl = "http://www.something-nice";

		$item = new Item( $itemId );
		$item->setStatements(
			new StatementList(
				new Statement(
					new PropertyValueSnak(
						$propertyId,
						new StringValue( $reconcileUrl )
					)
				),
			)
		);

		$itemRevision = 1234;

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'newFromIDs' )
			->with( [ $pageId0, $pageId1 ] )
			->willReturn( [ $title0, $title1 ] );

		$entityIdLookup = $this->createMock( EntityIdLookup::class );
		$entityIdLookup->method( 'getEntityIds' )
			->with( [ $title0, $title1 ] )
			->willReturn( [ new ItemId( 'Q1' ), new ItemId( 'Q2' ) ] );

		$entityRevisionLookup = $this->createMock( EntityRevisionLookup::class );
		$entityRevisionLookup->expects( $this->exactly( 2 ) )
			->method( 'getEntityRevision' )
			->willReturn( new EntityRevision( $item, $itemRevision ) );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->expects( $this->never() )
			->method( 'getNewId' );

		$externalLinks = $this->createMock( ExternalLinks::class );
		$externalLinks->expects( $this->once() )
			->method( 'pageIdsContainingUrl' )
			->willReturn( [ $pageId0, $pageId1 ] );

		$guidGenerator = $this->createMock( GuidGenerator::class );

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory,
			$guidGenerator
		);

		try {
			$exception = $service->getOrCreateItemByStatementUrl( $propertyId, $reconcileUrl );
			$this->fail( 'expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( 'wikibasereconcileedit-reconciliationservice-matched-multiple-items',
				$rex->getMessageValue()->getKey() );
		}
	}
}
