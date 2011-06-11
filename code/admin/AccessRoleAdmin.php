<?php

/**
 * Description of AccessRoleAdmin
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class AccessRoleAdmin extends ModelAdmin {
	public static $url_segment = 'access';
	public static $menu_title = 'Access Roles';
	
	public static $managed_models = array(
		'AccessRole',
	);
}
