<?php

use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [

	'WikibaseReconcileEdit.ExternalLinks' => function ( MediaWikiServices $services ): ExternalLinks {
		return new ExternalLinks(
			$services->getDBLoadBalancer()
		);
	},

	'WikibaseReconcileEdit.FullWikibaseItemInput' => function ( MediaWikiServices $services ): FullWikibaseItemInput {
		$repo = WikibaseRepo::getDefaultInstance();

		return new FullWikibaseItemInput(
			$repo->getBaseDataModelDeserializerFactory()
				->newEntityDeserializer()
		);
	},

	'WikibaseReconcileEdit.MinimalItemInput' => function ( MediaWikiServices $services ): MinimalItemInput {
		$repo = WikibaseRepo::getDefaultInstance();

		return new MinimalItemInput(
			$repo->getPropertyDataTypeLookup(),
			$repo->getValueParserFactory()
		);
	},

	'WikibaseReconcileEdit.ReconciliationService' => function ( MediaWikiServices $services ): ReconciliationService {
		$repo = WikibaseRepo::getDefaultInstance();

		return new ReconciliationService(
			$repo->getEntityIdLookup(),
			$repo->getEntityRevisionLookup(),
			$repo->newIdGenerator(),
			WikibaseReconcileEditServices::getExternalLinks( $services ),
			$services->getTitleFactory()
		);
	},

	'WikibaseReconcileEdit.SimplePutStrategy' => function ( MediaWikiServices $services ): SimplePutStrategy {
		return new SimplePutStrategy(
			new GuidGenerator()
		);
	},

];
