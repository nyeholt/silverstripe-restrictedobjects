<?php

/**
 * Controller that handles Security logout to prevent write errors
 * as logout tries to write the user AFTER logging out
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedSecurityController extends Security {
	
	public function index($redirect = true) {
		Restrictable::set_enabled(false);
		$member = Member::currentUser();
		Restrictable::set_enabled(true);

		if($member) {
			// run the logout as an admin so we can update the user object
			singleton('TransactionManager')->run(array($member, 'logOut'), Security::findAnAdministrator());
		}

		if($redirect) $this->redirectBack();

		return '';
	}
}
