<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\InputToEntity;

use Deserializers\Deserializer;
use MediaWiki\Extension\WikibaseReconcileEdit\ReconciliationException;
use Wikibase\DataModel\Entity\Item;
use Wikimedia\Message\MessageValue;

/**
 * @license GPL-2.0-or-later
 */
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
		$supportedTypes = [ 'item' ];
		if ( !array_key_exists( 'type', $inputEntity ) || !in_array( $inputEntity['type'], $supportedTypes ) ) {
			throw new ReconciliationException(
				MessageValue::new( 'wikibasereconcileedit-fullwikibaseiteminput-unsupported-type' )
					->textListParams( $supportedTypes )
					->numParams( count( $supportedTypes ) )
			);
		}
		return $this->deserializer->deserialize( $inputEntity );
	}

}
