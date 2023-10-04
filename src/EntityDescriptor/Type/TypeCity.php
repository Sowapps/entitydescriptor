<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;

/**
 * Entity Type City class
 */
class TypeCity extends TypeString {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		return (object) ['min' => 3, 'max' => 30];
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): string {
		return str_ucwords($value);
	}
	
}
