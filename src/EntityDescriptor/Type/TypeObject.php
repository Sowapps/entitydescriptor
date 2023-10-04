<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor\Type;

use Exception;
use Orpheus\EntityDescriptor\Entity\FieldDescriptor;
use Orpheus\EntityDescriptor\Entity\AbstractTypeDescriptor;
use Serializable;

/**
 * Entity Type Object class
 */
class TypeObject extends TypeString {
	
	/**
	 * @param string[] $rawArgs Arguments
	 */
	public function parseArgs(array $rawArgs): object {
		$args = (object) ['min' => 1, 'max' => 65535, 'class' => null];
		if( isset($rawArgs[0]) ) {
			$args->class = $rawArgs[0];
			if( $args->class === 'stdClass' ) {
				$args->class = null;
			}
		}
		
		return $args;
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @see AbstractTypeDescriptor::parseSqlValue()
	 */
	public function parseSqlValue(FieldDescriptor $field, mixed $value): mixed {
		if( is_object($value) ) {
			return $value;
		}
		/* @var string $value */
		$class = $field->arg('class');
		if( $class ) {
			if( array_key_exists('Serializable', class_implements($class)) ) {
				$obj = new $class();
				$obj->unserialize($value);
				
				return $obj;
			} else {
				return unserialize($value);
			}
			
		} else {
			return json_decode($value, false);
		}
	}
	
	/**
	 * @param FieldDescriptor $field The field to parse
	 * @param string $value The field value to parse
	 * @throws Exception
	 * @see AbstractTypeDescriptor::parseUserValue()
	 */
	public function parseUserValue(FieldDescriptor $field, mixed $value): mixed {
		if( is_string($value) ) {
			return $value;
		}
		/* @var mixed $value */
		$class = $field->arg('class');
		if( $class ) {
			if( !($value instanceof $class) ) {
				throw new Exception('Field ' . $field . '\'s value should be an instance of ' . $class . ', got ' . get_class($value));
			}
			if( $value instanceof Serializable ) {
				return $value->serialize();
			} else {
				return serialize($value);
			}
			
		} else {
			return json_encode($value);
		}
	}
	
}
