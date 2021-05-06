<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @covers \MediaWiki\Rest\Handler\LanguageLinksHandler
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
