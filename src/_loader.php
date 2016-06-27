<?php
use Orpheus\InputController\HTTPController\HTTPRoute;
use Orpheus\EntityDescriptor\User\User;

/* Loader File for the Entity Descriptor sources
 */

// Form Things

/** 
 * Get the field descriptor from a field path
 * @param string $fieldPath
 * @param string $class
 * @return FieldDescriptor
 */
function getField($fieldPath, $class=null) {
	if( $class === NULL ) {
		$fieldPathArr	= explode('/', $fieldPath);
		$class			= $fieldPathArr[0];
	}
	if( !class_exists($class, 1) || !in_array('Orpheus\EntityDescriptor\PermanentEntity', class_parents($class)) ) {
		return null;
	}
	return $class::getField($fieldPathArr[count($fieldPathArr)-1]);
}

// TODO: Improve HTTPRoute::registerAccessRestriction
// Require orpheus-inputcontroller for this feature
// Maybe we could let the core manager the access restrictions
HTTPRoute::registerAccessRestriction('role', function($route, $options) {
	if( !is_string($options) ) {
		throw new Exception('Invalid route access restriction option in routes config, allow string only');
	}
	return User::loggedCanAccessToRoute($route, $options);
});
