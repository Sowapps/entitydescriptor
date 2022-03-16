<?php
/**
 * AbstractUser
 */

namespace Orpheus\EntityDescriptor\User;

use Orpheus\Config\Config;
use Orpheus\EntityDescriptor\EntityDescriptor;
use Orpheus\EntityDescriptor\PermanentEntity;
use Orpheus\Exception\NotFoundException;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\Exception\UnknownKeyException;

/**
 * The abstract user class
 *
 * The user class represents a user known by the current website as a permanent entity.
 * This class is commonly inherited by a user class for registered users.
 * But a user can be a Facebook user or a Site user for example.
 *
 * Require core plugin
 */
abstract class AbstractUser extends PermanentEntity {
	
	const NOT_LOGGED = 0;
	const IS_LOGGED = 1;
	const LOGGED_FORCED = 3;
	
	protected static string $userClass;
	
	/**
	 * The table
	 */
	protected static string $table = 'user';
	
	/**
	 * The fields of this object
	 *
	 * @var array
	 */
	protected static array $fields = [];
	
	/**
	 * The validator
	 * The default one is an array system.
	 *
	 * @var EntityDescriptor
	 */
	protected static $validator = [];
	
	/**
	 * The domain of this class
	 * Used as default for translations.
	 *
	 * @var string
	 */
	protected static string $domain;
	
	protected static ?self $loggedUser = null;
	
	/**
	 * Magic string conversion
	 *
	 * @return string The string value of this object
	 *
	 * The string value is the contents of the publication.
	 */
	public function __toString() {
		return $this->fullname;
	}
	
	/**
	 * Check permissions
	 *
	 * @param int|string $right The right to compare, can be the right string to look for or an integer.
	 * @return boolean True if this user has enough access level.
	 *
	 * Compare the access level of this user to the incoming right.
	 * $right could be an int (access level) or a string (right)
	 */
	public function checkPerm($right) {
		if( !ctype_digit("$right") && $right != -1 ) {
			if( $GLOBALS['RIGHTS']->$right === null ) {
				throw new UnknownKeyException('unknownRight', $right);
			}
			$right = $GLOBALS['RIGHTS']->$right;
		}
		return $this->accesslevel >= $right;
	}
	
	/**
	 * Check user can use the role's permission
	 *
	 * @param string $role
	 * @return bool
	 * @throws UnknownKeyException
	 */
	public function hasRoleAccessLevel($role) {
		return $this->checkPerm(self::getRoleAccesslevel($role));
	}
	
	/**
	 * Get access level of a role
	 *
	 * @param string $role
	 * @return int
	 */
	public static function getRoleAccesslevel($role) {
		$roles = static::getAppRoles();
		return $roles[$role];
	}
	
	/**
	 * Get application roles
	 *
	 * @return array
	 */
	public static function getAppRoles() {
		return static::getUserRoles();
	}
	
	/**
	 * Get all user roles
	 *
	 * @return array
	 */
	public static function getUserRoles() {
		return Config::get('user_roles');
	}
	
	/**
	 * Use this user as face mask for logged user
	 *
	 * @throws UserException
	 */
	public function impersonate() {
		$loggedUser = self::getRealLoggedUser();
		if( !$loggedUser ) {
			// Must be logged in to impersonate
			static::throwException('impersonateRequireLoggedUser');
		}
		if( $loggedUser->equals($this) ) {
			// Can not impersonate my self
			static::throwException('impersonateRequireAnotherUser');
		}
		if( $loggedUser->accesslevel < $this->accesslevel ) {
			// Can not impersonate an user with more permissions
			static::throwException('forbiddenImpersonate');
		}
		$_SESSION['REAL_USER_ID'] = $loggedUser->id();
		$_SESSION['USER_ID'] = $this->id();
		// BC
		global $USER;
		$USER = $this;
		static::$loggedUser = $this;
	}
	
