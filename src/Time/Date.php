<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Time;

class Date extends DateTime {
	
	public function __toString() {
		return d($this);
	}
	
}
