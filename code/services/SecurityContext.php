<?php

/**
 * A context describing the security in place in the application,
 * effectively the user that's logged in
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class SecurityContext {
	
	protected $currentMember;
	
	public function __construct() {

	}
	
	public function getMember() {
		if (!$this->currentMember) {
			// need to allow member to write itself before it's fully loaded...
			$curr = Restrictable::set_enabled(false);
			if (Member::currentUserID()) {
				$this->currentMember = Member::currentUser();
			} else {
				// ignore this for now!
//				$this->currentMember = singleton('PublicMember');
			}
			Restrictable::set_enabled($curr);
		}
		return $this->currentMember;
	}

	public function setMember($member) {
		$this->currentMember = $member;
	}
}
