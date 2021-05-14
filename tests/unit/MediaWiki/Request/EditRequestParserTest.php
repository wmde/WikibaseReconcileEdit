<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser
 *
 * @license GPL-2.0-or-later
 */
class EditRequestParserTest extends TestCase {

	/**
	 * A valid payload for the “entity” parameter,
	 * for tests that check error handling in other parameters.
	 */
	private const VALID_ENTITY_PAYLOAD = [
		EditEndpoint::VERSION_KEY => '0.0.1/minimal',
		'statements' => [
			[
				'property' => 'P1',
				'value' => 'http://example.com/',
			],
		],
	];

	public function testParseRequestInterface_good(): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( [
				EditEndpoint::VERSION_KEY => '0.0.1/minimal',
				'statements' => [
					[
						'property' => 'P1',
						'value' => 'http://example.com/',
					],
				],
			] ),
			'reconcile' => json_encode( [
				EditEndpoint::VERSION_KEY => '0.0.1',
				'urlReconcile' => 'P1',
			] ),
		] ] );
		$requestParser = new EditRequestParser();

		$editRequest = $requestParser->parseRequestInterface( $request );

		$this->assertSame( [
			EditEndpoint::VERSION_KEY => '0.0.1/minimal',
			'statements' => [
				[
					'property' => 'P1',
					'value' => 'http://example.com/',
				],
			],
		], $editRequest->entity() );
		$this->assertSame( [
			EditEndpoint::VERSION_KEY => '0.0.1',
			'urlReconcile' => 'P1',
		], $editRequest->reconcile() );
	}

	/** @dataProvider provideInvalidReconcileJson */
	public function testParseRequestInterface_invalidReconcileJson( string $reconcile ): void {
		$request = new RequestData( [ 'postParams' => [
			'entity' => json_encode( self::VALID_ENTITY_PAYLOAD ),
			'reconcile' => $reconcile,
		] ] );
		$requestParser = new EditRequestParser();

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

}
