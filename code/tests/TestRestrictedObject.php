<?php

/**
 * Description of TestRestrictedObject
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestRestrictedObject extends SapphireTest {

	protected $extraDataObjects = array(
		'PrivateObject',
	);
	
	public function setUpOnce() {
		parent::setUpOnce();
		Restrictable::set_enabled(false);
		
		// needs to be done this way to work around SS bug
//		include_once dirname(dirname(__FILE__)).'/extensions/Restrictable.php';
//		Object::add_extension('PrivateObject', 'Restrictable');
	}
	
	public function setUp() {
		Restrictable::set_enabled(false);
		parent::setUp();
		
		Restrictable::set_enabled(true);
		singleton('PermissionService')->getCache()->clean('all');
		
		$instance = singleton('AccessRole');
		if (method_exists($instance, 'requireDefaultRecords')) $instance->requireDefaultRecords();
	}
	
	public function testGrant() {
		Restrictable::set_enabled(false);
		$user = $this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		$item = new PrivateObject();
		$item->Title = 'Test item';
		$item->write();
		
		$item->grant('Manager', Member::currentUser());
		
		$authorities = $item->getAuthorities();

		$this->assertTrue($authorities != null && $authorities->Count() > 0);
	}
	
	public function testCheckPerm() {
		Restrictable::set_enabled(false);
		$this->logInWithPermission('OTHERUSER');
		Restrictable::set_enabled(true);
		
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		$user = $this->cache_generatedMembers['ADMIN'];
		
		$item = new PrivateObject();
		$item->Title = 'testCan item ';
		$item->write();

		$item->grant('Manager', Member::currentUser());
		$item->grant('Manager', $otherUser);

		$this->logInWithPermission('OTHERUSER');
		
		$can = $item->checkPerm('View');
		$this->assertTrue($can);

		// triggers the cached lookup
		$can = $item->checkPerm('View');
		$this->assertTrue($can);
		
		$can = singleton('PermissionService')->checkRole($item, 'Manager');
		$this->assertTrue($can);
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		// try inherited items
		$otherItem = new PrivateObject();
		$otherItem->Title = 'Private child object';
		$otherItem->ParentID = $item->ID;
		$otherItem->write();
		
		$this->logInWithPermission('OTHERUSER');
		$can = $otherItem->checkPerm('View');
		
		$this->assertTrue($can);
		
		$this->assertTrue($otherItem->checkPerm('Write'));
		
		$this->assertTrue($otherItem->checkPerm('Publish'));
		
		$this->assertFalse($otherItem->checkPerm('Configure'));

		$otherItem->deny('Write', Member::currentUser());
		$this->assertFalse($otherItem->checkPerm('Write'));
		
		// now deny in the item we're inheriting from 
		$item->deny('UnPublish', Member::currentUser());
		$this->assertFalse($otherItem->checkPerm('UnPublish'));
		
		// but can still edit at that level
		$this->assertTrue($otherItem->checkPerm('Publish'));
		
		// now try just deleting the permission
		singleton('PermissionService')->removePermissions($item, 'Publish', Member::currentUser());
		$this->assertFalse($item->checkPerm('Publish'));
	}
	
	function testOwnership() {
		Restrictable::set_enabled(false);
		$this->logInWithPermission('OTHERUSER');
		Restrictable::set_enabled(true);
		
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('NONADMIN');
		Restrictable::set_enabled(true);
		
		$user = $this->cache_generatedMembers['NONADMIN'];
		
		$item = new PrivateObject();
		$item->Title = 'testCan item ';
		$item->write();

		$this->assertTrue($item->OwnerID == $user->ID);
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('OTHERUSER');
		Restrictable::set_enabled(true);
		
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];

		// need to reload $item here so that ->original is stored properly
		// otherwise it assumes we meant to do this all in the one user 
		// request. 
		$item = DataObject::get_by_id('PrivateObject', $item->ID);
		$item->OwnerID = $otherUser->ID;
		try {
			$item->write();
			$this->assertTrue(false);
		} catch (PHPUnit_Framework_ExpectationFailedException $fe) {
			throw $fe;
		} catch (Exception $e) {
			// this should fail
			$this->assertTrue(true);
		}
		
		
	}	
	
	public function testPagination() {
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('OTHERUSER');
		Restrictable::set_enabled(true);
		
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		$user = $this->cache_generatedMembers['ADMIN'];
		
		$security = singleton('SecurityContext');
		
		$not = array();
		$granted = array();
		
		for ($i = 0; $i < 100; $i++) {
			$item = new PrivateObject();
			$item->Title = 'Pagination item ' . $i;
			$item->write();
			
			if ($i % 5 === 0) {
				$item->grant('View', $otherUser);
				$granted[] = $item;
			} else {
				$not[] = $item;
			}
		}
		
		$this->logInWithPermission('OTHERUSER');
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		$can = $granted[0]->checkPerm('View');
		$cant = $not[0]->checkPerm('View');
		
		$list = PrivateObject::get()->sort('ID DESC')->limit(10)->restrict();
		
		$this->assertEquals(10, $list->count());
	}
	
	public function testSimpleMemberList() {
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		$user = $this->cache_generatedMembers['ADMIN'];
		$item = new PrivateObject();
		$item->Title = 'testCan item ';
		$item->write();

		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('OTHERUSER');
		Restrictable::set_enabled(true);
		
		$otherUser = $this->cache_generatedMembers['OTHERUSER'];
		
		$can = $item->checkPerm('View');
		$this->assertFalse($can);
		
		Restrictable::set_enabled(false);
		$this->logInWithPermission('ADMIN');
		Restrictable::set_enabled(true);
		
		// grant the new list permissions 
		$list = SimpleMemberList::create(array('Title' => 'test simple list'));
		$list->write();
		
		$item->grant('View', $list);
		
		$list->Members()->add($otherUser);
		
		$this->logInWithPermission('OTHERUSER');
		
		$can = $item->checkPerm('View');
		$this->assertTrue($can);
		
	}
}

class PrivateObject extends DataObject implements TestOnly {

	public static $db = array(
		'Title' => 'Varchar',
	);

	public static $has_one = array(
		'Parent'			=> 'PrivateObject',
	);
	
	public static $extensions = array(
		'Restrictable',
	);
}

class PrivateChildObject extends DataObject implements TestOnly {
	public static $has_one = array(
		'Parent'			=> 'PrivateObject',
	);
}