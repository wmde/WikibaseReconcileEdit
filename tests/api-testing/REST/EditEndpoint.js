'use strict';
const { assert, action, clientFactory } = require( 'api-testing' );
const wbk = require( 'wikibase-sdk' )( require( '../wikibase-edit.config' ) );
const axios = require( 'axios' );
const requestHelper = require( '../requestHelper' );

describe( 'POST /edit', () => {
	const basePath = 'rest.php/wikibase-reconcile-edit/v0';
	let actionAgent, client;

	let reconciliationPropertyId, billOfMaterialsPropertyId, namePropertyId;

	before( async () => {
		actionAgent = await action.alice();
		client = clientFactory.getRESTClient( basePath, actionAgent );

		// needs to maintain a list of what properties map to what "datatype"
		// could use PropertyLabelResolver to go around knowing the P numbers
		reconciliationPropertyId = await requestHelper.getOrCreateProperty( 'url', 'identifier' );
		namePropertyId = await requestHelper.getOrCreateProperty( 'string', 'name' );
		billOfMaterialsPropertyId = await requestHelper.getOrCreateProperty( 'wikibase-item', 'bill of material' );

	} );

	it( 'Should validate request body', async () => {
		const { status } = await client.post( '/edit', {} );
		assert.equal( status, 400 );
	} );

	it( 'Should only accept application/json requests', async () => {
		const requestStatements = [
			{
				property: reconciliationPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom'
			}
		];

		const params = requestHelper.getRequestPayload(
			requestStatements,
			reconciliationPropertyId,
			await actionAgent.token()
		);

		const { status } = await client.post(
			'/edit',
			params,
			{ 'content-type': 'application/x-www-form-urlencoded' }
		);

		assert.equal( status, 415 );
	} );

	it( 'Should reject invalid tokens', async () => {
		const requestStatements = [
			{
				property: reconciliationPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom'
			}
		];

		const params = requestHelper.getRequestPayload(
			requestStatements,
			reconciliationPropertyId,
			'BAD_TOKEN'
		);

		const { status } = await client.post(
			'/edit',
			params
		);

		assert.equal( status, 403 );
	} );

	it( 'Should create the repo item and a referenced bill of material', async () => {
		const requestStatements = [
			{
				property: reconciliationPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom'
			},
			{
				property: namePropertyId,
				value: 'OHLOOM'
			},
			{
				property: billOfMaterialsPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom/-/raw/834222370f34ad2a07d0e41d09eb54378573b8c3/sBoM.csv'
			}
		];

		const params = requestHelper.getRequestPayload(
			requestStatements,
			reconciliationPropertyId,
			await actionAgent.token()
		);

		const { status, body } = await client.post(
			'/edit',
			params
		);

		assert.equal( status, 200 );
		assert.nestedProperty( body, 'entityId' );

		const entityId = body.entityId;
		const resultingObject = await axios.get( wbk.getEntities( entityId ) );
		const claims = resultingObject.data.entities[ entityId ].claims;

		await requestHelper.assertRequestStatements(
			requestStatements,
			claims,
			reconciliationPropertyId
		);

	} );

	it( 'Should create clamp ring item with a link to bom', async () => {

		const requestStatements = [
			{
				property: reconciliationPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom/-/raw/834222370f34ad2a07d0e41d09eb54378573b8c3/okh.toml#Clamp_Ring'
			},
			{
				property: namePropertyId,
				value: 'Clamp Ring'
			},
			{
				property: billOfMaterialsPropertyId,
				value: 'https://gitlab.com/OSEGermany/ohloom/-/raw/834222370f34ad2a07d0e41d09eb54378573b8c3/sBoM.csv'
			}
		];

		const params = requestHelper.getRequestPayload(
			requestStatements,
			reconciliationPropertyId,
			await actionAgent.token()
		);

		const { status, body } = await client.post(
			'/edit',
			params
		);

		assert.equal( status, 200 );
		assert.nestedProperty( body, 'entityId' );

		const entityId = body.entityId;
		const resultingObject = await axios.get( wbk.getEntities( entityId ) );
		const claims = resultingObject.data.entities[ entityId ].claims;

		await requestHelper.assertRequestStatements(
			requestStatements,
			claims,
			reconciliationPropertyId
		);

	} );

} );
