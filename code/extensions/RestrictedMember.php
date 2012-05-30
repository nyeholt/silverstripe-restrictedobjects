<?php

/**
 * Capture the user being logged in and set them into the security context
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedMember extends DataExtension {
	
	public function memberAutoLoggedIn() {
		$this->memberLoggedIn();
	}
	
	public function memberLoggedIn() {
		
		singleton('SecurityContext')->setMember($this->owner);
	}
}
