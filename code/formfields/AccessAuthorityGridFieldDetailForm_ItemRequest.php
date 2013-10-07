<?php

/**
 * Special handler for saving access authority objects to make sure it's run through 
 * the permission service to make sure all grants are handled correctly
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessAuthorityGridFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest {
	
	function doSave($data, $form) {
		
		if ($this->gridField->forObject->checkPerm('ChangePermissions')) {
			if (isset($data['Members']) && is_array($data['Members'])) {
				foreach ($data['Members'] as $memberId) {
					$member = DataObject::get_by_id('Member', (int) $memberId);
					if (strlen($data['Role'])) {
						$this->gridField->forObject->grant($data['Role'], $member, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
					if (isset($data['Perms']) && strlen($data['Perms'])) {
						$this->gridField->forObject->grant($data['Perms'], $member, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
				}
			}

			if (isset($data['Groups']) && is_array($data['Groups'])) {
				foreach ($data['Groups'] as $groupId) {
					$group = DataObject::get_by_id('Group', (int) $groupId);
					if (strlen($data['Role'])) {
						$this->gridField->forObject->grant($data['Role'], $group, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
					if (isset($data['Perms']) && strlen($data['Perms'])) {
						$this->gridField->forObject->grant($data['Perms'], $group, $data['Grant'] == 'GRANT' ? 'GRANT' : 'DENY');
					}
				}
			}
			
			$message = sprintf(_t('AccessAuthorityGrid.PERMISSIONS_ADDED', 'Added permissions. '));
			$form->sessionMessage($message, 'good');
		} else {
			$message = sprintf(_t('AccessAuthorityGrid.FAILURE', 'You cannot do that. '));
			$form->sessionMessage($message, 'bad');
		}

		$controller = Controller::curr();
		$crumbs = $this->Breadcrumbs();
		if ($crumbs && $crumbs->count()>=2){
			$one_level_up = $crumbs->offsetGet($crumbs->count()-2);
			$link = $one_level_up->Link;
		} else {
			$link = $this->Link();
		}
		
		return $controller->redirect($link);
	}
}
