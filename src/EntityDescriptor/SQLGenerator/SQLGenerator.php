<?php
namespace Orpheus\EntityDescriptor\SQLGenerator;

use Orpheus\EntityDescriptor\EntityDescriptor;

// MySQL Generator

interface SQLGenerator {
	public function getColumnInfosFromField($field);
	
	public function getColumnDefinition($field, $withPK=true);
	
	public function getIndexDefinition($index);
	
	public function matchEntity(EntityDescriptor $ed);
	
	public function getCreate(EntityDescriptor $ed);
	
	/*
	protected function formatHTML_Command($string);
	
	protected function formatHTML_SubCommand($string);
	
	protected function formatHTML_ColumnType($string);
	
	protected function formatHTML_ReservedWord($string, $class='');
	
	protected function formatHTML_Identifier($string);
	
	protected function formatHTML_InlineBlock($string, $class);
	*/
}