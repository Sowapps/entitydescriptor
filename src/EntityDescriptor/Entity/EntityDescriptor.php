<?php
/**
 * EntityDescriptor class & types declarations
 */

namespace Orpheus\EntityDescriptor\Entity;

use Exception;
use Orpheus\Cache\CacheException;
use Orpheus\Cache\FileSystemCache;
use Orpheus\Config\Yaml\Yaml;
use Orpheus\EntityDescriptor\Exception\InvalidTypeFormat;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\Exception\InvalidFieldException;
use RuntimeException;

/**
 * A class to describe an entity
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 * This class uses a Yaml configuration file to describe the entity.
 * Thus, you can easily update your database using dev_entities module, and it validates the input data for you.
 */
class EntityDescriptor {
	
	const FLAG_ABSTRACT = 'abstract';
	const CACHE_DESCRIPTOR = 'entity-descriptor';
	const DEFAULT_FIELD_ID = 'id';
	
	const VERSION = 5;
	
	/**
	 * The ID field
	 *
	 * @var string
	 */
	protected string $idField = self::DEFAULT_FIELD_ID;
	
	/**
	 * The table
	 */
	protected ?string $table = null;
	
	/**
	 * The domain used by entity
	 */
	protected ?string $domain = null;
	
	/**
	 * All known types
	 *
	 * @var array
	 */
	protected static array $typeClasses = [];
	
	/**
	 * The class associated to this entity
	 *
	 * @var string|null
	 */
	protected ?string $class;
	
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
	protected int $version;
	
	/**
	 * The fields of this entity
	 *
	 * @var FieldDescriptor[]
	 */
	protected array $fields = [];
	
	/**
	 * The indexes of this entity
	 *
	 * @var string[]
	 */
	protected array $indexes = [];
	
	/**
	 * Is this entity abstract ?
	 *
	 * @var boolean
	 */
	protected bool $abstract = false;
	
