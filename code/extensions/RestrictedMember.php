<?php

/**
 * Capture the user being logged in and set them into the security context
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RestrictedMember extends DataExtension
{
    private static $db = array(
        'GroupMembership'        => 'MultiValueField',
    );
    
    public function memberAutoLoggedIn()
    {
        $this->beforeMemberLoggedIn();
    }
    
    public function beforeMemberLoggedIn()
    {
        singleton('SecurityContext')->setMember($this->owner);
    }
    
    public function updateEffectiveParents(&$parents)
    {
        $groups = $this->owner->Groups();
        foreach ($groups as $g) {
            $parents[] = $g;
        }
    }
    
    public function updateCMSFields(\FieldList $fields)
    {
        $fields->removeByName('GroupMembership');
    }
    
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        // we need to capture the current group list. If any of this changes, we need to purge
        // the permission cache
        $currentGroups = $this->owner->GroupMembership->getValues();
        $newGroups = $this->owner->Groups()->column('ID');
        array_walk($newGroups, function (&$value) {
            $value = (int) $value;
        });
        sort($newGroups);

        if ($newGroups != $currentGroups) {
            $this->owner->GroupMembership = $newGroups;
            $this->clearMemberPermissionCache();
        }
    }
    
    public function onUnlinkFromGroup()
    {
        // trigger a write, which will check group memebership, and fix permission caches. 
        $this->owner->write();
    }
    
    public function clearMemberPermissionCache()
    {
        // find all items that the member is directly bound to.
        // do things
        singleton('PermissionService')->clearUserCachedPerms($this->owner->ID);
    }
}
