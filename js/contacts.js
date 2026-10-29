function applyContact(contactID, button) {
	var complete = beginActionRequest('contact-apply', module, controller, [id], $(button));
	if (!complete) return;
	var $dialog = modalWindow.current;
	if ($dialog) $dialog.data('dw-modal-pending', true);
	pushMessages(Dewawi.message('MESSAGES_CONTACT_APPLYING'), 'info', modalWindowMessages($dialog));
	try {
		var contact = getContact(contactID);

		if (!contact) {
			contactActionError($dialog, {ok: false}, 'MESSAGES_CONTACT_APPLY_FAILED');
			return;
		}

		var data = {};
		var readFailed = false;
		var failRead = function () { readFailed = true; };
		var addresses = getRowsByParent('contacts', 'address', contact.id, 'contacts', 'contact', failRead);
		if (readFailed) { contactActionError($dialog, {ok: false}, 'MESSAGES_CONTACT_APPLY_FAILED'); return; }
		var billingAddress = findFirstByType(addresses, 'billing') || addresses[0] || null;
		var shippingAddress = findFirstByType(addresses, 'shipping') || null;

		data.contactid = contact.contactid;
		data.vatin = contact.vatin;
		data.taxfree = Number(contact.taxfree) === 1 ? 1 : 0;

		data.billingname1 = contact.name1;
		data.billingname2 = contact.name2;
		data.billingdepartment = contact.department;
		data.billingstreet = contact.street;
		data.billingpostcode = contact.postcode;
		data.billingcity = contact.city;
		data.billingcountry = contact.country;

		var $contactperson = $('[name="contactpersonid"]');
		if ($contactperson.length) {
			var currentContactNumber = $.trim(String($('[name="contactid"]').val() || ''));
			var selectedContactpersonId = currentContactNumber === String(contact.contactid)
				? String($contactperson.val() || '')
				: '';

			data.contactpersonid = selectedContactpersonId;
			refreshContactpersonOptions(contact.id, selectedContactpersonId, failRead);
			if (readFailed) { contactActionError($dialog, {ok: false}, 'MESSAGES_CONTACT_APPLY_FAILED'); return; }
		}

		if (billingAddress) {
			data.billingname1 = billingAddress.name1 || contact.name1;
			data.billingname2 = billingAddress.name2 || contact.name2;
			data.billingdepartment = billingAddress.department || contact.department;
			data.billingstreet = billingAddress.street;
			data.billingpostcode = billingAddress.postcode;
			data.billingcity = billingAddress.city;
			data.billingcountry = billingAddress.country;
		}

		if (shippingAddress) {
			data.shippingname1 = shippingAddress.name1 || contact.name1;
			data.shippingname2 = shippingAddress.name2 || contact.name2;
			data.shippingdepartment = shippingAddress.department || contact.department;
			data.shippingstreet = shippingAddress.street;
			data.shippingpostcode = shippingAddress.postcode;
			data.shippingcity = shippingAddress.city;
			data.shippingcountry = shippingAddress.country;
			data.shippingphone = shippingAddress.phone || '';
		}

		if (module === 'processes' || module === 'tasks') {
			data.customerid = data.contactid;
			delete data.contactid;
		}

		applyContactDataToForm(data);
		refreshContactInfoList('#phones', contact.phones);
		refreshContactInfoList('#emails', contact.emails);
		refreshContactInfoList('#internets', contact.internets);

		Dewawi.setDirty(true);

		var response = edit(data);

		if (!response || response.ok === false || response.saved === false || response.errors || (response.ok !== true && response.saved !== true)) {
			contactActionError($dialog, response, 'MESSAGES_CONTACT_APPLY_FAILED');
			return;
		}

		modalWindowClose(true);
		if (!$dialog) removeMessages('#messages');
		$('#tabfiles').data('needs-refresh', 1);
	} finally {
		if ($dialog) $dialog.removeData('dw-modal-pending');
		complete();
	}
}

function applyContactDataToForm(data) {
	$.each(data, function (name, value) {
		var $fields = $('[name="' + name + '"]');

		if (!$fields.length) {
			return;
		}

		if ($fields.is(':checkbox')) {
			$fields.prop('checked', Number(value) === 1);
			return;
		}

		$fields.val(value != null ? value : '');
	});
}

function getContact(contactID) {
	var contact = null;

	$.ajax({
		type: 'GET',
		async: false,
		url: baseUrl + '/contacts/contact/get/id/' + contactID,
		dataType: 'json',
		cache: false,
		success: function (response) {
			if (!response || response.ok !== true || !response.item) {
				return;
			}

			// Support the legacy nested response: { ok:true, item:{ ok:true, item:{...} } }
			if (response.item.item) {
				if (response.item.ok !== true) return;
				contact = response.item.item;
				return;
			}

			// Current row response: { ok:true, item:{...} }
			contact = response.item;
		},
		error: function () {
			contact = null;
		}
	});

	return contact && !Array.isArray(contact) && String(contact.id) === String(contactID) && contact.contactid !== undefined ? contact : null;
}

