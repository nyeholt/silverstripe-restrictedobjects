
;(function ($) {
	
	var service = 'jsonservice/permission/';
	var securityId = $('input[name=SecurityID]').val();
	
	$(function () {

		var mainDialog = $('#PermissionManagementDialog');
		var addPermDialog = $('#AddPermissionDialog');

		// we search for any .permissionManager, and get the info
		$('.permissionManager').each(function () {
			var nodeInfo = $(this).data('data-object');
			
			$(this).click (function () {
				initialiseDialog(nodeInfo);
			})
		})

		function initialiseDialog(nodeInfo) {
			var params = {
				SecurityID: securityId, 
				nodeID: nodeInfo.ID, 
				nodeType: nodeInfo.Type
			}

			$.get(service + '/getPermissionsFor', params, function (data) {
				if (data && data.items) {
					mainDialog.find('table.currentAuthorities tbody').empty();
					$('#PermissionTableRowTemplate').tmpl(data.items).appendTo(mainDialog.find('table.currentAuthorities tbody'));
					
					var addPermDialogOpts = {
						width: 600,
						height: 400,
						modal: false,
						buttons: [
							{
								text: 'Ok',
								click: function () {
									$(this).dialog('close');
								}
							},
							{
								text: 'Cancel',
								click: function () {
									$(this).dialog('close');
								}
							}
						]
					};
					
					mainDialog.dialog({
						width: 600,
						height: 400,
						modal: false,
						buttons: [
							{
								text: 'Add',
								click: function () {
									alert(addPermDialog);
									addPermDialog.dialog(addPermDialogOpts);
								}
							},
							{
								text: 'Ok',
								click: function () {
									// save
									$(this).dialog('close');
								}
							}
						]
					})
				}
			});
		}
	});
})(jQuery);