<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request\EditRequest;
use Wikibase\DataModel\Entity\Item;

class FullWikibaseItemInput {

	/**
	 * @var Deserializer
	 */
	private $deserializer;

	/**
	 * @param Deserializer $deserializer a deserializer for full Item JSON
	 */
	public function __construct( Deserializer $deserializer ) {
		$this->deserializer = $deserializer;
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
		if ( !array_key_exists( 'type', $inputEntity ) || $inputEntity['type'] !== 'item' ) {
			die( 'Only supported entity type is \'item\'' );
		}

		return $this->deserializer->deserialize( $inputEntity );
	}

}
