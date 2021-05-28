<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\InputToEntity;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use ValueParsers\StringParser;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;
use Wikibase\Repo\ValueParserFactory;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput
 * @license GPL-2.0-or-later
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

	private function mockPropertyLabelResolver(): PropertyLabelResolver {
		$mock = $this->createMock( PropertyLabelResolver::class );
		$mock->method( 'getPropertyIdsForLabels' )
			->willReturnCallback( function ( array $labels ) {
				return array_map( function ( $label ) {
					return [ $label => new PropertyId( $label ) ];
				}, $labels );
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
			$this->mockReconciliationService(),
			$this->mockPropertyLabelResolver()
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
			$this->mockReconciliationService(),
			$this->mockPropertyLabelResolver(),
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
		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService(),
			$this->mockPropertyLabelResolver(),
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

	public function testGetPropertyByPropertyID() {
		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService(),
			$this->mockPropertyLabelResolver(),
		);

		$prop = new PropertyId( 'P23' );

		$this->assertEquals( $prop, $sut->getPropertyId( 'P23' ) );
	}

	public function testGetPropertyIdByLabelNothingFound() {
		$exceptionMessageKey = 'wikibasereconcileedit-editendpoint-property-not-found';
		$propertyIdsArray = [];
		$propByLabel = 'im-a-label';
		$propertyLabelResolver = $this->createMock( PropertyLabelResolver::class );
		$propertyLabelResolver->method( 'getPropertyIdsForLabels' )
			->willReturn( $propertyIdsArray );

		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService(),
			$propertyLabelResolver,
		);

		try{
			$sut->getPropertyId( $propByLabel );
			$this->fail( 'expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( $exceptionMessageKey, $rex->getMessageValue()->getKey() );
		}
	}

	public function testGetPropertyIdByLabel() {
		$propByLabel = 'im-a-label';
		$expectedPropertyID = new PropertyId( 'P1234' );
		$propertyLabelResolver = $this->createMock( PropertyLabelResolver::class );
		$propertyLabelResolver->method( 'getPropertyIdsForLabels' )
			->with( [ $propByLabel ] )
			->willReturn( [ $propByLabel => $expectedPropertyID ] );

		$sut = new MinimalItemInput(
			$this->mockPropertyDataTypeLookup(),
			$this->mockValueParserFactory(),
			$this->mockReconciliationService(),
			$propertyLabelResolver,
		);

		$new = $sut->getPropertyId( $propByLabel );
		$this->assertEquals( $expectedPropertyID, $new );
	}
}
