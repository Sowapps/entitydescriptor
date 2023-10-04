<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Exception;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;
use Orpheus\Time\DateTime;

/**
 * Entity Type Datetime class
 */
class TypeDatetime extends AbstractTypeDescriptor {
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 * @throws InvalidTypeFormat
	 * @see AbstractTypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		if( $value instanceof DateTime ) {
			return;
		}
		if( !empty($input[$field->name . '_time']) ) {
			$value .= ' ' . $input[$field->name . '_time'];//Allow HH:MM:SS and HH:MM
		}
		// FR Only for now - Should use user language
		if( is_id($value) ) {
			return;
		}
		$dateTime = null;
		// TODO: Find a better way to check all formats
		// We now check first char for system dates
		if( $value[0] === '@' ) {
			$dateTime = substr($value, 1);
		} else {
			$format = null;
			if( $value[0] === '$' ) {
				$value = substr($value, 1);
				$format = DATE_FORMAT_GNU;
			}
			if( !is_date($value, true, $dateTime, $format) ) {
				throw new InvalidTypeFormat('notDatetime');
			}
		}
		$value = $dateTime;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @throws Exception
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): DateTime {
		return new DateTime(sqlDatetime($value));
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function formatSqlValue(FieldDescriptor $field, mixed $value): ?string {
		return $value !== null ? sqlDatetime($value) : null;
	}
	
	/**
	 * @param string $value
	 * @return \DateTime|null
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, mixed $value): ?DateTime {
		return $value && !in_array($value, ['0000-00-00', '0000-00-00 00:00:00']) ? new DateTime($value) : null;
	}
	
}
