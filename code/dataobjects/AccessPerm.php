<?php

/**
 * A low level permission in the system
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessPerm extends DataObject {
	public static $db = array(
		'Title'			=> 'Varchar',
		'Description'	=> 'Text',
	);
	
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();
		if (Director::isDev() || Director::isTest()) {
			$existing = DataObject::get('AccessPerm');
			if ($existing && $existing->count()) {
				return;
			}
			
			$role = new AccessPerm;
			$role->Title = 'View';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'Write';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'ChangePermissions';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'Create';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'Publish';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'UnPublish';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'Delete';
			$role->write();
			
			$role = new AccessPerm;
			$role->Title = 'Configure';
			$role->write();
		}
	}
}
