<?php

namespace Orpheus\EntityDescriptor\Service;

use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\Exception\NotFoundException;
use Orpheus\Exception\UserException;
use Orpheus\SqlRequest\SqlSelectRequest;
use RuntimeException;

/**
 * One entity service
 */
class EntityService {
	
	/**
	 * @var class-string<PermanentEntity> The entity class, if not used, override the calling methods
	 */
	protected string $entityClass;
	
	/**
	 * @var array The fields
	 */
	protected array $fields = [];
	
	/**
	 * @var array The columns
	 */
	protected array $columns = [];
	
	/**
	 * EntityService constructor.
	 */
	public function __construct(string $entityClass) {
		$this->entityClass = $entityClass;
	}
	
	public function extractPublicArray(object $item, $model = 'all'): array {
		if( method_exists($item, 'asArray') ) {
			return $item->asArray($model);
		}
		return $item->all;
	}
	
	/**
	 * @return class-string<PermanentEntity>
	 */
	public function getEntityClass(): string {
		if( !$this->entityClass ) {
			throw new RuntimeException('Invalid declaration of ' . get_called_class() . ', override calling methods or provide entityClass property');
		}
		return $this->entityClass;
	}
	
	public function getDomain(): string {
		/** @var class-string<PermanentEntity> $class */
		$class = $this->getEntityClass();
		return $class::getDomain();
	}
	
	/**
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadItem(string $id): PermanentEntity {
		/** @var class-string<PermanentEntity> $class */
		$class = $this->getEntityClass();
		return $class::load($id, false);
	}
	
	/**
	 * @return string The new item ID
	 */
	public function createItem(array $input, ?array $fields = null): string {
		$c = $this->getEntityClass();
		if( $fields == null ) {
			$fields = $this->getEditableFields($input, null);
		}
		if( method_exists($c, 'make') ) {
			return call_user_func([$c, 'make'], $input, $fields);
		}
		return call_user_func([$c, 'create'], $input, $fields);
	}
	
	public function getEditableFields(array $input, ?PermanentEntity $item): array {
		return !$this->fields ? call_user_func([$this->getEntityClass(), 'getEditableFields'], $input, $item) : $this->fields;
	}
	
	public function getSelectQuery(?array $filter = null): SqlSelectRequest {
		/** @var class-string<PermanentEntity> $class */
		$class = $this->getEntityClass();
		/** @var SqlSelectRequest $query */
		$query = $class::requestSelect();
		if( !empty($filter['max']) ) {
			$query->number($filter['max']);
			if( !empty($filter['page']) ) {
				$query->fromOffset($filter['max'] * ($filter['page'] - 1));
			}
		}
		if( !empty($filter['search']) && is_array($filter['search']) ) {
			foreach( $filter['search'] as $searchModel => $searchValue ) {
				if( method_exists($class, 'applyFilter') ) {
					$class::applyFilter($query, $searchModel, $searchValue);
				} elseif( method_exists($class, 'formatCondition') ) {
					$query->where($class::formatCondition($searchModel, $searchValue));
				} else {
					$query->where($searchModel, 'LIKE', '%' . $searchValue . '%');
				}
			}
		}
		return $query;
	}
	
	public function updateItem(PermanentEntity $item, array $input, ?array $fields = null): bool {
		return $item->update($input, $fields !== null ? $fields : $this->getEditableFields($input, $item));
	}
	
	public function deleteItem(PermanentEntity $item): bool {
		return $item->remove();
	}
	
	public function addColumn(string $label, string $orderKey, callable $valueFunction): void {
		$this->columns[] = (object) [
			'label'         => $label,
			'orderKey'      => $orderKey,
			'valueFunction' => $valueFunction,
		];
	}
	
	public function getColumns(): array {
		return $this->columns;
	}
	
	public function getFields(): array {
		return $this->fields;
	}
	
	public function setFields(array $fields): void {
		$this->fields = $fields;
	}
	
}
