<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Test\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
 *
 * @group Database
 */
class EditEndpointTest extends \MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private function newHandler() {
		return new EditEndpoint();
	}

	public function testExecute() {
		$reconcilePayload = [
			'hello',
			EditEndpoint::VERSION_KEY => '0.0.1'
		];

		$request = new RequestData( [
			'postParams' => [
				'entity' => 'Q1',
				'reconcile' => json_encode( $reconcilePayload )
			],
			'headers' => [
				'Content-Type' => 'json'
			],
			'method' => 'POST'
		] );

		$handler = $this->newHandler();

		$data = $this->executeHandlerAndGetBodyData( $handler, $request );
		dd( $data );
	}

}
