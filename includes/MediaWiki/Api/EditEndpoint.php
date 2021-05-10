<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\FullWikibaseItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity\MinimalItemInput;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ExternalLinks;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\MockEditDiskRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\UrlInputEditRequest;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use TitleFactory;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\Store\IdGenerator;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class EditEndpoint extends SimpleHandler {

	public const VERSION_KEY = "wikibasereconcileedit-version";

	/** @var TitleFactory */
	private $titleFactory;

	/** @var MediawikiEditEntityFactory */
	private $editEntityFactory;

	/** @var EntityIdLookup */
	private $entityIdLookup;

	/** @var EntityLookup */
	private $entityLookup;

	/** @var EntityRevisionLookup */
	private $entityRevisionLookup;

	/** @var IdGenerator */
	private $idGenerator;

	/** @var PropertyDataTypeLookup */
	private $propertyDataTypeLookup;

	/** @var ExternalLinks */
	private $externalLinks;

	/** @var FullWikibaseItemInput */
	private $fullWikibaseItemInput;

	/** @var MinimalItemInput */
	private $minimalItemInput;

	public function __construct(
		TitleFactory $titleFactory,
		MediawikiEditEntityFactory $editEntityFactory,
		EntityIdLookup $entityIdLookup,
		EntityLookup $entityLookup,
		EntityRevisionLookup $entityRevisionLookup,
		IdGenerator $idGenerator,
		PropertyDataTypeLookup $propertyDataTypeLookup,
		ExternalLinks $externalLinks,
		FullWikibaseItemInput $fullWikibaseItemInput,
		MinimalItemInput $minimalItemInput
	) {
		$this->titleFactory = $titleFactory;
		$this->editEntityFactory = $editEntityFactory;
		$this->entityIdLookup = $entityIdLookup;
		$this->entityLookup = $entityLookup;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->idGenerator = $idGenerator;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->externalLinks = $externalLinks;
		$this->fullWikibaseItemInput = $fullWikibaseItemInput;
		$this->minimalItemInput = $minimalItemInput;
	}

	public static function factory(
		TitleFactory $titleFactory,
		ExternalLinks $externalLinks,
		FullWikibaseItemInput $fullWikibaseItemInput,
		MinimalItemInput $minimalItemInput
	): self {
		$repo = WikibaseRepo::getDefaultInstance();

		return new self(
			$titleFactory,
			$repo->newEditEntityFactory(),
			$repo->getEntityIdLookup(),
			$repo->getEntityLookup(),
			$repo->getEntityRevisionLookup(),
			$repo->newIdGenerator(),
			$repo->getPropertyDataTypeLookup(),
			$externalLinks,
			$fullWikibaseItemInput,
			$minimalItemInput
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
		if ( $this->propertyDataTypeLookup->getDataTypeIdForProperty( $reconcileUrlProperty ) !== 'url' ) {
			die( 'urlReconcile property must be of type url' );
		}

		// Get Item from input
		if ( !array_key_exists( self::VERSION_KEY, $request->entity() ) ) {
			die( 'entity input version must be specified in key ' . self::VERSION_KEY );
		}
		$inputEntityVersion = $request->entity()[self::VERSION_KEY];
		if ( $inputEntityVersion === '0.0.1/full' ) {
			$inputEntity = $this->fullWikibaseItemInput->getItem( $request );
		} elseif ( $inputEntityVersion === '0.0.1/minimal' ) {
			$inputEntity = $this->minimalItemInput->getItem( $request );
		} else {
			die( 'unknown entity input version' );
		}

		// Validate Entity
		// Don't support references, qualifiers
		foreach ( $inputEntity->getStatements()->toArray() as $statement ) {
			if ( $statement->getQualifiers()->count() !== 0 || $statement->getReferences()->count() !== 0 ) {
				die( 'Qualifiers and References are not currently supported' );
			}
		}
		// Check for our reconciliation value
		$reconciliationStatements = $inputEntity->getStatements()->getByPropertyId( $reconcileUrlProperty );
		if ( $reconciliationStatements->count() !== 1 ) {
			die( 'Entity must have at least one statement for the reconciliation Property' );
		}
		$reconciliationStatement = $reconciliationStatements->toArray()[0];
		if ( !$reconciliationStatement->getMainSnak() instanceof PropertyValueSnak ) {
			die( 'Reconciliation statement must be of type value ' );
		}
		/** @var PropertyValueSnak $reconciliationMainSnak */
		$reconciliationMainSnak = $reconciliationStatement->getMainSnak();
		/** @var StringValue $reconciliationDataValue */
		$reconciliationDataValue = $reconciliationMainSnak->getDataValue();
		$reconciliationUrl = $reconciliationDataValue->getValue();

		// Find Items that use the URL
		$itemIdsThatReferenceTheUrl = $this->getItemIdsFromPageIds(
			$this->externalLinks->pageIdsContainingUrl( $reconciliationUrl )
		);

		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = [];
		foreach ( $itemIdsThatReferenceTheUrl as $itemId ) {
			/** @var Item $item */
			$item = $this->entityLookup->getEntity( $itemId );
			foreach ( $item->getStatements()->getByPropertyId( $reconcileUrlProperty )->toArray() as $statement ) {
				if ( !$statement->getMainSnak() instanceof PropertyValueSnak ) {
					continue;
				}
				/** @var PropertyValueSnak $mainSnak */
				$mainSnak = $statement->getMainSnak();
				$urlOfStatement = $mainSnak->getDataValue()->getValue();
				if ( $urlOfStatement === $reconciliationUrl ) {
					$itemsThatReferenceTheUrlInCorrectStatement[] = $item;
				}
			}
		}

		// If we have more than one item matches, something is wrong and we can't edit
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) > 1 ) {
			die( 'Matched multiple Items during reconciliation :(' );
		}

		// Get our base
		if ( count( $itemsThatReferenceTheUrlInCorrectStatement ) === 1 ) {
			$base = $itemsThatReferenceTheUrlInCorrectStatement[0];
			// XXX: This bit is so annoying...
			$baseRevId = $this->entityRevisionLookup
				->getLatestRevisionId( $base->getId() )
				->onConcreteRevision( function ( $revId ) {
					return $revId;
				} )
				->onRedirect( function () {
					throw new \RuntimeException();
				} )
				->onNonexistentEntity( function () {
					throw new \RuntimeException();
				} )
				->map();
		} else {
			$base = new Item();
			$baseRevId = false;
			// XXX: this is a bit evil, but needed to work around the fact we want to mint statement guids
			$base->setId( ItemId::newFromNumber( $this->idGenerator->getNewId( 'wikibase-item' ) ) );
		}

		// And make the edit
		$toSave = ( new SimplePutStrategy() )->apply( $base, $inputEntity );
		$editEntity = $this->editEntityFactory->newEditEntity(
			// TODO use a real user
			\User::newSystemUser( 'WikibaseReconcileEditReconciliator' ),
			$toSave->getId(),
			$baseRevId
		);
		$saveStatus = $editEntity->attemptSave(
			$toSave,
			'Reconciliation Edit',
			$baseRevId ? null : EDIT_NEW,
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

	/**
	 * @param int[] $pageIds
	 * @return ItemId[]
	 */
	private function getItemIdsFromPageIds( array $pageIds ) : array {
		$titles = $this->titleFactory->newFromIDs( $pageIds );
		$entityIds = $this->entityIdLookup->getEntityIds( $titles );
		$itemIds = [];
		foreach ( $entityIds as $entityId ) {
			if ( $entityId instanceof ItemId ) {
				$itemIds[] = $entityId;
			}
		}
		return $itemIds;
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
