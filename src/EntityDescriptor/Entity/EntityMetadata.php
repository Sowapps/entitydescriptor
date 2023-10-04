<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Entity;

use Orpheus\SqlAdapter\AbstractSqlAdapter;

class EntityMetadata {
	
	protected EntityDescriptor $descriptor;
	
	protected AbstractSqlAdapter $sqlAdapter;
	
	/**
	 * Cache of all object instances
	 *
	 * @var PermanentEntity[]
	 */
	protected array $instances = [];
	
	/**
	 * The fields of this object
	 *
	 * @var array|null
	 */
	protected ?array $fields = null;
	
	/**
	 * EntityMetadata constructor
	 */
	public function __construct(EntityDescriptor $descriptor, AbstractSqlAdapter $sqlAdapter) {
		$this->descriptor = $descriptor;
		$this->sqlAdapter = $sqlAdapter;
	}
	
	public function getName(): string {
		return $this->descriptor->getName();
	}
	
	public function getDescriptor(): EntityDescriptor {
		return $this->descriptor;
	}
	
	public function getSqlAdapter(): AbstractSqlAdapter {
		return $this->sqlAdapter;
	}
	
	public function getInstances(): array {
		return $this->instances;
	}
	
	public function getFields(): array {
		if( $this->fields === null ) {
			$this->fields = $this->descriptor->getFieldsName();
		}
		
		return $this->fields;
	}
	
	public function getInstance(string $id): ?PermanentEntity {
		return $this->instances[$id] ?? null;
	}
	
	public function cacheInstance(PermanentEntity $instance): void {
		$this->instances[$instance->id()] = $instance;
	}
	
	/**
	 * Remove deleted instances from cache
	 */
	public function clearDeletedInstances(): void {
		if( !$this->instances ) {
			return;
		}
		foreach( $this->instances as $id => $instance ) {
			if( $instance->isDeleted() ) {
				unset($this->instances[$id]);
			}
		}
	}
	
	/**
	 * Remove all instances
	 */
	public function clearAllInstances(): void {
		if( !$this->instances ) {
			return;
		}
		$this->instances = [];
	}
	
}
