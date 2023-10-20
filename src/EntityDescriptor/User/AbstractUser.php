<?php /** @noinspection ALL */

namespace Orpheus\EntityDescriptor\User;

use Orpheus\Config\Config;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
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
	
	protected static ?string $userClass = null;
	
	protected static ?self $loggedUser = null;
	
	abstract function getAuthenticationToken(): string;
	
	abstract static function getByAuthenticationToken(string $token): ?static;
	
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
	public function checkPerm(int|string $right): bool {
		if( !ctype_digit("$right") && $right !== -1 ) {
			if( $GLOBALS['RIGHTS']->$right === null ) {
				throw new UnknownKeyException(sprintf('Unknown right "%s"', $right), $right);
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
	public function hasRoleAccessLevel(string $role): bool {
		return $this->checkPerm(self::getRoleAccessLevel($role));
	}
	
	/**
	 * Get access level of a role
	 *
	 * @param string $role
	 * @return int
	 */
	public static function getRoleAccessLevel(string $role): int {
		$roles = static::getAppRoles();
		
		return $roles[$role];
	}
	
	/**
	 * Get application roles
	 *
	 * @return array
	 */
	public static function getAppRoles(): array {
		return static::getUserRoles();
	}
	
	/**
	 * Get all user roles
	 *
	 * @return array
	 */
	public static function getUserRoles(): array {
		$roles = Config::get('user_roles') ?? [];
		// Convert permission access level to integer
		foreach( $roles as &$permission ) {
			$permission = intval($permission);
		}
		
		return $roles;
	}
	
	/**
	 * Use this user as face mask for logged user
	 *
	 * @throws UserException
	 */
	public function impersonate(): void {
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
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public static function getRealLoggedUser(): ?static {
		if( !empty($_SESSION['REAL_USER_ID']) ) {
			/* @var static $userClass */
			$userClass = static::getUserClass();
			return $userClass ? $userClass::load($_SESSION['REAL_USER_ID']) : null;
		}
		return self::getLoggedUser();
	}
	
	public static function getUserClass(): ?string {
		return self::$userClass;
	}
	
	public static function setUserClass(?string $userClass = null) {
		self::$userClass = $userClass ?? static::class;
	}
	
	/**
	 * Get logged user
	 *
	 * @return AbstractUser|null The user of the current client logged in
	 */
	public static function getLoggedUser(): ?AbstractUser {
		global $USER;// BC - Auto load
		/** @var static $user */
		if( !static::isLogged() ) {
			return null;
		}
		$userId = static::getLoggedUserId();
		if( !static::$loggedUser || static::$loggedUser->id() != $userId ) {
			// Non-connected or session has a different user
			/** @var class-string<AbstractUser> $userClass */
			$userClass = static::getUserClass();
			static::$loggedUser = $userClass ? $userClass::load($userId) : null;
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
	 * @TODO Update using security service
	 */
	public static function getLoggedUserId(): ?int {
		return static::isLogged() ? $_SESSION['USER_ID'] : null;
	}
	
	public static function requireAuthenticatedUserId(): int {
		return self::getLoggedUserId() || static::throwNotFound();
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
	public function login(bool|string|null $force = false): void {
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
	public static function listLoginFields(): array {
		return ['email'];
	}
	
	/**
	 * Log out current user
	 */
	public static function userLogout(): void {
		$user = static::getLoggedUser();
		if( $user ) {
			$user->logout();
		}
	}
	
	/**
	 * Log out this user from the current session.
	 *
	 * @param string|null $reason
	 * @return boolean
	 */
	public function logout(?string $reason = null): bool {
		// Log out any user
		global $USER;
		$USER = null;
		unset($_SESSION['USER_ID']);
		$_SESSION['LOGOUT_REASON'] = $reason;
		return true;
	}
	
	/**
	 * Login from HTTP authentication, create user if not existing
	 * Create user from HTTP authentication
	 *
	 * @return boolean
	 * @warning Require other data than name and password are optional
	 */
	public static function httpAuthenticate(): bool {
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
	 * Log in a user from HTTP authentication according to server variables PHP_AUTH_USER and PHP_AUTH_PW
	 */
	public static function httpLogin(): void {
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
	 * Hash $str using a salt.
	 * Define constant USER_SALT to use your own salt.
	 *
	 * @param string $value The clear password.
	 * @return string The hashed string.
	 * @see hashString()
	 */
	public static function hashPassword(string $value): string {
		return hashString($value);
	}
	
	/**
	 * Create user from HTTP authentication
	 *
	 * @return AbstractUser object
	 * @warning Require other data than name and password ard optional
	 *
	 * Create user from HTTP authentication
	 */
	public static function httpCreate(): AbstractUser {
		return static::createAndGet(['name' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']]);
	}
	
	public function isAdmin(): bool {
		return $this && $this->checkPerm(1);
	}
	
	/**
	 * Checks if this user has admin access level.
	 * This is often used to determine if the current user can access to the admin panel.
	 *
	 * @return boolean True if this user is logged and is admin
	 */
	public static function isUserAdmin(): bool {
		$user = static::getLoggedUser();
		
		return $user && $user->isAdmin();
	}
	
	/**
	 * Get user access
	 * If anonymous, the user access is -1 (below zero)
	 *
	 * @return int
	 */
	public static function getUserAccess(): int {
		$user = static::getLoggedUser();
		
		return $user ? $user->accesslevel : -1;
	}
	
	/**
	 * Check if this user can access to a module
	 *
	 * @param string $route The route to look for
	 * @param string|int $accessLevel The access level
	 * @return boolean True if this user can access to $module
	 */
	public static function loggedCanAccessToRoute(string $route, string|int $accessLevel): bool {
		$user = static::getLoggedUser();
		if( !ctype_digit($accessLevel) ) {
			$accessLevel = static::getRoleAccessLevel($accessLevel);
		}
		$accessLevel = (int) $accessLevel;
		
		return (empty($user) && $accessLevel < 0) ||
			(!empty($user) && $accessLevel >= 0 &&
				$user instanceof AbstractUser && $user->checkPerm($accessLevel));
	}
	
	/**
	 * Check if this user has developer access
	 *
	 * @return boolean True if this user has developer access
	 */
	public static function loggedHasDeveloperAccess(): bool {
		$user = static::getLoggedUser();
		$requiredAccessLevel = static::getRoleAccessLevel('developer');
		
		return $user && $user->checkPerm($requiredAccessLevel);
	}
	
	/**
	 * Check if this user can do a restricted action
	 *
	 * @param string $action The action to look for
	 * @param AbstractUser|null $object The object to edit if editing one or null. Default value is null
	 * @return boolean True if this user can do this $action
	 * @throws UnknownKeyException
	 */
	public static function loggedCanDo(string $action, AbstractUser $object = null): bool {
		$user = static::getLoggedUser();
		
		return $user && $user->canDo($action, $object);
	}
	
	/**
	 * Check if this user can affect data on the given user
	 *
	 * @param string $action The action to look for
	 * @param object $object The object we want to edit
	 * @return boolean True if this user has enough access level to alter $object (or he is altering himself)
	 * @throws UnknownKeyException
	 * @see loggedCanDo()
	 * @see canAlter()
	 *
	 * Check if this user can affect $object.
	 */
	public function canDo(string $action, mixed $object = null): bool {
		return $this->equals($object) || ($this->checkPerm($action) && (!($object instanceof AbstractUser) || $this->canAlter($object)));
	}
	
	/**
	 * Check if this user can alter data on the given user
	 *
	 * @param AbstractUser $user The user we want to edit
	 * @return boolean True if this user has enough acess level to edit $user or he is altering himself
	 * @see loggedCanDo()
	 */
	public function canAlter(AbstractUser $user): bool {
		return !$user->accesslevel || $this->accesslevel > $user->accesslevel;
	}
	
	/**
	 * Check for object
	 *
	 * This function is called by create() after checking user input data and before running for them.
	 * In the base class, this method does nothing.
	 *
	 * @param array $data The new data to process.
	 * @param \Orpheus\EntityDescriptor\Entity\PermanentEntity|string $object The referenced object (update only). Default value is null.
	 * @throws UserException
	 */
	public static function verifyConflicts(array $data, PermanentEntity|string $object = null): void {
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
		if( $object ) {
			$query->where('id', '!=', id($object));
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
	
}
