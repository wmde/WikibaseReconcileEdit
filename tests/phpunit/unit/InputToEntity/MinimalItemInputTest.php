<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\InputToEntity;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationException;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use ValueParsers\StringParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ValueParserFactory;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput
 */
class MinimalItemInputTest extends \MediaWikiUnitTestCase {

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
			[
				'wikibasereconcileedit-version' => '0.0.1/minimal',
			],
			new Item()
		];
		yield '1 Label' => [
			[
				'wikibasereconcileedit-version' => '0.0.1/minimal',
				'labels' => [ 'en' => 'en-label' ],
			],
			FluidItem::init()
				->withLabel( 'en', 'en-label' )
				->item()
		];
		yield 'Full' => [
			[
				'wikibasereconcileedit-version' => '0.0.1/minimal',
				'labels' => [ 'en' => 'en-label' ],
				'descriptions' => [ 'fr' => 'fr-desc' ],
				'aliases' => [ 'en' => [
					'en-alias1',
					'en-alias2',
				] ],
				'sitelinks' => [ 'site1' => 'SomePage' ],
				'statements' => [
					[
						'property' => 'P23',
						'value' => 'im-a-string',
					],
				],
			],
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
	public function testGetItem( array $requestEntity, Item $expected ) {
		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService()
		);

		$prop = new PropertyId( 'P23' );

		$newItems = $sut->getItem( $requestEntity, $prop );
		$new = $newItems[0];

		$this->assertTrue(
			$new->equals( $expected ),
			'Expected:' . PHP_EOL . var_export( $expected, true ) . PHP_EOL . PHP_EOL .
			'Actual:' . PHP_EOL . var_export( $new, true )
		);
	}

	private function mockReconciliationService() {
		$mock = $this->createMock( ReconciliationService::class );
		return $mock;
	}

	public function testStatementsHavePropertyKey() {
		$requestEntityMissingProperty = [
			'wikibasereconcileedit-version' => '0.0.1/minimal',
			'statements' => [
				[
					'value' => 'im-a-string',
				],
			],
		];
		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService()
		);

		$prop = new PropertyId( 'P23' );

		try {
			$new = $sut->getItem( $requestEntityMissingProperty, $prop );
			$this->fail( 'expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( 'wikibasereconcileedit-minimaliteminput-required-keys',
				$rex->getMessageValue()->getKey() );
		}
	}

	public function testStatementsHaveValueKey() {
		$requestEntityMissingValue = [
			'wikibasereconcileedit-version' => '0.0.1/minimal',
			'statements' => [
				[
					'property' => 'P23',
				],
			],
		];
		$sut = $sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService()
		);

		$prop = new PropertyId( 'P23' );

		try {
			$new = $sut->getItem( $requestEntityMissingValue, $prop );
			$this->fail( 'expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( 'wikibasereconcileedit-minimaliteminput-required-keys',
				$rex->getMessageValue()->getKey() );
		}
	}

}