<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Exception;

use Orpheus\Exception\UserException;
use Orpheus\Publisher\PermanentObject\PermanentObject;

class DuplicateException extends UserException {
	
	/** @var PermanentObject */
	private $duplicate;
	
	public function __construct(PermanentObject $duplicate) {
		parent::__construct('duplicateEntity', $duplicate::getDomain());
		$this->duplicate = $duplicate;
	}
	
	/**
	 * @return PermanentObject
	 */
	public function getDuplicate() {
		return $this->duplicate;
	}
	
}
