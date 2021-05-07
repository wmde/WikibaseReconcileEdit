<?php

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\ReconciliationService;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [

	'WikibaseReconcileEdit.ExternalLinks' => function ( MediaWikiServices $services ): ExternalLinks {
		return new ExternalLinks(
			$services->getDBLoadBalancer()
		);
	},

	'WikibaseReconcileEdit.ReconciliationService' => function ( MediaWikiServices $services ): ReconciliationService {
		return new ReconciliationService(
			$services->get( 'WikibaseReconcileEdit.ExternalLinks' ),
			WikibaseRepo::getDefaultInstance()->getEntityIdLookup(),
			WikibaseRepo::getDefaultInstance()->getEntityLookup(),
			WikibaseRepo::getDefaultInstance()->getPropertyDataTypeLookup(),
			WikibaseRepo::getDefaultInstance()->getEntityRevisionLookup(),
			WikibaseRepo::getDefaultInstance()->newIdGenerator(),
		);
	}

];
