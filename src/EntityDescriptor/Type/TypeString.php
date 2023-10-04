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
 * Entity Type String class
 */
class TypeString extends AbstractTypeDescriptor {
	
	protected static int $defaultMinLength = 0;
	
	protected static int $defaultMaxLength = 65535;
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['min' => static::$defaultMinLength, 'max' => static::$defaultMaxLength];
		if( isset($rawArgs[1]) ) {
			$args->min = $rawArgs[0];
			$args->max = $rawArgs[1];
		} elseif( isset($rawArgs[0]) ) {
			$args->max = $rawArgs[0];
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
		$len = strlen($value);
		if( $len < $field->args->min ) {
			throw new InvalidTypeFormat('belowMinLength');
		}
		if( $len > $field->args->max ) {
			throw new InvalidTypeFormat('aboveMaxLength');
		}
	}
	
	public function htmlInputAttr(object $args): string {
		return ' maxlength="' . $args->max . '"';
	}
	
	public function emptyIsNull($field): bool {
		return $field->args->min > 0;
	}
	
}