	/**
	 * Construct the entity descriptor
	 *
	 * @param FieldDescriptor[] $fields
	 * @param object[] $indexes
	 */
	protected function __construct(string $name, array $fields, array $indexes, ?string $class = null) {
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
	public function isAbstract(): bool {
		return $this->abstract;
	}
	
	/**
	 * Set the abstract property of this entity
	 *
	 * @param boolean $abstract True to set descriptor as abstract
	 * @return EntityDescriptor the descriptor
	 */
	public function setAbstract(bool $abstract): static {
		$this->abstract = $abstract;
		
		return $this;
	}
	
	/**
	 * Get the name of the entity
	 *
	 * @return string The name of the descriptor
	 */
	public function getName(): string {
		return $this->name;
	}
	
	/**
	 * Get all indexes
	 *
	 * @return object[]
	 */
	public function getIndexes(): array {
		return $this->indexes;
	}
	
	/**
	 * Get fields' name
	 *
	 * @return string[]
	 */
	public function getFieldsName(): array {
		return array_keys($this->fields);
	}
	
	/**
	 * Validate input
	 */
	public function validate(array &$input, ?array $fields = null, ?PermanentEntity $ref = null, int &$errCount = 0, bool|array $ignoreRequired = false): array {
		$data = [];
		foreach( $this->fields as $fieldName => $fieldData ) {
			try {
				if( $fields !== null && !in_array($fieldName, $fields) ) {
					unset($input[$fieldName]);
					// If updating, we do not modify a field not in $fields
					// If creating, we set to default a field not in $fields
					if( $ref ) {
						continue;
					}
				}
				if( !$fieldData->writable ) {
					continue;
				}
				$value = $input[$fieldName] ?? null;
				$this->validateFieldValue($fieldName, $value, $input, $ref);
				// PHP does not make difference between 0 and NULL, so every non-null value is different from null.
				if( !$ref || ($ref->getValue($fieldName) === null xor $value === null) || $value != $ref->getValue($fieldName) ) {
					$data[$fieldName] = $value;
				}
				
			} catch( UserException $e ) {
				if( ($e instanceof InvalidFieldException && $e->getKey() === 'requiredField') && ($ignoreRequired === true || (is_array($ignoreRequired) && in_array($fieldName, $ignoreRequired))) ) {
					continue;
				}
				$errCount++;
//				if( isset($this->class) ) {
//					/** @var PermanentEntity $class */
//					$class = $this->class;
//					$class::reportException($e);
//				} else {
//					reportError($e);
//				}
			}
		}
		
		return $data;
	}
	
	/**
	 * Validate a value for a specified field, an exception is thrown if the value is invalid
	 * We should always take care about idempotence of the value, it could be a string typed by user or a final DateTime
	 * it validates explicitly then parse value to ensure the value is valid
	 *
	 * @param string $fieldName The field to use
	 * @param mixed $value input|output value to validate for this field
	 * @param string[] $input
	 * @noinspection PhpRedundantCatchClauseInspection
	 */
	public function validateFieldValue(string $fieldName, mixed &$value, array $input = [], ?PermanentEntity $ref = null): void {
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
		} catch( InvalidTypeFormat $e ) {
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
	 */
	public function getField(string $name): ?FieldDescriptor {
		return $this->fields[$name] ?? null;
	}
	
	/**
	 * Get all fields
	 *
	 * @return FieldDescriptor[]
	 */
	public function getFields(): array {
		return $this->fields;
	}
	
	public function getClass(): ?string {
		return $this->class;
	}
	
	public function setClass(string $class): void {
		$this->class = $class;
	}
	
	/**
	 * Load an entity descriptor from configuration file
	 *
	 * @throws CacheException
	 * @throws Exception
	 */
	public static function load(string $name, ?string $class = null): EntityDescriptor {
		$descriptorSource = ENTITY_DESCRIPTOR_CONFIG_FOLDER . '/' . $name;
		$cache = new FileSystemCache(self::CACHE_DESCRIPTOR, $name, filemtime(Yaml::getFilePath($descriptorSource)));
		
		// Comment when editing class and entity field types
		try {
			$descriptor = null;
			/** @var EntityDescriptor $descriptor */
			if( !defined('ENTITY_ALWAYS_RELOAD') && $cache->get($descriptor) && $descriptor && isset($descriptor->version) &&
				$descriptor->version === self::VERSION && $descriptor->class === $class ) {
				return $descriptor;
			}
		} catch( Exception ) {
			// If file is corrupted (version mismatch ?)
			$cache->clear();
		}
		// Unable to get from cache, building new one
		
		$config = Yaml::build($descriptorSource, true);
		$descriptor = self::build($name, $config, $class);
		$cache->set($descriptor);
		
		return $descriptor;
	}
	
	/**
	 * @throws CacheException
	 * @throws Exception
	 */
	public static function build(string $name, object $config, ?string $class = null): EntityDescriptor {
		if( empty($config->fields) ) {
			throw new Exception(sprintf('Descriptor file for "%s" is corrupted, empty or not found, there is no field.', $name));
		}
		// Build descriptor
		$fields = [];
		if( !empty($config->parent) ) {
			if( !is_array($config->parent) ) {
				$config->parent = [$config->parent];
			}
			foreach( $config->parent as $p ) {
				$p = static::load($p);
				$fields = array_merge($fields, $p->getFields());
			}
		}
		/** @var PermanentEntity $class */
		$idField = $class ? $class::getIdField() : self::DEFAULT_FIELD_ID;
		$fields[$idField] = FieldDescriptor::buildIdField($idField);
		foreach( $config->fields as $fieldName => $fieldInfos ) {
			if($fieldName === $idField) {
				// Ignore ID field, it must be autoconfigured
				continue;
			}
			$fields[$fieldName] = FieldDescriptor::parseType($fieldName, $fieldInfos);
		}
		
		// Indexes
		$indexes = [];
		if( !empty($config->indexes) ) {
			foreach( $config->indexes as $index ) {
				$iType = static::parseType($index);
				$indexes[] = (object) ['name' => $iType->default, 'type' => strtoupper($iType->type), 'fields' => $iType->args];
			}
		}
		// Save cache output
		$descriptor = new EntityDescriptor($name, $fields, $indexes, $class);
		if( !empty($config->flags) ) {
			if( in_array(self::FLAG_ABSTRACT, $config->flags) ) {
				$descriptor->setAbstract(true);
			}
		}
		
		return $descriptor;
	}
	
	
	/**
	 * parse type from configuration string
	 *
	 * @throws Exception
	 */
	public static function parseType(string $desc): object {
		$result = ['type' => null, 'args' => [], 'default' => null, 'flags' => []];
		$matches = null;
		/** @noinspection RegExpRedundantEscape */
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
				if( $len && $result['default'][$len - 1] === ')' ) {
					$result['default'] = static::parseType($result['default']);
				}
			}
		}
		
		return (object) $result;
	}
	
	/**
	 * Register a AbstractTypeDescriptor
	 */
	public static function registerType(string $name, string $class): void {
		static::$typeClasses[$name] = $class;
	}
	
	/**
	 * Get a type by name
	 *
	 * @param string $name Name of the type to get
	 */
	public static function getType(string $name): AbstractTypeDescriptor {
		if( !isset(static::$typeClasses[$name]) ) {
			throw new RuntimeException('unknownType_' . $name);
		}
		$type = static::$typeClasses[$name];
		if( is_string($type) ) {
			static::$typeClasses[$name] = $type = new $type($name);
		}
		
		return $type;
	}
	
	public function getIdField(): string {
		return $this->idField;
	}
	
	public function setIdField(string $idField): void {
		$this->idField = $idField;
	}
	
	public function getTable(): ?string {
		return $this->table ?? $this->name;
	}
	
	public function setTable(?string $table): void {
		$this->table = $table;
	}
	
	public function getDomain(): ?string {
		return $this->domain ?? $this->name;
	}
	
	public function setDomain(?string $domain): void {
		$this->domain = $domain;
	}
	
}
