<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use Status;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class EditEndpoint extends SimpleHandler {

	/** @var MediawikiEditEntityFactory */
	private $editEntityFactory;

	/** @var EditRequestParser */
	private $editRequestParser;

	/** @var ItemReconciler */
	private $itemReconciler;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->editRequestParser = $editRequestParser;
		$this->itemReconciler = $itemReconciler;
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler
	): self {
		$repo = WikibaseRepo::getDefaultInstance();

		return new self(
			$repo->newEditEntityFactory(),
			$editRequestParser,
			$itemReconciler
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

		// Reconcile the item
		try {
			$reconciledItem = $this->itemReconciler->reconcileItem(
				$inputEntity,
				$reconcileUrlProperty
			);
		} catch ( ReconciliationException $rex ) {
			throw new LocalizedHttpException( $rex->getMessageValue(), 400 );
		}

		// And make the edit
		$toSave = $reconciledItem->getItem();
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
			$reconciledItem->getBaseRevisionId()
		);

		if ( $saveStatus->isOK() ) {
			$saveStatus->merge( $editEntity->attemptSave(
				$toSave,
				'Reconciliation Edit',
				$reconciledItem->isNew() ? EDIT_NEW : EDIT_UPDATE,
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
