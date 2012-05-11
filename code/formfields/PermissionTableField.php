<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PermissionTableField extends ComplexTableField {
	
	protected $forObject;
	
	public function __construct($forObject, $name, $sourceClass, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = "") {
		$this->forObject = $forObject;
		if (!$detailFormFields) {
			$dummy = singleton('AccessAuthority');

			$members = new CheckboxSetField('Members', _t('AccessAuthority.MEMBERS', 'Members'), DataObject::get('Member'));
			$groups = new CheckboxSetField('Groups', _t('AccessAuthority.GROUPS', 'Groups'), DataObject::get('Group'));

			$allRoles = DataObject::get('AccessRole');
			if ($allRoles) {
				$allRoles = $allRoles->map('Title', 'Title');
			} else {
				$allRoles = array();
			}
			$roles = new DropdownField('Role', _t('AccessAuthority.ROLE', 'Role'), $allRoles, '', null, '(Role)');

			// deliberately only allow singles here - people should define roles!
			$perms = new DropdownField('Perms', _t('PermissionTable.PERMS', 'Permission - use roles for multiple!'), AccessRole::allPermissions(), '', null, '(Permission)');
			
			$detailFormFields = new FieldList(
				$roles,
				$perms,
				$members,
				$groups,
				new DropdownField('Grant', _t('AccessAuthority.Grant', 'Grant Access'), $dummy->dbObject('Grant')->enumValues())
			);
		}

		if (!$fieldList) {
			$fieldList = array(
				'Type' => 'Type',
				'getAuthority.Title' => 'Authority',
				'Grant' => 'Grant',
				'PermList' => 'Perms'
			);
		}
		
		$sourceFilter = '"ItemID" = '.Convert::raw2sql($forObject->ID).' AND "ItemType" = \''.Convert::raw2sql($forObject->class).'\'';

		parent::__construct($forObject, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
	}
	
	public function saveComplexTableField($data, $form, $params) {
		// instead of saving something directly, we want to actually call a 'grant' against the object
		// parent::saveComplexTableField($data, $form, $params);
		$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		$closeLink = sprintf(
			'<small><a href="%s" onclick="javascript:window.top.GB_hide(); return false;">(%s)</a></small>',
			$referrer,
			_t('ComplexTableField.CLOSEPOPUP', 'Close Popup')
		);
		
		if ($this->forObject->checkPerm('ChangePermissions')) {
			if (isset($data['Members']) && is_array($data['Members'])) {
				foreach ($data['Members'] as $memberId) {
					$member = DataObject::get_by_id('Member', (int) $memberId);
					if (strlen($data['Role'])) {
						$this->forObject->grant($data['Role'], $member, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
					if (isset($data['Perms']) && strlen($data['Perms'])) {
						$this->forObject->grant($data['Perms'], $member, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
				}
			}

			if (isset($data['Groups']) && is_array($data['Groups'])) {
				foreach ($data['Groups'] as $groupId) {
					$group = DataObject::get_by_id('Group', (int) $groupId);
					if (strlen($data['Role'])) {
						$this->forObject->grant($data['Role'], $group, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
					if (isset($data['Perms']) && strlen($data['Perms'])) {
						$this->forObject->grant($data['Perms'], $group, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
				}
			}
			
			$message = sprintf(_t('PermissionTableField.SUCCESS', 'Added permissions. '.$closeLink));
			$form->sessionMessage($message, 'good');
		} else {
			$message = sprintf(_t('PermissionTableField.FAILURE', 'You cannot do that. '.$closeLink));
			$form->sessionMessage($message, 'bad');
		}
		
		
		Director::redirectBack();
	}
}
