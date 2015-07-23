<?php

/**
 * Description of MemberLoginFilter
 *
 * @author marcus
 */
class MemberLoginFilter implements RequestFilter {
	
	public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model) {
		
	}

	/**
	 * Check if we're in a login request. If so, we're going to explicitly disable
	 * restrictedobjects permission checks. This is poor, but dictated by the core
	 * member login code performing writes prior to having a user context.
	 * 
	 * @param \SS_HTTPRequest $request
	 * @param \Session $session
	 * @param \DataModel $model
	 */
	public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model) {
		if (strtolower($request->httpMethod()) === 'post' && $request->getURL() === 'Security/LoginForm') {
			Restrictable::set_enabled(false);
		}
	}
}
