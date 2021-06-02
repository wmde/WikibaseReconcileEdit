<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciledItem;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Session\SessionProviderInterface;
use Status;
use User;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
abstract class EditEndpoint extends SimpleHandler {

	/** @var MediawikiEditEntityFactory */
	protected $editEntityFactory;

	/** @var EditRequestParser */
	protected $editRequestParser;

	/** @var ItemReconciler */
	protected $itemReconciler;

	/** @var User */
	protected $user;

	/** @var SessionProviderInterface */
	protected $sessionProvider;

	/** @var array */
	protected $requestBody;

	/** @var string */
	protected $editToken;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler,
		User $user,
		SessionProviderInterface $sessionProvider
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->editRequestParser = $editRequestParser;
		$this->itemReconciler = $itemReconciler;
		$this->user = $user;
		$this->sessionProvider = $sessionProvider;
	}

	public function run() {
		// Rest Validator returns null if submitted as form
		$this->requestBody = $this->getValidatedBody();

		if ( $this->requestBody === null ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-invalid-json-request-body' ),
				415
			);
		}

		$this->editToken = $this->getEditToken( $this->requestBody );

		if ( !$this->user->isRegistered() || !$this->user->matchEditToken( $this->editToken ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-unauthorized-access' ),
				403
			);
		}
	}

	/**
	 * @param ReconciledItem $reconciledItem
	 * @param ReconciliationServiceItem[] $otherItems
	 * @return Status
	 */
	public function persistItem( ReconciledItem $reconciledItem, array $otherItems ): Status {
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
				$this->user,
				$otherItem->getItem()->getId(),
				false
			);

			$saveStatus->merge( $otherItemEdit->attemptSave(
				$otherItem->getItem(),
				'Reconciliation Edit',
				EDIT_NEW,
				$this->editToken
			), true );
			if ( !$saveStatus->isOK() ) {
				break;
			}

			$otherItem->updateFromEntityRevision( $saveStatus->getValue()['revision'] );
		}

		$editEntity = $this->editEntityFactory->newEditEntity(
			$this->user,
			$toSave->getId(),
			$reconciledItem->getBaseRevisionId()
		);

		if ( $saveStatus->isOK() ) {
			$saveStatus->merge( $editEntity->attemptSave(
				$toSave,
				'Reconciliation Edit',
				$reconciledItem->isNew() ? EDIT_NEW : EDIT_UPDATE,
				$this->editToken
			), true );

			if ( $saveStatus->isOK() ) {
				$reconciledItem->finish( $saveStatus->getValue()['revision'] );
			}
		}

		return $saveStatus;
	}

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * Determines the CSRF token to be used when making edits
	 * @param array $body
	 * @return string
	 */
	private function getEditToken( array $body ) {
		if ( $this->sessionProvider->safeAgainstCsrf() ) {
			return $this->user->getEditToken();
		} else {
			return $body['token'] ?? '';
		}
	}

	protected function validateContentType( string $contentType ): void {
		if ( $contentType !== 'application/json' ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-invalid-content-type' )
					->textParams( 'application/json' ),
				415
			);
		}
	}
}
