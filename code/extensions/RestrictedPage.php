<?php

/**
 * Description of RestrictedPage
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedPage extends DataObjectDecorator {
	public function permissionSource() {
		return $this->owner->ParentID ? $this->owner->Parent() : SiteConfig::current_site_config();
	}
}
