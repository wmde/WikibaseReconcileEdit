<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\Reconciliation\ItemReconciler;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Session\SessionProviderInterface;
use RequestContext;
use User;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\EditEntity\MediawikiEditEntityFactory;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class SingleEditEndpoint extends EditEndpoint {

	public function __construct(
		MediawikiEditEntityFactory $editEntityFactory,
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler,
		User $user,
		SessionProviderInterface $sessionProvider
	) {
		parent::__construct( $editEntityFactory, $editRequestParser, $itemReconciler, $user, $sessionProvider );
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		ItemReconciler $itemReconciler
	): self {
		$repo = WikibaseRepo::getDefaultInstance();
		$editEntityFactory = method_exists( $repo, 'getEditEntityFactory' )
			? $repo->getEditEntityFactory() // 1.36+
			: $repo->newEditEntityFactory(); // 1.35

		return new self(
			$editEntityFactory,
			$editRequestParser,
			$itemReconciler,
			// @TODO Inject this, when there is a good way to do that
			RequestContext::getMain()->getUser(),
			RequestContext::getMain()->getRequest()->getSession()->getProvider()
		);
	}

	public function run() {
		parent::run();

		// Parse the request body
		try {
			$request = $this->editRequestParser->parseRequestBody( $this->requestBody );
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

		$saveStatus = $this->persistItem( $reconciledItem, $otherItems );

		$response = $this->getResponseBody( $saveStatus );

		if ( $saveStatus->isGood() ) {
			/** @var EntityRevision $entityRevision */
			$entityRevision = $saveStatus->getValue()['revision'];
			$response['entityId'] = $entityRevision->getEntity()->getId()->getSerialization();
			$response['revisionId'] = $entityRevision->getRevisionId();
		}

		return $response;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyValidator( $contentType ) {
		$this->validateContentType( $contentType );

		return new JsonBodyValidator( [
			'entity' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reconcile' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		] );
	}
}
