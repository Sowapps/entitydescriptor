<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Generator\Sql;

use Exception;
use Orpheus\EntityDescriptor\Entity\EntityDescriptor;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Type\TypeDate;
use Orpheus\EntityDescriptor\Type\TypeDatetime;
use Orpheus\EntityDescriptor\Type\TypeNumber;
use Orpheus\EntityDescriptor\Type\TypePassword;
use Orpheus\EntityDescriptor\Type\TypeString;
use Orpheus\Exception\UserException;
use Orpheus\SqlAdapter\AbstractSqlAdapter;
use Orpheus\SqlAdapter\Exception\SqlException;

/**
 * The SqlGeneratorMySql class
 *
 * Use this class to generate entity's table SQL queries and check changes in structure
 */
class SqlGeneratorMySql implements SqlGenerator {
	
	/**
	 * @throws Exception
	 */
	public function getColumnInfosFromField(FieldDescriptor $field): array {
		$fieldType = EntityDescriptor::getType($field->type);
		if( $fieldType instanceof TypeString ) {
			$max = $fieldType instanceof TypePassword ? 128 : $field->args->max;
			if( $max < 256 ) {
				$cType = "VARCHAR({$field->args->max})";
			} elseif( $max < 65536 ) {
				$cType = "TEXT";
			} elseif( $max < 16777216 ) {
				$cType = "MEDIUMTEXT";
			} else {
				$cType = "LONGTEXT";
			}
		} elseif( $fieldType instanceof TypeNumber ) {
			if( !isset($field->args->max) ) {
				throw new Exception(sprintf('Issue with %s, missing max argument', $field->name));
			}
			$dc = strlen((int) $field->args->max);
			$unsigned = $field->args->min >= 0 ? 1 : 0;
			if( !$field->args->decimals ) {
				// 				$max	= (int) $field->args->max;// Int max on 32 bits systems is incompatible with SQL
				$max = $field->args->max;// Treat it as in
				// 				debug('$field - '.$field->name.', type='.$field->type.' => '.$max);
				$f = 1 + $unsigned;
				if( $max < 128 * $f ) {
					$cType = 'TINYINT';
				} elseif( $max < 32768 * $f ) {
					$cType = 'SMALLINT';
				} elseif( $max < 8388608 * $f ) {
					$cType = 'MEDIUMINT';
				} elseif( $max < 2147483648 * $f ) {
					$cType = 'INT';
				} else {
					$cType = 'BIGINT';
				}
				$cType .= '(' . strlen($max) . ')';
				
			} else {
				$dc += $field->args->decimals + 1;
				// http://code.rohitink.com/2013/06/12/mysql-integer-float-decimal-data-types-differences/
				if( $dc < 23 && $field->args->decimals < 8 ) {// Approx accurate to 7 decimals
					// 				if( $dc < 7 ) {// Approx accurate to 7 decimals
					$cType = 'FLOAT';
				} else {// Approx accurate to 15 decimals
					$cType = 'DOUBLE';
				}
				$cType .= sprintf('(%s,%s)', $dc, $field->args->decimals);
			}
			if( $unsigned ) {
				$cType .= ' UNSIGNED';
			}
		} elseif( $fieldType instanceof TypeDate ) {
			$cType = 'DATE';
		} elseif( $fieldType instanceof TypeDatetime ) {
			$cType = 'DATETIME';
		} else {
			throw new UserException(sprintf('Type of %s (%s) not found', $field->name, $fieldType->getName()));
		}
		$r = ['name' => $field->name, 'type' => $cType, 'nullable' => !!$field->nullable];
		$r['autoIncrement'] = $r['primaryKey'] = ($field->name === 'id');
		
		return $r;
	}
	
	public function getColumnDefinition(array $fieldColumn, AbstractSqlAdapter $sqlAdapter, bool $withPK = true): string {
		$fieldColumn = (object) $fieldColumn;
		
		return $this->formatHTML_Identifier($fieldColumn->name, $sqlAdapter) . ' ' . $this->formatHTML_ColumnType($fieldColumn->type) .
			' ' . $this->formatHTML_ReservedWord($fieldColumn->nullable ? 'NULL' : 'NOT NULL') .
			(!empty($fieldColumn->autoIncrement) ? ' ' . $this->formatHTML_ReservedWord('AUTO_INCREMENT') : '') . (($withPK && !empty($fieldColumn->primaryKey)) ? ' ' . $this->formatHTML_ReservedWord('PRIMARY KEY') : '');
	}
	
	public function getIndexDefinition(object $index, AbstractSqlAdapter $sqlAdapter): string {
		$fields = '';
		foreach( $index->fields as $field ) {
			$fields .= ($fields ? ', ' : '') . $this->formatHTML_Identifier($field, $sqlAdapter);
		}
		
		return $this->formatHTML_ReservedWord($index->type) . (!empty($index->name) ? ' ' . $this->formatHTML_Identifier($index->name, $sqlAdapter) : '') . ' (' . $fields . ')';
	}
	
