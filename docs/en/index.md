# Restricted objects module


This module is based around the idea that code such as the following


	DataObject::get('MyObject');


is inherently insecure because it relies on the end-developer explicitly
filtering the result set manually. This is similar in one respect to the problems
of SQL injection, CSRF and XSS vulnerabilities - a framework should protect
its end users from these problems as much as possible, and NOT rely on 
its end users (in a framework, end users being developers) to always do the 
Right Thing. 

Secondly, it tries to address the problem that within Sapphire, writing code 
that does permission management at the moment is a very manual, inconsistent 
process. The CMS itself is a good example of this - it has explicit separate 
content relationships to manage access (ViewerGroups, EditorGroups etc) which
are incompatible with other content types (eg File, custom data objects) and
(I find from talking with clients) confusing in their behaviour. In
addition to this, Sapphire has another layer of permission definition with the
sectional access rights (eg Access to all CMS sections) which in some cases
override content permissions and other times don't. This module solves the 
first issue of permission management on dataobjects, leaving the sectional
access rights as they currently exist. As well as the capability
of granting access to content trees, it allows the explicit denial of access 
for specific subsets (or individual) users that might have access granted
at a higher level. 

Finally, it introduces the concept of content ownership to the system, whereby
someone who is the owner of a piece of content always has a specific set of
permissions to content they own (but not the ability to publish it). 

The basis of the module is that all code should be written with the idea that
that permission checks be made against low level permissions such as



	class DefaultPermissions implements PermissionDefiner {
		public function definePermissions() {
			return array(
				'View',
				'Write',
				'Delete',
				'CreateChildren',
				'Publish',
				'UnPublish',
				'ViewPermissions',
				'ChangePermissions',
				'DeletePermissions',
				'TakeOwnership',
				'Configure',
			);
		}
	}

CMS administrators then have the ability to bundle these low level permissions 
into meaningful roles which can be applied to specific sections of the site.
For example, an "Editor" role might only be able to View, Write and 
CreateChildren, whereas a Manager role might have permissions. A key point
though is that these roles can be applied to different trees of a site, meaning
a user can have the permssions given to a Manager in one tree, but no access
to another part of the tree. 


The module provides several different layers to help address these issues

* Code wrappers around `DataObject::get*` methods to automatically provide
  relevant filtering
* Data object extension to automatically add some low level permission checks
  for certain interactions, eg canView, canWrite, canPublish, canAddChildren
* API for executing code as another user (eg to be able to run a codeblock as
  an admin when you as a developer know that it needs elevated permissions)
* API for defining field level editing restrictions - that are actually
  checked before writing the object.
* Simple mechanism for defining new low level permissions
* Interface for creating and managing roles
* Interface for applying and managing permissions within the tree

