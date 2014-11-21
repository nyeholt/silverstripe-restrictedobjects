<?php

/**
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class RestrictedDataList extends Extension {
	
	public function restrict($perm = 'View') {
		return $this->owner->filterByCallback(function ($item) use ($perm) {
			if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
				return true;
			} else if ($item->hasMethod('can' . $perm)) {
				$method = 'can' . $perm;
				return $item->$method();
			} else {
				return $item->canView();
			}
		});
	}
	
	public function restrictedByID($id, $perm = 'View') {
		$item = $this->owner->byID($id);
		if (!$item) {
			return null;
		}
		
		if ($item->hasExtension('Restrictable') && $item->checkPerm($perm)) {
			return $item;
		} else if ($item->hasMethod('can' . $perm)) {
			$method = 'can' . $perm;
			return $item->$method() ? $item : null;
		} else {
			return $item->canView() ? $item : null;
		}
	}
}
