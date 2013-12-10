<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class RestrictedList extends DataList {
	
	/**
	 * The permission / role being checked for the data list items
	 *
	 * @var string 
	 */
	private $requiredPermission = 'View';

	/**
	 * Set the required permission
	 * 
	 * @param string $roleOrPerm
	 * @return \RestrictedList
	 */
	public function requirePerm($roleOrPerm) {
		$this->requiredPermission = $roleOrPerm;
		return $this;
	}

	public function toArray() {
		$query = $this->dataQuery->query();
		$rows = $query->execute();
		$results = array();
		
		foreach($rows as $row) {
			$item = $this->createDataObject($row);
			if ($item->hasExtension('Restrictable') && $item->checkPerm($this->requiredPermission)) {
				$results[] = $item;
			} else if ($item->canView()) {
				$results[] = $item;
			}
		}
		
		// TODO if we don't have enough, we need to query again, but
		// we'll look at that later...
		
		return $results;
	}
}
