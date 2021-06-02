'use strict';
const { assert, action, clientFactory, utils } = require( 'api-testing' );
const wbk = require( 'wikibase-sdk' )( require( '../wikibase-edit.config' ) );
const axios = require( 'axios' );
const requestHelper = require( '../requestHelper' );

describe( 'POST /batch-edit', () => {
	const basePath = 'rest.php/wikibase-reconcile-edit/v0';
	let actionAgent, client;

	const reconciliationPropertyLabel = 'identifier';
	const namePropertyLabel = 'name';
	const billOfMaterialsPropertyLabel = 'bill of materials';

	let reconciliationPropertyId, billOfMaterialsPropertyId, namePropertyId;

	before( async () => {
		actionAgent = await action.alice();
		client = clientFactory.getRESTClient( basePath, actionAgent );

		reconciliationPropertyId = await requestHelper.getOrCreateProperty( 'url', reconciliationPropertyLabel );
		namePropertyId = await requestHelper.getOrCreateProperty( 'string', namePropertyLabel );
		billOfMaterialsPropertyId = await requestHelper.getOrCreateProperty( 'wikibase-item', billOfMaterialsPropertyLabel );

	} );

	it( 'Should accept batch edit requests', async () => {

		const actualPropertyIds = [
			reconciliationPropertyId,
			namePropertyId,
			billOfMaterialsPropertyId
		];

		const thirdItemUrl = `https://gitlab.com/OSEGermany/ohbroom/something-something/sBoM.csv?random=${utils.uniq()}`;

		const itemOneStatements = [
			{
				property: reconciliationPropertyLabel,
				value: `https://gitlab.com/OSEGermany/ohloom/1?random=${utils.uniq()}`
			},
			{
				property: namePropertyLabel,
				value: 'OHLOOM-1'
			},
			{
				property: billOfMaterialsPropertyLabel,
				value: thirdItemUrl
			}
		];

		const itemTwoStatements = [
			{
				property: reconciliationPropertyLabel,
				value: `https://gitlab.com/OSEGermany/ohloom/2?random=${utils.uniq()}`
			},
			{
				property: namePropertyLabel,
				value: 'OHLOOM-2'
			},
			{
				property: billOfMaterialsPropertyLabel,
				value: thirdItemUrl
			}
		];

		const allItemStatements = [ itemOneStatements, itemTwoStatements ];

		const params = requestHelper.getBatchRequestPayload(
			allItemStatements,
			reconciliationPropertyId,
			await actionAgent.token()
		);

		const { status, body } = await client.post(
			'/batch-edit',
			params
		);

		assert.equal( status, 200 );
		assert.nestedProperty( body, 'results' );
		assert.equal( body.results.length, 2 );

		assert.nestedProperty( body, 'success' );
		assert.equal( body.success, true );

		for ( let i = 0; i < body.results.length; i++ ) {
			const requestStatements = allItemStatements[ i ];
			const entityId = body.results[ i ].entityId;
			const resultingObject = await axios.get( wbk.getEntities( entityId ) );
			const claims = resultingObject.data.entities[ entityId ].claims;

			const expectedRequestStatementsWithIds = requestStatements.map( ( element, index ) => {
				return {
					property: actualPropertyIds[ index ],
					value: element.value
				};
			} );

			await requestHelper.assertRequestStatements(
				expectedRequestStatementsWithIds,
				claims,
				reconciliationPropertyId
			);
		}
	} );

} );
