<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api;

use ApiMessage;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Session\SessionProviderInterface;
use Status;
use User;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
abstract class EditEndpoint extends SimpleHandler {

	/** @var EditRequestParser */
	protected $editRequestParser;

	/** @var EditRequestSaver */
	protected $editRequestSaver;

	/** @var User */
	protected $user;

	/** @var SessionProviderInterface */
	protected $sessionProvider;

	/** @var array */
	protected $requestBody;

	/** @var string */
	protected $editToken;

	public function __construct(
		EditRequestParser $editRequestParser,
		EditRequestSaver $editRequestSaver,
		User $user,
		SessionProviderInterface $sessionProvider
	) {
		$this->editRequestParser = $editRequestParser;
		$this->editRequestSaver = $editRequestSaver;
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

	public function needsWriteAccess() {
		return true;
	}

	/**
	 * Returns the base for the response body
	 * @param Status $status
	 * @return array
	 */
	public function getResponseBody( Status $status ): array {
		$response = [
			'success' => $status->isGood(),
			'errors' => []
		];

		$errors = $status->getErrors();
		if ( !empty( $errors ) ) {
			$response['errors'] = array_map( function ( $errorMessage ) {
				$msg = ApiMessage::create( $errorMessage );
				return [
					'code' => $msg->getApiCode(),
					'text' => $msg->text()
				];
			}, $errors );
		}

		return $response;
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
