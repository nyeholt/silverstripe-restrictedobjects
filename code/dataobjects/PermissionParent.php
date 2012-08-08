<?php

/**
 * A container for permissions to be assigned to, which restrictable objects 
 * can then inherit from if they so desire
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class PermissionParent extends DataObject {
	public static $db = array(
		'Title'		=> 'Varchar',
	);
	public static $extensions = array(
		'Restrictable',
	);
}
