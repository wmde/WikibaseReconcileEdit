<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\InputToEntity;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;

/**
 * @covers \MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput
 * @license GPL-2.0-or-later
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
			$this->assertSame( 'wikibasereconcileedit-unsupported-entity-type',
				$rex->getMessageValue()->getKey() );
		}
	}

	private function mockNewEntityDeserializer() {
		$mock = $this->createMock( Deserializer::class );
		return $mock;
	}

}
