<?php
/**
 * User
 */

namespace Orpheus\EntityDescriptor\User;

/**
 * A basic user class
 * 
 * The user class represents a basic user, you should never use this class, it was make for documentation.
 */
class User extends AbstractUser {
	
	// Final attributes
	
	/**
	 * The fields of this object
	 * 
	 * @var array
	 */
	protected static $fields			= array();
	
	/**
	 * The validator
	 * The default one is an array system.
	 * 
	 * @var array
	 */
	protected static $validator			= array();
	
	/**
	 * The domain of this class
	 * Used as default for translations.
	 * 
	 * @var unknown
	 */
	protected static $domain			= null;
	
}
User::init();
