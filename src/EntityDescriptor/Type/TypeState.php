<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;

/**
 * Entity Type State class
 */
class TypeState extends TypeEnum {
	
	/*
	 DEFAULT VALUE SHOULD BE THE FIRST OF SOURCE
	 */
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 * @throws InvalidTypeFormat
	 * @see AbstractTypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		TypeString::validate($field, $value, $input, $ref);
		if( !isset($field->args->source) ) {
			return;
		}
		$values = call_user_func($field->args->source, $input, $ref);
		if( !isset($values[$value]) ) {
			throw new InvalidTypeFormat('notEnumValue');
		}
		if( $ref === null ) {
			$value = key($values);
		} elseif(
			!isset($ref->{$field->name}) || (
				$ref->{$field->name} !== $value &&
				(!isset($values[$ref->{$field->name}]) || !in_array($value, $values[$ref->{$field->name}]))
			) ) {
			throw new InvalidTypeFormat('unreachableValue');
		}
	}
	
}
