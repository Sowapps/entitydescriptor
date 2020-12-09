<?php
/**
 * EntityDescriptor class & types declarations
 */

namespace Orpheus\EntityDescriptor;

use DateTime as VanillaDateTime;
use Exception;
use Orpheus\Cache\FSCache;
use Orpheus\Config\YAML\YAML;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\Exception\InvalidFieldException;
use Orpheus\Publisher\PermanentObject\PermanentObject;
use Orpheus\Publisher\SlugGenerator;
use Orpheus\Time\Date;
use Orpheus\Time\DateTime;

/**
 * A class to describe an entity
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 * This class uses a YAML configuration file to describe the entity.
 * Thus you can easily update your database using dev_entities module and it validate the input data for you.
 */
class EntityDescriptor {
	
	const FLAG_ABSTRACT = 'abstract';
	const DESCRIPTORCLASS = 'EntityDescriptor';
	const IDFIELD = 'id';
	const VERSION = 4;
	
	/**
	 * All known types
	 *
	 * @var array
	 */
	protected static $types = [];
	
	/**
	 * The class associated to this entity
	 *
	 * @var string
	 */
	protected $class;
	
	/**
	 * The entity's name
	 *
	 * @var string
	 */
	protected string $name;
	
	/**
	 * The entity's version
	 *
	 * @var int
	 */
	protected $version;
	
	/**
	 * The fields of this entity
	 *
	 * @var FieldDescriptor[]
	 */
	protected $fields = [];
	
	/**
	 * The indexes of this entity
	 *
	 * @var string[]
	 */
	protected $indexes = [];
	
	/**
	 * Is this entity abstract ?
	 *
	 * @var boolean
	 */
	protected $abstract = false;
	
	/**
	 * Construct the entity descriptor
	 *
	 * @param $name string
	 * @param $fields FieldDescriptor[]
	 * @param $indexes object[]
	 * @param $class string
	 */
	protected function __construct($name, $fields, $indexes, $class = null) {
		$this->name = $name;
		$this->class = $class;
		$this->fields = $fields;
		$this->indexes = $indexes;
		$this->version = self::VERSION;
	}
	
	/**
	 * Is this entity abstract ?
	 *
	 * @return boolean True if abstract
	 */
	public function isAbstract() {
		return $this->abstract;
	}
	
	/**
	 * Set the abstract property of this entity
	 *
	 * @param boolean True to set descriptor as abstract
	 * @return EntityDescriptor the descriptor
	 */
	public function setAbstract($abstract) {
		$this->abstract = $abstract;
		return $this;
	}
	
	/**
	 * Get the name of the entity
	 *
	 * @return string The name of the descriptor
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Get all indexes
	 *
	 * @return object[]
	 */
	public function getIndexes() {
		return $this->indexes;
	}
	
	/**
	 * Get fields' name
	 *
	 * @return string[]
	 */
	public function getFieldsName() {
		return array_keys($this->fields);
	}
	
	/**
	 * Validate input
	 *
	 * @param array $input
	 * @param array|null $fields
	 * @param PermanentEntity|null $ref
	 * @param int $errCount
	 * @return array
	 */
	public function validate(array &$input, $fields = null, $ref = null, &$errCount = 0, $ignoreRequired = false) {
		$data = [];
		foreach( $this->fields as $fieldName => &$fData ) {
			try {
				if( $fields !== null && !in_array($fieldName, $fields) ) {
					unset($input[$fieldName]);
					// If updating, we do not modify a field not in $fields
					// If creating, we set to default a field not in $fields
					if( $ref ) {
						continue;
					}
				}
				if( !$fData->writable ) {
					continue;
				}
				if( !isset($input[$fieldName]) ) {
					$input[$fieldName] = null;
				}
				$this->validateFieldValue($fieldName, $input[$fieldName], $input, $ref);
				// PHP does not make difference between 0 and NULL, so every non-null value is different from null.
				if( !isset($ref) || ($ref->getValue($fieldName) === null xor $input[$fieldName] === null) || $input[$fieldName] != $ref->getValue($fieldName) ) {
					$data[$fieldName] = $input[$fieldName];
				}
				
			} catch( UserException $e ) {
				if( $ignoreRequired === true || (is_array($ignoreRequired) && in_array($fieldName, $ignoreRequired)) ) {
					continue;
				}
				$errCount++;
				if( isset($this->class) ) {
					/** @var PermanentObject $c */
					$c = $this->class;
					$c::reportException($e);
				} else {
					reportError($e);
				}
			}
		}
		return $data;
	}
	