	/**
	 * Get real logged user if impersonating else then current logged user
	 *
	 * @return static
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public static function getRealLoggedUser() {
		if( !empty($_SESSION['REAL_USER_ID']) ) {
			/* @var static $userClass */
			$userClass = static::getUserClass();
			return $userClass::load($_SESSION['REAL_USER_ID']);
		}
		return self::getLoggedUser();
	}
	
	/**
	 * @return string
	 */
	public static function getUserClass(): string {
		return self::$userClass;
	}
	
	/**
	 * @param string|null $userClass
	 */
	public static function setUserClass($userClass = null) {
		self::$userClass = $userClass ?: static::getClass();
	}
	
	/**
	 * Get logged user
	 *
	 * @return static The user of the current client logged in
	 */
	public static function getLoggedUser(): ?AbstractUser {
		global $USER;// BC - Auto load
		/** @var static $user */
		if( !static::isLogged() ) {
			return null;
		}
		$userId = static::getLoggedUserID();
		if( !static::$loggedUser || static::$loggedUser->id() != $userId ) {
			// Non-connected or session has a different user
			/** @var static $userClass */
			$userClass = static::getUserClass();
			static::$loggedUser = $userClass::load($userId);
			if( !static::$loggedUser ) {
				// User does no more exist
				unset($_SESSION['USER_ID']);
				return null;
			}
			$USER = static::$loggedUser;
			static::$loggedUser->onConnected();
		}
		return static::$loggedUser;
	}
	
	/**
	 * Check if the client is logged in
	 *
	 * @return bool True if the current client is logged in
	 */
	public static function isLogged(): bool {
		return !empty($_SESSION['USER_ID']);
	}
	
	/**
	 * Get ID if user is logged
	 *
	 * @return string The id of the current client logged in
	 *
	 * Get the ID of the current user or 0.
	 */
	public static function getLoggedUserID(): int {
		return static::isLogged() ? (int) $_SESSION['USER_ID'] : 0;
	}
	
	/**
	 * Callback when user is connected
	 */
	public function onConnected() {
	
	}
	
	/**
	 * Log in this user to the current session.
	 *
	 * @param bool|string|null $force
	 */
	public function login($force = false) {
		if( !$force && static::isLogged() ) {
			if( $force === null ) {
				// null is silent
				return;
			}
			static::throwException('alreadyLoggedin');
		}
		/**
		 * @var AbstractUser $USER
		 * @deprecated
		 */
		global $USER;
		if( $force === 'auto' && isset($_SESSION['USER_ID']) && $_SESSION['USER_ID'] === $this->id() ) {
			// auto does not log the same user again
			if( isset($this->activity_date) ) {
				$this->activity_date = now();
			}
			return;
		}
		$USER = $this;
		$_SESSION['USER_ID'] = $this->id();
		if( !$force ) {
			static::logEvent('login');
		}
		static::logEvent('activity');
	}
	
	/**
	 * Terminate the current impersonate
	 *
	 * @throws UserException
	 * @warning We recommend to redirect user just after this action to avoid partial contents
	 */
	public static function terminateImpersonate() {
		if( !static::isImpersonating() ) {
			// Must be logged in to impersonate
			static::throwException('terminateImpersonateRequireImpersonate');
		}
		$loggedUser = self::getRealLoggedUser();
		$_SESSION['USER_ID'] = $loggedUser->id();
		unset($_SESSION['REAL_USER_ID']);
		// BC
		global $USER;
		$USER = $loggedUser;
		static::$loggedUser = $loggedUser;
	}
	
	/**
	 * Check if current logged user is impersonating
	 *
	 * @return bool
	 */
	public static function isImpersonating() {
		return !empty($_SESSION['REAL_USER_ID']);
	}
	
	/**
	 * Logs in an user using data
	 *
	 * @param array $data
	 * @param string $loginField
	 * @return static
	 * @throws UserException
	 */
	public static function userLogin($data, $loginField = 'email') {
		if( empty($data[$loginField]) ) {
			static::throwException('invalidLoginID');
		}
		$name = $data[$loginField];
		if( empty($data['password']) ) {
			static::throwException('invalidPassword');
		}
		$password = hashString($data['password']);
		
		$user = static::get()
			->where(static::formatValue($name) . 'IN (' . implode(',', static::listLoginFields()) . ')')
			->asObject()->run();
		if( !$user ) {
			static::throwException("invalidLoginID");
		}
		if( isset($user->published) && !$user->published ) {
			static::throwException('forbiddenLogin');
		}
		if( $user->password !== $password ) {
			static::throwException('wrongPassword');
		}
		$user->logout();
		$user->login();
		return $user;
	}
	
	/**
	 * List all available login fields
	 *
	 * @return string[]
	 */
	public static function listLoginFields() {
		return ['email'];
	}
	
	/**
	 * Log out current user
	 */
	public static function userLogout() {
		$user = static::getLoggedUser();
		if( $user ) {
			$user->logout();
		}
	}
	
	/**
	 * Log out this user from the current session.
	 *
	 * @param string $reason
	 * @return boolean
	 */
	public function logout($reason = null) {
		// Log out any user
		global $USER;
		$USER = null;
		unset($_SESSION['USER_ID']);
		$_SESSION['LOGOUT_REASON'] = $reason;
		return true;
	}
	
	/**
	 * Login from HTTP authentication, create user if not existing
	 *
	 * @return boolean
	 * @warning Require other data than name and password are optional
	 *
	 * Create user from HTTP authentication
	 */
	public static function httpAuthenticate() {
		try {
			static::httpLogin();
			return true;
		} catch( NotFoundException $e ) {
			if( Config::get('httpauth_autocreate') ) {
				$user = static::httpCreate();
				$user->login();
				return true;
			}
		} catch( UserException $e ) {
		}
		return false;
	}
	
	/**
	 * Log in an user from HTTP authentication according to server variables PHP_AUTH_USER and PHP_AUTH_PW
	 */
	public static function httpLogin() {
		$user = static::get()
			->where('name', $_SERVER['PHP_AUTH_USER'])
			->asObject()->run();
		if( empty($user) ) {
			static::throwNotFound();
		}
		if( $user->password != static::hashPassword($_SERVER['PHP_AUTH_PW']) ) {
			static::throwException("wrongPassword");
		}
		$user->logout();
		$user->login();
	}
	
	/**
	 * Hash a password
	 *
	 * @param string $str The clear password.
	 * @return string The hashed string.
	 * @see hashString()
	 *
	 * Hash $str using a salt.
	 * Define constant USER_SALT to use your own salt.
	 */
	public static function hashPassword($str) {
		return hashString($str);
	}
	
	/**
	 * Create user from HTTP authentication
	 *
	 * @return User object
	 * @warning Require other data than name and password ard optional
	 *
	 * Create user from HTTP authentication
	 */
	public static function httpCreate() {
		return static::createAndGet(['name' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']]);
	}
	
	/**
	 * @return bool
	 * @deprecated will evolve to instance's method
	 */
	public static function isAdmin() {
		return static::isUserAdmin();
	}
	
	/**
	 * Checks if this user has admin access level.
	 * This is often used to determine if the current user can access to the admin panel.
	 *
	 * @return boolean True if this user is logged and is admin
	 */
	public static function isUserAdmin() {
		$user = static::getLoggedUser();
		return $user && $user->checkPerm(1);
	}
	
	/**
	 * Get user access
	 * If anonymous, the user access is -1 (below zero)
	 *
	 * @return int
	 */
	public static function getUserAccess() {
		$user = static::getLoggedUser();
		return $user ? $user->accesslevel : -1;
	}
	
	/**
	 * Check if this user can access to a module
	 *
	 * @param string $route The route to look for
	 * @param int $accesslevel The access level
	 * @return boolean True if this user can access to $module
	 */
	public static function loggedCanAccessToRoute($route, $accesslevel) {
		$user = static::getLoggedUser();
		if( !ctype_digit($accesslevel) ) {
			$accesslevel = static::getRoleAccesslevel($accesslevel);
		}
		$accesslevel = (int) $accesslevel;
		return (empty($user) && $accesslevel < 0) ||
			(!empty($user) && $accesslevel >= 0 &&
				$user instanceof AbstractUser && $user->checkPerm($accesslevel));
	}
	
	/**
	 * Check if this user has developer access
	 *
	 * @return boolean True if this user has developer access
	 */
	public static function loggedHasDeveloperAccess() {
		$user = static::getLoggedUser();
		$requiredAccessLevel = (int) static::getRoleAccesslevel('developer');
		return $user && $user->checkPerm($requiredAccessLevel);
	}
	
	/**
	 * Check if this user can do a restricted action
	 *
	 * @param string $action The action to look for
	 * @param AbstractUser $object The object to edit if editing one or null. Default value is null
	 * @return boolean True if this user can do this $action
	 */
	public static function loggedCanDo($action, AbstractUser $object = null) {
		$user = static::getLoggedUser();
		return $user && $user->canDo($action, $object);
	}
	
	/**
	 * Check if this user can affect data on the given user
	 *
	 * @param string $action The action to look for
	 * @param object $object The object we want to edit
	 * @return boolean True if this user has enough access level to alter $object (or he is altering himself)
	 * @see loggedCanDo()
	 * @see canAlter()
	 *
	 * Check if this user can affect $object.
	 */
	public function canDo($action, $object = null) {
		return $this->equals($object) || ($this->checkPerm($action) && (!($object instanceof AbstractUser) || $this->canAlter($object)));
	}
	
	/**
	 * Check if this user can alter data on the given user
	 *
	 * @param AbstractUser $user The user we want to edit
	 * @return boolean True if this user has enough acess level to edit $user or he is altering himself
	 * @see loggedCanDo()
	 *
	 * Checks if this user can alter on $user.
	 */
	public function canAlter(AbstractUser $user) {
		return !$user->accesslevel || $this->accesslevel > $user->accesslevel;
	}
	
	/**
	 * Check for object
	 *
	 * This function is called by create() after checking user input data and before running for them.
	 * In the base class, this method does nothing.
	 *
	 * @param array $data The new data to process.
	 * @param mixed $ref The referenced object (update only). Default value is null.
	 * @throws UserException
	 */
	public static function checkForObject($data, $ref = null) {
		if( empty($data['email']) ) {
			return;//Nothing to check. Email is mandatory.
		}
		$where = 'email LIKE ' . static::formatValue($data['email']);
		$what = 'email';
		if( !empty($data['name']) ) {
			$what .= ', name';
			$where .= ' OR name LIKE ' . static::formatValue($data['name']);
		}
		$query = static::get()
			->fields($what)
			->where($where)
			->asArray();
		if( $ref ) {
			$query->where('id', '!=', id($ref));
		}
		$user = $query->run();
		if( $user ) {
			if( $user['email'] === $data['email'] ) {
				static::throwException('emailAlreadyUsed');
			} else {
				static::throwException('entryExisting');
			}
		}
	}
	
	/**
	 * Generate password
	 *
	 * @return string
	 * @deprecated Use PasswordGenerator from Orpheus WebTools
	 */
	public static function generatePassword() {
		return generatePassword(mt_rand(8, 12));
	}
	
}
