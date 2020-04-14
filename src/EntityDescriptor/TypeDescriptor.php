<?php
/**
 * TypeDescriptor
 */

namespace Orpheus\EntityDescriptor;

use stdClass;

/**
 * The TypeDescriptor class
 * A type value could have 3 forms, the user readable value, the programming value (the php one) and the SQL Value
 * e.g In case of date, user could enter & read "14/10/1988", but php wants a DateTime & SQL wants "1988-10-14"
 * Parsing is transforming any user/SQL value into programming value, formatting is the opposite.
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
abstract class TypeDescriptor {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected $name;
	
	/**
	 * Is this type writable ?
	 *
	 * @var boolean
	 */
	protected $writable;
	
	/**
	 * Is this type nullable ?
	 *
	 * @var boolean
	 */
	protected $nullable;
	
	/**
	 * Get the type name
	 *
	 * @return string the type name
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Get true if field is writable
	 *
	 * @return boolean
	 */
	public function isWritable() {
		return $this->writable;
	}
	
	/**
	 * Get true if field is nullable
	 *
	 * @return boolean
	 */
	public function isNullable() {
		return $this->nullable;
	}
	
	/**
	 * Get the html input attributes string for the given args
	 *
	 * @param array $args
	 * @return string
	 */
	public function htmlInputAttr($args) {
		return '';
	}
	
	/**
	 * Get the html input attributes array for the given Field descriptor
	 *
	 * @param FieldDescriptor $field
	 * @return string[]
	 */
	public function getHTMLInputAttr($field) {
		return [];
	}
	
	/**
	 * Get true if we consider null an empty input string
	 *
	 * @param FieldDescriptor $field
	 * @return boolean
	 */
	public function emptyIsNull($field) {
		return true;
	}
	
	/**
	 * Parse args from field declaration
	 *
	 * @param string[] $fargs Arguments
	 * @return stdClass
	 */
	public function parseArgs(array $fargs) {
		return new stdClass();
	}
	
	/**
	 * Validate value
	 *
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
	}
	
	/**
	 * Format value before being validated
	 *
	 * @param FieldDescriptor $field The field to format
	 * @param string $value The field value to format
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 */
	public function preFormat(FieldDescriptor $field, &$value, $input, &$ref) {
	}
	
	/**
	 * Parse user value into programming value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @return mixed
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return $value;
	}
	
	/**
	 * Format programming value into user value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param mixed $value The field value to parse
	 * @return string
	 */
	public function formatUserValue(FieldDescriptor $field, $value) {
		return "$value";
	}
	
	/**
	 * Parse SQL value into programming value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @return mixed
	 * @see PermanentObject::formatFieldSqlValue()
	 */
	public function parseSqlValue(FieldDescriptor $field, $value) {
		return $value;
	}
	
	/**
	 * Format programming value into SQL value
	 *
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @return string
	 */
	public function formatSqlValue(FieldDescriptor $field, $value) {
		return $value;
	}
	
}
