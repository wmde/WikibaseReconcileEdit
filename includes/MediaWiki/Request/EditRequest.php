<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit\MediaWiki\Request;

interface EditRequest {

	public function entity() : ?array;

	public function reconcile() : ?array;

}
