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
 * Entity Type Number class
 */
class TypeNumber extends AbstractTypeDescriptor {
	// Format number([max=2147483647 [, min=-2147483648 [, decimals=0]]])
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['decimals' => 0, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($rawArgs[2]) ) {
			$args->decimals = $rawArgs[0];
			$args->min = $rawArgs[1];
			$args->max = $rawArgs[2];
		} elseif( isset($rawArgs[1]) ) {
			$args->min = $rawArgs[0];
			$args->max = $rawArgs[1];
		} elseif( isset($rawArgs[0]) ) {
			$args->max = $rawArgs[0];
		}
		
		return $args;
	}
	
	/**
	 * @throws InvalidTypeFormat
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		$value = parseNumber($value);
		if( !is_numeric($value) ) {
			throw new InvalidTypeFormat('notNumeric');
		}
		if( $value < $field->args->min ) {
			throw new InvalidTypeFormat('belowMinValue');
		}
		if( $value > $field->args->max ) {
			throw new InvalidTypeFormat('aboveMaxValue');
		}
	}
	
	public function getHtmlInputAttr(FieldDescriptor $field): array {
		$min = $field->arg('min');
		$max = $field->arg('max');
		$decimals = $field->arg('decimals');
		
		return ['maxlength' => max(static::getMaxLengthOf($min, $decimals), static::getMaxLengthOf($max, $decimals)), 'min' => $min, 'max' => $max, 'type' => 'number'];
	}
	
	public function htmlInputAttr(object $args): string {
		return ' maxlength="' . max(static::getMaxLengthOf($args->min, $args->decimals), static::getMaxLengthOf($args->max, $args->decimals)) . '"';
	}
	
	/**
	 *
	 * Get the max length of number
	 */
	public static function getMaxLengthOf(int $number, int $decimals): int {
		return strlen($number) + ($decimals ? 1 + $decimals : 0);
	}
	
}
