
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
				$('select[name=role]').append('<option></option>').append(options);
				var options = $.tmpl('<option value="${$data}">${$data}</option>', res.permissions);
				$('select[name=permission]').append('<option></option>').append(options);
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
			
			var addPermDialogOpts = {
				width: 600,
				height: 400,
				modal: true,
				buttons: [
					{
						text: 'Ok',
						click: function () {
							var grantParams = $.extend({}, params);
							var role = addPermDialog.find('select[name=role]').val();
							var perm = addPermDialog.find('select[name=permission]').val();
							
							if (!role && !perm) {
								addPermDialog.find('select[name=role]').css('border-color', 'red');
								return;
							}
							
							addPermDialog.find('select[name=role]').css('border-color', 'none');

							grantParams.perm = role ? role : perm;
							grantParams.email = addPermDialog.find('input[name=MemberName]').val();
							grantParams.group = addPermDialog.find('input[name=GroupName]').val();

							$.post(service + '/grantTo', grantParams, function (data) {
								if (data && data.response) {
									if (data.response.status === false && data.response.message) {
										alert(data.response.message);
									} else {
										console.log(data.response);
										$(this).dialog('close');
										loadMainDialog();
									}
								}
							})
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
			
			loadMainDialog();
			
			function loadMainDialog() {
				$.get(service + '/getPermissionsFor', params, function (data) {
					if (data && data.response) {
						mainDialog.find('table.currentAuthorities tbody').empty();
						$('#PermissionTableRowTemplate').tmpl(data.response.items).appendTo(mainDialog.find('table.currentAuthorities tbody'));
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

			
		}
	});
})(jQuery);