<?php
/**
 * Loader File for the Entity Descriptor sources
 */

use Orpheus\EntityDescriptor\User\AbstractUser;
use Orpheus\InputController\HttpController\HttpRoute;
use Orpheus\Time\DateTime;


if( !defined('ORPHEUSPATH') ) {
	// Do not load in a non-orpheus environment
	return;
}

// TODO: Improve HttpRoute::registerAccessRestriction
// Require orpheus-inputcontroller for this feature
// Maybe we could let the core manager the access restrictions
HttpRoute::registerAccessRestriction('role', function ($route, $options) {
	if( !is_string($options) ) {
		throw new Exception('Invalid route access restriction option in routes config, allow string only');
	}
	
	return AbstractUser::loggedCanAccessToRoute($route, $options);
});

function now() {
	return new DateTime();
}
