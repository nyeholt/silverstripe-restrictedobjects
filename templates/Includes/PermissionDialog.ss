
<% require javascript(restrictedobjects/javascript/permission-dialog.js) %>
<% require javascript(restrictedobjects/javascript/jquery.tmpl.min.js) %>
<% require css(restrictedobjects/css/permission-dialog.css) %>

<div id="PermissionManagementDialog" class="dialog">
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
</div>