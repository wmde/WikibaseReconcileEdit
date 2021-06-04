<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Session\SessionProviderInterface;
use RequestContext;
use User;
use Wikibase\Lib\Store\EntityRevision;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @license GPL-2.0-or-later
 */
class SingleEditEndpoint extends EditEndpoint {

	public function __construct(
		EditRequestParser $editRequestParser,
		EditRequestSaver $editRequestSaver,
		User $user,
		SessionProviderInterface $sessionProvider
	) {
		parent::__construct( $editRequestParser, $editRequestSaver, $user, $sessionProvider );
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		EditRequestSaver $editRequestSaver
	): self {
		return new self(
			$editRequestParser,
			$editRequestSaver,
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

		$saveStatus = $this->editRequestSaver->persistEdits( [ $request ], $this->editToken, $this->user );

		$response = $this->getResponseBody( $saveStatus );

		if ( $saveStatus->isOK() ) {
			/** @var EntityRevision $entityRevision */
			$entityRevision = $saveStatus->getValue()[0]['revision'];
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
