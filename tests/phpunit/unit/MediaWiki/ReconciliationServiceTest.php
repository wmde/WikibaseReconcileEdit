<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationException;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationItem;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use Title;
use TitleFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\Store\IdGenerator;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService
 */
class ReconciliationServiceTest extends \MediaWikiUnitTestCase {

	public function testNoExternalLinksFound() {
		$propertyId = new PropertyId( 'P1' );
		$reconcileUrl = "http://www.something-nice";

		$expectedItem = new Item( new ItemId( 'Q10' ) );
		$expectedItem->setStatements( new StatementList(
			new Statement(
				new PropertyValueSnak(
					$propertyId,
					new StringValue( $reconcileUrl )
				)
			)
		) );

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

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory
		);

		$reconcileItem = $service->getOrCreateItemByStatementUrl( $propertyId, $reconcileUrl );

		$this->assertEquals( $expectedItem, $reconcileItem->getItem() );
		$this->assertFalse( $reconcileItem->getRevision() );
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
			'alreadyReconciledItems' => [
				$p1->serialize() => [
					$someUrl => new ReconciliationItem( new Item( $existingItemId ), 5678 )
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
	 * @param array $alreadyReconciledItems
	 *
	 */
	public function testGetItemByStatementUrlFoundSomething(
		PropertyId $reconcilePropertyId,
		string $reconcileUrl,
		ItemId $itemId,
		StatementList $itemStatements,
		int $itemRevision,
		$expectedRevision,
		$alreadyReconciledItems = []
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

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory
		);

		$wrapper = TestingAccessWrapper::newFromObject( $service );
		$wrapper->reconciliationItems = $alreadyReconciledItems;

		$reconcileItem = $wrapper->getOrCreateItemByStatementUrl( $reconcilePropertyId, $reconcileUrl );

		$this->assertEquals( $expectedRevision ? $itemId : $newItemID, $reconcileItem->getItem()->getId() );
		$this->assertEquals( $expectedRevision, $reconcileItem->getRevision() );
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

		$service = new ReconciliationService(
			$entityIdLookup,
			$entityRevisionLookup,
			$idGenerator,
			$externalLinks,
			$titleFactory
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
