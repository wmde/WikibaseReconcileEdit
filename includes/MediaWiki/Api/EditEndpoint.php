<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Api;

use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\ItemId;

class EditEndpoint extends SimpleHandler {

	private const VERSION_KEY = "onorprot-version";

	public function run() {
		// TODO use different Request interfaces / real input data
		$repo = WikibaseRepo::getDefaultInstance();
		$request = new \MediaWiki\Extension\OnOrProt\MediaWiki\Request\MockEditDiskRequest();

		// Validate and process reconciliation input
		// TODO use different services per version
		// TODO output an object that controls the reconciliations spec?
		$rawReconcile = $request->reconcile( $this->getRequest() );
		$inputReconcile = json_decode( $rawReconcile, true );
		if ( !array_key_exists( self::VERSION_KEY, $inputReconcile ) || $inputReconcile[self::VERSION_KEY] !== '0.0.1' ) {
			die( 'Only supported reconciliation version is 0.0.1' );
		}
		if ( !array_key_exists( 'urlReconcile', $inputReconcile ) || !preg_match( PropertyId::PATTERN, $inputReconcile['urlReconcile'] ) ) {
			die( '0.0.1 requires a single urlReconcile key mapped to a property id, such as P123' );
		}
		$reconcileUrlProperty = new PropertyId( $inputReconcile['urlReconcile'] );
		// For now this property must be of URL type
		if ( $repo->getPropertyDataTypeLookup()->getDataTypeIdForProperty( $reconcileUrlProperty ) !== 'url' ) {
			die( 'urlReconcile property must be of type url' );
		}

		// Validate entity input
		$rawEntity = $request->entity( $this->getRequest() );
		$inputEntity = json_decode( $rawEntity, true );
		if ( !array_key_exists( self::VERSION_KEY, $inputEntity ) || $inputEntity[self::VERSION_KEY] !== '0.0.1' ) {
			die( 'Only supported entity version is 0.0.1' );
		}
		if ( !array_key_exists( 'type', $inputEntity ) || $inputEntity['type'] !== 'item' ) {
			die( 'Only supported entity type is \'item\'' );
		}
		$deserializer = $repo->getBaseDataModelDeserializerFactory()->newEntityDeserializer();
		/** @var Item $inputEntity */
		$inputEntity = $deserializer->deserialize( $inputEntity );

		// Validate Entity
		// Don't support references, qualifiers or sitelinks
		foreach ( $inputEntity->getStatements()->toArray() as $statement ) {
			if ( $statement->getQualifiers()->count() !== 0 || $statement->getReferences()->count() !== 0 ) {
				die( 'Qualifiers and References are not currently supported' );
			}
		}
		if ( $inputEntity->getSiteLinkList()->count() !== 0 ) {
			die( 'Sitelinks are not currently supported' );
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

		// Is this URL in the externallinks table?
		$externalLinkIndexes = \LinkFilter::makeIndexes( $reconciliationUrl );
		if ( count( $externalLinkIndexes ) !== 1 ) {
			die( 'Unexpected issue with LinkFilter return' );
		}
		$externalLinkIndex = $externalLinkIndexes[0];
		$db = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		// TODO join and query correct ns only etc?
		$externalLinksResult = $db->select(
			'externallinks',
			'el_from',
			[
				'el_index' => $externalLinkIndex
			],
			__METHOD__
		);
		// Find ItemIds from the external link matches
		$itemIdsThatReferenceTheUrl = [];
		foreach ( $externalLinksResult as $row ) {
			$pageId = $row->el_from;
			$title = \Title::newFromID( $pageId );
			$inputEntityId = $repo->getEntityIdLookup()->getEntityIdForTitle( $title );
			if ( $inputEntityId && $inputEntityId instanceof ItemId ) {
				$itemIdsThatReferenceTheUrl[] = $inputEntityId;
			}
		}
		// Find Items that match the URL and Property ID
		$itemsThatReferenceTheUrlInCorrectStatement = [];
		foreach ( $itemIdsThatReferenceTheUrl as $itemId ) {
			/** @var Item $item */
			$item = $repo->getEntityLookup()->getEntity( $itemId );
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
			$baseRevId = $repo->getEntityRevisionLookup()
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
		}

		// Modify the base..
		// merge labels
		$base->getFingerprint()->getLabels()->addAll( $inputEntity->getFingerprint()->getLabels()->getIterator() );
		// merge, descriptions
		$base->getFingerprint()->getDescriptions()->addAll( $inputEntity->getFingerprint()->getDescriptions()->getIterator() );
		// merge, aliases
		// TODO such merging logic would be good to have in DataModel?
		foreach ( $inputEntity->getFingerprint()->getAliasGroups()->getIterator() as $inputAliasGroup ) {
			$language = $inputAliasGroup->getLanguageCode();
			$base->getFingerprint()->getAliasGroups()->setAliasesForLanguage(
				$language,
				array_unique( array_merge(
					$base->getFingerprint()->getAliasGroups()->getByLanguage( $language )->getAliases(),
					$inputEntity->getFingerprint()->getAliasGroups()->getByLanguage( $language )->getAliases()
				) )
			);
		}
		// set, statements
		// TODO use correct statement GUIDs
		$base->setStatements( $inputEntity->getStatements() );

		// And make the edit
		$editEntity = $repo->newEditEntityFactory()->newEditEntity(
			\User::newSystemUser( 'OnOrProtReconciliator' ), // TODO use a real user
			$base->getId(),
			$baseRevId
		);
		$saveStatus = $editEntity->attemptSave(
			$base,
			'Reconciliation Edit',
			$base->getId() ? null : EDIT_NEW,
			false // TODO actually do a token check?
		);

		// Make some sort of response
		$response = [
			'success' => $saveStatus->isGood()
		];
		return json_encode( $response );
	}

	public function needsWriteAccess() {
		return true;
	}

	public function getParamSettings() {
		return [
			'entity' => [
				self::PARAM_SOURCE => 'post',
				//ParamValidator::PARAM_TYPE => 'upload',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reconcile' => [
				self::PARAM_SOURCE => 'post',
				//ParamValidator::PARAM_TYPE => 'upload',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
