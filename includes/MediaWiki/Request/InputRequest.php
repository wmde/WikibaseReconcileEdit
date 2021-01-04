<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

use MediaWiki\Rest\RequestInterface;

/**
 * Allows the jump from a RequestInterface object to the strings needed for input
 */
interface InputRequest {

	/**
	 * @param RequestInterface $request from the API
	 * @return string RAW CSV content of the input
	 */
	public function input( RequestInterface $request ) : string;

	/**
	 * @param RequestInterface $request from the API
	 * @return string RAW JSON content of the input schema
	 */
	public function schema( RequestInterface $request ) : string;

}
