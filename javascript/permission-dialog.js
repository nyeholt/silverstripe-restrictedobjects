
;(function ($) {
	
	var service = 'jsonservice/permission';
	var securityId = $('input[name=SecurityID]').val();
	
	$(function () {

		var mainDialog = $('#PermissionManagementDialog');
		var addPermDialog = $('#AddAuthorityDialog');
		
		var params = {
			SecurityID: securityId
		};
		$.get(service + '/getPermissionDetails', params, function (data) {
			// populate stuff!
			if (data && data.response) {
				var res = data.response;
				var options = $.tmpl('<option value="${Title}">${Title}</option>', res.roles.items);
				$('select[name=role]').append(options);
				var options = $.tmpl('<option value="${$data}">${$data}</option>', res.permissions);
				$('select[name=permission]').append(options);
			}
		});

		// we search for any .permissionManager, and get the info
		$('.permissionManager').livequery(function () {
			var nodeInfo = $(this).data('object');
			
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
				if (data && data.response.items) {
					mainDialog.find('table.currentAuthorities tbody').empty();
					$('#PermissionTableRowTemplate').tmpl(data.response.items).appendTo(mainDialog.find('table.currentAuthorities tbody'));

					var addPermDialogOpts = {
						width: 600,
						height: 400,
						modal: true,
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
						modal: true,
						buttons: [
							{
								text: 'Add',
								click: function () {
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