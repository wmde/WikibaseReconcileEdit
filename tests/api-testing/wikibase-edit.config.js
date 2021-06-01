'use strict';

// eslint-disable-next-line node/no-missing-require
const testConfig = require( '../../.api-testing.config.json' );

const baseUrl = new URL( testConfig.base_uri );

/**
 * This configuration file is required by wikibase-edit & wikibase-sdk
 *
 * @see https://github.com/maxlath/wikibase-edit
 * @see https://github.com/maxlath/wikibase-sdk
 */
const generalConfig = {
	// A Wikibase instance is required
	instance: baseUrl.origin,
	wgScriptPath: baseUrl.pathname,

	// One authorization mean is required (unless in anonymous mode, see below)
	credentials: {
		username: testConfig.root_user.name,
		password: testConfig.root_user.password
	},
	// Optional
	// See https://meta.wikimedia.org/wiki/Help:Edit_summary
	// Default: empty
	summary: 'WikibaseReconcileEdit api-testing',

	// See https://www.mediawiki.org/wiki/Manual:Bots
	// Default: false
	bot: false
};

module.exports = generalConfig;
