<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Exception;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;

/**
 * Entity Type Integer class
 */
class TypeInteger extends TypeNumber {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['decimals' => 0, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($rawArgs[1]) ) {
			$args->min = $rawArgs[0];
			$args->max = $rawArgs[1];
		} elseif( isset($rawArgs[0]) ) {
			$args->max = $rawArgs[0];
		}
		
		return $args;
	}
	
	public function parseUserValue(FieldDescriptor $field, mixed $value): int {
		return intval($value);
	}
	
	/**
	 * @param string $value
	 * @return int|null
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, mixed $value): mixed {
		return $value !== null ? intval($value) : null;
	}
	
}
