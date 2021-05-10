<?php

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\MediaWikiServices;
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

];
