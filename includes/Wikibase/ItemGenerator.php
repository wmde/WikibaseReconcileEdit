<?php

namespace MediaWiki\Extension\OnOrProt\Wikibase;

use MediaWiki\Extension\OnOrProt\Model\Input;
use MediaWiki\Extension\OnOrProt\Model\Schema;
use Wikibase\DataModel\Entity\Item;

class ItemGenerator {

	/**
	 * @return Item[]
	 */
	public function generate( Schema $schema, Input $input ) : array {
		$items = [];
		foreach ( $input->rows as $row ) {
			$items[] = $this->generateOne( $schema, $row );
		}
		return $items;
	}

	private function generateOne( Schema $schema, array $row ) {
		$item = new Item();
		foreach ( $schema->columns as $colName => $colData ) {
			// Skip if the row doesn't have a value for this element?
			// TODO log? or fail? is this fishy?
			if ( !array_key_exists( $colName, $row ) ) {
				continue;
			}
			$value = $row[$colName];

			$this->applyAllToItem( $item, $colData['to'], $value );
		}
		return $item;
	}

	private function applyAllToItem( Item &$item, array $tos, $value ) : void {
		foreach ( $tos as $to ) {
			$this->applyToItem( $item, $to, $value );
		}
	}

	private function applyToItem( Item &$item, $to, $value ) {
		// TODO factor these out?
		if ( preg_match( '/^label@([a-z\-\_]+)$/', $to, $matches ) ) {
			$item->setLabel( $matches[1], $value );
			return;
		}
		if ( preg_match( '/^(P\d+)$/', $to, $matches ) ) {
			$propertyId = $matches[1];
			// TODO create a statement?
			// TODO how to create statement with qualifiers or references all at once?
			return;
		}
	}

}
