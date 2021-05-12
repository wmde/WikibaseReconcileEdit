<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki;

use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
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

	public static function getFullWikibaseItemInput( ContainerInterface $services = null ): FullWikibaseItemInput {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.FullWikibaseItemInput' );
	}

	public static function getMinimalItemInput( ContainerInterface $services = null ): MinimalItemInput {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.MinimalItemInput' );
	}

	public static function getReconciliationService( ContainerInterface $services = null ): ReconciliationService {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.ReconciliationService' );
	}

	public static function getSimplePutStrategy( ContainerInterface $services = null ): SimplePutStrategy {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'WikibaseReconcileEdit.SimplePutStrategy' );
	}

}
