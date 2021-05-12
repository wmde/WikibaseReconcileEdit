<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\MockEditDiskRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\UrlInputEditRequest;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class EditEndpoint extends SimpleHandler {

	public const VERSION_KEY = "wikibasereconcileedit-version";

	/** @var MediawikiEditEntityFactory */
	private $editEntityFactory;

	/** @var PropertyDataTypeLookup */
	private $propertyDataTypeLookup;

	/** @var ReconciliationService */
	private $reconciliationService;

	/** @var FullWikibaseItemInput */
	private $fullWikibaseItemInput;

	/** @var MinimalItemInput */
	private $minimalItemInput;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		FullWikibaseItemInput $fullWikibaseItemInput,
		MinimalItemInput $minimalItemInput,
		ReconciliationService $reconciliationService
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->reconciliationService = $reconciliationService;
		$this->fullWikibaseItemInput = $fullWikibaseItemInput;
		$this->minimalItemInput = $minimalItemInput;
	}

	public static function factory(
		FullWikibaseItemInput $fullWikibaseItemInput,
		MinimalItemInput $minimalItemInput,
		ReconciliationService $reconciliationService
	): self {
		$repo = WikibaseRepo::getDefaultInstance();

		return new self(
			$repo->newEditEntityFactory(),
			$repo->getPropertyDataTypeLookup(),
			$fullWikibaseItemInput,
			$minimalItemInput,
			$reconciliationService
		);
	}

	/**
	 * Get an EditRequest object from the current request
	 * @return EditRequest
	 */
	private function getEditRequest() : EditRequest {
		if ( isset( $_SERVER[ 'HTTP_X_WIKIBASERECONCILEEDIT_USE_DISK_REQUEST' ] ) ) {
			return new MockEditDiskRequest();
		}
		return new UrlInputEditRequest( $this->getRequest() );
	}

	public function run() {
		// Get the request
		$request = $this->getEditRequest();

		// Validate and process reconciliation input
		// TODO use different services per version
		// TODO output an object that controls the reconciliations spec?
		$inputReconcile = $request->reconcile();
		if ( $inputReconcile === null ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-json' ),
				400
			);
		}
		$supportedReconciliationVersions = [ '0.0.1' ];
		if (
			!array_key_exists( self::VERSION_KEY, $inputReconcile ) ||
			!in_array( $inputReconcile[self::VERSION_KEY], $supportedReconciliationVersions )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unsupported-reconcile-version' )
					->textListParams( $supportedReconciliationVersions )
					->numParams( count( $supportedReconciliationVersions ) ),
				400
			);
		}
		if (
			!array_key_exists( 'urlReconcile', $inputReconcile ) ||
			!preg_match( PropertyId::PATTERN, $inputReconcile['urlReconcile'] )
		) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-propertyid' )
					->textParams( $inputReconcile[self::VERSION_KEY], 'urlReconcile' ),
				400
			);
		}
		$reconcileUrlProperty = new PropertyId( $inputReconcile['urlReconcile'] );
		// For now this property must be of URL type
		$datatypeReconcileProperty = $this->propertyDataTypeLookup->getDataTypeIdForProperty( $reconcileUrlProperty );
		if ( $datatypeReconcileProperty !== 'url' ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-type-property-must-be-url' )
				->textParams( 'urlReconcile', $inputReconcile['urlReconcile'], $datatypeReconcileProperty ),
				400
			);
		}

		// Get Item from input
		if ( !array_key_exists( self::VERSION_KEY, $request->entity() ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-unspecified-entity-input-version' ),
				400
			);
		}
		$supportedEntityVersions = [ '0.0.1/full', '0.0.1/minimal' ];
		$inputEntityVersion = $request->entity()[self::VERSION_KEY];
		if ( $inputEntityVersion === '0.0.1/full' ) {
			$inputEntity = $this->fullWikibaseItemInput->getItem( $request );
		} elseif ( $inputEntityVersion === '0.0.1/minimal' ) {
			$inputEntity = $this->minimalItemInput->getItem( $request );
		} else {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-entity-input-version' )
					->textParams( $inputEntityVersion )
					->textListParams( $supportedEntityVersions )
					->numParams( count( $supportedEntityVersions ) ),
				400
			);
		}

		// Validate Entity
		// Don't support references, qualifiers
		foreach ( $inputEntity->getStatements()->toArray() as $statement ) {
			if ( $statement->getQualifiers()->count() !== 0 || $statement->getReferences()->count() !== 0 ) {
				throw new LocalizedHttpException(
					MessageValue::new( 'wikibasereconcileedit-editendpoint-qualifiers-references-not-supported' ),
					400
				);
			}
		}
		// Check for our reconciliation value
		$reconciliationStatements = $inputEntity->getStatements()->getByPropertyId( $reconcileUrlProperty );
		if ( $reconciliationStatements->count() !== 1 ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-reconciliation-property-missing-in-statements' ),
				400
			);
		}
		$reconciliationStatement = $reconciliationStatements->toArray()[0];
		if ( !$reconciliationStatement->getMainSnak() instanceof PropertyValueSnak ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconciliation-statement-type' ),
				400
			);
		}

		/** @var PropertyValueSnak $reconciliationMainSnak */
		$reconciliationMainSnak = $reconciliationStatement->getMainSnak();
		/** @var StringValue $reconciliationDataValue */
		$reconciliationDataValue = $reconciliationMainSnak->getDataValue();
		$reconciliationUrl = $reconciliationDataValue->getValue();

		$reconciliationItem = $this->reconciliationService->getOrCreateItemByStatementUrl(
			$reconcileUrlProperty,
			$reconciliationUrl
		);

		// And make the edit
		$toSave = ( new SimplePutStrategy() )->apply( $reconciliationItem->getItem(), $inputEntity );
		$editEntity = $this->editEntityFactory->newEditEntity(
			// TODO use a real user
			\User::newSystemUser( 'WikibaseReconcileEditReconciliator' ),
			$toSave->getId(),
			$reconciliationItem->getRevision()
		);
		$saveStatus = $editEntity->attemptSave(
			$toSave,
			'Reconciliation Edit',
			$reconciliationItem->getRevision() ? null : EDIT_NEW,
			// TODO actually do a token check?
			false
		);

		// Make some sort of response
		$response = [
			'success' => $saveStatus->isGood()
		];
		if ( $saveStatus->isGood() ) {
			/** @var EntityRevision $entityRevision */
			$entityRevision = $saveStatus->getValue()['revision'];
			$response['entityId'] = $entityRevision->getEntity()->getId()->getSerialization();
			$response['revisionId'] = $entityRevision->getRevisionId();
		}
		return json_encode( $response );
	}

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 * @return array
	 */
	public function getParamSettings() {
		return [
			'entity' => [
				self::PARAM_SOURCE => 'post',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reconcile' => [
				self::PARAM_SOURCE => 'post',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
