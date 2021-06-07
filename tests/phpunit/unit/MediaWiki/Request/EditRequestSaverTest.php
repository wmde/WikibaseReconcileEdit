<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciledItem;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use PHPUnit\Framework\TestCase;
use Status;
use User;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\EditEntity;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver
 *
 * @license GPL-2.0-or-later
 */
class EditRequestSaverTest extends TestCase {

	/** @var PropertyId */
	private $reconcileProperty;

	/** @var User */
	private $user;

	public function setUp(): void {
		parent::setUp();
		$this->reconcileProperty = new PropertyId( 'P1' );
		$this->user = $this->createMock( \User::class );
	}

	public function newRequestSaver(
		MediawikiEditEntityFactory $editEntity = null,
		ItemReconciler $itemReconciler = null
	) {
		$editEntity = $editEntity ?: $this->createMock( MediawikiEditEntityFactory::class );
		$itemReconciler = $itemReconciler ?: $this->createMock( ItemReconciler::class );

		return new EditRequestSaver( $editEntity, $itemReconciler );
	}

	public function testEditRequestSaverDoesNothing_WhenNoRequests(): void {
		$saver = $this->newRequestSaver();
		$status = $saver->persistEdits( [], 'token', $this->user );
		$this->assertTrue( $status->isGood() );
	}

	public function testEditRequestSavesWithout_OtherItems(): void {
		$item = FluidItem::withId( 'Q1' )->item();

		$itemReconciler = $this->createMock( ItemReconciler::class );
		$itemReconciler->method( 'reconcileItem' )
			->with( $item, $this->reconcileProperty )
			->willReturn( new ReconciledItem(
				$item,
				new ReconciliationServiceItem( $item, false )
			) );

		$expectedRevision = new EntityRevision( $item, 1234 );
		$editEntity = $this->createMock( EditEntity::class );
		$editEntity->method( 'attemptSave' )
			->willReturn( Status::newGood( [ 'revision' => $expectedRevision ] ) );

		$editEntityFactory = $this->createMock( MediawikiEditEntityFactory::class );
		$editEntityFactory->method( 'newEditEntity' )
			->with( $this->user, $item->getId(), false )
			->willReturn( $editEntity );
		$saver = $this->newRequestSaver( $editEntityFactory, $itemReconciler );

		$status = $saver->persistEdits( [
			new EditRequest( $this->reconcileProperty, $item )
		], '', $this->user );

		$this->assertTrue( $status->isGood() );
		$this->assertSame( $expectedRevision, $status->getValue()[0]['revision'] );
	}

	public function testEditRequestSavesWith_OtherItems(): void {
		$firstRequestItem = FluidItem::withId( 'Q1' )->item();
		$firstRequestOtherItem = FluidItem::withId( 'Q2' )->item();
		$secondRequestItem = FluidItem::withId( 'Q3' )->item();

		$otherItem = new ReconciliationServiceItem(
			$firstRequestOtherItem,
			false
		);

		$editRequestOne = new EditRequest( $this->reconcileProperty, $firstRequestItem, [ $otherItem ] );
		$editRequestTwo = new EditRequest( $this->reconcileProperty, $secondRequestItem );

		$itemReconciler = $this->createMock( ItemReconciler::class );
		$itemReconciler->method( 'reconcileItem' )
			->withConsecutive(
				[ $firstRequestItem, $this->reconcileProperty ],
				[ $secondRequestItem, $this->reconcileProperty ]
			)
			->willReturnOnConsecutiveCalls(
				new ReconciledItem(
					$firstRequestItem,
					new ReconciliationServiceItem( $firstRequestItem, false )
				),
				new ReconciledItem(
					$secondRequestItem,
					new ReconciliationServiceItem( $secondRequestItem, false )
				)
			);

		$firstEntityRevision = new EntityRevision( $firstRequestOtherItem, 6543 );

		$firstEditEntity = $this->createMock( EditEntity::class );
		$firstEditEntity->method( 'attemptSave' )
			->willReturn( Status::newGood( [ 'revision' => $firstEntityRevision ] ) );

		$secondEntityRevision = new EntityRevision( $firstRequestItem, 1234 );

		$secondEditEntity = $this->createMock( EditEntity::class );
		$secondEditEntity->method( 'attemptSave' )
			->willReturn( Status::newGood( [ 'revision' => $secondEntityRevision ] ) );

		$thirdEntityRevision = new EntityRevision( $secondRequestItem, 7891 );

		$thirdEditEntity = $this->createMock( EditEntity::class );
		$thirdEditEntity->method( 'attemptSave' )
			->willReturn( Status::newGood( [ 'revision' => $thirdEntityRevision ] ) );

		$editEntityFactory = $this->createMock( MediawikiEditEntityFactory::class );
		$editEntityFactory
			->expects( $this->exactly( 3 ) )
			->method( 'newEditEntity' )
			->withConsecutive(
				[ $this->user, $firstRequestOtherItem->getId(), false ],
				[ $this->user, $firstRequestItem->getId(), false ],
				[ $this->user, $secondRequestItem->getId(), false ],
			)
			->willReturnOnConsecutiveCalls(
				$firstEditEntity,
				$secondEditEntity,
				$thirdEditEntity
			);

		$saver = $this->newRequestSaver( $editEntityFactory, $itemReconciler );

		// gets updated once saved
		$this->assertFalse( $otherItem->getRevision() );

		$status = $saver->persistEdits( [
			$editRequestOne,
			$editRequestTwo
		], 'token', $this->user );

		$this->assertTrue( $status->isGood() );
		$this->assertSame( $secondEntityRevision, $status->getValue()[0]['revision'] );
		$this->assertSame( $thirdEntityRevision, $status->getValue()[1]['revision'] );

		// updated from save
		$this->assertSame( $firstEntityRevision->getRevisionId(), $otherItem->getRevision() );
	}

	public function testEditRequestSavesWith_OtherItemsThatFail(): void {
		$firstRequestItem = FluidItem::withId( 'Q1' )->item();
		$firstRequestOtherItem = FluidItem::withId( 'Q2' )->item();

		$otherItem = new ReconciliationServiceItem( $firstRequestOtherItem, false );
		$editRequestOne = new EditRequest( $this->reconcileProperty, $firstRequestItem, [ $otherItem ] );

		$itemReconciler = $this->createMock( ItemReconciler::class );

		$firstEditEntity = $this->createMock( EditEntity::class );
		$firstEditEntity->method( 'attemptSave' )
			->willReturn( Status::newFatal( 'not cool' ) );

		$editEntityFactory = $this->createMock( MediawikiEditEntityFactory::class );
		$editEntityFactory
			->expects( $this->exactly( 1 ) )
			->method( 'newEditEntity' )
			->with( $this->user, $firstRequestOtherItem->getId(), false )
			->willReturn( $firstEditEntity );

		$saver = $this->newRequestSaver( $editEntityFactory, $itemReconciler );

		// gets updated once saved
		$this->assertFalse( $otherItem->getRevision() );

		$status = $saver->persistEdits( [
			$editRequestOne,
		], 'token', $this->user );

		$this->assertFalse( $status->isGood() );
		$this->assertFalse( $status->isOK() );

		$this->assertEquals( 'not cool', $status->getErrors()[0]['message'] );
		$this->assertEquals( 'error', $status->getErrors()[0]['type'] );

		// not updated from save
		$this->assertFalse( $otherItem->getRevision() );
	}

}
