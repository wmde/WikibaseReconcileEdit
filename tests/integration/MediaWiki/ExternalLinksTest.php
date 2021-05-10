<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\Tests\Unit\EditStrategy;

use LinkFilter;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use Wikimedia\Rdbms\LoadBalancerSingle;

/**
 *
 * @covers MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks
 * @group Database
 * @group WikibaseReconcileEdit
 */
class ExternalLinksTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		$this->tablesUsed[] = 'externallinks';
	}

	private function newExternalLinks(): ExternalLinks {
		return new ExternalLinks(
			LoadBalancerSingle::newFromConnection( $this->db )
		);
	}

	public function testBasicConstruction() {
		$externalLinks = $this->newExternalLinks();

		$this->assertInstanceOf( ExternalLinks::class, $externalLinks );
	}

	public function getExternalLinksRows( $url, $pageId = 1 ) {
		$arr = [];

		foreach ( LinkFilter::makeIndexes( $url ) as $index ) {
			$arr[] = [
				'el_from' => $pageId,
				'el_to' => $url,
				'el_index' => $index,
				'el_index_60' => substr( $index, 0, 60 ),
			];
		}
		return $arr;
	}

	public function testPageIdsContainingMultiplePagesToTheSameLink() {
		$url = 'http://gitlab.com/OSEGermany/ohloom/1';

		$rows = $this->getExternalLinksRows( $url );
		$rows = array_merge( $rows, $this->getExternalLinksRows( $url, 2 ) );
		$this->db->insert( 'externallinks', $rows, __METHOD__ );

		$externalLinks = $this->newExternalLinks();

		$result = $externalLinks->pageIdsContainingUrl( $url );

		$this->assertSame( [ '1', '2' ], $result );
	}

	public function testPageIdsContainingALink() {
		$url = 'http://gitlab.com/OSEGermany/ohloom/1';
		$rows = $this->getExternalLinksRows( $url );
		$this->db->insert( 'externallinks', $rows, __METHOD__ );

		$externalLinks = $this->newExternalLinks();

		$result = $externalLinks->pageIdsContainingUrl( $url );

		$this->assertSame( [ '1' ], $result );
	}

	public function testPageIdsContainingNothing() {
		$url = 'http://something.not.found';
		$externalLinks = $this->newExternalLinks();

		$result = $externalLinks->pageIdsContainingUrl( $url );

		$this->assertSame( [], $result );
	}

}