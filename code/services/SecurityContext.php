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
		$this->currentMember = $this->getMember();
	}
	
	public function getMember() {
		if (!$this->currentMember) {
			$this->currentMember = Member::currentUser();
		}
		return $this->currentMember;
	}
	
	public function setMember($member) {
		$this->currentMember = $member;
	}
}