	/**
	 * @throws Exception
	 */
	public function getIncrementalChanges(EntityDescriptor $ed, AbstractSqlAdapter $sqlAdapter): ?string {
		try {
			// Try to update, if SHOW fails, we try to create the table
			$columns = $sqlAdapter->query(sprintf('SHOW COLUMNS FROM %s', $sqlAdapter->escapeIdentifier($ed->getName())), AbstractSqlAdapter::QUERY_FETCH_ALL);
			// Fields
			$fields = $ed->getFields();
			$alter = '';
			foreach( $columns as $cc ) {
				$cc = (object) $cc;
				$cf = ['name'       => $cc->Field, 'type' => strtoupper($cc->Type), 'nullable' => $cc->Null === 'YES',
					   'primaryKey' => $cc->Key === 'PRI', 'autoIncrement' => str_contains($cc->Extra, 'auto_increment')];
				if( isset($fields[$cf['name']]) ) {
					$fieldColumn = $this->getColumnInfosFromField($fields[$cf['name']]);
					unset($fields[$cf['name']]);
					// Current definition is different from former
					if( $fieldColumn !== $cf ) {
						$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('CHANGE COLUMN') . ' ' . $this->formatHTML_Identifier($cf['name'], $sqlAdapter) .
							' ' . $this->getColumnDefinition($fieldColumn, $sqlAdapter, !$cf['primaryKey']);
					}
				} else {
					// Remove column
					$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('DROP COLUMN') . ' ' . $this->formatHTML_Identifier($cf['name'], $sqlAdapter);
				}
			}
			foreach( $fields as $fieldColumn ) {
				$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('ADD COLUMN') . ' ' . $this->getColumnDefinition($this->getColumnInfosFromField($fieldColumn), $sqlAdapter);
			}
			unset($fields, $fieldColumn, $cc, $cf, $columns);
			// Indexes
			try {
				$rawIndexes = $sqlAdapter->query(sprintf('SHOW INDEX FROM %s', $sqlAdapter->escapeIdentifier($ed->getName())), AbstractSqlAdapter::QUERY_FETCH_ALL);
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
						if( $index->type === $ci->type && $index->fields === $ci->fields ) {
							unset($indexes[$ii]);
							$found = 1;
							break;
						}
					}
					if( !$found ) {
						// Remove index
						$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('DROP INDEX') . ' ' . $this->formatHTML_Identifier($ci->name, $sqlAdapter);
					}
				}
				foreach( $indexes as $index ) {
					$alter .= (!empty($alter) ? ", \n" : '') . $this->formatHTML_SubCommand('ADD') . ' ' . $this->getIndexDefinition($index, $sqlAdapter);
				}
			} catch( SqlException ) {
				return null;
			}
			if( empty($alter) ) {
				return null;
			}
			
			return sprintf('<div class="table-operation table-alter">%s %s%s%s;</div>',
				$this->formatHTML_Command('ALTER TABLE'), $this->formatHTML_Identifier($ed->getName(), $sqlAdapter), "\n", $alter);
		} catch( SqlException ) {
			return $this->getCreate($ed, $sqlAdapter);
		}
	}
	
	/**
	 * @throws Exception
	 */
	public function getCreate(EntityDescriptor $ed, AbstractSqlAdapter $sqlAdapter): string {
		$createDefinition = '';
		foreach( $ed->getFields() as $field ) {
			$createDefinition .= ($createDefinition ? ",\n" : '') . "\t" . $this->getColumnDefinition($this->getColumnInfosFromField($field), $sqlAdapter);
		}
		foreach( $ed->getIndexes() as $index ) {
			$createDefinition .= ",\n\t" . $this->getIndexDefinition($index, $sqlAdapter);
		}
		if( !$createDefinition ) {
			throw new UserException('No columns');
		}
		
		return sprintf('
<div class="table-operation table-create">%s %s (
%s
) %s %s %s;</div>', $this->formatHTML_Command(/** @lang text */ 'CREATE TABLE IF NOT EXISTS'), $this->formatHTML_Identifier($ed->getTable(), $sqlAdapter), $createDefinition,
			$this->formatHTML_ReservedWord('ENGINE=MYISAM'), $this->formatHTML_ReservedWord('CHARACTER SET'), $this->formatHTML_Identifier('utf8', $sqlAdapter));
	}
	
	public function getDrop(EntityDescriptor $ed, AbstractSqlAdapter $sqlAdapter): string {
		return sprintf('
<div class="table-operation table-delete">%s %s;</div>',
			$this->formatHTML_Command(/** @lang text */ 'DROP TABLE IF EXISTS'),
			$this->formatHTML_Identifier($ed->getTable(), $sqlAdapter)
		);
	}
	
	/**
	 * Format command into HTML
	 */
	protected function formatHTML_Command(string $string): string {
		return $this->formatHTML_ReservedWord($string, 'query_command');
	}
	
	/**
	 * Format subcommand into HTML
	 */
	protected function formatHTML_SubCommand(string $string): string {
		return $this->formatHTML_ReservedWord("\t " . $string, 'query_subCommand');
	}
	
	/**
	 * Format column type into HTML
	 */
	protected function formatHTML_ColumnType(string $string): string {
		return $this->formatHTML_ReservedWord($string, 'query_columnType');
	}
	
	/**
	 * Format reserved word into HTML
	 */
	protected function formatHTML_ReservedWord(string $string, string $class = ''): string {
		return $this->formatHTML_InlineBlock($string, 'query_reservedWord ' . $class);
	}
	
	/**
	 * Format identifier into HTML
	 */
	protected function formatHTML_Identifier(string $identifier, AbstractSqlAdapter $sqlAdapter): string {
		return $this->formatHTML_InlineBlock($sqlAdapter->escapeIdentifier($identifier), 'query_identifier');
	}
	
	/**
	 * Format inline block
	 */
	protected function formatHTML_InlineBlock(string $string, string $class): string {
		return sprintf('<div class="d-inline-block %s">%s</div>', $class, $string);
	}
	
}
