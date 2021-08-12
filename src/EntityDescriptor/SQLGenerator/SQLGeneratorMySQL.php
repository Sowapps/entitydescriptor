<?php
/**
 * SQLGeneratorMySQL
 */

namespace Orpheus\EntityDescriptor\SQLGenerator;

use Orpheus\EntityDescriptor\EntityDescriptor;
use Orpheus\EntityDescriptor\TypeDate;
use Orpheus\EntityDescriptor\TypeDatetime;
use Orpheus\EntityDescriptor\TypeNumber;
use Orpheus\EntityDescriptor\TypePassword;
use Orpheus\EntityDescriptor\TypeString;
use Orpheus\Exception\UserException;
use Orpheus\SQLAdapter\Exception\SQLException;
use Orpheus\SQLAdapter\SQLAdapterMySQL;

/**
 * The SQLGeneratorMySQL class
 *
 * Use this class to generate entity's table SQL queries and check changes in structure
 *
 * @author Florent Hazard <contact@sowapps.com>
 *
 */
class SQLGeneratorMySQL implements SQLGenerator {
	
	/**
	 *
	 * {@inheritDoc}
	 * @param string $field
	 * @see \Orpheus\EntityDescriptor\SQLGenerator\SQLGenerator::getColumnInfosFromField()
	 */
	public function getColumnInfosFromField($field): array {
		$TYPE = EntityDescriptor::getType($field->type);
		$cType = '';
		if( $TYPE instanceof TypeString ) {
			$max = $TYPE instanceof TypePassword ? 128 : $field->args->max;
			if( $max < 256 ) {
				$cType = "VARCHAR({$field->args->max})";
			} elseif( $max < 65536 ) {
				$cType = "TEXT";
			} elseif( $max < 16777216 ) {
				$cType = "MEDIUMTEXT";
			} else {
				$cType = "LONGTEXT";
			}
		} elseif( $TYPE instanceof TypeNumber ) {
			if( !isset($field->args->max) ) {
				debug('Issue with ' . $field->name . ', missing max argument', $field->args);
			}
			$dc = strlen((int) $field->args->max);
			$unsigned = $field->args->min >= 0 ? 1 : 0;
			if( !$field->args->decimals ) {
				// 				$max	= (int) $field->args->max;// Int max on 32 bits systems is incompatible with SQL
				$max = $field->args->max;// Treat it as in
				// 				debug('$field - '.$field->name.', type='.$field->type.' => '.$max);
				$f = 1 + 1 * $unsigned;
				if( $max < 128 * $f ) {
					$cType = "TINYINT";
				} elseif( $max < 32768 * $f ) {
					$cType = "SMALLINT";
				} elseif( $max < 8388608 * $f ) {
					$cType = "MEDIUMINT";
				} elseif( $max < 2147483648 * $f ) {
					$cType = "INT";
				} else {
					$cType = "BIGINT";
				}
				$cType .= '(' . strlen($max) . ')';
				
			} else {
				$dc += $field->args->decimals + 1;
				// http://code.rohitink.com/2013/06/12/mysql-integer-float-decimal-data-types-differences/
				if( $dc < 23 && $field->args->decimals < 8 ) {// Approx accurate to 7 decimals
					// 				if( $dc < 7 ) {// Approx accurate to 7 decimals
					$cType = "FLOAT";
				} else {// Approx accurate to 15 decimals
					$cType = "DOUBLE";
				}
				$cType .= "({$dc},{$field->args->decimals})";
			}
			if( $unsigned ) {
				$cType .= ' UNSIGNED';
			}
		} elseif( $TYPE instanceof TypeDate ) {
			$cType = 'DATE';
		} elseif( $TYPE instanceof TypeDatetime ) {
			$cType = 'DATETIME';
		} else {
			throw new UserException('Type of ' . $field->name . ' (' . $TYPE->getName() . ') not found');
		}
		$r = ['name' => $field->name, 'type' => $cType, 'nullable' => !!$field->nullable];
		$r['autoIncrement'] = $r['primaryKey'] = ($field->name == 'id');
		
		return $r;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @param string $field
	 * @param boolean $withPK
	 * @see \Orpheus\EntityDescriptor\SQLGenerator\SQLGenerator::getColumnDefinition()
	 */
	public function getColumnDefinition($field, $withPK = true): string {
		// 	text('mysqlColumnDefinition()');
		// 	text($field);
		$field = (object) $field;
		
		return $this->formatHTML_Identifier($field->name) . ' ' . $this->formatHTML_ColumnType($field->type) .
			' ' . $this->formatHTML_ReservedWord($field->nullable ? 'NULL' : 'NOT NULL') .
			(!empty($field->autoIncrement) ? ' ' . $this->formatHTML_ReservedWord('AUTO_INCREMENT') : '') . (($withPK && !empty($field->primaryKey)) ? ' ' . $this->formatHTML_ReservedWord('PRIMARY KEY') : '');
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @param string $index
	 * @see \Orpheus\EntityDescriptor\SQLGenerator\SQLGenerator::getIndexDefinition()
	 */
	public function getIndexDefinition($index): string {
		$fields = '';
		foreach( $index->fields as $field ) {
			$fields .= ($fields ? ', ' : '') . $this->formatHTML_Identifier($field);
		}
		
		return $this->formatHTML_ReservedWord($index->type) . (!empty($index->name) ? ' ' . $this->formatHTML_Identifier($index->name) : '') . ' (' . $fields . ')';
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @param EntityDescriptor $ed
	 * @see \Orpheus\EntityDescriptor\SQLGenerator\SQLGenerator::matchEntity()
	 */
	public function matchEntity(EntityDescriptor $ed): ?string {
		try {
			// Try to update, if SHOW fails, we try to create the table
			$columns = pdo_query('SHOW COLUMNS FROM ' . SQLAdapterMySQL::doEscapeIdentifier($ed->getName()), PDOFETCHALL);//|PDOERROR_MINOR
			// Fields
			$fields = $ed->getFields();
			$alter = '';
			foreach( $columns as $cc ) {
				$cc = (object) $cc;
				$cf = ['name'       => $cc->Field, 'type' => strtoupper($cc->Type), 'nullable' => $cc->Null == 'YES',
					   'primaryKey' => $cc->Key == 'PRI', 'autoIncrement' => strpos($cc->Extra, 'auto_increment') !== false];
				if( isset($fields[$cf['name']]) ) {
					$f = $this->getColumnInfosFromField($fields[$cf['name']]);
					unset($fields[$cf['name']]);
					// Current definition is different from former
					if( $f != $cf ) {
						// 						$alter .= (!empty($alter) ? ", \n" : '')."\t CHANGE COLUMN ".SQLAdapter::doEscapeIdentifier($cf['name']).' '.$this->getColumnDefinition($f, !$cf['primaryKey']);
						$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('CHANGE COLUMN') . ' ' . $this->formatHTML_Identifier($cf['name']) . ' ' . $this->getColumnDefinition($f, !$cf['primaryKey']);
					}
				} else {
					// Remove column
					// 					$alter .= (!empty($alter) ? ", \n" : '')."\t DROP COLUMN ".SQLAdapter::doEscapeIdentifier($cf['name']);
					$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('DROP COLUMN') . ' ' . $this->formatHTML_Identifier($cf['name']);
				}
			}
			foreach( $fields as $f ) {
				// 				$alter .= (!empty($alter) ? ", \n" : '')."\t ADD COLUMN ".$this->getColumnDefinition($this->getColumnInfosFromField($f));
				$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('ADD COLUMN') . ' ' . $this->getColumnDefinition($this->getColumnInfosFromField($f));
			}
			unset($fields, $f, $cc, $cf, $columns);
			// Indexes
			try {
				$rawIndexes = pdo_query('SHOW INDEX FROM ' . SQLAdapterMySQL::doEscapeIdentifier($ed->getName()), PDOFETCHALL);//|PDOERROR_MINOR
				$indexes = $ed->getIndexes();
				// Current indexes
				$cIndexes = [];
				foreach( $rawIndexes as $ci ) {
					$ci = (object) $ci;
					if( $ci->Key_name === 'PRIMARY' ) {
						continue;
					}
					if( !isset($cIndexes[$ci->Key_name]) ) {
						$type = 'INDEX';
						if( !$ci->Non_unique ) {
							$type = 'UNIQUE';
						} elseif( $ci->Index_type === 'FULLTEXT' ) {
							$type = 'FULLTEXT';
						}
						$cIndexes[$ci->Key_name] = (object) ['name' => $ci->Key_name, 'type' => $type, 'fields' => []];
					}
					$cIndexes[$ci->Key_name]->fields[] = $ci->Column_name;
				}
				// Match new to current ones
				foreach( $cIndexes as $ci ) {
					$found = 0;
					foreach( $indexes as $ii => $index ) {
						if( $index->type === $ci->type && $index->fields == $ci->fields ) {
							unset($indexes[$ii]);
							$found = 1;
							break;
						}
					}
					if( !$found ) {
						// Remove index
						$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('DROP INDEX') . ' ' . $this->formatHTML_Identifier($ci->name);
						// 						$alter .= (!empty($alter) ? ", \n" : '')."\t DROP INDEX ".SQLAdapter::doEscapeIdentifier($ci->name);
					}
				}
				foreach( $indexes as $index ) {
					$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('ADD') . ' ' . $this->getIndexDefinition($index);
					// 					$alter .= (!empty($alter) ? ", \n" : '')."\t ADD ".$this->getIndexDefinition($index);
				}
			} catch( SQLException $e ) {
				return null;
			}
			if( empty($alter) ) {
				return null;
			}
			
			return '<div class="table-operation table-alter">' . $this->formatHTML_Command('ALTER TABLE') . ' ' . $this->formatHTML_Identifier($ed->getName()) . "\n{$alter};</div>";
		} catch( SQLException $e ) {
			return $this->getCreate($ed);
		}
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @param EntityDescriptor $ed
	 * @see \Orpheus\EntityDescriptor\SQLGenerator\SQLGenerator::getCreate()
	 */
	public function getCreate(EntityDescriptor $ed): string {
		$createDefinition = '';
		foreach( $ed->getFields() as $field ) {
			$createDefinition .= (!empty($createDefinition) ? ", \n" : '') . "\t" . $this->getColumnDefinition($this->getColumnInfosFromField($field));
		}
		foreach( $ed->getIndexes() as $index ) {
			$createDefinition .= ", \n\t" . $this->getIndexDefinition($index);
		}
		if( !$createDefinition ) {
			throw new UserException('No columns');
		}
		
		return '
<div class="table-operation table-create">' . $this->formatHTML_Command('CREATE TABLE IF NOT EXISTS') . ' ' . $this->formatHTML_Identifier($ed->getName()) . ' (
' . $createDefinition . '
) ' . $this->formatHTML_ReservedWord('ENGINE=MYISAM') . ' ' . $this->formatHTML_ReservedWord('CHARACTER SET') . ' ' . $this->formatHTML_Identifier('utf8') . ';</div>';
	}
	
	/**
	 * Format command into HTML
	 *
	 * @param string $string
	 * @return string
	 */
	protected function formatHTML_Command($string): string {
		return $this->formatHTML_ReservedWord($string, 'query_command');
	}
	
	/**
	 * Format subcommand into HTML
	 *
	 * @param string $string
	 * @return string
	 */
	protected function formatHTML_SubCommand($string): string {
		return $this->formatHTML_ReservedWord("\t " . $string, 'query_subCommand');
	}
	
	/**
	 * Format column type into HTML
	 *
	 * @param string $string
	 * @return string
	 */
	protected function formatHTML_ColumnType($string): string {
		return $this->formatHTML_ReservedWord($string, 'query_columnType');
	}
	
	/**
	 * Format reserved word into HTML
	 *
	 * @param string $string
	 * @param string $class
	 * @return string
	 */
	protected function formatHTML_ReservedWord($string, $class = ''): string {
		return $this->formatHTML_InlineBlock($string, 'query_reservedWord ' . $class);
	}
	
	/**
	 * Format identifier into HTML
	 *
	 * @param string $string
	 * @return string
	 */
	protected function formatHTML_Identifier($string): string {
		return $this->formatHTML_InlineBlock(SQLAdapterMySQL::doEscapeIdentifier($string), 'query_identifier');
	}
	
	/**
	 * Format inline block
	 *
	 * @param string $string
	 * @param string $class
	 * @return string
	 */
	protected function formatHTML_InlineBlock($string, $class): string {
		return '<div class="ib ' . $class . '">' . $string . '</div>';
	}
	
}
