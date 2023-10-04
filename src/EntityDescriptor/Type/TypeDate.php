<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use DateTime as VanillaDateTime;
use Exception;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;
use Orpheus\Time\Date;

/**
 * Entity Type Date class
 */
class TypeDate extends AbstractTypeDescriptor {
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 * @throws InvalidTypeFormat
	 * @see AbstractTypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
		if( $value instanceof VanillaDateTime ) {
			return;
		}
		if( is_id($value) ) {
			return;
		}
		$dateTime = null;
		if( !is_date($value, false, $dateTime) ) {
			throw new InvalidTypeFormat('notDate');
		}
		$value = $dateTime;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @throws Exception
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): Date {
		return new Date(sqlDate($value));
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function formatSqlValue(FieldDescriptor $field, mixed $value): ?string {
		return $value !== null ? sqlDate($value) : null;
	}
	
	/**
	 * @param string $value
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, mixed $value): ?Date {
		return $value && !in_array($value, ['0000-00-00', '0000-00-00 00:00:00']) ? new Date($value) : null;
	}
	
}
