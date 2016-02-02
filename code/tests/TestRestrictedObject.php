<?php

/**
 * Description of TestRestrictedObject
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestRestrictedObject extends SapphireTest
{

    protected $extraDataObjects = array(
        'PrivateObject',
    );
    
    public function setUpOnce()
    {
        parent::setUpOnce();
        Restrictable::set_enabled(false);
        
        BasicAuth::protect_entire_site(false);
        
        // needs to be done this way to work around SS bug
//		include_once dirname(dirname(__FILE__)).'/extensions/Restrictable.php';
//		Object::add_extension('PrivateObject', 'Restrictable');
    }
    
    public function setUp()
    {
        Restrictable::set_enabled(false);
        parent::setUp();
        
        Restrictable::set_enabled(true);
        singleton('PermissionService')->getCache()->clean('all');
        
        $instance = singleton('AccessRole');
        if (method_exists($instance, 'requireDefaultRecords')) {
            $instance->requireDefaultRecords();
        }
    }
    
    public function testGrant()
    {
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
    
    public function testCheckPerm()
    {
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
    
    public function testInheritedPermissions()
    {
        $svc = singleton('PermissionService');
        /* @var $svc PermissionService */
        
        Restrictable::set_enabled(false);
        $this->logInWithPermission('ADMIN');
        Restrictable::set_enabled(true);
        
        $user = $this->cache_generatedMembers['ADMIN'];
        
        Restrictable::set_enabled(false);
        $this->logInWithPermission('USERONE');
        Restrictable::set_enabled(true);
        
        $user1 = $this->cache_generatedMembers['USERONE'];
        
        Restrictable::set_enabled(false);
        $this->logInWithPermission('USERTWO');
        Restrictable::set_enabled(true);
        
        $user2 = $this->cache_generatedMembers['USERTWO'];
        
        Restrictable::set_enabled(false);
        $this->logInWithPermission('ADMIN');
        Restrictable::set_enabled(true);
        
        $item = array();
        
        $item[] = $o = new PrivateObject();
        $o->Title = 'Treetop';
        $o->write();
        
        $item[] = $o = new PrivateObject();
        $o->Title = 'Left branch';
        $o->ParentID = $item[0]->ID;
        $o->write();
        
        $item[] = $o = new PrivateObject();
        $o->ParentID = $item[0]->ID;
        $o->Title = 'Right branch';
        $o->write();
        
        // 3
        $item[] = $o = new PrivateObject();
        $o->Title = 'Lgk1';
        $o->ParentID = $item[1]->ID;
        $o->write();
        // 4
        $item[] = $o = new PrivateObject();
        $o->ParentID = $item[1]->ID;
        $o->Title = 'Lgk2';
        $o->write();
        
        // 5
        $item[] = $o = new PrivateObject();
        $o->Title = 'Rgk1';
        $o->ParentID = $item[2]->ID;
        $o->write();
        // 6
        $item[] = $o = new PrivateObject();
        $o->ParentID = $item[2]->ID;
        $o->Title = 'Rgk2';
        $o->write();
        
        $this->assertFalse($item[0]->checkPerm('View', $user1));
        $this->assertFalse($item[0]->checkPerm('View', $user2));
        
        $this->assertFalse($o->checkPerm('View', $user1));
        $this->assertFalse($o->checkPerm('View', $user2));
        
        $item[0]->grant('Manager', $user1);
        $item[2]->grant('Editor', $user2);
        
        // user1 can view all
        foreach ($item as $i) {
            $this->assertTrue($i->checkPerm('View', $user1));
            $this->assertTrue($svc->checkRole($i, 'Manager', $user1));
        }
        
        // user2 not so much
        $this->assertTrue($svc->checkPerm($item[5], 'View', $user2));
        $this->assertTrue($svc->checkPerm($item[6], 'View', $user2));
        $this->assertTrue($svc->checkRole($item[5], 'Editor', $user2));
        $this->assertTrue($svc->checkRole($item[6], 'Editor', $user2));
        
        $this->assertFalse($item[0]->checkPerm('View', $user2));
        $this->assertFalse($item[3]->checkPerm('View', $user2));
        $this->assertFalse($item[4]->checkPerm('View', $user2));
        
        $svc->removePermissions($item[2], 'Editor', $user2);
        
        $this->assertFalse($svc->checkPerm($item[5], 'View', $user2));
        $this->assertFalse($svc->checkPerm($item[6], 'View', $user2));
        
        $item[0]->grant('Editor', $user2);
        
        // re-affirm 'can'
        $this->assertTrue($svc->checkPerm($item[5], 'View', $user2));
        
        $this->assertTrue($svc->checkPerm($item[4], 'View', $user2));
        $this->assertTrue($svc->checkPerm($item[3], 'View', $user2));
        $this->assertTrue($svc->checkPerm($item[1], 'View', $user2));
        
        // now try deny in between
        $item[1]->deny('View', $user2);
        $this->assertFalse($item[3]->checkPerm('View', $user2));
        $this->assertFalse($item[4]->checkPerm('View', $user2));
    }
    
    public function testOwnership()
    {
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
    
    public function testPagination()
    {
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
    
    public function testSimpleMemberList()
    {
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
    
    public function testGroupInheritedPermission()
    {
        Restrictable::set_enabled(false);
        $this->logInWithPermission('ADMIN');
        Restrictable::set_enabled(true);
        
        $user = $this->cache_generatedMembers['ADMIN'];
        $item = new PrivateObject();
        $item->Title = 'testagain item';
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
        
        $group1 = Group::create(array(
            'Title'        => 'Group1'
        ));
        $group1->write();
        
        $group2 = Group::create(array(
            'Title'        => 'Group2',
            'ParentID'    => $group1->ID,
        ));
        $group2->write();
        
        // grant to group 1
        $item->grant('View', $group1);

        $otherUser->Groups()->add($group1);
        $otherUser->write();
        singleton('PermissionService')->flushCache();
        
        
        $can = $item->checkPerm('View', $otherUser);
        
        $this->assertTrue($can);
        
        
        // remove the user
        $otherUser->Groups()->remove($group1);
        $otherUser->write();
        singleton('PermissionService')->flushCache();
        
        $can = $item->checkPerm('View', $otherUser);
        
        $this->assertFalse($can);
        
        $otherUser->Groups()->add($group2);
        $otherUser->write();
        singleton('PermissionService')->flushCache();
        
        $can = $item->checkPerm('View', $otherUser);
        $this->assertTrue($can);
        
        $group2->ParentID = 0;
        $group2->write();
        singleton('PermissionService')->flushCache();
        
        $can = $item->checkPerm('View', $otherUser);
        $this->assertFalse($can);
    }
}

class PrivateObject extends DataObject implements TestOnly
{

    public static $db = array(
        'Title' => 'Varchar',
    );

    public static $has_one = array(
        'Parent'            => 'PrivateObject',
    );
    
    public static $extensions = array(
        'Restrictable',
    );
}

class PrivateChildObject extends DataObject implements TestOnly
{
    public static $has_one = array(
        'Parent'            => 'PrivateObject',
    );
}
