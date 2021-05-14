<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Rest\RequestData;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser
 *
 * @license GPL-2.0-or-later
 */
class EditRequestParserTest extends TestCase {

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

}