	/**
	 * Validate a value for a specified field, an exception is thrown if the value is invalid
	 *
	 * @param string $fieldName The field to use
	 * @param mixed $value input|output value to validate for this field
	 * @param    $input string[]
	 * @param PermanentEntity $ref
	 * @throws    InvalidFieldException
	 */
	public function validateFieldValue($fieldName, &$value, $input = [], $ref = null) {
		if( !isset($this->fields[$fieldName]) ) {
			throw new InvalidFieldException('unknownField', $fieldName, $value, null, $this->name);
		}
		$field = $this->getField($fieldName);
		if( !$field->writable ) {
			throw new InvalidFieldException('readOnlyField', $fieldName, $value, null, $this->name);
		}
		$fieldType = $field->getType();
		
		$fieldType->preFormat($field, $value, $input, $ref);
		
		if( $value === null || ($value === '' && $fieldType->emptyIsNull($field)) ) {
			$value = null;
			if( isset($field->default) ) {
				// Look for default value
				$value = $field->getDefault();
				
			} elseif( !$field->nullable ) {
				// Reject null value
				throw new InvalidFieldException('requiredField', $fieldName, $value, null, $this->name);
			}
			// We will format valid null value later (in formatter)
			return;
		}
		// TYPE Validator - Use inheritance, mandatory in super class
		try {
			$fieldType->validate($field, $value, $input, $ref);
			// Field Validator - Could be undefined
			if( !empty($field->validator) ) {
				call_user_func_array($field->validator, [$field, &$value, $input, &$ref]);
			}
		} catch( FE $e ) {
			throw new InvalidFieldException($e->getMessage(), $fieldName, $value, $field->type, $this->name, $field->args);
		}
		
		// TYPE Formatter - Use inheritance, mandatory in super class
		$value = $fieldType->parseUserValue($field, $value);
		// Field Formatter - Could be undefined
	}
	
	/**
	 * Get one field by name
	 *
	 * @param string $name The field name
	 * @return FieldDescriptor
	 */
	public function getField($name) {
		return isset($this->fields[$name]) ? $this->fields[$name] : null;
	}
	
	/**
	 * Get all available entity descriptor
	 *
	 * @return EntityDescriptor[]
	 */
	public static function getAllEntityDescriptors() {
		$entities = [];
		foreach( static::getAllEntities() as $entity ) {
			$entities[$entity] = EntityDescriptor::load($entity);
		}
		return $entities;
	}
	
	/**
	 * Get all available entities
	 *
	 * @return string[]
	 */
	public static function getAllEntities() {
		$entities = cleanscandir(pathOf(CONFDIR . ENTITY_DESCRIPTOR_CONFIG_PATH));
		foreach( $entities as $i => &$filename ) {
			$pi = pathinfo($filename);
			if( $pi['extension'] != 'yaml' ) {
				unset($entities[$i]);
				continue;
			}
			$filename = $pi['filename'];
		}
		return $entities;
	}
	
	/**
	 * Load an entity descriptor from configuraiton file
	 *
	 * @param string $name
	 * @param string $class
	 * @return EntityDescriptor
	 * @throws Exception
	 */
	public static function load($name, $class = null) {
		$descriptorPath = ENTITY_DESCRIPTOR_CONFIG_PATH . $name;
		$cache = new FSCache(self::DESCRIPTORCLASS, $name, filemtime(YAML::getFilePath($descriptorPath)));
		
		// Comment when editing class and entity field types
		$descriptor = null;
		try {
			if( !defined('ENTITY_ALWAYS_RELOAD')
				&& $cache->get($descriptor)
				&& isset($descriptor->version)
				&& $descriptor->version == self::VERSION ) {
				return $descriptor;
			}
		} catch( Exception $e ) {
			// If file is corrupted (version mismatch ?)
			$cache->reset();
		}
		// Unable to get from cache, building new one
		
		$conf = YAML::build($descriptorPath, true);
		if( empty($conf->fields) ) {
			throw new \Exception('Descriptor file for "' . $name . '" is corrupted, empty or not found, there is no field.');
		}
		// Build descriptor
		$fields = [];
		if( !empty($conf->parent) ) {
			if( !is_array($conf->parent) ) {
				$conf->parent = [$conf->parent];
			}
			foreach( $conf->parent as $p ) {
				$p = static::load($p);
				if( !empty($p) ) {
					$fields = array_merge($fields, $p->getFields());
				}
			}
		}
		$idField = $class ? $class::getIDField() : self::IDFIELD;
		$fields[$idField] = FieldDescriptor::buildIDField($idField);
		foreach( $conf->fields as $fieldName => $fieldInfos ) {
			$fields[$fieldName] = FieldDescriptor::parseType($fieldName, $fieldInfos);
		}
		
		// Indexes
		$indexes = [];
		if( !empty($conf->indexes) ) {
			foreach( $conf->indexes as $index ) {
				$iType = static::parseType(null, $index);
				$indexes[] = (object) ['name' => $iType->default, 'type' => strtoupper($iType->type), 'fields' => $iType->args];
			}
		}
		// Save cache output
		$descriptor = new EntityDescriptor($name, $fields, $indexes, $class);
		if( !empty($conf->flags) ) {
			if( in_array(self::FLAG_ABSTRACT, $conf->flags) ) {
				$descriptor->setAbstract(true);
			}
		}
		$cache->set($descriptor);
		return $descriptor;
	}
	
