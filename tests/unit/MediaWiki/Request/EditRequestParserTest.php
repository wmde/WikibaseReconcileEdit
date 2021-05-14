<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\InMemoryDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;

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
		EditEndpoint::VERSION_KEY => '0.0.1/minimal',
		'statements' => [
			[
				'property' => self::URL_PROPERTY,
				'value' => 'http://example.com/',
			],
		],
	];

	private function getEditRequestParser(): EditRequestParser {
		return new EditRequestParser( $this->getPropertyDataTypeLookup() );
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

	public function testParseRequestInterface_good(): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( [
				EditEndpoint::VERSION_KEY => '0.0.1/minimal',
				'statements' => [
					[
						'property' => self::URL_PROPERTY,
						'value' => 'http://example.com/',
					],
				],
			] ),
			'reconcile' => json_encode( [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::URL_PROPERTY,
			] ),
		] ] );
		$requestParser = $this->getEditRequestParser();

		$editRequest = $requestParser->parseRequestInterface( $request );

		$this->assertSame( [
			EditEndpoint::VERSION_KEY => '0.0.1/minimal',
			'statements' => [
				[
					'property' => self::URL_PROPERTY,
					'value' => 'http://example.com/',
				],
			],
		], $editRequest->entity() );
		$this->assertEquals(
			new PropertyId( self::URL_PROPERTY ),
			$editRequest->reconcilePropertyId()
		);
	}

	/** @dataProvider provideInvalidReconcileJson */
	public function testParseRequestInterface_invalidReconcileJson( string $reconcile ): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => $reconcile,
		] ] );
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestInterface( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-reconcile-json',
				$e->getMessageValue()->getKey() );
		}
	}

	public function provideInvalidReconcileJson(): iterable {
		yield 'JSON syntax error' => [ '{' ];
		yield 'number' => [ '1' ];
		yield 'string' => [ '""' ];
		yield 'null' => [ 'null' ];
	}

	/** @dataProvider provideUnsupportedReconcileVersion */
	public function testParseRequestInterface_unsupportedReconcileVersion(
		?string $reconcileVersion
	): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => json_encode(
				$reconcileVersion !== null
					? [ EditRequestParser::VERSION_KEY => $reconcileVersion ]
					: []
			),
		] ] );
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestInterface( $request );
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
	public function testParseRequestInterface_invalidPropertyId( ?string $propertyId ): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => json_encode(
				$propertyId !== null
					? [ EditRequestParser::VERSION_KEY => '0.0.1', 'reconcileUrl' => $propertyId ]
					: [ EditRequestParser::VERSION_KEY => '0.0.1' ]
			),
		] ] );
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestInterface( $request );
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

	public function testParseRequestInterface_invalidDataType(): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => json_encode( [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::STRING_PROPERTY,
			] ),
		] ] );
		$requestParser = $this->getEditRequestParser();

		try {
			$requestParser->parseRequestInterface( $request );
			$this->fail( 'expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 'wikibasereconcileedit-editendpoint-invalid-type-property-must-be-url',
				$e->getMessageValue()->getKey() );
		}
	}

	public function testParseRequestInterface_missingProperty(): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => json_encode( [
				EditRequestParser::VERSION_KEY => '0.0.1',
				'urlReconcile' => self::MISSING_PROPERTY,
			] ),
		] ] );
		$requestParser = $this->getEditRequestParser();

		$this->expectException( PropertyDataTypeLookupException::class );
		$requestParser->parseRequestInterface( $request );
	}

}
