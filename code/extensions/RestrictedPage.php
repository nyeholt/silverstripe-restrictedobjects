<?php

/**
 * A helper for making pages restricted by allowing perm lookups 
 * for pages to make use of the site config too
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedPage extends DataExtension {
	public function permissionSource() {
		return $this->owner->ParentID ? $this->owner->Parent() : SiteConfig::current_site_config();
	}
}
