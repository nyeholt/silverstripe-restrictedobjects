<?php

/**
 * A service interface to functionality related to getting and setting
 * permission information for nodes
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PermissionService {
	
	const SOURCES_MAP = 'sources_map';
	
	public function __construct() {
	}
	
	/**
	 * 
	 * Allow this service to be accessed from the web
	 *
	 * @return array
	 */
	public function webEnabledMethods() {
		return array(
			'removeAuthority'		=> 'POST',
			'grantTo'				=> 'POST',
			'checkPerm'				=> 'GET',
			'getPermissionsFor'		=> 'GET',
			'getPermissionDetails'	=> 'GET'
		);
	}

	/**
	 * @var Zend_Cache_Core 
	 */
	protected $cache;
	
	protected $parents = array();
	
	protected $groups = array();

	/**
	 *
	 * @return Zend_Cache_Core
	 */
	public function getCache() {
		if (!$this->cache) {
			$this->cache = SS_Cache::factory('restricted_perms', 'Output', array('automatic_serialization' => true));
		}
		return $this->cache;
	}

	
	public function getAllRoles() {
		return DataObject::get('AccessRole');
	}

	public function getPermissionDetails() {
		return array(
			'roles'				=> $this->getAllRoles(),
			'permissions'		=> $this->allPermissions(),
//			'users'				=> DataObject::get('Member'),
//			'groups'			=> DataObject::get('Group')
		);
	}
	

	protected $allPermissions;

	public function allPermissions() {
		if (!$this->allPermissions) {
			$options = array();
			$definers = ClassInfo::implementorsOf('PermissionDefiner');
			$perms = array();
			foreach ($definers as $definer) {
				$cls = new $definer();
				$perms = array_merge($perms, $cls->definePermissions());
			}

			$this->allPermissions = $perms;
		}

		return $this->allPermissions;
	}
	
	public function flushCache() {
		$this->parents = array();
		$this->groups = array();
	}

	/**
	 * Alternative grant method using group name or email address
	 *
	 * @param DataObject $node
	 * @param type $perm
	 * @param DataObject $to
	 * @param type $grant 
	 */
	public function grantTo(DataObject $node, $perm, $email, $group, $grant = 'GRANT') {
		$userObj = $groupObj = null;
		if (strlen($email)) {
			$userObj = DataObject::get_one('Member', '"Email" = \''. Convert::raw2sql($email).'\'');
		} else if (strlen($group)) {
			$groupObj = DataObject::get_one('Group', '"Title" = \'' . Convert::raw2sql($group).'\'');
		}

		$to = $userObj ? $userObj : $groupObj;
		
		if (!$to) {
			return array('status' => false, 'message' => 'Unknown authority');
		}
		
		return $this->grant($node, $perm, $to, $grant);
	}
	
	/**
	 * Delete an authority
	 *
	 * @param DataObject $node 
	 *			The node to delete from
	 * @param DataObject $authority
	 *			The AccessAuthority we're removing
	 */
	public function removeAuthority(DataObject $node, DataObject $authority) {
		if (!$this->checkPerm($node, 'DeletePermissions')) {
			throw new PermissionDeniedException("You do not have permission to do that");
		}

		if ($authority) {
			$authority->delete();
		}
		
		return $node;
	}
	
	/**
	 * Grants a specific permission to a given user or group
	 *
	 * @param string $perm
	 * @param Member|Group $to
	 */
	public function grant(DataObject $node, $perm, DataObject $to, $grant = 'GRANT') {
		// make sure we can !!
		if (!$this->checkPerm($node, 'ChangePermissions')) {
			throw new PermissionDeniedException("You do not have permission to do that");
		}

		$role = DataObject::get_one('AccessRole', '"Title" = \'' . Convert::raw2sql($perm) . '\'');

		$composedOf = array($perm);
		if ($role && $role->exists()) {
			$composedOf = $role->Composes->getValues();
		}

		$type = $to instanceof Member ? 'Member' : get_class($to);
		$filter = array(
			'Type'			=> $type,
			'AuthorityID'	=> $to->ID,
			'ItemID'		=> $node->ID,
			'ItemType'		=> $node->class,
			'Grant'			=> $grant,
		);
		
		$list = DataList::create('AccessAuthority')->filter($filter);
		$existing = $list->first();
		if (!$existing || !$existing->exists()) {
			$existing = new AccessAuthority;
			$existing->Type = $type;
			$existing->AuthorityID = $to->ID;
			$existing->ItemID = $node->ID;
			$existing->ItemType = $node->class;
			$existing->Grant = $grant;
			$existing->Role = $role ? $role->Title : '';
		}

		$currentRoles = $existing->Perms->getValues();
		if (!$currentRoles) {
			$new = $composedOf;
		} else {
			$new = array_merge($currentRoles, $composedOf);
		}

		$new = array_unique($new);
		$existing->Perms = $new;
		$existing->write();

		foreach ($new as $perm) {
			$this->clearPermCacheFor($node, $perm);
		}

		return $existing;
	}
	
	/**
	 * Check for the presence of ALL permissions in a given role for the user to an object
	 *
	 * @param DataObject $node
	 *			The object to check perms on
	 * @param string $role
	 *			The role to check against
	 * @param Member $member 
	 *			The member to check -if not set, the current user is used
	 */
	public function checkRole(DataObject $node, $role, $member = null) {
		$role = DataObject::get_one('AccessRole', '"Title" = \'' . Convert::raw2sql($role) . '\'');

		if ($role && $role->exists()) {
			$composedOf = $role->Composes->getValues();
			if ($composedOf && is_array($composedOf)) {
				foreach ($composedOf as $perm) {
					if (!$this->checkPerm($node, $perm, $member)) {
						return false;
					}
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes a set of permissions applied on an object to a particular user/group
	 *
	 * @param DataObject $node
	 * @param type $perm
	 * @param DataObject $to
	 * @param type $grant 
	 */
	public function removePermissions(DataObject $node, $perm, DataObject $userOrGroup, $grant = 'GRANT') {
		if (!$this->checkPerm($node, 'ChangePermissions')) {
			throw new PermissionDeniedException("You do not have permission to do that");
		}

		$composedOf = $perm;
		if (!is_array($perm)) {
			$role = DataObject::get_one('AccessRole', '"Title" = \'' . Convert::raw2sql($perm) . '\'');
			$composedOf = array($perm);
			if ($role && $role->exists()) {
				$composedOf = $role->Composes->getValues();
			}
		}
		
		$type = $userOrGroup instanceof Member ? 'Member' : get_class($userOrGroup);
		$filter = array(
			'Type' => $type,
			'AuthorityID' => $userOrGroup->ID,
			'ItemID' => $node->ID,
			'ItemType' => $node->class,
			'Grant' => $grant,
		);

		$existing = DataList::create('AccessAuthority')->filter($filter)->first();
		
		if (!$existing || !$existing->exists()) {
			return;
		}

		$current = $existing->Perms->getValues();
		if (is_array($current) && count($current)) {
			$new = array_diff($current, $composedOf);
			$existing->Perms = $new;
			$existing->write();
			
			$this->clearPermCacheFor($node);
			
			if (!count($new)) {
				try {
					$this->removeAuthority($node, $existing);
				} catch (Exception $e) {
					// oh well
				}
			}
		}
	}

	/**
	 * Return true or false as to whether a given user can access an object
	 * 
	 * @param DataObject $node
	 *			The object to check perms on
	 * @param string $perm
	 *			The permission to check against
	 * @param Member $member 
	 *			The member to check - if not set, the current user is used
	 * 
	 * @return type 
	 */
	public function checkPerm(DataObject $node, $perm, $member=null) {
		if (!$node) {
			return false;
		}
		
		if (!$member) {
			$member = singleton('SecurityContext')->getMember();
		}

		if (is_int($member)) {
			$member = DataObject::get_by_id('Member', $member);
		}

		if (Permission::check('ADMIN', 'any', $member)) {
			return true;
		}
		
		$permCache = $this->getCache();
		/* @var $permCache Zend_Cache_Core */
		$key = $this->permCacheKey($node, $perm);
		$userGrants = null;
		if ($key) {
			$userGrants = $permCache->load($key);
		} else {
			$o = 0;
		}
		 
		
		if ($userGrants && isset($userGrants[$perm][$member->ID])) {
			return $userGrants[$perm][$member->ID];
		} 

		// okay, we need to build up all the info we have about the node for permissions
		$s = $this->realiseAllSources($node);

		$userGrants = array($perm => array());
		$result = null;

		// if no member, just check public view
		$public = $this->checkPublicPerms($node, $perm);

		if ($public) {
			$result = true;
		}
		if (!$member) {
			$result = false;
		}

		if (is_null($result)) {
			// see whether we're the owner, and if the perm we're checking is in that list
			if ($this->checkOwnerPerms($node, $perm, $member)) {
				$result = true;
			}
		}
		
		$accessAuthority = '';
		$directGrant = null;

		$can = false;

		if (is_null($result)) {
			$filter = array(
				'ItemID'		=> $node->ID,
				'ItemType'		=> $node->class,
			);

			$existing = DataList::create('AccessAuthority')->filter($filter);
			// get all access authorities for this object

			$gids = isset($this->groups[$member->ID]) ? $this->groups[$member->ID] : null;
			if (!$gids) {
				$groups = $member ? $member->Groups() : array();
				$gids = array();
				if ($groups && $groups->Count()) {
					$gids = $groups->map('ID', 'ID');
				}
				$this->groups[$member->ID] = $gids;
			}

			$can = false;
			$directGrant = 'NONE';
			if ($existing && $existing->count()) {
				foreach ($existing as $access) {
					// check if this mentions the perm in question
					$perms = $access->Perms->getValues();
					if($perms){
						if (!in_array($perm, $perms)) {
							continue;
						}	
					}

					$grant = null;
					if ($access->Type == 'Group') {
						if (isset($gids[$access->AuthorityID])) {
							$grant = $access->Grant;
						}
					} else if ($access->Type == 'Member') {
						if ($member->ID == $access->AuthorityID) {
							$grant = $access->Grant;
						}
					} else {
						// another mechanism that will require a lookup of members in a list
						// TODO cache this
						$authority = $access->getAuthority();
						if ($authority instanceof ListOfMembers) {
							$listMembers = $authority->getAllMembers()->map('ID', 'Title');
							if (isset($listMembers[$member->ID])) {
								$grant = $access->Grant;
							}
						}
					}

					if ($grant) {
						// if it's deny, we can just break away immediately, otherwise we need to evaluate all the 
						// others in case there's another DENY in there somewhere
						if ($grant === 'DENY') {
							$directGrant = 'DENY';
							// immediately break
							break;
						} else {
							// mark that it's been granted for now
							$directGrant = 'GRANT';
						}
					}
				}
			}
		}

		// return immediately if we have something
		if ($directGrant === 'GRANT') {
			$result = true;
		}
		if ($directGrant === 'DENY') {
			$result = false;
		}

		// otherwise query our parents
		if (is_null($result) && $node->InheritPerms) {
			$permParents = $this->getEffectiveParents($node);
			if (count($permParents) || $permParents instanceof IteratorAggregate) {
				foreach ($permParents as $permParent) {
					if ($permParent && $this->checkPerm($permParent, $perm, $member)) {
						$result = true;
					}
				}
			}
			$result = false;
		}

		$userGrants[$perm][$member->ID] = $result;
		$permCache->save($userGrants, $key);

		return $result;
	}

	/**
	 * Checks the permissions for a public user
	 * 
	 * @param string $perm 
	 */
	public function checkPublicPerms(DataObject $node, $perm) {
		if ($perm == 'View') {
			if ($node->PublicAccess) {
				return true;
			}

			if($node->InheritPerms) {
				$permParents = $this->getEffectiveParents($node);
				if (count($permParents) || $permParents instanceof IteratorAggregate) {
					foreach ($permParents as $permParent) {
						if ($permParent && $this->checkPublicPerms($permParent, $perm)) {
							return true;
						}
					}
				}
			}
		}
		return false;
	}
	
	public function getEffectiveParents($node) {
		$key = get_class($node) . '-' . $node->ID;
		if (isset($this->parents[$key])) {
			return $this->parents[$key];
		}
		
		// determine what we're looking up
		if (method_exists($node, 'effectiveParents')) {
			$this->parents[$key] = $node->effectiveParents();
			return $this->parents[$key];
		}
		
		if (method_exists($node, 'effectiveParent')) {
			$this->parents[$key] = array($node->effectiveParent());
			return $this->parents[$key];
		}

		// otherwise, put it together ourselves
		$fullResult = null;
		$result = null;
		if (method_exists($node, 'permissionSource')) {
			$result = $node->permissionSource();
		} else {
			$result = $this->parentFor($node);
			if ($result && !$result->ID) {
				$result = null;
			}
		}
		if ($result) {
			$fullResult = array($result);
		}

		if (method_exists($node, 'permissionSources')) {
			$result = $node->permissionSources();
			foreach ($result as $r) {
				$fullResult[] = $r;
			}
		} else {
			$result = new ArrayObject();
			$parent = $this->parentFor($node);
			if ($parent && $parent->ID) {
				$fullResult[] = $parent;
			}
		}

		if ($key && $node->ID) {
			$this->parents[$key] = $fullResult;
		}
		return $fullResult;
	}
	
	/**
	 * @deprecated 
	 * 
	 * @param string $type
	 * @param DataObject $node
	 * @return array
	 */
	public function getEffective($type, $node) {
		return $this->getEffectiveParents($node);
	}
	
	protected function parentFor($node) {
		if (!$node->ParentID) {
			return;
		}

		$key = 'parent-' . get_class($node) . '-' . $node->ParentID;
		if (isset($this->parents[$key])) {
			return $this->parents[$key];
		} 

		$this->parents[$key] = $node->Parent();
		return $this->parents[$key];
	}

	/**
	 * Is the member the owner of this object, and is the permission being checked
	 * in the list of permissions that owners have?
	 *
	 * @param string $perm
	 * @param Member $member
	 * @return boolean
	 */
	protected function checkOwnerPerms(DataObject $node,$perm, $member) {
		$ownerId = $node->OwnerID;
		if (!$node) {
			return;
		}

		if ($node->isChanged('OwnerID')) {
			$changed = $node->getChangedFields();
			$ownerId = isset($changed['OwnerID']['before']) && $changed['OwnerID']['before'] ? $changed['OwnerID']['before'] : $ownerId;
		}
		if (!$member || ($ownerId != $member->ID)) {
			return false;
		}

		$cache = $this->getCache();

		$perms = $cache->load('ownerperms');
		if (!$perms) {
			// find the owner role and take the permissions of it 
			$ownerRole = DataObject::get_one('AccessRole', '"Title" = \'Owner\'');
			if ($ownerRole && $ownerRole->exists()) {
				$perms = $ownerRole->Composes->getValues();
				if (is_array($perms)) {
					$cache->save($perms, 'ownerperms');
				}
			} else {
				// just fall back to checking OwnerID == $member->ID
				return $ownerId == $member->ID;
			}
		}

		if (is_array($perms) && in_array($perm, $perms)) {
			return true;
		}
	}

	/**
	 *
	 * @param DataObject $node
	 * @param boolean $includeInherited 
	 *			Include inherited permissions in the list?
	 */
	public function getPermissionsFor(DataObject $node, $includeInherited = false) {
		if ($this->checkPerm($node, 'ViewPermissions')) {
			$authorities = $node->getAuthorities();
			$formatted = ArrayList::create();
			
			if (!$authorities) {
				$formatted = ArrayList::create();
			} else {
				foreach ($authorities as $authority) {
					$auth = $authority->getAuthority();
					if ($auth) {
						$authority->DisplayName = $auth->getTitle();
						$authority->PermList = implode(', ', $authority->Perms->getValues());
						
					} else {
						$authority->DisplayName = 'INVALID AUTHORITY: #' . $authority->ID;
					}
					
					$formatted->push($authority);
				}
			}
			return $formatted;
		}
	}

	/**
	 * Clear any cached permissions for this object
	 *
	 * @param DataObject $item
	 * @param type $perm 
	 */
	public function clearPermCacheFor($nodeStr) {
		if (!$nodeStr) {
			return;
		}

		if (is_object($nodeStr)) {
			$nodeStr = $nodeStr->ClassName.'_'.$nodeStr->ID;
		}

		$key = $nodeStr;
		$this->getCache()->remove($key);

		$this->clearSourcesCache($nodeStr);
	}
	
	protected function clearSourcesCache($nodeStr) {
		$sourcesMap = $this->getCache()->load(self::SOURCES_MAP);
		$kidsToClear = array();
		if (isset($sourcesMap[$nodeStr])) {
			// store and do _after_ the cache write below
			$kidsToClear = $sourcesMap[$nodeStr];
		}

		unset($sourcesMap[$nodeStr]);
		$this->getCache()->save($sourcesMap, self::SOURCES_MAP);

		$key = "sources_$nodeStr";
		$this->getCache()->remove($key);
		
		foreach ($kidsToClear as $keystr) {
			$this->clearPermCacheFor($keystr);
		}
	}
	
	/**
	 * Get the key for this item in the cache
	 * 
	 * @param type $perm
	 * @return string
	 */
	public function permCacheKey(DataObject $node) {
		if($node && $node->ID){
			return $node->class . '_' . $node->ID; //  . '-' . $node->class);
		}
	}
	
	/**
	 * Realise all parent sources of the given node
	 * 
	 * @param DataObject $node
	 * @param array $addTo
	 */
	public function realiseAllSources($node, $sourceTo = null, &$addTo = null) {
		if (!$addTo) {
			$addTo = array();
		}
		
		$myIdent = $node->ClassName . "_" . $node->ID;
		
		// if needbe, update the source map
		if ($sourceTo) {
			$sourceMap = $this->getCache()->load(self::SOURCES_MAP);
			if (!$sourceMap) {
				$sourceMap = array();
			}

			$myTree = isset($sourceMap[$myIdent]) ? $sourceMap[$myIdent] : array();
		
			$myTree[$sourceTo] = 1;
			$sourceMap[$myIdent] = $myTree;
			$this->getCache()->save($sourceMap, self::SOURCES_MAP);
		}

		$parents = $this->getEffectiveParents($node);
		if ($parents) {
			foreach ($parents as $parent) {
				$addTo[] = "{$parent->ClassName},$parent->ID";
				$this->realiseAllSources($parent, $myIdent, $addTo);
			}
		}
		
		$key = "sources_{$node->ClassName}_$node->ID";
		$this->getCache()->save($addTo, $key);
		
		return $addTo;
	}
}

class PermissionDeniedException extends Exception {
	public function __construct($permission, $message = '', $code = null, $previous = null) {
		if ($previous) {
			parent::__construct($message . ' (' . $permission  .')', $code, $previous);	
		} else {
			parent::__construct($message . ' (' . $permission  .')', $code);
		}
	}
}
