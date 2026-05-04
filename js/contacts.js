function applyContact(contactID) {
	var contact = getContact(contactID);
	var data = {};

	if (!contact) {
		pushMessages(['Kontakt konnte nicht geladen werden.']);
		return;
	}

	var addresses = getRowsByParent('contacts', 'address', contact.id, 'contacts', 'contact');
	var billingAddress = findFirstByType(addresses, 'billing') || addresses[0] || null;
	var shippingAddress = findFirstByType(addresses, 'shipping') || null;

	setValue('#taboverview #contactid, #tabcustomer #contactid, #contactid', contact.contactid);
	setValue('#taboverview #customerid, #tabcustomer #customerid, #customerid', contact.contactid);

	if (billingAddress) {
		setValue('#billingname1', billingAddress.name1 || contact.name1);
		setValue('#billingname2', billingAddress.name2 || contact.name2);
		setValue('#billingdepartment', billingAddress.department || contact.department);
		setValue('#billingstreet', billingAddress.street);
		setValue('#billingpostcode', billingAddress.postcode);
		setValue('#billingcity', billingAddress.city);
		setValue('#billingcountry', billingAddress.country);

		data.billingname1 = billingAddress.name1 || contact.name1;
		data.billingname2 = billingAddress.name2 || contact.name2;
		data.billingdepartment = billingAddress.department || contact.department;
		data.billingstreet = billingAddress.street;
		data.billingpostcode = billingAddress.postcode;
		data.billingcity = billingAddress.city;
		data.billingcountry = billingAddress.country;
	}

	setCheckbox('#taboverview #taxfree, #tabcustomer #taxfree, #taxfree', contact.taxfree);
	setValue('#contactinfo', contact.info);

	data.contactid = contact.contactid;
	data.billingname1 = contact.name1;
	data.billingname2 = contact.name2;
	data.billingdepartment = contact.department;
	data.billingstreet = contact.street;
	data.billingpostcode = contact.postcode;
	data.billingcity = contact.city;
	data.billingcountry = contact.country;
	data.vatin = contact.vatin;
	data.taxfree = Number(contact.taxfree) === 1 ? 1 : 0;

	data.contactid = contact.contactid;
	data.vatin = contact.vatin;
	data.taxfree = Number(contact.taxfree) === 1 ? 1 : 0;

	if (billingAddress) {
		data.billingname1 = billingAddress.name1 || contact.name1;
		data.billingname2 = billingAddress.name2 || contact.name2;
		data.billingdepartment = billingAddress.department || contact.department;
		data.billingstreet = billingAddress.street;
		data.billingpostcode = billingAddress.postcode;
		data.billingcity = billingAddress.city;
		data.billingcountry = billingAddress.country;
	} else {
		data.billingname1 = contact.name1;
		data.billingname2 = contact.name2;
		data.billingdepartment = contact.department;
	}

	refreshContactInfoList('#phones', contact.phones);
	refreshContactInfoList('#emails', contact.emails);
	refreshContactInfoList('#internets', contact.internets);

	if (module === 'processes' || module === 'tasks') {
		data.customerid = data.contactid;
		delete data.contactid;
	}

	Dewawi.setDirty(true);

	var response = edit(data);

	if (response && response.ok === false) {
		pushMessages([response.message || 'Kontakt konnte nicht gespeichert werden.']);
		return;
	}

	modalWindowClose();

	$('#tabfiles').data('needs-refresh', 1);
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
