<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\MockEditDiskRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\UrlInputEditRequest;
use MediaWiki\Extension\WikibaseReconcileEdit\Wikibase\ReconciliationService;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikibase\Lib\Store\EntityIdLookup;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class EditEndpoint extends SimpleHandler {

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup;

	private $reconciliationService;

	public function __construct(
		ReconciliationService $reconciliationService
	) {
		$this->reconciliationService = $reconciliationService;
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
		// TODO inject these services
		$repo = WikibaseRepo::getDefaultInstance();
		$this->entityIdLookup = $repo->getEntityIdLookup();

		// Get the request
		$request = $this->getEditRequest();

		// Validate and process reconciliation input
		// TODO use different services per version
		// TODO output an object that controls the reconciliations spec?
		if ( $request->reconcile() === null ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'wikibasereconcileedit-editendpoint-invalid-reconcile-json' ),
				400
			);
		}
		[ $baseRevId, $toSave ] = $this->reconciliationService->reconcile( $request );

		$editEntity = $repo->newEditEntityFactory()->newEditEntity(
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
