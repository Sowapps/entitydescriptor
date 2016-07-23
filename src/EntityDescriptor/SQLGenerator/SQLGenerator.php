<?php
/**
 * SQLGenerator
 */

namespace Orpheus\EntityDescriptor\SQLGenerator;

use Orpheus\EntityDescriptor\EntityDescriptor;

/**
 * The SQLGenerator interface
 * 
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
interface SQLGenerator {
	
	/**
	 * Get column informations from $field
	 * 
	 * @param string $field
	 */
	public function getColumnInfosFromField($field);
	
	/**
	 * Get column definition
	 * 
	 * @param string $field
	 * @param boolean $withPK
	 */
	public function getColumnDefinition($field, $withPK=true);

	/**
	 * Get index definition
	 *
	 * @param string $index
	 */
	public function getIndexDefinition($index);
	
	/**
	 * Get changes with entity
	 * 
	 * @param EntityDescriptor $ed
	 */
	public function matchEntity(EntityDescriptor $ed);
	
	/**
	 * Get create SQL query
	 * 
	 * @param EntityDescriptor $ed
	 */
	public function getCreate(EntityDescriptor $ed);
	
}