	/**
	 * Get all fields
	 *
	 * @return FieldDescriptor[]
	 */
	public function getFields() {
		return $this->fields;
	}
	
	/**
	 * parse type from configuration string
	 *
	 * @param string $fieldName
	 * @param string $desc
	 * @return object
	 * @throws Exception
	 */
	public static function parseType($fieldName, $desc) {
		$result = ['type' => null, 'args' => [], 'default' => null, 'flags' => []];
		$matches = null;
		if( !preg_match('#([^\(\[=]+)(?:\(([^\)]*)\))?(?:\[([^\]]*)\])?(?:=([^\[]*))?#', $desc, $matches) ) {
			throw new Exception('failToParseType');
		}
		$result['type'] = trim($matches[1]);
		$result['args'] = !empty($matches[2]) ? preg_split('#\s*,\s*#', $matches[2]) : [];
		$result['flags'] = !empty($matches[3]) ? preg_split('#\s#', $matches[3], -1, PREG_SPLIT_NO_EMPTY) : [];
		if( isset($matches[4]) ) {
			$result['default'] = $matches[4];
			if( $result['default'] === 'true' ) {
				$result['default'] = true;
			} elseif( $result['default'] === 'false' ) {
				$result['default'] = false;
			} else {
				$len = strlen($result['default']);
				if( $len && $result['default'][$len - 1] == ')' ) {
					$result['default'] = static::parseType($fieldName, $result['default']);
				}
			}
		}
		return (object) $result;
	}
	
	/**
	 * Register a TypeDescriptor
	 *
	 * @param TypeDescriptor $type
	 */
	public static function registerType(TypeDescriptor $type) {
		static::$types[$type->getName()] = $type;
	}
	
	/**
	 * Get a type by name
	 *
	 * @param string $name Name of the type to get
	 * @param string $type Output parameter for type
	 * @return TypeDescriptor
	 * @throws Exception
	 */
	public static function getType($name, &$type = null) {
		if( !isset(static::$types[$name]) ) {
			throw new Exception('unknownType_' . $name);
		}
		$type = &static::$types[$name];
		return $type;
	}
}

/**
 * shorten Field Exception class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class FE extends Exception {
}

defifn('ENTITY_DESCRIPTOR_CONFIG_PATH', 'entities/');

// Primary Types

/**
 * Entity Type Number class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeNumber extends TypeDescriptor {
	// Format number([max=2147483647 [, min=-2147483648 [, decimals=0]]])
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'number';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 0, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($fargs[2]) ) {
			$args->decimals = $fargs[0];
			$args->min = $fargs[1];
			$args->max = $fargs[2];
		} elseif( isset($fargs[1]) ) {
			$args->min = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->max = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		$value = sanitizeNumber($value);
		// 		$value	= str_replace(array(tc('decimal_point'), tc('thousands_sep')), array('.', ''), $value);
		if( !is_numeric($value) ) {
			throw new FE('notNumeric');
		}
		if( $value < $field->args->min ) {
			throw new FE('belowMinValue');
		}
		if( $value > $field->args->max ) {
			throw new FE('aboveMaxValue');
		}
	}
	
	/**
	 * @param FieldDescriptor $field
	 * @see TypeDescriptor::getHTMLInputAttr()
	 */
	public function getHTMLInputAttr($field) {
		$min = $field->arg('min');
		$max = $field->arg('max');
		$decimals = $field->arg('decimals');
		return ['maxlength' => max(static::getMaxLengthOf($min, $decimals), static::getMaxLengthOf($max, $decimals)), 'min' => $min, 'max' => $max, 'type' => 'number'];
	}
	
	/**
	 *
	 * Get the max length of number
	 *
	 * @param int $number
	 * @param int $decimals
	 * @return int
	 */
	public static function getMaxLengthOf($number, $decimals) {
		return strlen((int) $number) + ($decimals ? 1 + $decimals : 0);
	}
	
	/**
	 * @param array $args
	 * @see TypeDescriptor::htmlInputAttr()
	 */
	public function htmlInputAttr($args) {
		return ' maxlength="' . max(static::getMaxLengthOf($args->min, $args->decimals), static::getMaxLengthOf($args->max, $args->decimals)) . '"';
	}
}

