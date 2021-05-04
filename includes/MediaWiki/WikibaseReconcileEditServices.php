<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

class WikibaseReconcileEditServices {

	private function __construct() {
		// should not be instantiated
	}

	public static function getExternalLinks( ContainerInterface $services = null ): ExternalLinks {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.ExternalLinks' );
	}

}
