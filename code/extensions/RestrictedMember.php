<?php

/**
 * Description of RestrictedMember
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedMember extends DataObjectDecorator {
	public function memberLoggedIn() {
		singleton('SecurityContext')->setMember($this->owner);
	}
}