function setValue(selector, value) {
	$(selector).val(value != null ? value : '');
}

function setCheckbox(selector, value) {
	$(selector).prop('checked', Number(value) === 1);
}

function refreshContactInfoList(selector, items) {
	var $target = $(selector);

	if (!$target.length) {
		return;
	}

	$target.empty();

	if (!Array.isArray(items) || !items.length) {
		return;
	}

	$.each(items, function (index, item) {
		if (item == null) return;
		var value = item.value || item.phone || item.email || item.internet || item;

		if (value) {
			if (typeof value === 'string' || typeof value === 'number') $('<div>', {'class': 'dw-list-value'}).text(value).appendTo($target);
		}
	});
}

function getRowsByParent(moduleName, controllerName, parentId, parentModule, parentController, onError) {
	var items = [];

	$.ajax({
		type: 'GET',
		async: false,
		url: baseUrl + '/' + moduleName + '/' + controllerName + '/get/parentid/' + parentId,
		data: {
			parent_module: parentModule,
			parent_controller: parentController
		},
		dataType: 'json',
		cache: false,
		success: function (response) {
			if (response && response.ok === true && Array.isArray(response.items)) items = response.items;
			else if (onError) onError(response);
		},
		error: function (xhr) { if (onError) onError(xhr.responseJSON); }
	});

	return items;
}

function refreshContactpersonOptions(contactId, selectedId, onError) {
	var $select = $('[name="contactpersonid"]');

	if (!$select.length) {
		return;
	}

	var emptyLabel = $select.find('option[value=""]').first().text();
	var failed = false;
	var contactpersons = getRowsByParent('contacts', 'contactperson', contactId, 'contacts', 'contact', function (response) { failed = true; if (onError) onError(response); });
	if (failed) return;

	$select.empty().append($('<option>').val('').text(emptyLabel));

	$.each(contactpersons, function (index, contactperson) {
		$select.append(
			$('<option>').val(contactperson.id).text(contactperson.displayname || '')
		);
	});

	$select.val(String(selectedId || ''));
}

function findFirstByType(items, type) {
	for (var i = 0; i < items.length; i++) {
		if (String(items[i].type || '') === type) {
			return items[i];
		}
	}

	return null;
}

function contactActionError($dialog, response, failureKey) {
	var failed = response && (response.ok === false || response.saved === false || response.errors);
	pushMessages(Dewawi.message(failed ? failureKey : 'MESSAGES_ACTION_STATUS_UNKNOWN'), 'danger', modalWindowMessages($dialog));
}

