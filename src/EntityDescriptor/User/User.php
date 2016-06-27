<?php
namespace Orpheus\EntityDescriptor\User;

/**
 * A basic user class
 * 
 * The user class represents a basic user, you should never use this class, it was make for documentation.
 */
class User extends AbstractUser {
	
	// Final attributes
	protected static $fields	= null;
	protected static $validator	= null;
	protected static $domain	= null;
	
}
User::init();
