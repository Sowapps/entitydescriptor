<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Time;

use Exception;

class Date extends DateTime {
	
	/**
	 * @throws Exception
	 */
	public function __toString() {
		return d($this);
	}
	
}
