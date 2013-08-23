<?php

/**
 * A container for permissions to be assigned to, which restrictable objects 
 * can then inherit from if they so desire
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PermissionParent extends DataObject {
	private static $db = array(
		'Title'		=> 'Varchar',
	);
	private static $extensions = array(
		'Restrictable',
	);
}
