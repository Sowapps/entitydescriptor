<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

/**
 * Entity Type Float class
 */
class TypeFloat extends TypeNumber {
	
	// Format float([[max=2147483647, min=-2147483648], [decimals=2]]])
	protected int $decimals = 2;
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['decimals' => $this->decimals, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($rawArgs[2]) ) {
			$args->decimals = $rawArgs[0];
			$args->min = $rawArgs[1];
			$args->max = $rawArgs[2];
		} elseif( isset($rawArgs[1]) ) {
			$args->min = $rawArgs[0];
			$args->max = $rawArgs[1];
		} elseif( isset($rawArgs[0]) ) {
			$args->decimals = $rawArgs[0];
		}
		
		return $args;
	}
	
}
