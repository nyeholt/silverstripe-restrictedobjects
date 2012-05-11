<?php

/**
 * A datalist that filters the allowed result set
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedDataList extends DataList {
	
	protected $requiredPermission = 'View';
	
	public function __construct($dataClass, $requiredPermission = 'View') {
		parent::__construct($dataClass);
		$this->requiredPermission = $requiredPermission;
	}

	/**
	 * Create a DataObject from the given SQL row
	 * 
	 * @param array $row
	 * @return DataObject
	 */
	protected function createDataObject($row) {
		$defaultClass = $this->dataClass;

		// Failover from RecordClassName to ClassName
		if(empty($row['RecordClassName'])) {
			$row['RecordClassName'] = $row['ClassName'];
		}
		
		// Instantiate the class mentioned in RecordClassName only if it exists, otherwise default to $this->dataClass
		if(class_exists($row['RecordClassName'])) {
			$item = new $row['RecordClassName']($row, false, $this->model);
		} else {
			$item = new $defaultClass($row, false, $this->model);
		}
		
		if ($item->hasExtension('Restrictable')) {
			return $item->checkPerm($this->requiredPermission) ? $item : null;
		}

		return $item;
	}
}
