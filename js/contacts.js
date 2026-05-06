function applyContact(contactID) {
	var contact = getContact(contactID);

	if (!contact) {
		pushMessages(['Contact could not be loaded.']);
		return;
	}

	var data = {};
	var addresses = getRowsByParent('contacts', 'address', contact.id, 'contacts', 'contact');
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

	if (response && response.ok === false) {
		pushMessages([response.message || 'Contact could not be saved.']);
		return;
	}

	modalWindowClose();

	$('#tabfiles').data('needs-refresh', 1);
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
			if (!response || !response.ok || !response.item) {
				return;
			}

			// Support current nested response: { ok:true, item:{ ok:true, item:{...} } }
			if (response.item.item) {
				contact = response.item.item;
				return;
			}

			// Support desired response: { ok:true, item:{...} }
			contact = response.item;
		},
		error: function () {
			contact = null;
		}
	});

	return contact;
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

	if (!items || !items.length) {
		return;
	}

	$.each(items, function (index, item) {
		var value = item.value || item.phone || item.email || item.internet || item;

		if (value) {
			$target.append('<div class="dw-list-value">' + value + '</div>');
		}
	});
}

function getRowsByParent(moduleName, controllerName, parentId, parentModule, parentController) {
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
			if (response && response.ok && response.items) {
				items = response.items;
			}
		}
	});

	return items;
}

function findFirstByType(items, type) {
	for (var i = 0; i < items.length; i++) {
		if (String(items[i].type || '') === type) {
			return items[i];
		}
	}

	return null;
}
