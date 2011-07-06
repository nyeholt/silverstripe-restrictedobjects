<?php

/**
 * A user that represents a public user of the system
 * 
 * NOTE: This is not actively being used anywhere just yet... an explicit 'public' 
 * flag is currently being used to indicate 'public view'. 
 * 
 * This might need to be considered later when public users can do things
 * like creating items, but realistically that should be managed using
 * runAs blocks of code
 * 
 * @deprecated
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PublicMember extends Member {
	
	public function __construct($record = null, $isSingleton = false) {
		parent::__construct($record, $isSingleton);
		
		// we're explicitly setting our ID to -1. This is detected by the
		// permission service management code for public permission application
		$this->ID = -1;
	}
	
	public function write() {
		throw new Exception("Cannot save public member");
	}
}
