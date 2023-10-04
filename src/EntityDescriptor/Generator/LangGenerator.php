<?php
/**
 * LangGenerator
 */

namespace Orpheus\EntityDescriptor\Generator;

use Orpheus\EntityDescriptor\Entity\EntityDescriptor;
use Orpheus\Publisher\Exception\InvalidFieldException;

/**
 * The lang generator class is used to generate lang file from an entity descriptor
 * 
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class LangGenerator {
	
	/**
	 * Some values to test if field is valid
	 * 
	 * @var array
	 */
	public static array $testedValues = array(null, '', '0', 'string', '1.997758887755445', '-974455277432344345647573654743352', '974455277432344345647573654743352');

	/**
	 * Get all exception string this entity could generate
	 *
	 * @return	InvalidFieldException[] A set of exception
	 */
	public function getRows(EntityDescriptor $descriptor): array {
		$rows = [];
		foreach( $descriptor->getFieldsName() as $field ) {
			$rows += $this->getErrorsForField($descriptor, $field);
		}
		return array_unique($rows);
	}
	
	/**
	 * Get all exception this field could generate
	 *
	 * @return InvalidFieldException[]
	 */
	public function getErrorsForField(EntityDescriptor $descriptor, string $field): array {
		$errors = [];
		foreach( static::$testedValues as $value ) {
			try {
				$descriptor->validateFieldValue($field, $value);
			} catch( InvalidFieldException $e ) {
				$errors[$e->getKey()] = $e;
			}
		}
		return $errors;
	}
	
}
