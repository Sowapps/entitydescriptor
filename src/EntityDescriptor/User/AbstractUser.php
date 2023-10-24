<?php /** @noinspection ALL */

namespace Orpheus\EntityDescriptor\User;

use Orpheus\Config\Config;
use Orpheus\EntityDescriptor\Entity\PermanentEntity;
use Orpheus\Exception\UserException;
use Orpheus\Publisher\Exception\UnknownKeyException;
use Orpheus\Service\SecurityService;

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
	
	public static function getUserClass(): ?string {
		return self::$userClass;
	}
	
	public static function setUserClass(?string $userClass = null) {
		self::$userClass = $userClass ?? static::class;
	}
	
	/**
	 * Get active user
	 *
	 * @return AbstractUser|null The user of the current client logged in
	 */
	public static function getActiveUser(): ?AbstractUser {
		return SecurityService::get()->getActiveUser();
	}
	
	/**
	 * Get ID if user is logged
	 *
	 * @return string The id of the current client logged in
	 */
	public static function getLoggedUserId(): ?int {
		return static::getActiveUser()?->id();
	}
	
	public static function requireAuthenticatedUserId(): int {
		return self::getLoggedUserId() || static::throwNotFound();
	}
	
	/**
	 * Callback when user is authenticated
	 */
	public function onAuthenticated() {
	
	}
	
	public static function getUserByLogin(?string $login, ?string $password) {
		if( !$login ) {
			static::throwException('invalidLoginId');
		}
		if( !$password ) {
			static::throwException('invalidPassword');
		}
		$password = hashString($password);
		/** @var AbstractUser $user */
		$user = static::get()
			->where(static::formatValue($login) . 'IN (' . implode(',', static::listLoginFields()) . ')')
			->asObject()->run();
		if( !$user ) {
			static::throwException("invalidLoginId");
		}
		if( isset($user->published) && !$user->published ) {
			static::throwException('forbiddenLogin');
		}
		if( $user->password !== $password ) {
			static::throwException('wrongPassword');
		}
		
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
	
	public function isAdmin(): bool {
		return $this->checkPerm(1);
	}
	
	/**
	 * Checks if this user has admin access level.
	 * This is often used to determine if the current user can access to the admin panel.
	 *
	 * @return boolean True if this user is logged and is admin
	 */
	public static function isUserAdmin(): bool {
		$user = static::getActiveUser();
		
		return $user && $user->isAdmin();
	}
	
	/**
	 * Get user access
	 * If anonymous, the user access is -1 (below zero)
	 *
	 * @return int
	 */
	public static function getUserAccess(): int {
		$user = static::getActiveUser();
		
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
		$user = static::getActiveUser();
		if( !ctype_digit($accessLevel) ) {
			$accessLevel = static::getRoleAccessLevel($accessLevel);
		}
		$accessLevel = (int) $accessLevel;
		
		return (!$user && $accessLevel < 0) || // Restricted to visitors
			($user && $accessLevel >= 0 && $user->checkPerm($accessLevel));// Restricted to members
	}
	
	/**
	 * Check if this user has developer access
	 *
	 * @return boolean True if this user has developer access
	 */
	public static function loggedHasDeveloperAccess(): bool {
		$user = static::getActiveUser();
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
		$user = static::getActiveUser();
		
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
