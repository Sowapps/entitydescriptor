<?php
/**
 * Sql
 */

namespace Orpheus\EntityDescriptor\Generator\Sql;

use Orpheus\EntityDescriptor\Entity\EntityDescriptor;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\SqlAdapter\AbstractSqlAdapter;

/**
 * The Sql interface
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
interface SqlGenerator {
	
	/**
	 * Get column information from $field
	 */
	public function getColumnInfosFromField(FieldDescriptor $field): array;
	
	/**
	 * Get column definition
	 */
	public function getColumnDefinition(array $fieldColumn, AbstractSqlAdapter $sqlAdapter, bool $withPK = true): string;
	
	/**
	 * Get index definition
	 */
	public function getIndexDefinition(object $index, AbstractSqlAdapter $sqlAdapter): string;
	
	/**
	 * Get changes with entity
	 */
	public function getIncrementalChanges(EntityDescriptor $ed, AbstractSqlAdapter $sqlAdapter): ?string;
	
	/**
	 * Get create SQL query
	 */
	public function getCreate(EntityDescriptor $ed, AbstractSqlAdapter $sqlAdapter): string;
	
}
