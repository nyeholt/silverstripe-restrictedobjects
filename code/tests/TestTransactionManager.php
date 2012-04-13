<?php

/**
 * Description of TestTransactionManager
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestTransactionManager extends SapphireTest {
	protected $extraDataObjects = array(
		'TransTestObj',
	);
	
	public function setUpOnce() {
		parent::setUpOnce();
		Restrictable::set_enabled(true);
	}

	public function testRunAs() {
		Restrictable::set_enabled(false);
		$this->logInWithPermission('FIRST');
		Restrictable::set_enabled(true);
		
		$first = $this->cache_generatedMembers['FIRST'];
		
		// cerate an object, it should be by the second user
		$item = new TransTestObj();
		$item->Title = 'By first';
		$item->write();
		$this->assertTrue($item->OwnerID == $first->ID);
				
		$this->loginWithPermission('SECOND');
		$second = $this->cache_generatedMembers['SECOND'];
		
		$other = new TransTestObj();
		$other->Title = 'By second';
		$other->write();
		$this->assertTrue($other->OwnerID == $second->ID);
		
		// k, so now, if we try writing to the first there should be an exception
		$item->Title = 'changed by second';
		try {
			$item->write();
			$this->assertFalse(true);
		} catch (PermissionDeniedException $pde) {
			$this->assertTrue(true);
		}
		
		$tm = singleton('TransactionManager');
		
		// currently logged in as $second, want to do something as $first without
		// touching the Session settings
		$this->assertTrue(singleton('SecurityContext')->getMember()->ID == $second->ID);
		$tm->run(array($this, 'updateAsOther'), $first, $item);
		$item = DataObject::get_by_id('TransTestObj', $item->ID);
		$this->assertEquals($item->Title, 'changed by second in subfunc');
		
		$tm->run(function () use ($item) {
			$item->Title = 'changed by second again';
			$item->write();
		}, $first);
		
		$item = DataObject::get_by_id('TransTestObj', $item->ID);
		$this->assertEquals($item->Title, 'changed by second again');
	}
	
	public function updateAsOther($item) {
		$item->Title = 'changed by second in subfunc';
		$item->write();
	}
}

class TransTestObj extends DataObject implements TestOnly {
	public static $db = array(
		'Title'		=> 'Varchar',
	);
	public static $extensions = array('Restrictable');
}