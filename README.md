# SilverStripe Restricted Objects module

This module changes SilverStripe's object access module to be locked down
as the default case, meaning that by default, there are NO permissions to
an object except those explicitly granted to a user on a node (or tree), with
the exception of admin users, who always have full access to all objects)

The new model of permission access has a few concepts that are slightly
different to the existing model, and draws off the model seen in Alfresco

* Permissions - low level units describing a capability. For example, View, 
  Write, Publish, ChangePermissions. When writing code and you need to check
  permissions, these are the things checked against
* Roles - Groupings of permissions. For example, an Editor role is made up 
  of the View and Edit permissions
* Authority - a user or a group within the system
* Grant - Being explicit about whether a user can or cannot do something 
  (a permission is GRANTed or DENY'd to an authority)

In the existing SilverStripe model, Roles and Permissions are high level 
concepts without any context to the content in the system. They cover
high level permission concepts such as 'access the CMS'. For node specific
control, there is only explicit support for allowing View or Edit permission. 

This new model allows you to specify
much more finely grained access restrictions directly to a node or tree of 
nodes. For example, it is possible to specify that a user can perform the 
Editor role (giving view and write access) in one part of the tree, but 
also Manager access (Editor plus Publish/Delete etc) to another part of the 
tree. 

Additionally, you can explicitly DENY access to a node inside a tree where
the user might already have been granted access at a higher point. 

Finally, the new model supports the concept of a content owner, who has
(almost) unrestricted access to content that they themselves have created 
within areas that they have been allowed to create within. 

## Maintainer Contacts
*  Marcus Nyeholt <marcus@silverstripe.com.au>

## Versions

The master branch of this module is currently aiming for SilverStripe 3.1 compatibility

* [SilverStripe 3.0 compatible version](https://github.com/nyeholt/silverstripe-restrictedobjects/tree/1.0)
* [SilverStripe 2.4 compatible version](https://github.com/nyeholt/silverstripe-restrictedobjects/tree/ss24)


## Requirements
* SilverStripe 3.0+

## Installation

*  Place this directory in the root of your SilverStripe installation. Ensure
   that the folder name is `restrictedobjects`.
*  Run dev/build with the ?disable_perms=1 parameter - in dev mode, permissions
   can be disabled by using this flag, which is needed to ensure things are 
   installed correctly
* Assign the extensions
  
```php
Object::add_extension('Page', 'Restrictable');
Object::add_extension('SiteConfig', 'Restrictable');
Object::add_extension('Page', 'RestrictedPage');
```

* Run dev/build again, login to your system as an admin
* On your "Site" object, make sure the "Allow public access" checkbox is
  enabled so that the site is still viewable.
* Create the following DB index; SS doesn't give a nice way to do this 
  and static $indexes doesn't seem to work anyway...
  * ALTER TABLE `AccessAuthority` ADD INDEX ( `ItemID` , `ItemType` ) ;

## Typical use cases

When would you want to use this model?

From an end user perspective

* You want to manage access to dataobjects with specificly structured 
  permission sets
* You want to grant certain access for specific authorities to one part of 
  your site tree, but not to others, and those permissions are not just 
  'read' or 'write'. 
* You want to define roles that match organisational roles within tree
  structures. 
* You want to be able to deny specific authorities certain permissions 
  within a section of the site tree, while leaving a broad set of permissions
  granted above and below. 

From a developer perspective

* You want to define permissions that are specific to the functionality you
  are managing, using verbs to describe the permissions, and nouns for the 
  role that groups these permissions together. 
* You want to ensure that the code you execute is checking access rights 
  before nodes are accessed or modified
* You want to avoid writing explicit code to manage custom permission
  assignment for your own modules or sites

## Quick start

To manage permissions using the restricted objects module, there are a few 
steps commonly performed

* Navigate to the node (SiteConfig, Page, or custom data object type) that 
  you want permissions applied on. 
* On the Permissions tab, click "Add Access Authority"
* Select the role to give the user. This will give them the permissions
  defined for this role in the Access Roles section of the CMS
* Select which members or groups to grant this role for
* Select whether to GRANT or DENY this permission
* Click "Save"
* Once the permission table refreshes, you will see all the permissions
  granted for that "Authority". 

Some default access roles are automatically created when you install the 
system. These can be accessed via the Access Roles section.
To define new roles, you simply create a new AccessRole item, and select
the permissions you want to use within it. 

Assigning roles are done from the "Permissions" tab of any content item.
You can specify the role to assign for a user or a group, and whether you are
granting or denying that role. You can also choose individual permissions
to apply or revoke. 

To define a new low-level permission item, you must define a class that 
implements PermissionDefiner (not to be confused with the default
SilverStripe interface PermissionProvider) and return a simple array of
strings in the definePermissions() method. These strings should be the 
permissions you check for in `$obj->checkPerm('CustomPerm');`

See the (wiki)[https://github.com/nyeholt/silverstripe-restrictedobjects/wiki]
for more.


