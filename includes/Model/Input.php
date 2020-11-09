<?php

namespace MediaWiki\Extension\OnOrProt\Model;

class Input {

	/**
	 * @var null|string[]
	 */
	public $headers = null;

	/**
	 * @var array[]
	 */
	public $rows;

	public function __construct( string $rawCsvInput, bool $csvHasHeaders ) {
		$data = array_map( 'str_getcsv', explode( PHP_EOL, $rawCsvInput ) );
		array_walk( $data, function ( &$a ) use ( $data ) {
		  $a = array_combine( $data[0], $a );
		});

		if ( $csvHasHeaders ) {
			$this->headers = array_shift( $data );
		}

		$this->rows = $data;
	}

}
