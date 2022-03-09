<?php

namespace Orpheus\EntityDescriptor;

use Orpheus\Exception\NotFoundException;
use Orpheus\Exception\UserException;
use Orpheus\SqlRequest\SqlSelectRequest;
use RuntimeException;

class EntityService {
	
	/**
	 * @var string The entity class, if not used, override the calling methods
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
	 *
	 * @param string $entityClass
	 */
	public function __construct(string $entityClass) {
		$this->entityClass = $entityClass;
	}
	
	/**
	 * @return array
	 */
	public function extractPublicArray($item, $model = 'all') {
		if( method_exists($item, 'asArray') ) {
			return $item->asArray($model);
		}
		return $item->all;
	}
	
	/**
	 * @return string
	 */
	public function getDomain() {
		$c = $this->getEntityClass();
		return $c::getDomain();
	}
	
	/**
	 * @return string|PermanentEntity
	 */
	public function getEntityClass() {
		if( !$this->entityClass ) {
			throw new RuntimeException('Invalid declaration of ' . get_called_class() . ', override calling methods or provide entityClass property');
		}
		return $this->entityClass;
	}
	
	/**
	 * @param string|int $id
	 * @return PermanentEntity
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function loadItem($id) {
		$c = $this->getEntityClass();
		return $c::load($id, false);
	}
	
	/**
	 * @param array $input
	 * @param array|null $fields
	 * @return int The new item ID
	 */
	public function createItem($input, $fields = null) {
		$c = $this->getEntityClass();
		if( $fields == null ) {
			$fields = $this->getEditableFields($input, null);
		}
		if( method_exists($c, 'make') ) {
			return call_user_func([$c, 'make'], $input, $fields);
		}
		return call_user_func([$c, 'create'], $input, $fields);
	}
	
	public function getEditableFields(array $input, ?PermanentEntity $item) {
		return $this->fields ? $this->fields->getEditFields() : call_user_func([$this->getEntityClass(), 'getEditableFields'], $input, $item);
	}
	
	/**
	 * @param array|null $filter
	 * @return SqlSelectRequest
	 */
	public function getSelectQuery($filter = null): SqlSelectRequest {
		/** @var PermanentEntity $entityClass */
		$entityClass = $this->getEntityClass();
		/** @var SqlSelectRequest $query */
		$query = $entityClass::get();
		if( !empty($filter['max']) ) {
			$query->number($filter['max']);
			if( !empty($filter['page']) ) {
				$query->fromOffset($filter['max'] * ($filter['page'] - 1));
			}
		}
		if( !empty($filter['search']) && is_array($filter['search']) ) {
			foreach( $filter['search'] as $searchModel => $searchValue ) {
				if( method_exists($entityClass, 'applyFilter') ) {
					$entityClass::applyFilter($query, $searchModel, $searchValue);
				} elseif( method_exists($entityClass, 'formatCondition') ) {
					$query->where($entityClass::formatCondition($searchModel, $searchValue));
				} else {
					$query->where($searchModel, 'LIKE', '%' . $searchValue . '%');
				}
			}
		}
		return $query;
	}
	
	public function updateItem(PermanentEntity $item, $input, $fields = null): int {
		return $item->update($input, $fields !== null ? $fields : $this->getEditableFields($input, $item));
	}
	
	public function deleteItem(PermanentEntity $item): int {
		return $item->remove();
	}
	
	public function addColumn($label, $orderKey, $valueFunction) {
		$this->columns[] = (object) [
			'label'         => $label,
			'orderKey'      => $orderKey,
			'valueFunction' => $valueFunction,
		];
	}
	
	/**
	 * @return array
	 */
	public function getColumns(): array {
		return $this->columns;
	}
	
	public function getItemLink($item) {
		return $item->getLink();
	}
	
	/**
	 * @return array
	 */
	public function getFields(): array {
		return $this->fields;
	}
	
	/**
	 * @param array $fields
	 */
	public function setFields(array $fields) {
		$this->fields = $fields;
	}
	
}
