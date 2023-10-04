<?php
/**
 * AbstractTypeDescriptor
 */

namespace Orpheus\EntityDescriptor\Entity;

use stdClass;

/**
 * The AbstractTypeDescriptor class
 * A type value could have 3 forms, the user readable value, the programming value (the php one) and the SQL Value
 * E.g. In case of date, user could enter & read "14/10/1988", but php wants a DateTime & SQL wants "1988-10-14"
 * Parsing is transforming any user/SQL value into programming value, formatting is the opposite.
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
abstract class AbstractTypeDescriptor {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name;
	
	/**
	 * Is this type writable ?
	 *
	 * @var boolean
	 */
	protected bool $writable = true;
	
	/**
	 * Is this type nullable ?
	 *
	 * @var boolean
	 */
	protected bool $nullable = false;
	
	/**
	 * AbstractTypeDescriptor constructor
	 */
	public function __construct(string $name) {
		$this->name = $name;
	}
	
	/**
	 * Get the type name
	 *
	 * @return string the type name
	 */
	public function getName(): string {
		return $this->name;
	}
	
	/**
	 * Get true if field is writable
	 */
	public function isWritable(): bool {
		return $this->writable;
	}
	
	/**
	 * Get true if field is nullable
	 */
	public function isNullable(): bool {
		return $this->nullable;
	}
	
	/**
	 * Get the html input attributes string for the given args
	 */
	public function htmlInputAttr(object $args): string {
		return '';
	}
	
	/**
	 * Get the html input attributes array for the given Field descriptor
	 *
	 * @return string[]
	 */
	public function getHtmlInputAttr(FieldDescriptor $field): array {
		return [];
	}
	
	/**
	 * Get true if we consider null an empty input string
	 */
	public function emptyIsNull(FieldDescriptor $field): bool {
		return true;
	}
	
	/**
	 * Parse args from field declaration
	 *
	 * @param string[] $rawArgs Arguments
	 * @return stdClass
	 */
	public function parseArgs(array $rawArgs): object {
		return new stdClass();
	}
	
	/**
	 * Validate value
	 * This should handle a string and the final type, e.g. DateTime for TypeDatetime
	 *
	 * @param FieldDescriptor $field The field to validate
	 * @param mixed $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 */
	public function validate(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
	}
	
	/**
	 * Format value before being validated.
	 * Validator should allow string and final type (string, object...)
	 * Use this function if user/developer could try to validate a validator from another type
	 *
	 * @param FieldDescriptor $field The field to format
	 * @param mixed $value The field value to format
	 * @param array $input The input to validate
	 * @param PermanentEntity|null $ref The object to update, may be null
	 */
	public function preFormat(FieldDescriptor $field, mixed &$value, array $input, ?PermanentEntity &$ref): void {
	}
	
	/**
	 * Parse user value into programming value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): mixed {
		return $value;
	}
	
	/**
	 * Format programming value into user value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param mixed $value The field value to parse
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function formatUserValue(FieldDescriptor $field, mixed $value): string {
		return "$value";
	}
	
	/**
	 * Parse SQL value into programming value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string|null $value The field value to parse
	 * @see PermanentEntity::formatFieldSqlValue()
	 */
	public function parseSqlValue(FieldDescriptor $field, ?string $value): mixed {
		return $value;
	}
	
	/**
	 * Format programming value into SQL value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @return mixed (string, null or any special type)
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function formatSqlValue(FieldDescriptor $field, mixed $value): mixed {
		return $value;
	}
	
}
