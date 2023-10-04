<?php
/**
 * FieldDescriptor
 */

namespace Orpheus\EntityDescriptor\Entity;

use Exception;
use stdClass;

/**
 * The FieldDescriptor class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class FieldDescriptor {
	
	/**
	 * The field name
	 *
	 * @var string
	 */
	public string $name;
	
	/**
	 * The field type
	 *
	 * @var string
	 */
	public string $type;
	
	/**
	 * The field arguments
	 *
	 * @var object
	 */
	public object $args;
	
	/**
	 * The field's default value
	 *
	 * @var mixed
	 */
	public mixed $default;
	
	/**
	 * Is this field writable ?
	 *
	 * @var boolean
	 */
	public bool $writable = false;
	
	/**
	 * Is this field nullable ?
	 *
	 * @var boolean
	 */
	public bool $nullable = false;
	
	/**
	 * Constructor
	 */
	public function __construct(string $name, string $type, array $rawArgs, mixed $default) {
		$this->name = $name;
		$this->type = $type;
		$fieldType = $this->getType();
		$this->args = $fieldType->parseArgs($rawArgs);
		$this->default = $default;
	}
	
	/**
	 * Magic toString
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
	
	/**
	 * Get arg value for this field
	 *
	 * @param string $key The argument key
	 * @return string|int|NULL The argument value
	 */
	public function arg(string $key): int|string|null {
		return $this->args->$key ?? null;
	}
	
	/**
	 * Get the HTML input tag for this field
	 *
	 * @return string[]
	 * @throws Exception
	 */
	public function getHtmlInputAttr(): array {
		return $this->getType()->getHtmlInputAttr($this);
	}
	
	/**
	 * Get the type of the field
	 */
	public function getType(): AbstractTypeDescriptor {
		return EntityDescriptor::getType($this->type);
	}
	
	/**
	 * Get the default value (if this field is NULL)
	 */
	public function getDefault(): mixed {
		if( $this->default instanceof stdClass ) {
			$this->default = call_user_func_array($this->default->type, (array) $this->default->args);
		} elseif( is_string($this->default) && defined($this->default) ) {
			return constant($this->default);
		}
		return $this->default;
	}
	
	/**
	 * Parse field type configuration from file string
	 *
	 * @param string|string[] $desc
	 * @return FieldDescriptor The parsed field descriptor
	 * @throws Exception
	 */
	public static function parseType(string $fieldName, array|string $desc): FieldDescriptor {
		if( is_array($desc) ) {
			$typeDesc = $desc['type'];
		} else {
			$typeDesc = $desc;
			$desc = [];
		}
		$parse = EntityDescriptor::parseType($typeDesc);
		
		/* Field : String name, AbstractTypeDescriptor type, Array args, default, writable, nullable */
		$field = new static($fieldName, $parse->type, $parse->args, $parse->default);
		$fieldType = $field->getType();
		
		// Type's default
		$field->writable = $fieldType->isWritable();
		$field->nullable = $fieldType->isNullable();
		
		// Field flags
		if( isset($desc['writable']) ) {
			$field->writable = !empty($desc['writable']);
		} elseif( $field->writable ) {
			$field->writable = !in_array('readonly', $parse->flags);
		} else {
			$field->writable = in_array('writable', $parse->flags);
		}
		if( isset($desc['nullable']) ) {
			$field->nullable = !empty($desc['nullable']);
		} elseif( $field->nullable ) {
			$field->nullable = !in_array('notnull', $parse->flags);
		} else {
			$field->nullable = in_array('nullable', $parse->flags);
		}
		return $field;
	}
	
	/**
	 * Build ID field for an entity
	 */
	public static function buildIdField(string $name): static {
		// Require writable & nullable to false
		return new static($name, 'ref', [], null);
	}
}
