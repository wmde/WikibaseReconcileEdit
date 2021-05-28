<?php

use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\WikibaseReconcileEditServices;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationService;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Services\Statement\GuidGenerator;
use Wikibase\Lib\Store\Sql\Terms\CachedDatabasePropertyLabelResolver;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTermInLangIdsResolver;
use Wikibase\Lib\Store\Sql\Terms\DatabaseTypeIdsStore;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [

	'WikibaseReconcileEdit.EditRequestParser' => function ( MediaWikiServices $services ): EditRequestParser {
		$repo = WikibaseRepo::getDefaultInstance();

		return new EditRequestParser(
			$repo->getPropertyDataTypeLookup(),
			WikibaseReconcileEditServices::getFullWikibaseItemInput( $services ),
			WikibaseReconcileEditServices::getMinimalItemInput( $services )
		);
	},

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

	'WikibaseReconcileEdit.ItemReconciler' => function ( MediaWikiServices $services ): ItemReconciler {
		return new ItemReconciler(
			WikibaseReconcileEditServices::getReconciliationService( $services ),
			WikibaseReconcileEditServices::getSimplePutStrategy( $services )
		);
	},

	'WikibaseReconcileEdit.MinimalItemInput' => function ( MediaWikiServices $services ): MinimalItemInput {
		$repo = WikibaseRepo::getDefaultInstance();

		return new MinimalItemInput(
			$repo->getPropertyDataTypeLookup(),
			$repo->getValueParserFactory(),
			WikibaseReconcileEditServices::getReconciliationService( $services ),
			WikibaseReconcileEditServices::getPropertyLabelResolver( $services ),
		);
	},

	'WikibaseReconcileEdit.PropertyLabelResolver' => function ( MediaWikiServices $services ):
		CachedDatabasePropertyLabelResolver {
		$repo = WikibaseRepo::getDefaultInstance();
		$settings = $repo->getSettings();

		$languageCode = $services->getContentLanguage()->getCode();
		$cacheKeyPrefix = $settings->getSetting( 'sharedCacheKeyPrefix' );
		$cacheType = $settings->getSetting( 'sharedCacheType' );
		$cacheDuration = $settings->getSetting( 'sharedCacheDuration' );
		$cache = ObjectCache::getInstance( $cacheType );

		// Cache key needs to be language specific
		$cacheKey = $cacheKeyPrefix . ':TermPropertyLabelResolver' . '/' . $languageCode;
		$entitySource = $repo->getEntitySourceDefinitions()->getSourceForEntityType(
			Property::ENTITY_TYPE
		);
		$loadBalancer = $services->getDBLoadBalancerFactory()->getMainLB(
			$entitySource->getDatabaseName()
		);

		$wanObjectCache = $services->getMainWANObjectCache();

		$typeIdsStore = new DatabaseTypeIdsStore(
			$loadBalancer,
			$wanObjectCache,
			$entitySource->getDatabaseName()
		);

		$databaseTermIdsResolver = new DatabaseTermInLangIdsResolver(
			$typeIdsStore,
			$typeIdsStore,
			$loadBalancer,
			$entitySource->getDatabaseName()
		);

		return new CachedDatabasePropertyLabelResolver(
			$languageCode,
			$databaseTermIdsResolver,
			$cache,
			$cacheDuration,
			$cacheKey
		);
	},

	'WikibaseReconcileEdit.ReconciliationService' => function ( MediaWikiServices $services ): ReconciliationService {
		$repo = WikibaseRepo::getDefaultInstance();
		$guidGenerator = new GuidGenerator();
		$idGenerator = method_exists( $repo, 'getIdGenerator' )
			? $repo->getIdGenerator() // 1.36+
			: $repo->newIdGenerator(); // 1.35

		return new ReconciliationService(
			$repo->getEntityIdLookup(),
			$repo->getEntityRevisionLookup(),
			$idGenerator,
			WikibaseReconcileEditServices::getExternalLinks( $services ),
			$services->getTitleFactory(),
			$guidGenerator,
		);
	},

	'WikibaseReconcileEdit.SimplePutStrategy' => function ( MediaWikiServices $services ): SimplePutStrategy {
		return new SimplePutStrategy(
			new GuidGenerator()
		);
	},

];
