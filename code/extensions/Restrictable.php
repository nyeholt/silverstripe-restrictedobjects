<?php

/**
 * An extension that adds role granting functionality
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class Restrictable extends DataObjectDecorator {

	/**
	 * @var Zend_Cache_Core 
	 */
	protected $cache;

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

	public function extraStatics() {
		return array(
			'db' => array(
				'InheritPerms' => 'Boolean',
				'PublicAccess' => 'Boolean',
			),
			'has_one' => array(
				'Owner' => 'Member',
			),
			'defaults' => array(
				'InheritPerms' => true,
			),
		);
	}

	public function getAuthorities() {
		$filter = '"ItemID" = ' . ((int) $this->owner->ID) . ' AND "ItemType" = \'' . Convert::raw2sql($this->owner->class) . '\'';
		$items = DataObject::get('AccessAuthority', $filter);
		return $items;
	}

	/**
	 * Grants a specific permission to a given user or group
	 *
	 * @param string $perm
	 * @param Member|Group $to
	 */
	public function grant($perm, DataObject $to, $grant = 'GRANT') {
		$role = DataObject::get_one('AccessRole', '"Title" = \'' . Convert::raw2sql($perm) . '\'');

		$composedOf = array($perm);
		if ($role && $role->exists()) {
			$composedOf = $role->Composes->getValues();
		}

		$type = $to instanceof Member ? 'Member' : 'Group';
		$filter = array(
			'Type =' => $type,
			'AuthorityID =' => $to->ID,
			'ItemID =' => $this->owner->ID,
			'ItemType =' => $this->owner->class,
			'Grant =' => $grant,
		);

		$existing = DataObject::get_one('AccessAuthority', singleton('SiteUtils')->dbQuote($filter));
		if (!$existing || !$existing->exists()) {
			$existing = new AccessAuthority;
			$existing->Type = $type;
			$existing->AuthorityID = $to->ID;
			$existing->ItemID = $this->owner->ID;
			$existing->ItemType = $this->owner->class;
			$existing->Grant = $grant;
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
			$key = $this->permCacheKey($perm);
			$this->getCache()->remove($key);
		}
	}

	/**
	 * Deny access to a data object to a particular group/user
	 *
	 * @param string $perm
	 * @param Member $to
	 * @return 
	 */
	public function deny($perm, $to) {
		return $this->grant($perm, $to, 'DENY');
	}

	/**
	 * Get the key for this item in the cache
	 *
	 * @param type $perm
	 * @return string
	 */
	public function permCacheKey($perm) {
		return md5($perm . '-' . $this->owner->ID . '-' . $this->owner->class);
	}

	/**
	 * Return true or false as to whether a given user can access an object
	 * 
	 * @param type $perm
	 * @param type $member
	 * @param type $alsoFor
	 * @return type 
	 */
	public function checkPerm($perm, $member=null) {
		if (!$member) {
			$member = Member::currentUser();
		}
		if (is_int($member)) {
			$member = DataObject::get_by_id('Member', $member);
		}

		if (Permission::check('ADMIN')) {
			return true;
		}

		// if no member, just check public view
		$public = $this->checkPublicPerms($perm);
		if ($public) {
			return true;
		}

		// see whether we're the owner, and if the perm we're checking is in that list
		if ($this->checkOwnerPerms($perm, $member)) {
			return true;
		}

		$permCache = $this->getCache();
		/* @var $permCache Zend_Cache_Core */

		$accessAuthority = '';

		$key = $this->permCacheKey($perm);
		$userGrants = $permCache->load($key);

		$directGrant = null;

		if ($userGrants && isset($userGrants[$member->ID])) {
			$directGrant = $userGrants[$member->ID];
		} else {
			$userGrants = array();
		}

		$can = false;

		if (!$directGrant) {
			$filter = array(
				'ItemID =' => $this->owner->ID,
				'ItemType =' => $this->owner->class,
			);

			// get all access authorities for this object
			$existing = DataObject::get('AccessAuthority', singleton('SiteUtils')->dbQuote($filter));

			$groups = $member ? $member->Groups() : array();
			$gids = array();
			if ($groups && $groups->Count()) {
				$gids = $groups->map('ID', 'ID');
			}

			$can = false;
			$directGrant = 'NONE';
			if ($existing && $existing->count()) {
				foreach ($existing as $access) {
					// check if this mentions the perm in question
					$perms = $access->Perms->getValues();
					if (!in_array($perm, $perms)) {
						continue;
					}

					$grant = null;
					if ($access->Type == 'Group') {
						if (isset($gids[$access->AuthorityID])) {
							$grant = $access->Grant;
						}
					} else {
						if ($member->ID == $access->AuthorityID) {
							$grant = $access->Grant;
						}
					}

					if ($grant) {
						// if it's deny, we can just break away immediately, otherwise we need to evaluate all the 
						// others in case there's another DENY in there somewhere
						if ($grant == 'DENY') {
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

			$userGrants[$member->ID] = $directGrant;
			$permCache->save($userGrants, $key);
		}

		// return immediately if we have something
		if ($directGrant === 'GRANT') {
			return true;
		}
		if ($directGrant === 'DENY') {
			return false;
		}

		// otherwise query our parents
		if ($this->owner->InheritPerms) {
			$permParent = $this->effectiveParent();
			return $permParent ? $permParent->checkPerm($perm, $member) : false;
		}

		return false;
	}

	/**
	 * Returns the effective parent of a given node for permission purposes
	 * 
	 * Allows objects that aren't in a traditional Parent/Child relationship
	 * to indicate where their permissions are coming from
	 *
	 * @param DataObject $node 
	 */
	protected function effectiveParent() {
		$permParent = null;
//		if (method_exists($this->owner, 'permissionSource')) {
		if ($this->owner->hasMethod('permissionSource')) {
			$permParent = $this->owner->permissionSource();
		} else if ($this->owner->ParentID) {
			$permParent = $this->owner->Parent();
		}
		return $permParent;
	}

	/**
	 * Checks the permissions for a public user
	 * 
	 * @param string $perm 
	 */
	public function checkPublicPerms($perm) {
		if ($perm == 'View') {
			if ($this->owner->PublicAccess) {
				return true;
			}
			$parent = $this->effectiveParent();
			if ($parent) {
				return $parent->checkPublicPerms($perm);
			}
		}
		return false;
	}

	/**
	 * Is the member the owner of this object, and is the permission being checked
	 * in the list of permissions that owners have?
	 *
	 * @param string $perm
	 * @param Member $member
	 * @return boolean
	 */
	protected function checkOwnerPerms($perm, $member) {
		if ($this->owner->OwnerID != $member->ID) {
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
			}
		}

		if (is_array($perms) && in_array($perm, $perms)) {
			return true;
		}
	}

	/**
	 * Allow users to specify an array of field level permission requirements on a content
	 * object that will be checked when editing items. 
	 * 
	 * This should return an array (
	 * 		'FieldName'		=> 'RequiredPermission',
	 * )
	 * 
	 */
	public function fieldPermissions() {
		return array();
	}

	public function canView($member=null) {
		$res = $this->checkPerm('View', $member);
		return $res;
	}

	public function canEdit($member=null) {
		$res = $this->checkPerm('Write', $member);
		return $res;
	}

	public function canDelete($member=null) {
		$res = $this->checkPerm('Delete', $member);
		return $res;
	}

	public function canPublish($member=null) {
		$res = $this->checkPerm('Publish', $member);
		return $res;
	}

	public function updateCMSFields(FieldSet $fields) {
		// $controller, $name, $sourceClass, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = ""
		$fieldPerms = $this->owner->fieldPermissions();

		foreach ($fieldPerms as $fieldId => $permission) {
			if (!$this->checkPerm($permission)) {
				// convert to a readonly field
				$fields->makeFieldReadonly($fieldId);
			}
		}

		if ($this->owner->checkPerm('ViewPermissions')) {
			$table = new PermissionTableField($this->owner, 'Authorities', 'AccessAuthority');

			$perms = array('show');

			if ($this->owner->checkPerm('ChangePermissions')) {
				$perms[] = 'add';
				$fields->addFieldToTab('Root.Permissions', new CheckboxField('InheritPerms', _t('Restrictable.INHERIT_PERMS', 'Inherit Permissions')));
				$fields->addFieldToTab('Root.Permissions', new CheckboxField('PublicAccess', _t('Restrictable.PUBLIC_ACCESS', 'Publicly Accessible')));
			}

			if ($this->checkPerm('TakeOwnership')) {
				$fields->addFieldToTab('Root.Permissions', new DropdownField('OwnerID', _t('Restrictable.OWNER', 'Owner'), DataObject::get('Member')->map('ID', 'Title')));
			}

			if ($this->owner->checkPerm('DeletePermissions')) {
				$perms[] = 'delete';
			}

			$table->setPermissions($perms);
			$fields->addFieldToTab('Root.Permissions', $table);
		}
	}
	
	/**
	 * handles SiteTree::canAddChildren, useful for other types too
	 */
	public function canAddChildren() {
		if ($this->checkPerm('CreateChildren')) {
			return true;
		} else {
			return false;
		}
	}

	public function onBeforeWrite() {
		try {
			// see if we're actually allowed to do this!
			if (!$this->owner->ID) {
				$parent = $this->effectiveParent();
				if ($parent) {
					// check create children
					if (!$parent->canAddChildren()) {
						throw new PermissionDeniedException('CreateChildren');
					}
				}
			}

			// get the changed items first
			$changed = $this->owner->getChangedFields(false, 2);

			// set the owner now so that our perm check in a second works.
			if (!$this->owner->OwnerID) {
				$this->owner->OwnerID = Member::currentUserID();
			}

			// don't allow write
			if (!$this->checkPerm('Write')) {
				throw new PermissionDeniedException('You must have write permission');
			}

			$fields = $this->owner->fieldPermissions();
			$fields['OwnerID'] = 'TakeOwnership';

			foreach ($changed as $field => $details) {
				if (isset($fields[$field])) {
					// check the permission				
					if (!$this->checkPerm($fields[$field])) {
						// this should never happen because the field should not be visible for editing 
						// in the first place. 
						throw new PermissionDeniedException("Invalid permissions to edit $field, " . $fields[$field] . " required");
					}
				}
			}
		} catch (PermissionDeniedException $pde) {
			Security::permissionFailure();
			throw $pde;
		}
	}

}

class PermissionDeniedException extends Exception {
	
}