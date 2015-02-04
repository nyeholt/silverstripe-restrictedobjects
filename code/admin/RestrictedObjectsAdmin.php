<?php

/**
 * Description of AccessRoleAdmin
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedObjectsAdmin extends ModelAdmin {
	private static $url_segment = 'access';
	private static $menu_title = 'Restricted Objects';
	
	private static $managed_models = array(
		'AccessRole', 'PermissionParent', 'SimpleMemberList'
	);
}
