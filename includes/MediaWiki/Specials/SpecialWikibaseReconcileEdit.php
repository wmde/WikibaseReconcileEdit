<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Specials;

use HTMLForm;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestParser;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequestSaver;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use MediaWiki\Message\Converter;
use MediaWiki\Rest\LocalizedHttpException;
use SpecialPage;
use Status;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\EntityUrlLookup;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 */
class SpecialWikibaseReconcileEdit extends SpecialPage {

	/** @var string */
	private $reconcileDefaultJSON;

	/** @var string */
	private	$entityDefaultJSON;

	/** @var EditRequestParser */
	private $editRequestParser;

	/** @var EditRequestSaver */
	private $editRequestSaver;

	/** @var EntityUrlLookup */
	private $entityUrlLookup;

	public function __construct(
		EditRequestParser $editRequestParser,
		EditRequestSaver $editRequestSaver,
		EntityUrlLookup $entityUrlLookup
	) {
		parent::__construct( 'WikibaseReconcileEdit' );

		$this->editRequestParser = $editRequestParser;
		$this->editRequestSaver = $editRequestSaver;
		$this->entityUrlLookup = $entityUrlLookup;

		$propertyId = new PropertyId( 'P1' );

		$this->reconcileDefaultJSON = json_encode( [
			EditRequestParser::VERSION_KEY => '0.0.1',
			'urlReconcile' => $propertyId->serialize(),
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$this->entityDefaultJSON = json_encode( [
			EditRequestParser::VERSION_KEY => '0.0.1/minimal',
			'statements' => [
				[
					'property' => $propertyId->serialize(),
					'value' => "http://example.com/",
				],
			],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	public static function factory(
		EditRequestParser $editRequestParser,
		EditRequestSaver $editRequestSaver
	): self {
		return new self(
			$editRequestParser,
			$editRequestSaver,
			WikibaseRepo::getDefaultInstance()->getEntityUrlLookup()
		);
	}

	public function getDescription(): string {
		return $this->msg( 'wikibasereconcileedit-special-description' )->text();
	}

	protected function getGroupName(): string {
		return 'wikibase';
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->requireLogin();
		$this->showRequestForm();

		$out = $this->getOutput();
		$out->addModules( 'ext.WikibaseReconcileEdit' );
	}

	public function submitForm( $data ) {
		$data['reconcile'] = json_decode( $data['reconcile'], true );
		$data['entity'] = json_decode( $data['entity'], true );

		try {
			$request = $this->editRequestParser->parseRequestBody( $data );
			$status = $this->editRequestSaver->persistEdits(
				[ $request ],
				$data['token'],
				$this->getContext()->getUser()
			);
		} catch ( LocalizedHttpException | ReconciliationException $exception ) {
			return Status::newFatal(
				( new Converter() )->convertMessageValue( $exception->getMessageValue() )
			);
		}

		/** @var EntityRevision $entityRevision */
		$entityRevision = $status->getValue()[0]['revision'];

		$entityUrl = $this->entityUrlLookup
			->getFullUrl( $entityRevision->getEntity()->getId() );

		if ( $entityUrl ) {
			$this->getOutput()->redirect( $entityUrl );
		}
	}

	private function showRequestForm() {
		$form = HTMLForm::factory(
			'ooui',
			$this->getFormFields(),
			$this->getContext(),
		);

		$form->setSubmitCallback( [ $this, 'submitForm' ] );

		$form->show();
	}

	private function getFormFields(): array {
		$fields = [];

		$fields['token'] = [
			'type' => 'hidden',
			'maxlength' => 255,
			'required' => true,
			'default' => $this->getContext()->getUser()->getEditToken(),
			'name' => 'token'
		];

		$fields['reconcile'] = [
			'type' => 'text',
			'label-message' => 'wikibasereconcileedit-special-reconcile-label',
			'required' => true,
			'default' => $this->reconcileDefaultJSON,
			'name' => 'reconcile'

		];

		$fields['entity'] = [
			'type' => 'textarea',
			'label-message' => 'wikibasereconcileedit-special-entity-label',
			'required' => true,
			'default' => $this->entityDefaultJSON,
			'name' => 'entity'
		];

		return $fields;
	}
}
