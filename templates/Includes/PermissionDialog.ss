
<% require javascript(restrictedobjects/javascript/permission-dialog.js) %>
<% require javascript(restrictedobjects/javascript/jquery.tmpl.min.js) %>
<% require css(restrictedobjects/css/permission-dialog.css) %>

<div id="PermissionManagementDialog" class="dialog" title="Permissions">
	<table class="currentAuthorities">
		<thead>
			<tr>
				<th>Name</th>
				<th>Grant</th>
				<th>Perms</th>
			</tr>
		</thead>
		<tbody>
		</tbody>
	</table>
</div>

<script id="PermissionTableRowTemplate" type="text/x-jquery-tmpl">
	<tr data-authority="\${ID}" class="authorityEntry">
		<td>\${DisplayName}</td>
		<td>\${Grant}</td>
		<td>\${PermList}</td>
	</tr>
</script>

<div id="AddAuthorityDialog" class="dialog">
	<form method="post" action="">
		<div>
			<label for="role">Role</label><select name="role"></select> 
			<label for="permission">or permission</label><select name="permission"></select>
		</div>
		<div>
			<label class="userLabel">User email address</label><input name="MemberName" type="text" /> 
			<label class="groupLabel">or enter group</label><input name="GroupName" type="text" />
		</div>
		<div>
			<label for="grantType">With permission</label>
			<select name="grant">
				<option value="GRANT" selected="selected">Grant</option>
				<option value="DENY">Deny</option>
			</select>
		</div>
	</form>
</div>