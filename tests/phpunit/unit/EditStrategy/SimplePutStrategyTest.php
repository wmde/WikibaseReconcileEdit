<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\EditStrategy;

use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Statement\GuidGenerator;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy
 * @license GPL-2.0-or-later
 */
class SimplePutStrategyTest extends \MediaWikiUnitTestCase {

	private function mockGuidGenerator() {
		// TODO it would be nice if datamodel-services provided nicer GuidGenerator stuff for tests?
		$mock = $this->createMock( GuidGenerator::class );
		$mock->method( 'newGuid' )
			->willReturnCallback( function ( EntityId $id ) {
				return $id->getSerialization() . '$NEW-GUID';
			} );
		return $mock;
	}

	public function provideTestStrategy() {
		yield 'Empty' => [ new Item(), new Item(), new Item() ];
		yield 'Labels remain (apply empty)' => [
			FluidItem::init()->withLabel( 'en', 'Foo' )->item(),
			new Item(),
			FluidItem::init()->withLabel( 'en', 'Foo' )->item()
		];
		yield 'Descriptions remain (apply empty)' => [
			FluidItem::init()->withDescription( 'en', 'Foo' )->item(),
			new Item(),
			FluidItem::init()->withDescription( 'en', 'Foo' )->item()
		];
		yield 'Aliases remain (apply empty)' => [
			FluidItem::init()->withAlias( 'en', 'Foo' )->item(),
			new Item(),
			FluidItem::init()->withAlias( 'en', 'Foo' )->item()
		];
		yield 'Overwrite label' => [
			FluidItem::init()->withLabel( 'en', 'Foo' )->item(),
			FluidItem::init()->withLabel( 'en', 'Replace' )->item(),
			FluidItem::init()->withLabel( 'en', 'Replace' )->item()
		];
		yield 'Overwrite description' => [
			FluidItem::init()->withDescription( 'en', 'Foo' )->item(),
			FluidItem::init()->withDescription( 'en', 'Replace' )->item(),
			FluidItem::init()->withDescription( 'en', 'Replace' )->item()
		];
		yield 'Add alias' => [
			FluidItem::init()->withAlias( 'en', 'Foo' )->item(),
			FluidItem::init()->withAlias( 'en', 'Replace' )->item(),
			FluidItem::init()->withAlias( 'en', 'Replace' )->withAlias( 'en', 'Foo' )->item()
		];
		yield 'Add sitelink' => [
			FluidItem::init()->item(),
			FluidItem::init()->withSitelink( 'en', 'New' )->item(),
			FluidItem::init()->withSitelink( 'en', 'New' )->item()
		];
		yield 'Overwrite sitelink' => [
			FluidItem::init()->withSitelink( 'en', 'Old' )->item(),
			FluidItem::init()->withSitelink( 'en', 'New' )->item(),
			FluidItem::init()->withSitelink( 'en', 'New' )->item()
		];
		yield 'Sitelink is removed when not provided' => [
			FluidItem::init()->withSitelink( 'en', 'New' )->item(),
			FluidItem::init()->item(),
			FluidItem::init()->item(),
		];
		yield 'Statements get removed' => [
			FluidItem::init()->withStringValue( 'P1', 'Foo' )->item(),
			new Item(),
			new Item(),
		];
		yield 'Statements get kept, with GUID' => [
			FluidItem::withId( 'Q1' )->withStringValue( 'P1', 'Foo', 'Q1$OLD-GUID' )->item(),
			FluidItem::init()->withStringValue( 'P1', 'Foo' )->item(),
			FluidItem::withId( 'Q1' )->withStringValue( 'P1', 'Foo', 'Q1$OLD-GUID' )->item(),
		];
		yield 'Statements get added' => [
			FluidItem::withId( 'Q1' )->item(),
			FluidItem::init()->withStringValue( 'P1', 'Foo' )->item(),
			FluidItem::withId( 'Q1' )->withStringValue( 'P1', 'Foo', 'Q1$NEW-GUID' )->item(),
		];
		yield 'Statements get replaced' => [
			FluidItem::withId( 'Q1' )->withStringValue( 'P1', 'Foo', 'Q1$OLD-GUID' )->item(),
			FluidItem::init()->withStringValue( 'P1', 'Replace' )->item(),
			FluidItem::withId( 'Q1' )->withStringValue( 'P1', 'Replace', 'Q1$NEW-GUID' )->item(),
		];
		yield 'Statements get removed, kept and replaced' => [
			FluidItem::withId( 'Q1' )
				->withStringValue( 'P1', 'Foo', 'Q1$OLD1-GUID' )
				->withStringValue( 'P1', 'Keep', 'Q1$OLD2-GUID' )
				->item(),
			FluidItem::init()
				->withStringValue( 'P1', 'Keep' )
				->withStringValue( 'P1', 'Replace' )
				->item(),
			FluidItem::withId( 'Q1' )
				->withStringValue( 'P1', 'Keep', 'Q1$OLD2-GUID' )
				->withStringValue( 'P1', 'Replace', 'Q1$NEW-GUID' )
				->item(),
		];
	}

	/**
	 * @dataProvider provideTestStrategy
	 */
	public function testStrategy( Item $base, Item $input, Item $expected ) {
		$sut = new SimplePutStrategy( $this->mockGuidGenerator() );
		$new = $sut->apply( $base, $input );
		$this->assertTrue(
			$new->equals( $expected ),
			'Expected:' . PHP_EOL . var_export( $expected, true ) . PHP_EOL . PHP_EOL .
			'Actual:' . PHP_EOL . var_export( $new, true )
		);
	}

}
