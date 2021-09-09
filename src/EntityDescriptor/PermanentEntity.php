<?php
/**
 * The Permanent Entity class
 *
 * Permanent entity objects are Permanent Object using entity descriptor
 *
 * @author Florent Hazard <contact@sowapps.com>
 */

namespace Orpheus\EntityDescriptor;

use Exception;
use Orpheus\Exception\NotFoundException;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\PermanentObject\PermanentObject;
use Orpheus\Time\DateTime;

/**
 * The permanent entity class
 *
 * A permanent entity class that combine a PermanentObject with EntityDescriptor's features.
 */
abstract class PermanentEntity extends PermanentObject {
	
	/**
	 * Entity classes
	 */
	protected static array $entityClasses = [];
	
	/**
	 * Validator of new field values
	 *
	 * @var EntityDescriptor
	 */
	protected static $validator = null;
	
	/**
	 * Known entities
	 */
	protected static array $knownEntities = [];
	
	/**
	 * Validate field value and set it
	 *
	 * @param string $field
	 * @param mixed $value
	 */
	public function putValue($field, $value) {
		$this->validateValue($field, $value);
		$this->setValue($field, $value);
	}
	
	/**
	 * Validate field value from the validator using this entity
	 *
	 * @param string $field
	 * @param mixed $value
	 */
	public function validateValue($field, $value) {
		static::$validator->validateFieldValue($field, $value, null, $this);
	}
	
	/**
	 * Helper method to get whereClause string from an entity
	 *
	 * @param string $prefix The prefix for fields, e.g "table." (with dot)
	 * @return string
	 *
	 * Helper method to get whereClause string from an entity.
	 * The related entity should have entity_type and entity_id fields.
	 */
	public function getEntityWhereClause($prefix = ''): string {
		return $prefix . 'entity_type LIKE ' . static::formatValue(static::getEntity()) . ' AND ' . $prefix . 'entity_id=' . $this->id();
	}
	
	/**
	 * Get this entity name
	 *
	 * @return string
	 */
	public static function getEntity(): string {
		return static::getTable();
	}
	
	public function getLabel(): string {
		return static::getClass() . '#' . $this->{static::$ID_FIELD};
	}
	
	public function __toString() {
		try {
			return $this->getLabel();
		} catch( Exception $e ) {
			log_error($e->getMessage() . "<br />\n" . $e->getTraceAsString(), 'PermanentEntity::__toString()', false);
			
			return '';
		}
	}
	
	/**
	 * Try to load entity from an entity string and an id integer
	 *
	 * @param string $entity
	 * @param int $id
	 */
	public static function loadEntity($entity, $id) {
		/** @var PermanentEntity $entity */
		return $entity::load($id);
	}
	
	/**
	 * Initialize entity class
	 * You must call this method after the class declaration
	 *
	 * @param bool $isFinal Is this class final ?
	 */
	public static function init($isFinal = true) {
		if( static::$validator ) {
			throw new Exception('Class ' . static::getClass() . ' with table ' . static::$table . ' is already initialized.');
		}
		static::$domain ??= static::$table;
		if( $isFinal ) {
			$ed = EntityDescriptor::load(static::$table, static::getClass());
			static::$fields = $ed->getFieldsName();
			static::$validator = $ed;
			static::$entityClasses[static::$table] = static::getClass();
		}
	}
	
	/**
	 * Get entity instance by type and id
	 *
	 * @param string|PermanentEntity $entityType
	 * @param int $entityId
	 * @return PermanentEntity
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public static function findEntityObject($entityType, $entityId = null): PermanentEntity {
		if( $entityType instanceof PermanentEntity ) {
			$entityId = $entityType->entity_id;
			$entityType = $entityType->entity_type;
		}
		$class = null;
		if( isset(static::$entityClasses[$entityType]) ) {
			// In loaded classes
			$class = static::$entityClasses[$entityType];
		} else {
			// Not in loaded classes, try to load all to find it
			foreach( self::$knownEntities as $entityClass => $state ) {
				/** @var PermanentEntity $entityClass */
				if( $entityClass::getEntity() === $entityType ) {
					$class = $entityClass;
				}
			}
		}
		if( !$class ) {
			self::throwNotFound();
		}
		return $class::load($entityId, false);
	}
	
	/**
	 * Get field descriptor from field name
	 *
	 * @param string $field
	 * @return FieldDescriptor
	 */
	public static function getField($field) {
		return static::$validator->getField($field);
	}
	
	/**
	 * Register an entity
	 *
	 * @param string $class
	 */
	public static function registerEntity($class) {
		if( array_key_exists($class, static::$knownEntities) ) {
			return;
		}
		static::$knownEntities[$class] = null;
	}
	
	/**
	 * List all known entities
	 *
	 * @return string[]
	 */
	public static function listKnownEntities(): array {
		$entities = [];
		foreach( static::$knownEntities as $class => &$state ) {
			if( $state == null ) {
				$state = class_exists($class, true) && is_subclass_of($class, 'Orpheus\EntityDescriptor\PermanentEntity');
			}
			if( $state === true ) {
				$entities[] = $class;
			}
		}
		
		return $entities;
	}
	
	/**
	 * Parse the value from SQL scalar to PHP type
	 *
	 * @param string $name The field name to parse
	 * @param mixed $value The field value to parse
	 * @return mixed The parsed value
	 * @see PermanentObject::formatFieldSqlValue()
	 */
	protected static function parseFieldSqlValue(string $name, $value) {
		$field = static::$validator->getField($name);
		if( $field ) {
			return $field->getType()->parseSqlValue($field, $value);
		}
		
		return parent::parseFieldSqlValue($name, $value);
	}
	
	/**
	 * Format the value from PHP type to SQL scalar
	 *
	 * @param string $name The field name to format
	 * @param mixed $value The field value to format
	 * @return mixed The formatted $Value
	 * @see PermanentObject::formatValue()
	 */
	protected static function formatFieldSqlValue($name, $value) {
		$field = static::$validator->getField($name);
		if( $field ) {
			return $field->getType()->formatSqlValue($field, $value);
		}
		
		return parent::formatFieldSqlValue($name, $value);
	}
	
	protected static function now($time = null): DateTime {
		return new DateTime(sqlDatetime($time));
	}
	
	public static function onEdit(array &$data, $object) {
		// Format all fields into SQL values
		foreach( $data as $field => &$value ) {
			$value = static::formatFieldSqlValue($field, $value);
		}
	}
}
