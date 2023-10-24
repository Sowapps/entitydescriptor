<?php
/**
 * The Permanent Entity class
 *
 * Permanent entity objects are Permanent Object using entity descriptor
 *
 * @author Florent Hazard <contact@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Entity;

use DateTimeInterface;
use Exception;
use Orpheus\Cache\CacheException;
use Orpheus\EntityDescriptor\Exception\DuplicateException;
use Orpheus\Exception\NotFoundException;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\Exception\FieldNotFoundException;
use Orpheus\Publisher\Transaction\CreateTransactionOperation;
use Orpheus\Publisher\Transaction\DeleteTransactionOperation;
use Orpheus\Publisher\Transaction\UpdateTransactionOperation;
use Orpheus\SqlAdapter\AbstractSqlAdapter;
use Orpheus\SqlAdapter\Exception\SqlException;
use Orpheus\SqlRequest\SqlInsertRequest;
use Orpheus\SqlRequest\SqlSelectRequest;
use Orpheus\SqlRequest\SqlUpdateRequest;
use Orpheus\Time\DateTime;
use RuntimeException;

/**
 * The permanent entity class
 *
 * A permanent entity class with EntityDescriptor's features.
 */
abstract class PermanentEntity {
	
	const OUTPUT_MODEL_MINIMALS = 'min';
	const OUTPUT_MODEL_PUBLIC = 'public';
	const OUTPUT_MODEL_ALL = 'all';
	
	/**
	 * @var array Array with metadata of all entities mapped by class name
	 */
	protected static array $entityMetadata = [];
	
	/**
	 * The instance to use, see config file, if null, use default
	 *
	 * @var string|null
	 * @deprecated TODO Remove it
	 */
	protected static ?string $instanceName = null;
	
	/**
	 * Should check fields integrity when load one element ?
	 *
	 * @var bool
	 */
	protected static bool $checkFieldIntegrity = !!ENTITY_CLASS_CHECK;
	
	/**
	 * Entity classes
	 */
	protected static array $mappingEntityClass = [];
	
	/**
	 * Known entities (but may be not loaded & initialized)
	 */
	protected static array $knownEntities = [];
	
	/**
	 * The object's data
	 *
	 * @var array
	 */
	protected array $data = [];
	
	/**
	 * The original data of object
	 * Only filled when edited to store previous data (first loaded, got from db)
	 *
	 * @var array
	 */
	protected array $originalData = [];
	
	/**
	 * Is this object deleted ?
	 *
	 * @var boolean
	 */
	protected bool $isDeleted = false;
	
	/**
	 * Is this object called onSaved ?
	 * It prevents recursive calls
	 *
	 * @var boolean
	 */
	protected bool $onSavedInProgress = false;
	
	public function __construct(array $data) {
		if( !static::getMetadata() ) {
			throw new RuntimeException(sprintf(
				'Creating instance of class "%s" for non-initialized entity class, please call one of methods initialize*()',
				static::class));
		}
		$this->setData($data);
	}
	
	/**
	 * Validate field value and set it
	 *
	 * @throws Exception
	 */
	public function putValue(string $field, mixed $value): void {
		$this->validateValue($field, $value);
		$this->setValue($field, $value);
	}
	
