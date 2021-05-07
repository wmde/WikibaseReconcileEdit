<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Test\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @group Database
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private function newHandler() {
		return new EditEndpoint();
	}

	private function newRequest( array $params ): RequestInterface {
		return new RequestData( [
			'postParams' => array_map( 'json_encode', $params ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'method' => 'POST',
		] );
	}

	public function testExecuteNoPropertyFound() {
		$reconcilePayload = [
			'urlReconcile' => 'P1',
			EditEndpoint::VERSION_KEY => '0.0.1'
		];

		$request = $this->newRequest( [
			'entity' => 'Q1',
			'reconcile' => $reconcilePayload,
		] );

		$handler = $this->newHandler();

		$this->expectException( PropertyDataTypeLookupException::class );
		$this->executeHandlerAndGetBodyData( $handler, $request );
	}

}
