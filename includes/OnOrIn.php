<?php

namespace MediaWiki\Extension\OnOrProt;

use MediaWiki\Rest\SimpleHandler;

/**
 * WIP
 * From https://gerrit.wikimedia.org/r/c/mediawiki/extensions/Wikibase/+/624100
 */
class OnOrIn extends SimpleHandler {

	public function run() {
		/**
		 * 1) INPUT to the API from user
		 * Could be actual files, or could be URls?
		 * Could also not be CSV, and we could provide automatic ways to transform other file types to CSV / whatever we use internally.
		 */
		$schema = json_decode( file_get_contents( __DIR__ . '/../data/csv/schema.json' ), true );

		$data = array_map( 'str_getcsv', file( __DIR__ . '/../data/csv/1.csv' ) );
		array_walk( $data, function ( &$a ) use ( $data ) {
		  $a = array_combine( $data[0], $a );
		});

		$headings = null;
		if ( $schema['meta']['headers'] ) {
			$headings = array_shift($data);
		}

		/**
		 * 2) MAP the input data to Wikibase Item / concpets
		 * Might want properties too in the future?
		 */
		$items = [];
		foreach ( $data as $row ) {
			// TODO this would or could be the point that this is split up into jobs?
			$item = new Item();
			// TODO go from the CSV to the $item using the $schema
			$items[] = $item;
		}

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
		// TODO
	}
}
