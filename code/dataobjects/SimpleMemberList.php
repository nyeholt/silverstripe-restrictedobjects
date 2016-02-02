<?php

/**
 * A generic list of members. Essentially a group without any parent
 * inheritance, that removes management of user lists away from 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class SimpleMemberList extends DataObject implements ListOfMembers
{
    private static $db = array(
        'Title'        => 'Varchar(255)',
        
    );

    private static $many_many = array(
        'Members'        => 'Member',
    );
    
    public function getAllMembers()
    {
        return $this->Members();
    }
}
