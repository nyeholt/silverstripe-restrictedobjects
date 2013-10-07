<?php

/**
 * An access authority describes the Role a user or group has
 * which is then associated with a given object
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessAuthority extends DataObject {
	private static $db = array(
		'Type'				=> "Enum('Member,Group')",
		'AuthorityID'		=> 'Int',
		'Role'				=> 'Varchar',			// recorded so future role changes can propagate
		'Perms'				=> 'MultiValueField',
		'Grant'				=> "Enum('GRANT,DENY','GRANT')",
		'ItemID'			=> 'Int',
		'ItemType'			=> 'Varchar',
	);
	
	private static $indexes = array(
		'Item'		=> '(ItemID,ItemType)',
	);
	
	public function getAuthority() {
		if ($this->Type && $this->AuthorityID > 0) {
			return DataObject::get_by_id($this->Type, $this->AuthorityID);
		}

		if ($this->AuthorityID == -1) {
			return singleton('PublicMember');
		}
	}
	
	public function getItem() {
		return DataObject::get_by_id($this->ItemType, $this->ItemID);
	}
	
	public function PermList() {
		if($this->Perms->getValues()){
			return '<p>'.implode('</p><p>', $this->Perms->getValues()).'</p>';	
		}
	}
	
	public function onAfterDelete() {
		parent::onBeforeDelete();
		
		if($values = $this->Perms->getValues()){
			foreach ($values as $perm) {
				singleton('PermissionService')->clearPermCacheFor($this->getItem(), $perm);
			}
		}
	}
	
	public function canView($member = null) {
		return Member::currentUserID() > 0;
	}
	
	public function getCMSFields() {
		$fields = FieldList::create();
		
		$dummy = singleton('AccessAuthority');

		$members = new CheckboxSetField('Members', _t('AccessAuthority.MEMBERS', 'Members'), DataObject::get('Member'));
		$groups = new CheckboxSetField('Groups', _t('AccessAuthority.GROUPS', 'Groups'), DataObject::get('Group'));

		$allRoles = DataObject::get('AccessRole');
		if ($allRoles) {
			$allRoles = $allRoles->map('Title', 'Title');
		} else {
			$allRoles = array();
		}
		$roles = DropdownField::create('Role', _t('AccessAuthority.ROLE', 'Role'), $allRoles)->setEmptyString('(Role)');

		// deliberately only allow singles here - people should define roles!
		$perms = DropdownField::create('Perms', 
				_t('PermissionTable.PERMS', 'Permission - use roles for multiple!'), 
				AccessRole::allPermissions()
			)->setEmptyString('(Permission)');

		$detailFormFields = new FieldList(
			$roles,
			$perms,
			$members,
			$groups,
			new DropdownField('Grant', _t('AccessAuthority.Grant', 'Grant Access'), $dummy->dbObject('Grant')->enumValues())
		);
		
		return $detailFormFields;
	}
	
	public function summaryFields() {
		$fieldList = array(
			'Type'					=> 'Type',
			'getAuthority.Title'	=> 'Authority',
			'Grant'					=> 'Grant',
			'PermList'				=> 'Perms'
		);

		return $fieldList;
	}
}
