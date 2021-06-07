<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciledItem;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ReconciliationServiceItem;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use Status;
use User;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;

/**
 * Service for saving entities
 *
 * @license GPL-2.0-or-later
 */
class EditRequestSaver {

	/** @var MediawikiEditEntityFactory */
	protected $editEntityFactory;

	/** @var ItemReconciler */
	protected $itemReconciler;

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		ItemReconciler $itemReconciler
	) {
		$this->editEntityFactory = $editEntityFactory;
		$this->itemReconciler = $itemReconciler;
	}

	/**
	 * @param EditRequest[] $requests
	 * @param string $editToken
	 * @param User $user
	 * @return Status
	 * @throws ReconciliationException
	 */
	public function persistEdits( array $requests, string $editToken, User $user ): Status {
		$status = Status::newGood( [] );
		foreach ( $requests as $request ) {
			$reconciledItem = $this->itemReconciler->reconcileItem(
				$request->entity(),
				$request->reconcilePropertyId()
			);
			$otherItems = $request->otherItems();

			$otherStatus = $this->persistItem( $reconciledItem, $otherItems, $editToken, $user );
			$status->merge( $otherStatus );

			if ( !$otherStatus->isOk() ) {
				break;
			}
			$status->setResult( true, array_merge( $status->getValue(), [ $otherStatus->getValue() ] ) );
		}

		return $status;
	}

	/**
	 * @param ReconciledItem $reconciledItem
	 * @param ReconciliationServiceItem[] $otherItems
	 * @return Status
	 */
	private function persistItem(
		ReconciledItem $reconciledItem,
		array $otherItems,
		string $editToken,
		User $user
	): Status {
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
				$user,
				$otherItem->getItem()->getId(),
				false
			);

			$saveStatus->merge( $otherItemEdit->attemptSave(
				$otherItem->getItem(),
				'Reconciliation Edit',
				EDIT_NEW,
				$editToken
			), true );

			if ( !$saveStatus->isOK() ) {
				break;
			}

			$otherItem->updateFromEntityRevision( $saveStatus->getValue()['revision'] );
		}

		if ( $saveStatus->isOK() ) {

			$editEntity = $this->editEntityFactory->newEditEntity(
				$user,
				$toSave->getId(),
				$reconciledItem->getBaseRevisionId()
			);

			$saveStatus->merge( $editEntity->attemptSave(
				$toSave,
				'Reconciliation Edit',
				$reconciledItem->isNew() ? EDIT_NEW : EDIT_UPDATE,
				$editToken
			), true );

			if ( $saveStatus->isOK() ) {
				$reconciledItem->finish( $saveStatus->getValue()['revision'] );
			}
		}

		return $saveStatus;
	}

}
