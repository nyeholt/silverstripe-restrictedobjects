<?php

/**
 * @author marcus
 */
class BasicAuthEnablerExtension extends Extension {
	static $do_reenable = false;
	
	public function onBeforeSecurityLogin() {
		$this->onBeforeInit();
	}
	
	public function onBeforeInit() {
		// see if we have a requirement for basic auth. If so, and there's no current user, we should disable
		// restrictions on member objects as it will break the login process. Can re-enable afterwards
		if (Config::inst()->get('BasicAuth', 'entire_site_protected') && !Member::currentUserID()) {
			if (Restrictable::get_enabled() && Member::has_extension('Restrictable')) {
				Restrictable::set_enabled(false);
				self::$do_reenable = true;
			}
			
		}
	}
	
	public function onAfterInit() {
		if (self::$do_reenable) {
			Restrictable::set_enabled(true);
		}
	}
}
