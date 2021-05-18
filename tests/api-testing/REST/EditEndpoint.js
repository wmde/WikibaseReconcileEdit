'use strict';
const { REST, assert } = require( 'api-testing' );
const wbk = require( 'wikibase-sdk' )( require( '../wikibase-edit.config' ) );
const axios = require( 'axios' );
const requestHelper = require( '../requestHelper' );

describe( 'POST /edit', () => {
	const basePath = 'rest.php/wikibase-reconcile-edit/v0';
	const client = new REST( basePath );

	let reconciliationPropertyId, billOfMaterialsPropertyId, namePropertyId;

	before( async () => {
		// needs to maintain a list of what properties map to what "datatype"
		// could use PropertyLabelResolver to go around knowing the P numbers
		reconciliationPropertyId = await requestHelper.getOrCreateProperty( 'url', 'identifier' );
		namePropertyId = await requestHelper.getOrCreateProperty( 'string', 'name' );
		billOfMaterialsPropertyId = await requestHelper.getOrCreateProperty( 'wikibase-item', 'bill of material' );

	} );

	it( 'Should create the repo item and a referenced bill of material ', async () => {
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
			reconciliationPropertyId
		);
		const { status, body } = await client.post(
			'/edit',
			params,
			{ 'content-type': 'application/x-www-form-urlencoded' } // why?
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
			reconciliationPropertyId
		);

		const { status, body } = await client.post(
			'/edit',
			params,
			{ 'content-type': 'application/x-www-form-urlencoded' } // why?
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
