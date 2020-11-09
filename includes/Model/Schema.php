<?php

namespace MediaWiki\Extension\OnOrProt\Model;

class Schema {

	/**
	 * @var bool
	 */
	public $csvHasHeaders;

	/**
	 * @var array
	 */
	public $columns;

	public function __construct( string $rawSchemaJson ) {
		$parsed = json_decode( $rawSchemaJson, true );
		$this->csvHasHeaders = (bool)$parsed['meta']['headers'];
		$this->columns = $parsed['columns'];
	}

}
