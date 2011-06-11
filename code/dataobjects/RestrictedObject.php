<?php

/**
 * A dataobject that explicitly uses the restrictable extension.
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedObject extends DataObject {
	public static $extensions = array(
		'Restrictable',
	);
}
