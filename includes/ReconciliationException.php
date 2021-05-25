<?php

namespace MediaWiki\Extension\WikibaseReconcileEdit;

use Exception;
use Wikimedia\Message\MessageValue;

/**
 * Base class for all WikibaseReconcileEdit exceptions
 *
 * @license GPL-2.0-or-later
 */
class ReconciliationException extends Exception {
	/**
	 * @var MessageValue
	 */
	private $messageValue;

	public function __construct( MessageValue $messageValue, $errorData = null ) {
		parent::__construct(
			'Reconciliation exception with key ' . $messageValue->getKey(), $errorData
		);
		$this->messageValue = $messageValue;
	}

	public function getMessageValue() {
		return $this->messageValue;
	}
}
