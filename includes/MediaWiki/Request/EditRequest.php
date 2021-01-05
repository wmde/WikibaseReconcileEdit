<?php

namespace MediaWiki\Extension\OnOrProt\MediaWiki\Request;

interface EditRequest {

	public function entity() : ?array;

	public function reconcile() : ?array;

}
