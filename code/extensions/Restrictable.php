<?php

/**
 * An extension that adds role granting functionality
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class Restrictable extends DataObjectDecorator {

	/**
	 * @var boolean
	 */
	protected static $enabled = true;

	public static function set_enabled($v = true) {
		self::$enabled = $v;
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
		return singleton('PermissionService')->grant($this->owner, $perm, $to, $grant);
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
	 * Return true or false as to whether a given user can access an object
	 * 
	 * @param type $perm
	 * @param type $member
	 * @param type $alsoFor
	 * @return type 
	 */
	public function checkPerm($perm, $member=null) {
		return singleton('PermissionService')->checkPerm($this->owner, $perm, $member);
	}

	/**
	 * Returns the effective parent of a given node for permission purposes
	 * 
	 * Allows objects that aren't in a traditional Parent/Child relationship
	 * to indicate where their permissions are coming from
	 *
	 * @param DataObject $node 
	 */
	public function effectiveParent() {
		$permParent = null;
		if ($this->owner->hasMethod('permissionSource')) {
			$permParent = $this->owner->permissionSource();
		} else if ($this->owner->ParentID) {
			$permParent = $this->owner->Parent();
		}
		return $permParent;
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
		if (self::$enabled) {
			$res = $this->checkPerm('View', $member);
			return $res;
		}
	}

	public function canEdit($member=null) {
		if (self::$enabled) {
			$res = $this->checkPerm('Write', $member);
			return $res;
		}
	}

	public function canDelete($member=null) {
		if (self::$enabled) {
			$res = $this->checkPerm('Delete', $member);
			return $res;
		}
	}

	public function canPublish($member=null) {
		if (self::$enabled) {
			$res = $this->checkPerm('Publish', $member);
			return $res;
		}
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
			$rootTab = $fields->fieldByName('Root');
			$fileRootTab = $fields->fieldByName('BottomRoot');
			$addTo = null;
			if ($rootTab) {
				$addTo = $fields->findOrMakeTab('Root.Permissions');
			} else if ($fileRootTab) {
				$addTo = $fields->findOrMakeTab('BottomRoot.Permissions');
			}
			// only add if we have a CMS backend!
			if ($addTo) {
				if ($this->owner->checkPerm('ChangePermissions')) {
					$perms[] = 'add';
					$addTo->push(new CheckboxField('InheritPerms', _t('Restrictable.INHERIT_PERMS', 'Inherit Permissions')));
					$addTo->push(new CheckboxField('PublicAccess', _t('Restrictable.PUBLIC_ACCESS', 'Publicly Accessible')));
				}

				if ($this->checkPerm('TakeOwnership')) {
					$addTo->push(new DropdownField('OwnerID', _t('Restrictable.OWNER', 'Owner'), DataObject::get('Member')->map('ID', 'Title')));
				}

				if ($this->owner->checkPerm('DeletePermissions')) {
					$perms[] = 'delete';
				}

				$table->setPermissions($perms);
				$addTo->push($table);
			}
			
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
		if (self::$enabled) {
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
				if (!$this->owner->OwnerID && singleton('SecurityContext')->getMember()) {
					$this->owner->OwnerID = singleton('SecurityContext')->getMember()->ID;
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
}
