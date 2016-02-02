<?php

/**
 * Extension to enable the overriding of the grid field delete-relation
 * action so that user removal from a group will trigger a permission
 * purge
 * 
 * @author marcus
 */
class RestrictedGroupExtension extends DataExtension
{
    public function updateCMSFields(FieldList $fields)
    {
        $grid = $fields->dataFieldByName('Members');
        if ($grid) {
            $grid->getConfig()->removeComponentsByType('GridFieldDeleteAction');
            $grid->getConfig()->addComponent(new MemberGroupDeleteAction(true));
        }
    }
    
    public function onBeforeWrite()
    {
        // if we're moving groups around, we need to just get rid of all cached stuff, as
        // it's too expensive to try and figure out what is what. 
        if ($this->owner->ID && $this->owner->isChanged('ParentID')) {
            singleton('PermissionService')->purgePermissionCache();
        }
    }
}

class MemberGroupDeleteAction extends GridFieldDeleteAction
{
    public function handleAction(\GridField $gridField, $actionName, $arguments, $data)
    {
        $item = $gridField->getList()->byID($arguments['RecordID']);
        if (!$item) {
            return;
        }
        
        parent::handleAction($gridField, $actionName, $arguments, $data);
        
        $item->extend('onUnlinkFromGroup', $gridField);

        $group = $gridField->getForm()->getRecord();
        if ($group) {
            $group->updateGroupCache();
        }
    }
}
