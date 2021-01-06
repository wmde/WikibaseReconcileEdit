<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Api\EditEndpoint;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\WikibaseRepo;

class FullWikibaseItemInput {

	/**
	 * @var Deserializer
	 */
	private $deserializer;

	/**
	 * @param Deserializer|null $deserializer a deserializer for full Item JSON
	 */
	public function __construct( ?Deserializer $deserializer = null ) {
		$this->deserializer = $deserializer;
		if ( !$this->deserializer ) {
			$this->deserializer = WikibaseRepo::getDefaultInstance()
				->getBaseDataModelDeserializerFactory()
				->newEntityDeserializer();
		}
	}

	/**
	 * @param EditRequest $request
	 * @return Item
	 */
	public function getItem( EditRequest $request ) : Item {
		$inputEntity = $request->entity();
		if ( $inputEntity === null ) {
			die( 'Invalid entity JSON supplied' );
		}
		// TODO this version check stuff shouldn't happen here..
		if (
			!array_key_exists( EditEndpoint::VERSION_KEY, $inputEntity ) ||
			$inputEntity[EditEndpoint::VERSION_KEY] !== '0.0.1' ) {
			die( 'Only supported entity version is 0.0.1' );
		}
		if ( !array_key_exists( 'type', $inputEntity ) || $inputEntity['type'] !== 'item' ) {
			die( 'Only supported entity type is \'item\'' );
		}

		return $this->deserializer->deserialize( $inputEntity );
	}

}
