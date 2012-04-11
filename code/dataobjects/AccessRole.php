<?php

/**
 * Description of AccessRole
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessRole extends DataObject {
	public static $db = array(
		'Title'			=> 'Varchar',
		'Description'	=> 'Text',
		'Composes'		=> 'MultiValueField',
	);
	
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
			$existing = DataObject::get('AccessRole');
			if ($existing && $existing->count()) {
				return;
			}
			
			$dp = self::allPermissions();

			$role = new AccessRole;
			$role->Title = 'Admin';
			$role->Composes = array_values($dp);
			$role->write();
			
			$ownerPerms = $dp;
			// get rid of publish from owners
			unset($ownerPerms['Publish']);
			
			$role = new AccessRole;
			$role->Title = 'Owner';
			$role->Composes = array_keys($ownerPerms);
			$role->write();

			unset($dp['TakeOwnership']);
			unset($dp['Configure']);

			$role = new AccessRole;
			$role->Title = 'Manager';
			$role->Composes = array_keys($dp);
			$role->write();

			$role = new AccessRole;
			$role->Title = 'Editor';
			$role->Composes = array('View','Write','CreateChildren');
			$role->write();
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab('Root.Main', new MultiValueListField('Composes', _t('AccessRole.COMPOSES', 'Composes perms'), self::allPermissions()));
		return $fields;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ($this->Title == 'Owner') {
			// a hack, but necessary for the moment...
			singleton('PermissionService')->getCache()->remove('ownerperms');
		}
	}
	
	public static function allPermissions() {
		$perms = singleton('PermissionService')->allPermissions();
		return array_combine($perms, $perms);
	}
}

class DefaultPermissions implements PermissionDefiner {
	public function definePermissions() {
		return array(
			'View',
			'Write',
			'Delete',
			'CreateChildren',
			'Publish',
			'UnPublish',
			'ViewPermissions',
			'ChangePermissions',
			'DeletePermissions',
			'TakeOwnership',
			'Configure',
		);
	}
}
