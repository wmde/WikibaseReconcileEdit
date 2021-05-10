<?php

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [

	'WikibaseReconcileEdit.ExternalLinks' => function ( MediaWikiServices $services ): ExternalLinks {
		return new ExternalLinks(
			$services->getDBLoadBalancer()
		);
	},

	'WikibaseReconcileEdit.FullWikibaseItemInput' => function ( MediaWikiServices $services ): FullWikibaseItemInput {
		return new FullWikibaseItemInput();
	},

	'WikibaseReconcileEdit.MinimalItemInput' => function ( MediaWikiServices $services ): MinimalItemInput {
		return new MinimalItemInput();
	},

];
