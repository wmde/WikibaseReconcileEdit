<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Rest\LocalizedHttpException;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
class EditRequestParser {

	public const VERSION_KEY = "wikibasereconcileedit-version";

	private const SUPPORTED_ENTITY_VERSIONS = [ '0.0.1/full', '0.0.1/minimal' ];
	private const SUPPORTED_RECONCILE_VERSIONS = [ '0.0.1' ];

	/** @var PropertyDataTypeLookup */
	private $propertyDataTypeLookup;

	/** @var FullWikibaseItemInput */
	private $fullWikibaseItemInput;

	/** @var MinimalItemInput */
	private $minimalItemInput;

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup,
		FullWikibaseItemInput $fullWikibaseItemInput,
		MinimalItemInput $minimalItemInput
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->fullWikibaseItemInput = $fullWikibaseItemInput;
		$this->minimalItemInput = $minimalItemInput;
	}

	private function parseReconcilePropertyId( array $requestBody ): PropertyId {
		$reconcile = $requestBody['reconcile'];

		if ( !is_array( $reconcile ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-parameter' ),
				400
			);
		}

		if (
			!array_key_exists( self::VERSION_KEY, $reconcile ) ||
			!in_array( $reconcile[self::VERSION_KEY], self::SUPPORTED_RECONCILE_VERSIONS )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version' )
					->textListParams( self::SUPPORTED_RECONCILE_VERSIONS )
					->numParams( count( self::SUPPORTED_RECONCILE_VERSIONS ) ),
				400
			);
		}

		if (
			!array_key_exists( 'urlReconcile', $reconcile ) ||
			!preg_match( PropertyId::PATTERN, $reconcile['urlReconcile'] )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid' )
					->textParams( $reconcile[self::VERSION_KEY], 'urlReconcile' ),
				400
			);
		}
		$reconcilePropertyId = new PropertyId( $reconcile['urlReconcile'] );

		$datatype = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $reconcilePropertyId );
		if ( $datatype !== 'url' ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-type-property-must-be-url' )
					->textParams( 'urlReconcile', $reconcilePropertyId->getSerialization(), $datatype ),
				400
			);
		}

		return $reconcilePropertyId;
	}

	private function parseEditRequest( $entity, PropertyId $reconcilePropertyId ): EditRequest {
		if (
			!is_array( $entity ) ||
			!array_key_exists( self::VERSION_KEY, $entity )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unspecified-entity-input-version' ),
				400
			);
		}
		$inputEntityVersion = $entity[self::VERSION_KEY];
		if ( $inputEntityVersion === '0.0.1/full' ) {
			$inputEntity = $this->fullWikibaseItemInput->getItem( $entity );
			$otherItems = [];
		} elseif ( $inputEntityVersion === '0.0.1/minimal' ) {
			[ $inputEntity, $otherItems ] = $this->minimalItemInput->getItem( $entity, $reconcilePropertyId );
		} else {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-entity-input-version' )
					->textParams( $inputEntityVersion )
					->textListParams( self::SUPPORTED_ENTITY_VERSIONS )
					->numParams( count( self::SUPPORTED_ENTITY_VERSIONS ) ),
				400
			);
		}

		return new EditRequest(
			$reconcilePropertyId,
			$inputEntity,
			$otherItems,
		);
	}

	/** @return EditRequest[] */
	public function parseBatchRequestBody( array $requestBody ): array {
		$reconcilePropertyId = $this->parseReconcilePropertyId( $requestBody );
		$editRequests = [];
		$entities = $requestBody['entities'];

		foreach ( $entities as $entity ) {
			$editRequests[] = $this->parseEditRequest( $entity, $reconcilePropertyId );
		}

		return $editRequests;
	}

	public function parseRequestBody( array $requestBody ): EditRequest {
		$reconcilePropertyId = $this->parseReconcilePropertyId( $requestBody );
		$entity = $requestBody['entity'] ?: null;

		return $this->parseEditRequest( $entity, $reconcilePropertyId );
	}

}