EntityDescriptor::registerType(new TypeNumber());

/**
 * Entity Type String class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeString extends TypeDescriptor {
	
	protected static $defaultMinLength = 0;
	protected static $defaultMaxLength = 65535;
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'string';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['min' => static::$defaultMinLength, 'max' => static::$defaultMaxLength];
		if( isset($fargs[1]) ) {
			$args->min = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->max = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		$len = strlen($value);
		if( $len < $field->args->min ) {
			throw new FE('belowMinLength');
		}
		if( $len > $field->args->max ) {
			throw new FE('aboveMaxLength');
		}
	}
	
	/**
	 * Get as HTML attribute
	 *
	 * @param FieldDescriptor $field
	 * @return array
	 */
	public function getHTMLAttr($field) {
		return ['maxlength' => $field->arg('max'), 'type' => 'text'];
	}
	
	/**
	 * @param array $args
	 * @see TypeDescriptor::htmlInputAttr()
	 */
	public function htmlInputAttr($args) {
		return ' maxlength="' . $args->max . '"';
	}
	
	/**
	 * @param FieldDescriptor $field
	 * @see TypeDescriptor::emptyIsNull()
	 */
	public function emptyIsNull($field) {
		return $field->args->min > 0;
	}
}

EntityDescriptor::registerType(new TypeString());

/**
 * Entity Type Date class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeDate extends TypeDescriptor {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'date';
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		if( $value instanceof VanillaDateTime ) {
			return;
		}
		if( is_id($value) ) {
			return;
		}
		$dateTime = null;
		if( !is_date($value, false, $dateTime) ) {
			throw new FE('notDate');
		}
		$value = $dateTime;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return new Date(sqlDate($value));
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function formatSqlValue(FieldDescriptor $field, $value) {
		return $value !== null ? sqlDate($value) : null;
	}
	
	/**
	 * @param FieldDescriptor $field
	 * @param string $value
	 * @return Date|null
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, $value) {
		return $value && !in_array($value, ['0000-00-00', '0000-00-00 00:00:00']) ? new Date($value) : null;
	}
}

EntityDescriptor::registerType(new TypeDate());

/**
 * Entity Type Datetime class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeDatetime extends TypeDescriptor {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'datetime';
	/*
	 * Date format is storing a date, not a specific moment, we don't care about timezone
	 */
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
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
				throw new FE('notDatetime');
			}
		}
		$value = $dateTime;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return new DateTime(sqlDatetime($value));
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function formatSqlValue(FieldDescriptor $field, $value) {
		return $value !== null ? sqlDatetime($value) : null;
	}
	
	/**
	 * @param FieldDescriptor $field
	 * @param string $value
	 * @return \Orpheus\Time\DateTime|null
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, $value) {
		return $value && !in_array($value, ['0000-00-00', '0000-00-00 00:00:00']) ? new DateTime($value) : null;
	}
}

EntityDescriptor::registerType(new TypeDatetime());

/**
 * Entity Type Time class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeTime extends TypeString {
	
	/**
	 * The time format to use
	 *
	 * @var string
	 *
	 * If $format is changed, don't forget that the current string limit is 5
	 */
	public static $format = SYSTEM_TIME_FORMAT;
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'time';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		return (object) ['min' => 5, 'max' => 5];
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		if( !is_time($value, $value) ) {
			throw new FE('notTime');
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return strftime(static::$format, mktime($value[1], $value[2]));
	}
}

EntityDescriptor::registerType(new TypeTime());

// Derived types

/**
 * Entity Type Integer class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeInteger extends TypeNumber {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'integer';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 0, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($fargs[1]) ) {
			$args->min = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->max = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return intval($value);
	}
	
	/**
	 * @param FieldDescriptor $field
	 * @param string $value
	 * @return int|null
	 * @throws Exception
	 */
	public function parseSqlValue(FieldDescriptor $field, $value) {
		return $value !== null ? intval($value) : null;
	}
}

