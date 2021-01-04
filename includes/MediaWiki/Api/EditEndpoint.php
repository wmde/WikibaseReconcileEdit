<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Api;

use MediaWiki\Extension\OnOrProt\Model\Input;
use MediaWiki\Extension\OnOrProt\Model\Schema;
use MediaWiki\Extension\OnOrProt\Wikibase\ItemGenerator;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;
use Wikibase\DataModel\Entity\Item;

class EditEndpoint extends SimpleHandler {

	public function run() {
		// TODO use different Request interfaces
		$request = new \MediaWiki\Extension\OnOrProt\MediaWiki\Request\MockEditDiskRequest();
		$rawEntity = $request->entity( $this->getRequest() );
		$rawReconcile = $request->reconcile( $this->getRequest() );

		var_dump($rawReconcile);
		die();

		/**
		 * 1) INPUT to the API from user
		 * Could be actual files, or could be URls?
		 * Could also not be CSV, and we could provide automatic ways to transform other file types to CSV / whatever we use internally.
		 */
		$schema = new Schema( $rawSchema );
		$input = new Input( $rawInput, $schema->csvHasHeaders );

		/**
		 * 2) MAP the input data to Wikibase Item / concpets
		 * Might want properties too in the future?
		 */
		$itemGenerator = new ItemGenerator();
		$items = $itemGenerator->generate( $schema, $input );

		/**
		 * 3) Perform reconciliation & set the ID if it is know?
		 * Based on the infomation that is provided in the schema file.
		 * This will initially be using the query service?
		 * This might in the future include PHP based checks? and or Elastic search lookups if possible?
		 */
		// TODO

		/**
		 * 4) Make the edit
		 * Either by a) creating a new entity?
		 * b) Overriding what exists on the existing item with what was sent in the input?
		 * TODO maybe strategy for this needs to be defined in the schema?
		 */

		/**
		 * 5) Output stuff to the user?
		 */
		$rawOutput = [];
		foreach ( $items as $item ) {
			$rawOutput[] = var_export( $item, true );
		}
		return $rawOutput;
	}

	public function needsWriteAccess() {
		return true;
	}

	public function getParamSettings() {
		return [
			'input' => [
				self::PARAM_SOURCE => 'post',
				ParamValidator::PARAM_TYPE => 'upload',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'schema' => [
				self::PARAM_SOURCE => 'post',
				ParamValidator::PARAM_TYPE => 'upload',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
