<?php

if (($RESTRICTED_OBJECTS_DIR = basename(dirname(__FILE__))) != 'restrictedobjects') {
	die("The restricted objects module must be installed in /restrictedobjects, not $RESTRICTED_OBJECTS_DIR");
}

if (!class_exists('MultiValueField')) {
	die('The restricted objects module requires the multivaluefield module from http://github.com/nyeholt/silverstripe-multivaluefield');
}

Director::addRules(100, array(
	'Security/logout' => 'RestrictedSecurityController',
));

Member::add_extension('Member', 'RestrictedMember');

// if we're in Dev, and have set "no initial checks", which is common during testing, disable perms
if ((Director::isDev() || Director::is_cli()) && isset($_GET['disable_perms'])) {
	Restrictable::set_enabled(false);
}

SS_Cache::set_cache_lifetime('restricted_perms', 3600);