EntityDescriptor::registerType(new TypeInteger());

/**
 * Entity Type Boolean class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeBoolean extends TypeInteger {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'boolean';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		return (object) ['decimals' => 0, 'min' => 0, 'max' => 1];
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		$value = (int) !empty($value);
		parent::validate($field, $value, $input, $ref);
	}
}

EntityDescriptor::registerType(new TypeBoolean());

/**
 * Entity Type Float class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeFloat extends TypeNumber {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'float';
	
	// Format float([[max=2147483647, min=-2147483648], [decimals=2]]])
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 2, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($fargs[2]) ) {
			$args->decimals = $fargs[0];
			$args->min = $fargs[1];
			$args->max = $fargs[2];
		} elseif( isset($fargs[1]) ) {
			$args->min = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->decimals = $fargs[0];
		}
		return $args;
	}
}

EntityDescriptor::registerType(new TypeFloat());

/**
 * Entity Type Double class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeDouble extends TypeNumber {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'double';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 8, 'min' => -2147483648, 'max' => 2147483647];
		if( isset($fargs[2]) ) {
			$args->decimals = $fargs[0];
			$args->min = $fargs[1];
			$args->max = $fargs[2];
		} elseif( isset($fargs[1]) ) {
			$args->min = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->decimals = $fargs[0];
		}
		return $args;
	}
}

EntityDescriptor::registerType(new TypeDouble());

/**
 * Entity Type Natural class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeNatural extends TypeInteger {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'natural';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 0, 'min' => 0, 'max' => 4294967295];
		if( isset($fargs[0]) ) {
			$args->max = $fargs[0];
		}
		return $args;
	}
}

EntityDescriptor::registerType(new TypeNatural());

/**
 * Entity Type Ref class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeRef extends TypeNatural {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'ref';
	// 	protected $nullable	= false;
	// MySQL needs more logic to select a null field with an index
	// Prefer to set default to 0 instead of using nullable
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['entity' => null, 'decimals' => 0, 'min' => 0, 'max' => 4294967295];
		if( isset($fargs[0]) ) {
			$args->entity = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		id($value);
		parent::validate($field, $value, $input, $ref);
	}
}

EntityDescriptor::registerType(new TypeRef());

/**
 * Entity Type Email class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeEmail extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'email';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		return (object) ['min' => 5, 'max' => 100];
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		if( !is_email($value) ) {
			throw new FE('notEmail');
		}
	}
}

EntityDescriptor::registerType(new TypeEmail());

/**
 * Entity Type Password class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypePassword extends TypeString {
	
	protected static $defaultMinLength = 5;
	protected static $defaultMaxLength = 128;
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'password';
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		if( empty($input[$field->name . '_conf']) || $value != $input[$field->name . '_conf'] ) {
			throw new FE('invalidConfirmation');
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return hashString($value);
	}
}

EntityDescriptor::registerType(new TypePassword());

/**
 * Entity Type Phone class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypePhone extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'phone';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		return (object) ['min' => 10, 'max' => 20];
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		// FR Only for now - Should use user language
		if( !is_phone_number($value) ) {
			throw new FE('notPhoneNumber');
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		// FR Only for now - Should use user language
		return standardizePhoneNumber_FR($value, '.', 2);
	}
}

EntityDescriptor::registerType(new TypePhone());

/**
 * Entity Type URL class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeURL extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'url';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		return (object) ['min' => 10, 'max' => 400];
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		if( !is_url($value) ) {
			throw new FE('notURL');
		}
	}
}

EntityDescriptor::registerType(new TypeURL());

/**
 * Entity Type IP class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeIP extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'ip';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['min' => 7, 'max' => 40, 'version' => null];
		if( isset($fargs[0]) ) {
			$args->version = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		if( !is_ip($value) ) {
			throw new FE('notIPAddress');
		}
	}
}

EntityDescriptor::registerType(new TypeIP());

/**
 * Entity Type Enum class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeEnum extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'enum';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['min' => 1, 'max' => 50, 'source' => null];
		if( isset($fargs[0]) ) {
			$args->source = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		parent::validate($field, $value, $input, $ref);
		if( !isset($field->args->source) ) {
			return;
		}
		$values = call_user_func($field->args->source, $input, $ref);
		if( is_id($value) ) {
			if( !isset($values[$value]) ) {
				throw new FE('notEnumValue');
			}
			// Get the real enum value from index
			$value = $values[$value];
		} elseif( !isset($values[$value]) && !in_array($value, $values) ) {
			throw new FE('notEnumValue');
		}
	}
}

EntityDescriptor::registerType(new TypeEnum());

/**
 * Entity Type State class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeState extends TypeEnum {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'state';
	
	/*
	 DEFAULT VALUE SHOULD BE THE FIRST OF SOURCE
	 */
	
	/**
	 * @param FieldDescriptor $field The field to validate
	 * @param string $value The field value to validate
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::validate()
	 */
	public function validate(FieldDescriptor $field, &$value, $input, &$ref) {
		TypeString::validate($field, $value, $input, $ref);
		if( !isset($field->args->source) ) {
			return;
		}
		$values = call_user_func($field->args->source, $input, $ref);
		if( !isset($values[$value]) ) {
			throw new FE('notEnumValue');
		}
		if( $ref === null ) {
			$value = key($values);
		} elseif(
			!isset($ref->{$field->name}) || (
				$ref->{$field->name} !== $value &&
				(!isset($values[$ref->{$field->name}]) || !in_array($value, $values[$ref->{$field->name}]))
			) ) {
			throw new FE('unreachableValue');
		}
	}
}

