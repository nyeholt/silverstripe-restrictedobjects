# SilverStripe Restricted Objects module

This module changes SilverStripe's object access module to be locked down
as the default case, meaning that by default, there are NO permissions to
an object except those explicitly granted to a user to a node (or tree)

The new model of permission access has a few concepts that are slightly
different to the existing model, and draws off the model seen in Alfresco

* Permissions - low level units describing a capability. For example, View, 
  Write, Publish, ChangePermissions. When writing code and you need to check
  permissions, these are the things checked against
* Roles - Groupings of permissions. For example, an Editor role is made up 
  of the View and Edit permissions

The current model allows you to grant a role, made up of high level permission 
concepts such as 'access the CMS', to a group on a site-wide basis, OR allow 
a group edit/view access on a node. This new model allows you to specify
much more fine grained access restrictions directly to a node or tree of 
nodes, for example specifying a user can perform the Editor role (giving view
and write access) to one part of the tree, but also Manager access (Editor plus
Publish/Delete etc) to another part of the tree. 

Additionally, you can explicitly DENY access to a node inside a tree where
the user might already have been granted access at a higher point. 


## Maintainer Contacts
*  Marcus Nyeholt <marcus@silverstripe.com.au>

## Requirements
* SilverStripe 2.4+

## Installation
*  Place this directory in the root of your SilverStripe installation. Ensure
   that the folder name is `restrictedobjects`.
*  Regenerate the manifest cache by visiting any page on your site with the
   `?flush` URL parameter set.
* Assign the extensions
  
```php
Object::add_extension('Page', 'Restrictable');
Object::add_extension('SiteConfig', 'Restrictable');
Object::add_extension('Page', 'RestrictedPage');
```

## Usage Overview
