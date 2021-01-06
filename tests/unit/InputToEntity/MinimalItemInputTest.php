<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\InputToEntity;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\MockEditDiskRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use ValueParsers\StringParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ValueParserFactory;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput
 */
class MinimalItemInputTest extends \MediaWikiUnitTestCase {

	public function testBasicConstruction() {
		new MinimalItemInputTest();
		$this->expectNotToPerformAssertions();
	}

	private function mockPropertyDataTypeLookup() {
		$mock = $this->createMock( PropertyDataTypeLookup::class );
		$mock->method( 'getDataTypeIdForProperty' )
			->willReturnCallback( function ( EntityId $id ) {
				return $id->getSerialization() . '-data-type';
			} );
		return $mock;
	}

	private function mockValueParserFactory() {
		$mock = $this->createMock( ValueParserFactory::class );
		$mock->method( 'newParser' )
			->willReturnCallback( function () {
				return new StringParser();
			} );
		return $mock;
	}

	public function provideTestGetItem() {
		yield 'Empty' => [
			__DIR__ . '/data/minimal-empty.json',
			new Item()
		];
		yield '1 Label' => [
			__DIR__ . '/data/minimal-label.json',
			FluidItem::init()
				->withLabel( 'en', 'en-label' )
				->item()
		];
		yield 'Full' => [
			__DIR__ . '/data/minimal-complete.json',
			FluidItem::init()
				->withLabel( 'en', 'en-label' )
				->withDescription( 'fr', 'fr-desc' )
				->withAlias( 'en', 'en-alias2' )
				->withAlias( 'en', 'en-alias1' )
				->withSiteLink( 'site1', 'SomePage' )
				->withStringValue( 'P23', 'im-a-string' )
				->item()
		];
	}

	/**
	 * @dataProvider provideTestGetItem
	 */
	public function testGetItem( string $requestJsonFile, Item $expected ) {
		$sut = new MinimalItemInput( $this->mockPropertyDataTypeLookup(), $this->mockValueParserFactory() );
		$new = $sut->getItem( new MockEditDiskRequest( $requestJsonFile, null ) );
		$this->assertTrue(
			$new->equals( $expected ),
			'Expected:' . PHP_EOL . var_export( $expected, true ) . PHP_EOL . PHP_EOL .
			'Actual:' . PHP_EOL . var_export( $new, true )
		);
	}

}
