<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver;
use MediaWiki\Session\SessionProviderInterface;
use Message;
use PHPUnit\Framework\TestCase;
use Status;
use User;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint
 * @license GPL-2.0-or-later
 */
class EditEndpointTest extends TestCase {

	public function testGetResponseBodyWithErrors() {
		$stub = $this->getEndpoint();
		$status = $this->createMock( Status::class );
		$status->method( 'getErrors' )
			->willReturn(
				[
					'some error',
					[ 'message' => 'edit-already-exists', 'type' => "error" ],
					new Message( 'edit-conflict' ),
				]
			);
		$response = $stub->getResponseBody( $status );

		$stringError = $response['errors'][0];
		$editError = $response['errors'][1];
		$editConflict = $response['errors'][2];

		$this->assertEquals( 'some_error', $stringError['code'] );
		$this->assertEquals( 'edit-already-exists', $editError['code'] );
		$this->assertEquals( 'editconflict', $editConflict['code'] );
	}

	private function getEndpoint(): EditEndpoint {
		$stub = $this->getMockForAbstractClass(
			EditEndpoint::class,
			[
				$this->createMock( EditRequestParser::class ),
				$this->createMock( EditRequestSaver::class ),
				$this->createMock( User::class ),
				$this->createMock( SessionProviderInterface::class ),
			]
		);
		return $stub;
	}

}
