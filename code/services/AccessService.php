<?php

/**
 * Description of AccessService
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessService {
	
	public function getAllRoles() {
		return DataObject::get('AccessRole');
	}
}
