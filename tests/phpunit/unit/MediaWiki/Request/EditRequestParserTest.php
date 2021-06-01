<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Request;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\FluidItem;
use MediaWiki\Rest\LocalizedHttpException;
use PHPUnit\Framework\TestCase;
use ValueParsers\StringParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Services\Term\PropertyLabelResolver;
use Wikibase\Repo\ValueParserFactory;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser
 *
 * @license GPL-2.0-or-later
 */
class EditRequestParserTest extends TestCase {

	private const URL_PROPERTY = 'P1';
	private const STRING_PROPERTY = 'P2';
	private const MISSING_PROPERTY = 'P1000';

	/**
	 * A valid payload for the “entity” parameter,
	 * for tests that check error handling in other parameters.
	 */
	private const VALID_ENTITY_PAYLOAD = [
		EditRequestParser::VERSION_KEY => '0.0.1/minimal',
		'statements' => [
			[
				'property' => self::URL_PROPERTY,
				'value' => 'http://example.com/',
			],
		],
	];

	/**
	 * A valid payload for the “reconcile” parameter,
	 * for tests that check error handling in other parameters.
	 */
	private const VALID_RECONCILE_PAYLOAD = [
		EditRequestParser::VERSION_KEY => '0.0.1',
		'urlReconcile' => self::URL_PROPERTY,
	];

	private function mockPropertyLabelResolver(): PropertyLabelResolver {
		$mock = $this->createMock( PropertyLabelResolver::class );
		$mock->expects( $this->never() )
			->method( 'getPropertyIdsForLabels' );
		return $mock;
	}

	private function getEditRequestParser(): EditRequestParser {
		$propertyDataTypeLookup = $this->getPropertyDataTypeLookup();
		$itemDeserializer = $this->createMock( Deserializer::class );
		// $itemDeserializer is not used since we only test 0.0.1/minimal so far
		$reconciliationService = $this->createMock( ReconciliationService::class );
		// $reconciliationService is not used since we only test url-type properties so far

		return new EditRequestParser(
			$propertyDataTypeLookup,
			new FullWikibaseItemInput( $itemDeserializer ),
			new MinimalItemInput(
				$propertyDataTypeLookup,
				new ValueParserFactory( [
					'url' => function () {
						return new StringParser();
					},
				] ),
				$reconciliationService,
				$this->mockPropertyLabelResolver()
			)
		);
	}

	private function getPropertyDataTypeLookup(): PropertyDataTypeLookup {
		$propertyDataTypeLookup = new InMemoryDataTypeLookup();

		$propertyDataTypeLookup->setDataTypeForProperty(
			new PropertyId( self::URL_PROPERTY ),
			'url'
		);
		$propertyDataTypeLookup->setDataTypeForProperty(
			new PropertyId( self::STRING_PROPERTY ),
			'string'
		);

		return $propertyDataTypeLookup;
	}

	public function testParseRequestBody_good(): void {
		$request = [
			'entity' => [
				EditRequestParser::VERSION_KEY => '0.0.1/minimal',
				'statements' => [
					[
						'property' => self::URL_PROPERTY,
						'value' => 'http://example.com/',
					],
				],
			],
			'reconcile' => [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::URL_PROPERTY,
			],
		];

		$requestParser = $this->getEditRequestParser();

		$editRequest = $requestParser->parseRequestBody( $request );

		$this->assertEquals(
			FluidItem::init()
				->withStringValue( self::URL_PROPERTY, 'http://example.com/' )
				->item(),
			$editRequest->entity()
		);
		$this->assertEquals(
			new PropertyId( self::URL_PROPERTY ),
			$editRequest->reconcilePropertyId()
		);
	}

	public function testParseRequestBody_unspecifiedEntityVersion(): void {
		$request = [
			'entity' => [],
			'reconcile' => self::VALID_RECONCILE_PAYLOAD,
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-unspecified-entity-input-version',
				$e->getMessageValue()->getKey() );
		}
	}

	/** @dataProvider provideUnsupportedEntityVersion */
	public function testParseRequestBody_unsupportedEntityVersion(
		string $entityVersion
	): void {
		$request = [
			'entity' => [ EditRequestParser::VERSION_KEY => $entityVersion ],
			'reconcile' => self::VALID_RECONCILE_PAYLOAD,
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-entity-input-version',
				$e->getMessageValue()->getKey() );
		}
	}

	public function provideUnsupportedEntityVersion(): iterable {
		yield '0.0.0' => [ '0.0.0' ];
		yield '0.0.1' => [ '0.0.1' ];
		yield '0.0.1/potato' => [ '0.0.1/potato' ];
	}

	/** @dataProvider provideInvalidReconcileJson */
	public function testParseRequestBody_invalidReconcileParameter( $reconcile ): void {
		$request = [
			'entity' => self::VALID_ENTITY_PAYLOAD,
			'reconcile' => $reconcile,
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-parameter',
				$e->getMessageValue()->getKey() );
		}
	}

	public function provideInvalidReconcileJson(): iterable {
		yield 'number' => [ 1 ];
		yield 'string' => [ 'asdf' ];
		yield 'null' => [ 'null' ];
	}

	/** @dataProvider provideUnsupportedReconcileVersion */
	public function testParseRequestBody_unsupportedReconcileVersion(
		?string $reconcileVersion
	): void {
		$request = [
			'entity' => self::VALID_ENTITY_PAYLOAD,
			'reconcile' => $reconcileVersion !== null
					? [ EditRequestParser::VERSION_KEY => $reconcileVersion ]
					: []
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version',
				$e->getMessageValue()->getKey() );
		}
	}

	public function provideUnsupportedReconcileVersion(): iterable {
		yield 'missing' => [ null ];
		yield '0.0.0' => [ '0.0.0' ];
	}

	/** @dataProvider provideInvalidPropertyId */
	public function testParseRequestBody_invalidPropertyId( ?string $propertyId ): void {
		$request = [
			'entity' => self::VALID_ENTITY_PAYLOAD,
			'reconcile' => $propertyId !== null
					? [ EditRequestParser::VERSION_KEY => '0.0.1', 'reconcileUrl' => $propertyId ]
					: [ EditRequestParser::VERSION_KEY => '0.0.1' ]
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid',
				$e->getMessageValue()->getKey() );
		}
	}

	public function provideInvalidPropertyId(): iterable {
		yield 'missing' => [ null ];
		yield 'empty' => [ '' ];
		yield 'item ID' => [ 'Q123' ];
		yield 'statement ID' => [ 'P40$ea25003c-4c23-63fa-86d9-62bfcd2b05a4' ];
		yield 'numeric part missing' => [ 'P' ];
	}

	public function testParseRequestBody_invalidDataType(): void {
		$request = [
			'entity' => self::VALID_ENTITY_PAYLOAD,
			'reconcile' => [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::STRING_PROPERTY,
			]
		];
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestBody( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-type-property-must-be-url',
				$e->getMessageValue()->getKey() );
		}
	}

	public function testParseRequestBody_missingProperty(): void {
		$request = [
			'entity' => self::VALID_ENTITY_PAYLOAD,
			'reconcile' => [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::MISSING_PROPERTY,
			],
		];
		$requestParser = $this->getEditRequestParser();

		$this->expectException( PropertyDataTypeLookupException::class );
		$requestParser->parseRequestBody( $request );
	}

}
