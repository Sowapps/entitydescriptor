<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;

/**
 * Entity Type Enum class
 */
class TypeEnum extends TypeString {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['min' => 1, 'max' => 50, 'source' => null];
		if( isset($rawArgs[0]) ) {
			$args->source = $rawArgs[0];
		}
		
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 * @throws InvalidTypeFormat
	 * @see AbstractTypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		parent::validate($field, $value, $input, $ref);
		if( !isset($field->args->source) ) {
			return;
		}
		$values = call_user_func($field->args->source, $input, $ref);
		if( ctype_digit($value) && isset($values[$value]) ) {
			// Get the real enum value from index
			$value = $values[$value];
		} elseif( !isset($values[$value]) && !in_array($value, $values) ) {
			throw new InvalidTypeFormat('notEnumValue');
		}
	}
	
}
