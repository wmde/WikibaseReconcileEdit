<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\InputToEntity;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationException;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput
 */
class FullWikibaseItemInputTest extends \MediaWikiUnitTestCase {

	public function provideTestGetItem() {
		yield 'Empty' => [
			[]
		];
		yield 'Type Property' => [
			[
				'type' => 'property'
			]
		];
	}

	/**
	 * @dataProvider provideTestGetItem
	 */
	public function testItemType( array $requestEntity ) {
		$sut = new FullWikibaseItemInput( $this->mockNewEntityDeserializer() );

		try {
			$new = $sut->getItem( $requestEntity );
			$this->fail( 'expected ReconciliationException to be thrown' );
		} catch ( ReconciliationException $rex ) {
			$this->assertSame( 'wikibasereconcileedit-fullwikibaseiteminput-unsupported-type',
				$rex->getMessageValue()->getKey() );
		}
	}

	private function mockNewEntityDeserializer() {
		$mock = $this->createMock( Deserializer::class );
		return $mock;
	}

}
