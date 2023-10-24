<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\EntityDescriptor;

use Orpheus\Core\AbstractOrpheusLibrary;
use Orpheus\Core\Route;
use Orpheus\EntityDescriptor\Entity\EntityDescriptor;
use Orpheus\EntityDescriptor\Type\TypeBoolean;
use Orpheus\EntityDescriptor\Type\TypeCity;
use Orpheus\EntityDescriptor\Type\TypeDate;
use Orpheus\EntityDescriptor\Type\TypeDatetime;
use Orpheus\EntityDescriptor\Type\TypeDouble;
use Orpheus\EntityDescriptor\Type\TypeEmail;
use Orpheus\EntityDescriptor\Type\TypeEnum;
use Orpheus\EntityDescriptor\Type\TypeFloat;
use Orpheus\EntityDescriptor\Type\TypeInteger;
use Orpheus\EntityDescriptor\Type\TypeIpAddress;
use Orpheus\EntityDescriptor\Type\TypeNatural;
use Orpheus\EntityDescriptor\Type\TypeNumber;
use Orpheus\EntityDescriptor\Type\TypeObject;
use Orpheus\EntityDescriptor\Type\TypePassword;
use Orpheus\EntityDescriptor\Type\TypePhone;
use Orpheus\EntityDescriptor\Type\TypePostalCode;
use Orpheus\EntityDescriptor\Type\TypeRef;
use Orpheus\EntityDescriptor\Type\TypeSlug;
use Orpheus\EntityDescriptor\Type\TypeState;
use Orpheus\EntityDescriptor\Type\TypeString;
use Orpheus\EntityDescriptor\Type\TypeTime;
use Orpheus\EntityDescriptor\Type\TypeUrl;
use Orpheus\EntityDescriptor\User\AbstractUser;
use Orpheus\InputController\HttpController\HttpRoute;

class OrpheusEntityDescriptorLibrary extends AbstractOrpheusLibrary {
	
	public function configure(): void {
		defifn('ENTITY_DESCRIPTOR_CONFIG_FOLDER', '/entities');
		
		// Primary Types
		
		EntityDescriptor::registerType('number', TypeNumber::class);
		EntityDescriptor::registerType('string', TypeString::class);
		EntityDescriptor::registerType('date', TypeDate::class);
		EntityDescriptor::registerType('datetime', TypeDatetime::class);
		EntityDescriptor::registerType('time', TypeTime::class);
		
		// Derived types
		
		EntityDescriptor::registerType('integer', TypeInteger::class);
		EntityDescriptor::registerType('boolean', TypeBoolean::class);
		EntityDescriptor::registerType('float', TypeFloat::class);
		EntityDescriptor::registerType('double', TypeDouble::class);
		EntityDescriptor::registerType('natural', TypeNatural::class);
		EntityDescriptor::registerType('ref', TypeRef::class);
		EntityDescriptor::registerType('email', TypeEmail::class);
		EntityDescriptor::registerType('password', TypePassword::class);
		EntityDescriptor::registerType('phone', TypePhone::class);
		EntityDescriptor::registerType('url', TypeUrl::class);
		EntityDescriptor::registerType('ip', TypeIpAddress::class);
		EntityDescriptor::registerType('enum', TypeEnum::class);
		EntityDescriptor::registerType('state', TypeState::class);
		EntityDescriptor::registerType('object', TypeObject::class);
		EntityDescriptor::registerType('city', TypeCity::class);
		EntityDescriptor::registerType('postalcode', TypePostalCode::class);
		EntityDescriptor::registerType('slug', TypeSlug::class);
	}
	
	public function start(): void {
		// TODO: Improve HttpRoute::registerAccessRestriction
		// Require orpheus-inputcontroller for this feature
		// Maybe we could let the core manager the access restrictions
		HttpRoute::registerAccessRestriction('role', function (Route $route, string $options) {
			//	if( !is_string($options) ) {
			//		throw new Exception('Invalid route access restriction option in routes config, allow string only');
			//	}
			
			return AbstractUser::loggedCanAccessToRoute($route->getName(), $options);
		});
	}
	
}