	/**
	 * Validate field value from the validator using this entity
	 */
	public function validateValue(string $field, mixed $value): void {
		static::getDescriptor()->validateFieldValue($field, $value, [], $this);
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
	public function getEntityWhereClause(string $prefix = ''): string {
		return $prefix . 'entity_type LIKE ' . static::formatValue(static::getEntity()) . ' AND ' . $prefix . 'entity_id=' . $this->id();
	}
	
	public function getLabel(): string {
		return $this->getReference();
	}
	
	public function getReference(): string {
		return static::class . '#' . $this->id();
	}
	
	/**
	 * Set all data of object (internal use only)
	 *
	 * @warning Internal use only, to load & reload
	 */
	protected function setData(array $data): void {
		foreach( static::getFields() as $fieldName ) {
			// We consider null as a valid value.
			$fieldValue = null;
			if( !array_key_exists($fieldName, $data) ) {
				// Data not found but should be, this object is out of date
				// Data not in DB, this class is invalid
				// Disable $checkFieldIntegrity if you want to mock up this entity
				if( static::$checkFieldIntegrity ) {
					throw new RuntimeException(sprintf('The class %s is out of date, the field "%s" is unknown in database.', static::class, $fieldName));
				}
			} else {
				$fieldValue = $data[$fieldName];
			}
			$this->data[$fieldName] = $this->parseFieldSqlValue($fieldName, $fieldValue);
		}
		$this->originalData = [];
		if( DEBUG_ENABLED ) {
			$this->checkIntegrity();
		}
	}
	
	/**
	 * Check object integrity & validity
	 */
	public function checkIntegrity() {
	}
	
	/**
	 * Get this permanent object's ID
	 *
	 * @return string The id of this object.
	 */
	public function id(): string {
		return $this->getValue(static::getMetadata()->getDescriptor()->getIdField());
	}
	
	/**
	 * Get the value of field $key or all data values if $key is null.
	 *
	 * @param string|null $key Name of the field to get.
	 */
	public function getValue(?string $key = null): mixed {
		if( !$key ) {
			return $this->data;
		}
		if( !array_key_exists($key, $this->data) ) {
			throw new FieldNotFoundException($key, static::class);
		}
		
		return $this->data[$key];
	}
	
	/**
	 * Set the field $key with the new $value.
	 *
	 * @param string $key Name of the field to set
	 * @param mixed $value New value of the field
	 * @return $this
	 * @throws Exception
	 */
	public function setValue(string $key, mixed $value): static {
		if( !in_array($key, static::getFields()) ) {
			// Unknown key
			throw new FieldNotFoundException($key, static::class);
			
		} elseif( $key === static::getIdField() ) {
			// ID is not editable
			throw new Exception("idNotEditable");
			
		} elseif( $value !== $this->data[$key] ) {
			// The value is different
			if( !isset($this->originalData[$key]) ) {
				// Keep first one only, once updated, we should remove it
				$this->originalData[$key] = $this->data[$key];
			} else {
				if( $this->originalData[$key] === $value ) {
					// The first value is the same as the new one, revert changes
					unset($this->originalData[$key]);
				}
			}
			$this->data[$key] = $value;
		}
		
		return $this;
	}
	
	/**
	 * Destructor
	 *
	 * If something was modified, it saves the new data.
	 */
	public function __destruct() {
		if( $this->hasChanges() ) {
			try {
				$this->save();
			} catch( Exception $e ) {
				// Can be destructed outside the matrix
				log_error($e, 'PermanentEntity::__destruct(): Saving');
			}
		}
	}
	
	public function revert(): PermanentEntity {
		// Apply back the previous values
		foreach( $this->originalData as $key => $value ) {
			$this->data[$key] = $value;
			unset($this->originalData[$key]);
		}
		
		return $this;
	}
	
	public function hasChanges(): bool {
		return !!$this->originalData;
	}
	
	/**
	 * Save current changes of this entity to the database
	 * Input is NOT validated here, we recommend it for programming changes only
	 *
	 * @return bool|int True in case of success
	 * @throws Exception
	 * @see static::update()
	 */
	public function save(): bool|int {
		if( !$this->hasChanges() || $this->isDeleted() ) {
			return false;
		}
		
		$fields = array_keys($this->originalData);
		$data = array_filter_by_keys($this->data, $fields);
		if( !$data ) {
			throw new Exception('No updated data found but there is modified fields, unable to update');
		}
		$operation = $this->getUpdateOperation($data, $fields);
		// Do not validate, new data are invalid due to the fact the new data are already in object
		$r = $operation->run();
		// Object takes new values as acquired
		$this->originalData = [];
		if( !$this->onSavedInProgress ) {
			// Protect script against saving loops
			$this->onSavedInProgress = true;
			static::onSaved($data, $this);
			$this->onSavedInProgress = false;
		}
		
		return $r;
	}
	
	/**
	 * Update this permanent entity from input data array
	 * Parameter $fields is really useful to allow partial modification only (against form hack).
	 * Input is validated here, we recommend it for forms
	 *
	 * @param array $input The input data we will check and extract, used by children
	 * @param string[] $fields The array of fields to check
	 * @param int &$errCount Output parameter for the number of occurred errors validating fields.
	 */
	public function update(array $input, array $fields, int &$errCount = 0): bool {
		$operation = $this->getUpdateOperation($input, $fields);
		$operation->validate($errCount);
		
		return $operation->runIfValid();
	}
	
	/**
	 * Check if this object is deleted
	 *
	 * @return boolean True if this object is deleted
	 *
	 * Checks if this object is known as deleted.
	 */
	public function isDeleted(): bool {
		return $this->isDeleted;
	}
	
	/**
	 * Get the update operation
	 *
	 * @param array $input The input data we will check and extract, used by children
	 * @param string[] $fields The array of fields to check
	 */
	public function getUpdateOperation(array $input, array $fields): UpdateTransactionOperation {
		$operation = new UpdateTransactionOperation(static::class, $input, $fields, $this);
		$operation->setSqlAdapter(static::getSqlAdapter());
		
		return $operation;
	}
	
	/**
	 * Get this entity name
	 */
	public static function getEntity(): string {
		return static::getTable();
	}
	
	/**
	 * Try to load entity from an entity string and an id integer
	 */
	public static function loadEntity(string $entity, string $id): ?PermanentEntity {
		/** @var PermanentEntity $entity */
		return $entity::load($id);
	}
	
	/**
	 * Magic getter
	 *
	 * @param string $name Name of the property to get
	 * @return mixed The value of field $name
	 *
	 * Get the value of field $name.
	 * 'all' returns all fields.
	 */
	public function __get(string $name) {
		return $this->getValue($name == 'all' ? null : $name);
	}
	
	/**
	 * Magic setter for entity's property
	 *
	 * @param string $name Name of the property to set
	 * @param mixed $value New value of the property
	 * @throws Exception
	 */
	public function __set(string $name, mixed $value) {
		$this->setValue($name, $value);
	}
	
	/**
	 * Magic isset
	 *
	 * @param string $name Name of the property to check is set
	 * @return bool
	 *
	 * Checks if the field $name is set.
	 */
	public function __isset(string $name): bool {
		return isset($this->data[$name]);
	}
	
	/**
	 * Magic toString
	 *
	 * @return string The string value of the object.
	 */
	public function __toString() {
		try {
			return $this->getLabel();
		} catch( Exception $e ) {
			log_error($e->getMessage() . "<br />\n" . $e->getTraceAsString(), 'PermanentEntity::__toString()');
			
			return '';
		}
	}
	
	/**
	 * Get this permanent object's unique ID
	 *
	 * @return string The uid of this object.
	 *
	 * Get this object ID according to the table and id.
	 */
	public function uid(): string {
		return $this->getTable() . '#' . $this->id();
	}
	
	/**
	 * Free the object (remove)
	 *
	 * @see remove()
	 */
	public function free(): bool {
		if( $this->remove() ) {
			$this->data = [];
			$this->originalData = [];
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * What do you think it does ?
	 */
	public function remove(): bool {
		if( $this->isDeleted() ) {
			return true;
		}
		$operation = $this->getDeleteOperation();
		$errors = 0;
		$operation->validate($errors);
		
		return $operation->runIfValid();
	}
	
	/**
	 * Get the delete operation for this object
	 */
	public function getDeleteOperation(): DeleteTransactionOperation {
		$operation = new DeleteTransactionOperation(static::class, $this);
		$operation->setSqlAdapter(static::getSqlAdapter());
		
		return $operation;
	}
	
	/**
	 * Reload fields from database
	 * Also it removes the reloaded fields from the modified ones list.
	 */
	public function reload(): bool {
		$idField = static::getIdField();
		try {
			$data = static::requestSelect()
				->where($idField, '=', $this->$idField)
				->output(AbstractSqlAdapter::RETURN_ARRAY_FIRST)
				->run();
		} catch( SqlException ) {
			$data = null;
		}
		if( !$data ) {
			$this->markAsDeleted();
			
			return false;
		}
		$this->setData($data);
		
		return true;
	}
	
	/**
	 * Get data with an exportable format.
	 * We recommend to filter only data you need using $filterKeys
	 *
	 * @param string[]|null $filterKeys The key to filter, else all
	 */
	protected function getExportData(?array $filterKeys = null): array {
		$data = $filterKeys ? array_filter_by_keys($this->data, $filterKeys) : $this->data;
		foreach( $data as &$value ) {
			if( $value instanceof DateTime ) {
				$value = $value->format(DateTimeInterface::W3C);
			}
		}
		
		return $data;
	}
	
	/**
	 * Check if this object is cached and cache it
	 */
	protected function checkCache(): static {
		if( !static::getCachedInstance($this->id()) ) {
			static::setCachedInstance($this);
		}
		
		return $this;
	}
	
	public static function getCachedInstance(string $id): ?static {
		return static::getMetadata()->getInstance($id);
	}
	
	protected static function setCachedInstance(PermanentEntity $instance): void {
		static::getMetadata()->cacheInstance($instance);
	}
	
	/**
	 * Mark this object as deleted
	 *
	 * @see isDeleted()
	 * @warning Be sure what you are doing before calling this function (never out of this class' context).
	 *
	 * Mark this object as deleted
	 */
	public function markAsDeleted(): static {
		$this->isDeleted = true;
		
		return $this;
	}
	
	/**
	 * Check if this object is valid
	 *
	 * @return boolean True if this object is valid
	 *
	 * Check if this object is not deleted.
	 * May be used for others cases.
	 */
	public function isValid(): bool {
		return !$this->isDeleted();
	}
	
	/**
	 * Verify equality with another object
	 *
	 * @param mixed $other The object to compare.
	 * @return boolean True if this object represents the same data, else False.
	 *
	 * Compare the class and the ID field value of the 2 objects.
	 */
	public function equals(mixed $other): bool {
		return ($this === $other) || (is_object($other) && get_class($this) == get_class($other) && $this->id() == $other->id());
	}
	
	/**
	 * Log an event
	 *
	 * @param string $event The event to log in this object
	 * @param mixed $time A specified time to use for logging event
	 * @param string|null $ipAdd A specified IP Address to use for logging event
	 * @throws Exception
	 * @see getLogEvent()
	 * @deprecated USe another function or update this one
	 */
	public function logEvent(string $event, mixed $time = null, ?string $ipAdd = null): void {
		/** @noinspection PhpDeprecationInspection */
		$log = static::getLogEvent($event, $time, $ipAdd);
		if( in_array($event . '_time', static::getFields()) ) {
			$this->setValue($event . '_time', $log[$event . '_time']);
		} elseif( in_array($event . '_date', static::getFields()) ) {
			$this->setValue($event . '_date', dateTime($log[$event . '_time']));
		} else {
			return;
		}
		if( in_array($event . '_agent', static::getFields()) && isset($_SERVER['HTTP_USER_AGENT']) ) {
			$this->setValue($event . '_agent', $_SERVER['HTTP_USER_AGENT']);
		}
		if( in_array($event . '_referer', static::getFields()) && isset($_SERVER['HTTP_REFERER']) ) {
			$this->setValue($event . '_referer', $_SERVER['HTTP_REFERER']);
		}
		try {
			$this->setValue($event . '_ip', $log[$event . '_ip']);
		} catch( FieldNotFoundException ) {
		}
	}
	
	public function asArray(string $model = self::OUTPUT_MODEL_ALL): array {
		if( $model === self::OUTPUT_MODEL_ALL ) {
			return $this->getValue();
		}
		if( $model === self::OUTPUT_MODEL_MINIMALS ) {
			return ['id' => $this->id(), 'label' => $this->getLabel()];
		}
		
		return [];
	}
	
	/**
	 * Get entity instance by type and id
	 */
	public static function findEntityObject(string $entityType, string $entityId): PermanentEntity {
		$class = static::$mappingEntityClass[$entityType] ?? null;
		if( !$class ) {
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
	 */
	public static function getField(string $field): FieldDescriptor {
		return static::getDescriptor()->getField($field);
	}
	
	/**
	 * Register an entity
	 */
	public static function registerEntity(string $class): void {
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
			if( $state === null ) {
				$state = class_exists($class) && is_subclass_of($class, PermanentEntity::class);
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
	 * @param string|null $value The field value to parse
	 * @return mixed The parsed value
	 */
	protected static function parseFieldSqlValue(string $name, ?string $value): mixed {
		$field = static::getDescriptor()->getField($name);
		if( $field ) {
			return $field->getType()->parseSqlValue($field, $value);
		}
		
		return $value;
	}
	
	/**
	 * Format the value from PHP type to SQL scalar
	 *
	 * @param string $name The field name to format
	 * @param mixed $value The field value to format
	 * @return mixed The formatted $Value
	 */
	protected static function formatFieldSqlValue(string $name, mixed $value): mixed {
		$field = static::getDescriptor()->getField($name);
		if( $field ) {
			return $field->getType()->formatSqlValue($field, $value);
		}
		
		return $value;
	}
	
	public static function onEdit(array &$input, ?PermanentEntity $object): void {
		// Format all fields into SQL values
		foreach( $input as $field => &$value ) {
			/**
			 * Allowed value types are string, bool, NULL, object (with id field)
			 *
			 * @see AbstractSqlAdapter::escapeValue()
			 */
			$value = static::formatFieldSqlValue($field, $value);
		}
	}
	
	/**
	 * Get the SQL Adapter of this class
	 *
	 * @throws SqlException
	 */
	public static function getSqlAdapter(): AbstractSqlAdapter {
		return static::getMetadata()->getSqlAdapter();
	}
	
	/**
	 * Callback when object was saved
	 */
	public static function onSaved(array $data, PermanentEntity|int $object): void {
	}
	
	/**
	 * Get some permanent objects
	 *  Get an objects' list using this class' table.
	 *  Take care that output=AbstractSqlAdapter::ARR_OBJECTS and number=1 is different from output=AbstractSqlAdapter::OBJECT
	 *
	 * @param null $options The options used to get the permanents object
	 * @return SqlSelectRequest|array|PermanentEntity|null An array of array containing object's data
	 * @see AbstractSqlAdapter
	 * @deprecated Use static::getSelectRequest()
	 */
	public static function get($options = null): SqlSelectRequest|array|static|null {
		if( $options === null ) {
			return static::requestSelect();
		}
		if( is_string($options) ) {
			$args = func_get_args();
			$options = [];// Pointing argument
			/** @noinspection SpellCheckingInspection */
			foreach( ['where', 'orderby'] as $i => $key ) {
				if( !isset($args[$i]) ) {
					break;
				}
				$options[$key] = $args[$i];
			}
		}
		$options['table'] = static::getTable();
		// May be incompatible with old revisions (< R398)
		if( !isset($options['output']) ) {
			$options['output'] = AbstractSqlAdapter::ARR_OBJECTS;
		} else {
			$options['output'] = intval($options['output']);
		}
		//This method intercepts outputs of array of objects.
		$onlyOne = $objects = 0;
		if( in_array($options['output'], [AbstractSqlAdapter::ARR_OBJECTS, AbstractSqlAdapter::RETURN_OBJECT]) ) {
			if( $options['output'] === AbstractSqlAdapter::RETURN_OBJECT ) {
				$options['number'] = 1;
				$onlyOne = 1;
			}
			$options['output'] = AbstractSqlAdapter::RETURN_ARRAY_ASSOC;
			$objects = 1;
		}
		$sqlAdapter = static::getSqlAdapter();
		$r = $sqlAdapter->select($options);
		if( empty($r) && in_array($options['output'], [AbstractSqlAdapter::RETURN_ARRAY_ASSOC, AbstractSqlAdapter::ARR_OBJECTS, AbstractSqlAdapter::RETURN_ARRAY_FIRST]) ) {
			return $onlyOne && $objects ? null : [];
		}
		if( !empty($r) && $objects ) {
			if( $onlyOne ) {
				$r = static::instantiate($r[0]);
			} else {
				foreach( $r as &$rdata ) {
					$rdata = static::instantiate($rdata);
				}
			}
		}
		
		return $r;
	}
	
	/**
	 * Get select query
	 *
	 * @return SqlSelectRequest The query
	 * @see AbstractSqlAdapter
	 */
	public static function requestSelect(): SqlSelectRequest {
		return (new SqlSelectRequest(static::getSqlAdapter(), static::getIdField(), static::class))
			->from(static::getTable())->asObjectList();
	}
	
	/**
	 * Get insert query
	 *
	 * @return SqlInsertRequest The query
	 * @see AbstractSqlAdapter
	 */
	public static function requestInsert(): SqlInsertRequest {
		return (new SqlInsertRequest(static::getSqlAdapter(), static::getIdField(), static::class))
			->from(static::getTable());
	}
	
	/**
	 * Get update query
	 *
	 * @return SqlUpdateRequest The query
	 * @see AbstractSqlAdapter
	 */
	public static function requestUpdate(): SqlUpdateRequest {
		return (new SqlUpdateRequest(static::getSqlAdapter(), static::getIdField(), static::class))
			->from(static::getTable());
	}
	
	/**
	 * Loads the object with the ID $id or the array data.
	 * The return value is always a static object (no null, no array, no other object).
	 *
	 * @param mixed $id The object ID to load or a valid array of the object's data
	 * @param boolean $nullable True to silent errors row and return null
	 * @param boolean $useCache True to cache load and set cache, false to not cache
	 * @return PermanentEntity|null The object loaded from database
	 * @see static::get()
	 */
	public static function load(string $id, bool $nullable = true, bool $useCache = true): static|null {
		if( !is_id($id) ) {
			static::throwException('invalidId');
		}
		// Loading cached
		if( $useCache ) {
			$entity = static::getCachedInstance($id);
			if( $entity ) {
				return $entity;
			}
		}
		
		// Getting data
		$entity = static::requestSelect()
			->where('id', '=', $id)
			->asObject()
			->run();
		if( !$entity ) {
			if( $nullable ) {
				return null;
			}
			static::throwNotFound();
		}
		
		// Caching object
		return $entity;
	}
	
	public static function buildRaw(array $data, bool $useCache = true): static {
		// Loading cached
		if( $useCache ) {
			$id = $data[self::getIdField()];
			$entity = static::getCachedInstance($id);
			if( $entity ) {
				return $entity;
			}
		}
		
		return new static($data);
	}
	
	/**
	 * Throws an NotFoundException with the current domain
	 *
	 * @param string|null $message the text message, may be a translation string
	 * @see NotFoundException
	 */
	public static function throwNotFound(?string $message = null) {
		throw new NotFoundException($message, static::getDomain());
	}
	
	/**
	 * Throws an UserException with the current domain
	 *
	 * @param string $message the text message, may be a translation string
	 * @throws UserException
	 * @see UserException
	 */
	public static function throwException(string $message): void {
		throw new UserException($message, static::getDomain());
	}
	
	/**
	 * Instantiate object from data, allowing you to instantiate child class
	 */
	protected static function instantiate(array $data, bool $useCache = true): static {
		if( $useCache ) {
			$id = $data[static::getIdField()] ?? null;
			$entity = $id ? self::getCachedInstance($id) : null;
			if( $entity ) {
				return $entity;
			}
		}
		
		return new static($data);
	}
	
	/**
	 * Build a new log event for $event for this time and the user IP address.
	 *
	 * @param string $event The event to log in this object
	 * @param mixed|null $time A specified time to use for logging event
	 * @param string|null $ipAddress A specified IP Address to use for logging event
	 * @throws Exception
	 * @see logEvent()
	 * @deprecated
	 */
	public static function getLogEvent(string $event, mixed $time = null, ?string $ipAddress = null): array {
		return [
			$event . '_time' => $time ?? time(),
			$event . '_date' => dateTime($time),
			$event . '_ip'   => $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
		];
	}
	
	/**
	 * Callback when validating update
	 */
	public static function onValidUpdate(array &$input, int $newErrors): bool {
		if( $input ) {
			static::fillLogEvent($input, 'edit');
			static::fillLogEvent($input, 'update');
		}
		
		return !!$input;
	}
	
	/**
	 * Add an $event log in this $array
	 */
	public static function fillLogEvent(array &$array, string $event): void {
		// All event fields will be filled, if value is not available, we set to null
		$entityFields = static::getFields();
		if( in_array($event . '_time', $entityFields) ) {
			$array[$event . '_time'] = time();
		} elseif( in_array($event . '_date', $entityFields) ) {
			try {
				$array[$event . '_date'] ??= dateTime();
			} catch(Exception) {
				// Should never happen
			}
		} else {
			// Date and time are mandatory
			return;
		}
		if( in_array($event . '_ip', $entityFields) ) {
			$array[$event . '_ip'] = clientIP();
		}
		if( in_array($event . '_agent', $entityFields) ) {
			$array[$event . '_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
		}
		if( in_array($event . '_referer', $entityFields) ) {
			$array[$event . '_referer'] = $_SERVER['HTTP_REFERER'] ?? null;
		}
	}
	
	/**
	 * Get the object whatever we give to it
	 *
	 * @see id()
	 */
	public static function object(PermanentEntity|string &$entity): static|int|null {
		return $entity = is_object($entity) ? $entity : static::load($entity);
	}
	
	/**
	 * Cache an array of objects
	 *
	 * @param static[] $instances
	 */
	public static function cacheInstances(array &$instances): array {
		foreach( $instances as &$instance ) {
			$instance = $instance->checkCache();
		}
		
		return $instances;
	}
	
	/**
	 * Escape identifier through instance
	 *
	 * @param string|null $identifier The identifier to escape. Default is table name.
	 * @return string The escaped identifier
	 * @see static::escapeIdentifier()
	 * @deprecated Use SqlSelectRequest
	 */
	public static function ei(?string $identifier = null): string {
		return static::escapeIdentifier($identifier);
	}
	
	/**
	 * Escape identifier through instance
	 *
	 * @param string|null $identifier The identifier to escape. Default is table name.
	 * @return string The escaped identifier
	 * @see AbstractSqlAdapter::escapeIdentifier()
	 * @see static::ei()
	 * @deprecated Use SqlSelectRequest
	 */
	public static function escapeIdentifier(?string $identifier = null): string {
		$sqlAdapter = static::getSqlAdapter();
		
		return $sqlAdapter->escapeIdentifier($identifier ?: static::getTable());
	}
	
	/**
	 * Escape value through instance
	 *
	 * @param mixed $value The value to format
	 * @return string The formatted $Value
	 * @see AbstractSqlAdapter::formatValue()
	 */
	public static function formatValue(mixed $value): string {
		$sqlAdapter = static::getSqlAdapter();
		
		return $sqlAdapter->formatValue($value);
	}
	
	/**
	 * Callback when validating create
	 */
	public static function onValidCreate(array &$input, int &$newErrors): bool {
		static::fillLogEvent($input, 'create');
		static::fillLogEvent($input, 'edit');
		
		return true;
	}
	
	/**
	 * Callback when validating create
	 */
	public static function onValidEdit(array $input, ?PermanentEntity $object, int &$newErrors): bool {
		static::verifyConflicts($input, $object);
		
		return true;
	}
	
	/**
	 * Create a new permanent object
	 *
	 * @param array $input The input data we will check, extract and create the new object.
	 * @param array|null $fields The array of fields to check. Default value is null.
	 * @param int $errCount Output parameter to get the number of found errors. Default value is 0
	 * @return static The new permanent object
	 * @see testUserInput()
	 * @see create()
	 *
	 * Create a new permanent object from ths input data.
	 * To create an object, we expect that it is valid, else we throw an exception.
	 */
	public static function createAndGet(array $input = [], ?array $fields = null, int &$errCount = 0): static {
		return static::load(static::create($input, $fields, $errCount));
	}
	
	/**
	 * Create a new permanent object from ths input data.
	 * To create an object, we expect that it is valid, else we throw an exception.
	 *
	 * @param array $input The input data we will check, extract and create the new object.
	 * @param array|null $fields The array of fields to check. Default value is null.
	 * @param int $errCount Output parameter to get the number of found errors. Default value is 0
	 * @return int The ID of the new permanent object.
	 * @see testUserInput()
	 * @see createAndGet()
	 */
	public static function create(array $input = [], ?array $fields = null, int &$errCount = 0): int {
		// TODO Replace error count and result by OperationResult (success, errors, result)
		// TODO Implement OperationResult
		$operation = static::getCreateOperation($input, $fields);
		$operation->validate($errCount);
		if( !$operation->isValid() ) {
			static::throwException('errorCreateChecking');
		}
		
		return $operation->run();
	}
	
	/**
	 * Get the create operation
	 *
	 * @param array $input The input data we will check and extract, used by children
	 * @param string[] $fields The array of fields to check
	 */
	public static function getCreateOperation(array $input, ?array $fields): CreateTransactionOperation {
		$operation = new CreateTransactionOperation(static::class, $input, $fields);
		$operation->setSqlAdapter(static::getSqlAdapter());
		
		return $operation;
	}
	
	/**
	 * Test user input
	 *
	 * @param array $input The new data to process.
	 * @param array|null $fields The array of fields to check. Default value is null.
	 * @param PermanentEntity|null $ref The referenced object (update only). Default value is null.
	 * @param int $errCount The resulting error count, as pointer. Output parameter.
	 * @param array|bool $ignoreRequired
	 * @throws DuplicateException
	 * @see create()
	 * @see checkUserInput()
	 */
	public static function testUserInput(array $input, ?array $fields = null, PermanentEntity $ref = null, int &$errCount = 0, bool $ignoreRequired = false): bool {
		$data = static::checkUserInput($input, $fields, $ref, $errCount, $ignoreRequired);
		if( $errCount ) {
			return false;
		}
		try {
			static::verifyConflicts($data, $ref);
		} catch( UserException $e ) {
			$errCount++;
			reportError($e, static::getDomain());
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Check if the class could generate a valid object from $input.
	 * The method could modify the user input to fix it, but it must return the data.
	 * The data are passed through the validator, for different cases:
	 * - If empty, this function return an empty array.
	 * - If an array, it uses a field => checkMethod association.
	 *
	 * @param array $input The user input data to check.
	 * @param string[]|null $fields The array of fields to check. Default value is null.
	 * @param PermanentEntity|null $ref The referenced object (update only). Default value is null.
	 * @param int $errCount The resulting error count, as pointer. Output parameter.
	 * @return array The valid data.
	 */
	public static function checkUserInput(array $input, ?array $fields = null, ?PermanentEntity $ref = null, int &$errCount = 0, bool $ignoreRequired = false): array {
		$descriptor = static::getDescriptor();
		
		$data = $descriptor->validate($input, $fields, $ref, $errCount, $ignoreRequired);
		if( !$data ) {
			static::throwException($ref ? 'update.noChange' : 'create.emptyInput');
		}
		
		return $data;
	}
	
	/**
	 * Check for object
	 * This function is called by create() after checking user input data and before running for them.
	 * In the base class, this method does nothing.
	 *
	 * @param array $data The new data to process.
	 * @param PermanentEntity|null $object The referenced object (update only). Default value is null.
	 * @throws UserException
	 */
	public static function verifyConflicts(array $data, ?PermanentEntity $object = null) {
	}
	
	/**
	 * Initialize entity class
	 * You must call this method after the class declaration
	 * @throws CacheException
	 */
	public static function initialize(string $name): void {
		static::initializeWithDescriptor(EntityDescriptor::load($name));
	}
	
	public static function initializeWithDescriptor(EntityDescriptor $descriptor): void {
		$metadata = self::getMetadata();
		if( $metadata ) {
			throw new RuntimeException(sprintf('Unable to initialize already initialized class "%s", having "%s" while injecting "%s"',
				static::class, $metadata->getName(), $descriptor->getName()));
		}
		$descriptor->setClass(static::class);
		$metadata = new EntityMetadata($descriptor, AbstractSqlAdapter::getInstance());
		self::$entityMetadata[static::class] = $metadata;
		static::$mappingEntityClass[$metadata->getName()] = static::class;
	}
	
	public static function getMetadata(): ?EntityMetadata {
		return self::$entityMetadata[static::class] ?? null;
	}
	
	/**
	 * Translate text according to the object domain
	 *
	 * @param string $text The text to translate
	 * @param array $values The values array to replace in text. Could be used as second parameter.
	 * @return string The translated text
	 * @see t()
	 */
	public static function text(string $text, array $values = []): string {
		return t($text, static::getDomain(), $values);
	}
	
	/**
	 * Translate text according to the object domain
	 *
	 * @param string $text The text to translate
	 * @param array $values The values array to replace in text. Could be used as second parameter.
	 * @deprecated Use static::text() or t()
	 */
	public static function _text(string $text, array $values = []): void {
		echo static::text($text, $values);
	}
	
	/**
	 * Report an UserException
	 *
	 * @param UserException $e the user exception
	 * @see UserException
	 */
	public static function reportException(UserException $e): void {
		reportError($e);
	}
	
	/**
	 * Serve to bypass the integrity check, should be only used while developing
	 */
	public static function setCheckFieldIntegrity(bool $checkFieldIntegrity): void {
		static::$checkFieldIntegrity = $checkFieldIntegrity;
	}
	
	/**
	 * Get the name of this class
	 *
	 * @return string The name of this class.
	 * @deprecated USe static::class instead
	 */
	public static function getClass(): string {
		return get_called_class();
	}
	
	/**
	 * Get the table of this class
	 *
	 * @return string The table of this class.
	 */
	public static function getTable(): string {
		return static::getMetadata()->getDescriptor()->getTable();
	}
	
	/**
	 * Get the domain of this class, can be guessed from $table or specified in $domain.
	 *
	 * @return string The domain of this class.
	 */
	public static function getDomain(): string {
		return static::getMetadata()->getDescriptor()->getDomain();
	}
	
	/**
	 * Get the available fields of this entity
	 *
	 * @return string[] The available fields in entity
	 */
	public static function getFields(): array {
		return static::getMetadata()->getFields();
	}
	
	/**
	 * Get the ID field name of this entity
	 *
	 * @return string The ID field
	 */
	public static function getIdField(): string {
		return static::getMetadata()->getDescriptor()->getIdField();
	}
	
	public static function getDescriptor(): EntityDescriptor {
		return static::getMetadata()->getDescriptor();
	}
	
}
