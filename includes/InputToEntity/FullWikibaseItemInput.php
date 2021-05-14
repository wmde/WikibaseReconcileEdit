<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use Deserializers\Deserializer;
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

	public function getItem( array $inputEntity ) : Item {
		if ( !array_key_exists( 'type', $inputEntity ) || $inputEntity['type'] !== 'item' ) {
			die( 'Only supported entity type is \'item\'' );
		}

		return $this->deserializer->deserialize( $inputEntity );
	}

}
