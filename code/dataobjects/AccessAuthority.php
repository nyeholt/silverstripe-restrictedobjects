<?php

/**
 * An access authority describes the Role a user or group has
 * which is then associated with a given object
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessAuthority extends DataObject {
	public static $db = array(
		'Type'				=> "Enum('Member,Group')",
		'AuthorityID'		=> 'Int',
		'Perms'				=> 'MultiValueField',
		'Grant'				=> "Enum('GRANT,DENY','GRANT')",
		'ItemID'			=> 'Int',
		'ItemType'			=> 'Varchar',
	);

	public function getAuthority() {
		if ($this->Type && $this->AuthorityID) {
			return DataObject::get_by_id($this->Type, $this->AuthorityID);
		}
	}
	
	public function getItem() {
		return DataObject::get_by_id($this->ItemType, $this->ItemID);
	}
	
	public function PermList() {
		return '<p>'.implode('</p><p>', $this->Perms->getValues()).'</p>';
	}
	
	public function onAfterDelete() {
		parent::onBeforeDelete();
		
		$values = $this->Perms->getValues();
		foreach ($values as $perm) {
			singleton('PermissionService')->clearPermCacheFor($this->getItem(), $perm);
		}
	}
}
