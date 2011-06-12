<?php

if (($RESTRICTED_OBJECTS_DIR = basename(dirname(__FILE__))) != 'restrictedobjects') {
	die("The restricted objects module must be installed in /restrictedobjects, not $RESTRICTED_OBJECTS_DIR");
}

if (!class_exists('MultiValueField')) {
	die('The restricted objects module requires the multivaluefield module from http://github.com/nyeholt/silverstripe-multivaluefield');
}