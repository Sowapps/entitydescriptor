<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

/**
 * Entity Type Natural class
 */
class TypeNatural extends TypeInteger {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['decimals' => 0, 'min' => 0, 'max' => 4294967295];
		if( isset($rawArgs[0]) ) {
			$args->max = $rawArgs[0];
		}
		
		return $args;
	}
	
}
