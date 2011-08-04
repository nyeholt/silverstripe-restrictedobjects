
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
	<tr>
		<td>\${DisplayName}</td>
		<td>\${Grant}</td>
		<td>\${PermList}</td>
	</tr>
</script>

<div id="AddAuthorityDialog" class="dialog">
	<form method="post" action="">
		<div>
			<select name="role"></select> OR <select name="permission"></select>
		</div>
		<div>
			<select name="member"></select> OR <select name="group"></select>
		</div>
		<div>
			<select name="grant">
				<option value="GRANT" selected="selected">Grant</option>
				<option value="DENY">Deny</option>
			</select>
		</div>
	</form>
</div>