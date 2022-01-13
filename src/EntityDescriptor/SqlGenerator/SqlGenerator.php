<?php
/**
 * SqlGenerator
 */

namespace Orpheus\EntityDescriptor\SqlGenerator;

use Orpheus\EntityDescriptor\EntityDescriptor;
use Orpheus\EntityDescriptor\FieldDescriptor;
use Orpheus\SqlAdapter\SqlAdapter;

/**
 * The SqlGenerator interface
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
interface SqlGenerator {
	
	/**
	 * Get column information from $field
	 *
	 * @param FieldDescriptor $field
	 * @return array
	 */
	public function getColumnInfosFromField(FieldDescriptor $field): array;
	
	/**
	 * Get column definition
	 *
	 * @param array $fieldColumn
	 * @param boolean $withPK
	 * @return string
	 */
	public function getColumnDefinition(array $fieldColumn, SqlAdapter $sqlAdapter, $withPK = true): string;
	
	/**
	 * Get index definition
	 *
	 * @param string $index
	 * @return string
	 */
	public function getIndexDefinition($index, SqlAdapter $sqlAdapter): string;
	
	/**
	 * Get changes with entity
	 *
	 * @param EntityDescriptor $ed
	 * @return string
	 */
	public function matchEntity(EntityDescriptor $ed, SqlAdapter $sqlAdapter): ?string;
	
	/**
	 * Get create SQL query
	 *
	 * @param EntityDescriptor $ed
	 * @return string
	 */
	public function getCreate(EntityDescriptor $ed, SqlAdapter $sqlAdapter);
	
}
