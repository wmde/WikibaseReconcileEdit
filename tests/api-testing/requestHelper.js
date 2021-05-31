'use strict';
const { assert } = require( 'api-testing' );
const wbEdit = require( 'wikibase-edit' )( require( './wikibase-edit.config' ) );
const wbk = require( 'wikibase-sdk' )( require( './wikibase-edit.config' ) );
const axios = require( 'axios' );

const getOrCreateProperty = async function ( propertyType, label ) {
	if ( label ) {
		const url = await wbk.searchEntities( { search: label, type: 'property' } );
		const response = await axios.get( url );

		for ( let i = 0; i < response.data.search.length; i++ ) {

			const prop = response.data.search[ i ];

			if ( prop.datatype === propertyType && prop.match.text === label ) {
				return prop.id;
			}

		}
	}
	const response = await wbEdit.entity.create( {
		type: 'property',
		datatype: propertyType,
		labels: label ? { en: label } : [],
		descriptions: [],
		aliases: [],
		claims: []
	} );

	return response.entity.id;
};

const getStatementValue = function ( entity, propertyId ) {
	const claims = entity.claims;

	const firstStatement = claims[ propertyId ][ 0 ];
	return firstStatement.mainsnak.datavalue;
};

const assertRequestStatements = async function (
	requestStatements,
	claims,
	reconciliationPropertyId
) {
	for ( let i = 0; i < requestStatements.length; i++ ) {
		const requestStatement = requestStatements[ i ];
		const firstStatement = claims[ requestStatement.property ][ 0 ];
		const dataValue = firstStatement.mainsnak.datavalue;

		// this covers string + url values
		if ( dataValue.type === 'string' ) {
			assert.equal( dataValue.value, requestStatement.value );
			// referenced items should contain a reconcilation statement with the
			// same value as from the original request
		} else if ( dataValue.type === 'wikibase-entityid' ) {
			const referencedItem = await axios.get( wbk.getEntities( dataValue.value.id ) );

			const statementValue = getStatementValue(
				referencedItem.data.entities[ dataValue.value.id ],
				reconciliationPropertyId
			);

			assert.equal( statementValue.value, requestStatement.value );
		}
	}
};

const getEntityPayload = function ( requestStatements ) {
	return {
		'wikibasereconcileedit-version': '0.0.1/minimal',
		statements: requestStatements
	};

};

const getBatchRequestPayload = function ( entityStatements, reconciliationPropertyId, token ) {
	const reconcile = {
		'wikibasereconcileedit-version': '0.0.1',
		urlReconcile: reconciliationPropertyId
	};

	return {
		reconcile: reconcile,
		entities: entityStatements.map( ( x ) => getEntityPayload( x ) ),
		token: token
	};
};

const getRequestPayload = function ( requestStatements, reconciliationPropertyId, token ) {
	const reconcile = {
		'wikibasereconcileedit-version': '0.0.1',
		urlReconcile: reconciliationPropertyId
	};

	return {
		reconcile: reconcile,
		entity: getEntityPayload( requestStatements ),
		token: token
	};
};

module.exports = {
	assertRequestStatements,
	getRequestPayload,
	getOrCreateProperty,
	getStatementValue,
	getBatchRequestPayload
};
