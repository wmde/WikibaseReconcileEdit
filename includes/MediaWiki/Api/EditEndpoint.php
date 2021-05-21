<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use DataValues\StringValue;
use MediaWiki\Extension\WikibaseReconcileEdit\EditStrategy\SimplePutStrategy;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationException;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\ReconciliationService;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use Status;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class EditEndpoint extends SimpleHandler {

	/** @var MediawikiEditEntityFactory */
	private $editEntityFactory;

	/** @var EditRequestParser */
	private $editRequestParser;

	/** @var ReconciliationService */
	private $reconciliationService;

	/** @var SimplePutStrategy */
	private $simplePutStrategy;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		EditRequestParser $editRequestParser,
		ReconciliationService $reconciliationService,
		SimplePutStrategy $simplePutStrategy
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->editRequestParser = $editRequestParser;
		$this->reconciliationService = $reconciliationService;
		$this->simplePutStrategy = $simplePutStrategy;
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		ReconciliationService $reconciliationService,
		SimplePutStrategy $simplePutStrategy
	): self {
		$repo = WikibaseRepo::getDefaultInstance();

		return new self(
			$repo->newEditEntityFactory(),
			$editRequestParser,
			$reconciliationService,
			$simplePutStrategy
		);
	}

	public function run() {
		// Get the request
		try {
			$request = $this->editRequestParser->parseRequestInterface( $this->getRequest() );
		} catch ( ReconciliationException $rex ) {
			throw new LocalizedHttpException( $rex->getMessageValue(), 400 );
		}

		$reconcileUrlProperty = $request->reconcilePropertyId();
		$inputEntity = $request->entity();
		$otherItems = $request->otherItems();

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

		try {
			$reconciliationItem = $this->reconciliationService->getOrCreateItemByStatementUrl(
				$reconcileUrlProperty,
				$reconciliationUrl
			);
		} catch ( ReconciliationException $rex ) {
			throw new LocalizedHttpException( $rex->getMessageValue(), 400 );
		}

		// And make the edit
		$toSave = $this->simplePutStrategy->apply( $reconciliationItem->getItem(), $inputEntity );
		$saveStatus = Status::newGood();

		foreach ( $otherItems as $otherItem ) {
			// don't need to save this again
			if ( $otherItem->getRevision() ) {
				continue;
			}

			// The base item references itself through a statement
			// It will be saved at a later stage so no need to do it here
			if ( $otherItem->getItem() === $toSave ) {
				continue;
			}

			$otherItemEdit = $this->editEntityFactory->newEditEntity(
				// TODO use a real user
				\User::newSystemUser( 'WikibaseReconcileEditReconciliator' ),
				$otherItem->getItem()->getId(),
				false
			);

			$saveStatus->merge( $otherItemEdit->attemptSave(
				$otherItem->getItem(),
				'Reconciliation Edit',
				EDIT_NEW,
				// TODO actually do a token check?
				false
			), true );
			if ( !$saveStatus->isOK() ) {
				break;
			}
		}

		$editEntity = $this->editEntityFactory->newEditEntity(
			// TODO use a real user
			\User::newSystemUser( 'WikibaseReconcileEditReconciliator' ),
			$toSave->getId(),
			$reconciliationItem->getRevision()
		);

		if ( $saveStatus->isOK() ) {
			$saveStatus->merge( $editEntity->attemptSave(
				$toSave,
				'Reconciliation Edit',
				$reconciliationItem->getRevision() ? null : EDIT_NEW,
				// TODO actually do a token check?
				false
			), true );
		}

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

		return $response;
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
