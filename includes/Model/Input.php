<?php

namespace MediaWiki\Extension\OnOrProt\Model;

class Input {

	/**
	 * @var array[]
	 */
	public $rows;

	public function __construct( string $rawCsvInput, bool $csvHasHeaders ) {
		// Raw CSV string to array of rows
		$data = array_map( 'str_getcsv', explode( PHP_EOL, trim( $rawCsvInput ) ) );
		array_walk( $data, function ( &$a ) use ( $data ) {
		  $a = array_combine( $data[0], $a );
		});

		// If we have headers (according to schema) then remove them
		if ( $csvHasHeaders ) {
			array_shift( $data );
		}

		$this->rows = $data;
	}

}
