<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;
use Orpheus\Publisher\SlugGenerator;

/**
 * Entity Type Slug class
 */
class TypeSlug extends TypeString {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['field' => 'name', 'min' => 0, 'max' => 100];
		if( isset($rawArgs[2]) ) {
			$args->field = $rawArgs[0];
			$args->min = $rawArgs[1];
			$args->max = $rawArgs[2];
		} elseif( isset($rawArgs[1]) ) {
			$args->field = $rawArgs[0];
			$args->max = $rawArgs[1];
		} elseif( isset($rawArgs[0]) ) {
			$args->field = $rawArgs[0];
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
	public function preFormat(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		$otherName = $field->arg('field');
		$otherValue = $input[$otherName] ?? $ref?->$otherName;
		if( $otherValue ) {
			$slugGenerator = new SlugGenerator();
			$value = $slugGenerator->format($otherValue);
		}
		
		parent::validate($field, $value, $input, $ref);
	}
	
}
