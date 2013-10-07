<?php

/**
 * An extension that adds role granting functionality
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class Restrictable extends DataExtension {

	/**
	 * @var boolean
	 */
	protected static $enabled = true;
	
	private static $db = array(
		'InheritPerms' => 'Boolean',
		'PublicAccess' => 'Boolean',
	);
	
	private static $has_one = array(
		'Owner' => 'Member',
	);
	
	private static $defaults = array(
		'InheritPerms' => true,
	);

	public static function get_enabled() {
		return self::$enabled;
	}
	
	public static function set_enabled($v = true) {
		$prev = self::$enabled;
		self::$enabled = $v;
		return $prev;
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
	 * @deprecated
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
	 * Return a list of all parents of this node
	 */
	public function effectiveParents() {
		$permParents = new ArrayObject();
		if ($this->owner->hasMethod('permissionSource')) {
			$permParents[] = $this->owner->permissionSource();
		} else if ($this->owner->hasMethod('permissionSources')) {
			$permParents = $this->owner->permissionSources();
		} else if ($this->owner->ParentID) {
			$permParents[] = $this->owner->Parent();
		}
		return $permParents;
	}

	/**
	 * Allow users to specify an array of field level permission requirements on a content
	 * object that will be checked when editing items. 
	 * 
	 * This should return an array (
	 * 		'FieldName'		=> 'RequiredPermission',
	 * )
	 * 
	 * Typically this is called directly in the extension so that OwnerID is always specified. 
	 * 
	 */
	public function fieldPermissions() {
		// so we can pass around by ref and not worry about end users NOT
		// using &$fields in their code...
		$fieldPerms = new ArrayObject();

		if (method_exists($this->owner, 'fieldPermissions')) {
			$fieldPerms = $this->owner->fieldPermissions();
			if (!is_array($fieldPerms)) {
				// force it to be an array
				$fieldPerms = new ArrayObject();
			}
		}

		$fieldPerms['OwnerID'] = 'TakeOwnership';
		$this->owner->extend('updateFieldPermissions', $fieldPerms);

		return $fieldPerms;
	}

	/**
	 * Check for a View permission only if the item exists in the DB
	 * @param type $member
	 * @return type 
	 */
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

	public function updateCMSFields(FieldList $fields) {
		// $controller, $name, $sourceClass, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = ""
		$fieldPerms = $this->fieldPermissions();

		// first, make a field readonly if there's no permission to edit it
		foreach ($fieldPerms as $fieldId => $permission) {
			if (!$this->checkPerm($permission)) {
				// convert to a readonly field
				$hasField = $fields->dataFieldByName($fieldId);
				if ($hasField) {
					$fields->makeFieldReadonly($fieldId);
				}
			}
		}

		if ($this->owner->checkPerm('ViewPermissions')) {
			
			$accessList = DataList::create('AccessAuthority')->filter(array('ItemID' => $this->owner->ID, 'ItemType' => $this->owner->class));
			$listField = GridField::create(
				'AccessAuthority',
				false,
				$accessList,
				$fieldConfig = GridFieldConfig_RecordEditor::create(20)
			);

			$fieldConfig->removeComponentsByType('GridFieldEditButton');
			
			// AccessAuthorityGridFieldDetailForm_ItemRequest
			$detailForm = $fieldConfig->getComponentByType('GridFieldDetailForm');
			$detailForm->setItemRequestClass('AccessAuthorityGridFieldDetailForm_ItemRequest');
			$listField->forObject = $this->owner;

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

//				$table->setPermissions($perms);
				$addTo->push($listField);
			}
		}
	}
	
	/**
	 * Set default for inherit
	 *
	 * @param FieldSet $fields
	 */
	public function updateFrontEndFields(FieldList $fields) {
		if (!$this->owner->ID) {
			$fields->replaceField('InheritPerms',new CheckboxField('InheritPerms', _t('Restrictable.INHERIT_PERMS', 'Inherit Permissions'), true));
		}
		
		$ownerField = $fields->fieldByName('OwnerID');
		if ($ownerField) {
			$members = singleton('DataService')->getAllMember();
			$source = array();
			if ($members) {
				$source = $members->map();
			}
			if (method_exists($ownerField, 'setSource')) {
				$ownerField->setSource($source);
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
					if ($parent && $parent->ID) {
						// check create children
						if ($parent->hasMethod('canAddChildren') && !$parent->canAddChildren()) {
							throw new PermissionDeniedException('CreateChildren', "Cannot create " . $this->owner->ClassName . " under " . $parent->ClassName . " #$parent->ID");
						}
					}
				}

				// get the changed items first
				$changed = $this->owner->getChangedFields(false, 2);
				
				$allowWrite = false;

				// set the owner now so that our perm check in a second works.
				if (!$this->owner->OwnerID && singleton('SecurityContext')->getMember()) {
					$this->owner->OwnerID = singleton('SecurityContext')->getMember()->ID;
					// ignore any changed fields setting for this field
					unset($changed['OwnerID']);
				} else if (!$this->owner->OwnerID && $this->owner instanceof Member) {
					// allow the write to occur
					unset($changed['OwnerID']);
					$allowWrite = true;
				}

				// don't allow write
				if (!$allowWrite && !$this->checkPerm('Write')) {
					throw new PermissionDeniedException('Write', 'You must have write permission to ' . $this->owner->ClassName . ' #' . $this->owner->ID);
				}

				$fields = $this->fieldPermissions();

				foreach ($changed as $field => $details) {
					if (isset($fields[$field])) {
						// check the permission
						if (!$this->checkPerm($fields[$field])) {
							// this should never happen because the field should not be visible for editing 
							// in the first place. 
							throw new PermissionDeniedException($fields[$field], "Invalid permissions to edit $field, " . $fields[$field] . " required");
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
