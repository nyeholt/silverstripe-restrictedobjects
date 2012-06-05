<?php

/**
 * Helper for running bits and pieces of code in transactions
 * 
 * Especially useful for running code as different user
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TransactionManager {
	public function __construct() {
		
	}
	
	public function run($closure, $as=null) {
		DB::getConn()->transactionStart();
		$args = func_get_args();
		array_shift($args);array_shift($args);
		$current = singleton('SecurityContext')->getMember();
		if ($as) {
			singleton('SecurityContext')->setMember($as);
		}
		
		if (is_array($closure)) {
			call_user_func_array($closure, $args);
		} else {
			$closure();
		}

		if ($as) {
			singleton('SecurityContext')->setMember($current);
		}
		DB::getConn()->transactionEnd();
	}
}