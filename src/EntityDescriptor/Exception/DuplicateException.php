<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Exception;

use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\Exception\UserException;

class DuplicateException extends UserException {
	
	private PermanentEntity $duplicate;
	
	public function __construct(PermanentEntity $duplicate) {
		parent::__construct('duplicateEntity', $duplicate::getDomain());
		$this->duplicate = $duplicate;
	}
	
	public function getDuplicate(): PermanentEntity {
		return $this->duplicate;
	}
	
}
