<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
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

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService
 */
class ReconciliationServiceTest extends \MediaWikiUnitTestCase {

	public function testNoExternalLinksFound() {
		$expectedItem = new Item( new ItemId( 'Q10' ) );

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

		$propertyId = new PropertyId( 'P1' );
		$reconcileUrl = "http://www.something-nice";

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
	}

	/**
	 * @dataProvider reconciliationProvider
	 *
	 * @param PropertyId $reconcilePropertyId
	 * @param string $reconcileUrl
	 * @param ItemId $itemId
	 * @param StatementList $itemStatements
	 * @param int $itemRevision revision ID returned by the entity revision lookup
	 * @param int|false $expectedRevision expected revision ID returned by the reconciliation service
	 * (either $itemRevision or false)
	 */
	public function testGetItemByStatementUrlFoundSomething(
		PropertyId $reconcilePropertyId,
		string $reconcileUrl,
		ItemId $itemId,
		StatementList $itemStatements,
		int $itemRevision,
		$expectedRevision
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

		$reconcileItem = $service->getOrCreateItemByStatementUrl( $reconcilePropertyId, $reconcileUrl );

		$this->assertEquals( $expectedRevision ? $itemId : $newItemID, $reconcileItem->getItem()->getId() );
		$this->assertEquals( $expectedRevision, $reconcileItem->getRevision() );
	}
}