EntityDescriptor::registerType(new TypeState());

/**
 * Entity Type Object class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeObject extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'object';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['min' => 1, 'max' => 65535, 'class' => null];
		if( isset($fargs[0]) ) {
			$args->class = $fargs[0];
			if( $args->class === 'stdClass' ) {
				$args->class = null;
			}
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseSqlValue()
	 */
	public function parseSqlValue(FieldDescriptor $field, $value) {
		if( is_object($value) ) {
			return $value;
		}
		/* @var string $value */
		$class = $field->arg('class');
		if( $class ) {
			if( array_key_exists('Serializable', class_implements($class, true)) ) {
				$obj = new $class();
				$obj->unserialize($value);
				return $obj;
			} else {
				return unserialize($value);
			}
			
		} else {
			return json_decode($value, false);
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		if( is_string($value) ) {
			return $value;
		}
		/* @var mixed $value */
		$class = $field->arg('class');
		if( $class ) {
			if( !($value instanceof $class) ) {
				throw new Exception('Field ' . $field . '\'s value should be an instance of ' . $class . ', got ' . get_class($value));
			}
			if( $value instanceof Serializable ) {
				return $value->serialize();
			} else {
				return serialize($value);
			}
			
		} else {
			return json_encode($value);
		}
	}
}

EntityDescriptor::registerType(new TypeObject());

/**
 * Entity Type City class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeCity extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'city';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['min' => 3, 'max' => 30];
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see TypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, $value) {
		return str_ucwords($value);
	}
}

EntityDescriptor::registerType(new TypeCity());

/**
 * Entity Type Postal Code class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypePostalCode extends TypeInteger {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'postalcode';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['decimals' => 0, 'min' => 10000, 'max' => 99999];
		return $args;
	}
}

EntityDescriptor::registerType(new TypePostalCode());

/**
 * Entity Type Slug class
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class TypeSlug extends TypeString {
	
	/**
	 * The type's name
	 *
	 * @var string
	 */
	protected string $name = 'slug';
	
	/**
	 * @param string[] $fargs Arguments
	 * @see TypeDescriptor::parseArgs()
	 */
	public function parseArgs(array $fargs) {
		$args = (object) ['field' => 'name', 'min' => 0, 'max' => 100];
		if( isset($fargs[2]) ) {
			$args->field = $fargs[0];
			$args->min = $fargs[1];
			$args->max = $fargs[2];
		} elseif( isset($fargs[1]) ) {
			$args->field = $fargs[0];
			$args->max = $fargs[1];
		} elseif( isset($fargs[0]) ) {
			$args->field = $fargs[0];
		}
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to format
	 * @param string $value The field value to format
	 * @param array $input The input to validate
	 * @param PermanentEntity $ref The object to update, may be null
	 * @see TypeDescriptor::preFormat()
	 */
	public function preFormat(FieldDescriptor $field, &$value, $input, &$ref) {
		$otherName = $field->arg('field');
		$otherValue = (isset($input[$otherName]) ? $input[$otherName] : ($ref ? $ref->$otherName : null));
		if( $otherValue ) {
			$slugGenerator = new SlugGenerator();
			$value = $slugGenerator->format($otherValue);
		}
		return parent::validate($field, $value, $input, $ref);
	}
}

EntityDescriptor::registerType(new TypeSlug());
