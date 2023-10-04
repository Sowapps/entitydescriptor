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
 * Entity Type Time class
 */
class TypeTime extends TypeString {
	
	/*
	 * The time format to use
	 *
	 * @var string
	 *
	 * If $format is changed, don't forget that the current string limit is 5
	 */
//	public static string $format = SYSTEM_TIME_FORMAT;
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		return (object) ['min' => 5, 'max' => 5];
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
		if( !is_time($value, $value) ) {
			throw new InvalidTypeFormat('notTime');
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param mixed $value The field value to parse
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): string|false {
		return $value;
	}
	
}
