<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

/**
 * Entity Type Postal Code class
 */
class TypePostalCode extends TypeInteger {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		return (object) ['decimals' => 0, 'min' => 10000, 'max' => 99999];
	}
	
}