(function ($) {
	'use strict';

	$(function () {
		if (!window.DewawiToolbar) {
			return;
		}

		DewawiToolbar.actionConfig.tags = { selection: 'multiple' };
		DewawiToolbar.actions.export = function (selection, $button) {
			if (!$('#contactExport').length) {
				this.submitExport();
				return;
			}

			modalWindow('contactExport', undefined, $button);
		};
		DewawiToolbar.actions.tags = function (selection, $button) {
			var $dialog = $('#contactTags');

			if (!$dialog.length || modalWindowBusy(modalWindow.current)) {
				return;
			}

			if (modalWindow.current && modalWindow.current[0] === $dialog[0]) return;
			$dialog.data('contact-ids', selection.ids);
			modalWindow('contactTags', undefined, $button);
		};

		$(document).on('click', '[data-contact-export-profile]', function () {
			var profile = String($(this).data('contact-export-profile') || '');

			if (profile !== 'combined' && profile !== 'companies' && profile !== 'contactpersons') {
				return;
			}

			if (!$('#contactExport').is(':visible') || modalWindowBusy(modalWindow.current)) return;
			modalWindowClose();
			DewawiToolbar.submitExport({ profile: profile });
		});

		$(document).on('click', '[data-contact-tag-operation]', function () {
			var $button = $(this), $dialog = $('#contactTags');
			var operation = String($button.data('contact-tag-operation') || '');
			var tagId = parseInt($dialog.find('[name="bulk-tagid"]').val(), 10);
			var ids = ($dialog.data('contact-ids') || []).map(String).filter(function (id, index, values) { return values.indexOf(id) === index; });
			if ($dialog.data('dw-modal-pending') || (operation !== 'add' && operation !== 'remove')) return;
			if (!tagId || tagId < 1 || !ids.length) { contactActionError($dialog, {ok: false}, 'MESSAGES_TAG_INVALID'); return; }
			var complete = beginActionRequest('contact-tags', 'contacts', 'contact', ids, $button, $dialog.data(operation === 'add' ? 'confirm-add' : 'confirm-remove'));
			if (!complete) return;
			$dialog.data('dw-modal-pending', true);
			pushMessages(Dewawi.message('MESSAGES_TAGS_UPDATING'), 'info', modalWindowMessages($dialog));
			$.ajax({
				type: 'POST', url: Dewawi.url('contacts', 'contact', 'tags'), dataType: 'json', cache: false, timeout: 120000,
				data: {ids: ids, tagid: tagId, operation: operation},
				success: function (response) {
					if (!response || response.ok !== true || response.saved === false || response.errors || response.operation !== operation
						|| typeof response.changed !== 'number' || response.changed < 0 || response.changed % 1 !== 0
						|| typeof response.unchanged !== 'number' || response.unchanged < 0 || response.unchanged % 1 !== 0
						|| response.changed + response.unchanged !== ids.length) {
						contactActionError($dialog, response && (response.ok === false || response.saved === false || response.errors) ? response : null, 'MESSAGES_TAGS_UPDATE_FAILED');
						return;
					}
					var message = Dewawi.message(operation === 'add' ? 'MESSAGES_TAGS_ASSIGNED' : 'MESSAGES_TAGS_REMOVED')
						.replace('%1$d', response.changed).replace('%2$d', response.unchanged);
					modalWindowClose(true);
					search(function () { pushMessages(message, 'success', '#messages'); });
				},
				error: function (xhr) { contactActionError($dialog, xhr.responseJSON, 'MESSAGES_TAGS_UPDATE_FAILED'); },
				complete: function () { $dialog.removeData('dw-modal-pending'); complete(); }
			});
		});

		function setEmailContactpersonMode($dialog, mode) {
			var useExisting = mode === 'existing';
			$dialog.find('[data-email-contactperson-new]').prop('hidden', useExisting);
			$dialog.find('[data-email-contactperson-existing]').prop('hidden', !useExisting);
		}

		$(document).on('click', '[data-email-contactperson-assign]', function () {
			var $button = $(this);
			var $dialog = $('#emailContactpersonAssignment');

			if (!$dialog.length || modalWindowBusy(modalWindow.current)) {
				return;
			}

			if (modalWindow.current && modalWindow.current[0] === $dialog[0]) return;
			$dialog.data('email-id', parseInt($button.data('email-id'), 10) || 0);
			$dialog.find('[data-email-contactperson-address]').text(String($button.data('email-address') || ''));
			$dialog.find('input[type="text"]').val('');
			$dialog.find('[name="salutation"]').val('0');
			$dialog.find('[name="existing_contactpersonid"]').val('');
			$dialog.find('[name="assignment_type"][value="new"]').prop('checked', true);
			setEmailContactpersonMode($dialog, 'new');
			modalWindow('emailContactpersonAssignment', undefined, $button);
		});

		$(document).on('change', '#emailContactpersonAssignment [name="assignment_type"]', function () {
			setEmailContactpersonMode($('#emailContactpersonAssignment'), String(this.value || 'new'));
		});

		$(document).on('click', '[data-email-contactperson-submit]', function () {
			var $button = $(this);
			var $dialog = $('#emailContactpersonAssignment');
			var mode = String($dialog.find('[name="assignment_type"]:checked').val() || 'new');
			var payload = {
				contactid: parseInt($dialog.data('contact-id'), 10) || 0,
				emailid: parseInt($dialog.data('email-id'), 10) || 0,
				assignment_type: mode
			};

			if (mode === 'existing') {
				payload.contactpersonid = parseInt($dialog.find('[name="existing_contactpersonid"]').val(), 10) || 0;
			} else {
				$.each(['salutation', 'title', 'firstname', 'lastname', 'position', 'department'], function (index, name) {
					payload[name] = $dialog.find('[name="' + name + '"]').val() || '';
				});
			}

			if (payload.contactid < 1 || payload.emailid < 1 || (mode !== 'new' && mode !== 'existing')
				|| (mode === 'existing' && payload.contactpersonid < 1) || (mode === 'new' && !$.trim(payload.lastname))) {
				contactActionError($dialog, {ok: false}, 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID');
				return;
			}
			var complete = beginActionRequest('email-contactperson', 'contacts', 'email', [payload.emailid], $button);
			if (!complete) return;
			$dialog.data('dw-modal-pending', true);
			pushMessages(Dewawi.message('MESSAGES_EMAIL_CONTACTPERSON_ASSIGNING'), 'info', modalWindowMessages($dialog));

			$.ajax({
				type: 'POST',
				url: Dewawi.url('contacts', 'email', 'assigncontactperson'),
				timeout: 120000,
				dataType: 'json',
				cache: false,
				data: payload,
				success: function (response) {
					if (!response || response.ok !== true || response.saved === false || response.errors) {
						contactActionError($dialog, response, 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_FAILED');
						return;
					}

					modalWindowClose(true);
					$.cookie('tab', '#tabcontactperson', {
						path: cookiePath + '/edit'
					});
					window.location.reload();
				},
				error: function (xhr) { contactActionError($dialog, xhr.responseJSON, 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_FAILED'); },
				complete: function () {
					$dialog.removeData('dw-modal-pending');
					complete();
				}
			});
		});
	});
})(jQuery);
