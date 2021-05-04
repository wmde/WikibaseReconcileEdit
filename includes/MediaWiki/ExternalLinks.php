<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use LinkFilter;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Interface for the externallinks MediaWiki table.
 * There are no useful abstraction in MediaWiki for accessing this table, this we need to create our own.
 */
class ExternalLinks {

	/** @var ILoadBalancer */
	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param string $url the URL to look for in the table
	 * @return int[] Page Ids that contain the $url
	 */
	public function pageIdsContainingUrl( string $url ) : array {
		$dbr = $this->loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
		return $dbr->selectFieldValues(
			'externallinks',
			'el_from',
			[
				'el_index' => $this->getSingleIndexOrDie( $url ),
			],
			__METHOD__
		);
	}

	/**
	 * @param string $url
	 * @return string the el_index value
	 */
	private function getSingleIndexOrDie( string $url ) : string {
		$indexes = LinkFilter::makeIndexes( $url );
		if ( count( $indexes ) !== 1 ) {
			die( 'Unexpected issue with LinkFilter return' );
		}
		return $indexes[0];
	}

}
