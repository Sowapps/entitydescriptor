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
 * Entity Type Phone class
 * @warning Require redesign, only handling french phone numbers
 */
class TypePhone extends TypeString {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		return (object) ['min' => 10, 'max' => 20];
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
		// FR Only for now - Should use user language
		if( !$this->is_phone_number($value) ) {
			throw new InvalidTypeFormat('notPhoneNumber');
		}
	}
	
	/**
	 * Check if the input is a phone number.
	 * It can only validate french phone number.
	 * The separator can be '.', ' ' or '-', it can be omitted.
	 * E.g.: +336.12.34.56.78, 01-12-34-56-78
	 *
	 * @param string $number The phone number to check.
	 * @return bool True if $number si a valid phone number.
	 */
	private function is_phone_number(string $number): bool {
		$number = str_replace(['.', ' ', '-'], '', $number);
		
		return preg_match("#^(?:\+[0-9]{1,3}|0)[0-9]{9}$#", $number);
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param mixed $value The field value to parse
	 * @return mixed
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): string {
		// FR Only for now - Should use user language
		return $this->standardizePhoneNumber_FR($value);
	}
	
	/**
	 * Standardize the phone number to FR country format
	 *
	 * @param string $number The input phone number.
	 */
	private function standardizePhoneNumber_FR(string $number): string {// If there is no delimiter, we try to put one
		$number = str_replace(['.', ' ', '-'], '', $number);
		$length = strlen($number);
		if( $length < 10 ) {
			return '';
		}
		$n = '';
		for( $i = strlen($number) - 2; $i > 3 || ($number[0] !== '+' && $i > (2 - 1)); $i -= 2 ) {
			$n = '.' . substr($number, $i, 2) . $n;
		}
		
		return substr($number, 0, $i + 2) . $n;
	}
	
}
