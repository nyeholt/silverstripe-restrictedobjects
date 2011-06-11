<?php

/**
 * Indicates that this class defines some low level permssions
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
interface PermissionDefiner {
	/**
	 * Define a list of permissions in an array. These 
	 * can then be used to create more complex permissions at a later
	 * point in time
	 */
	public function definePermissions();
}
