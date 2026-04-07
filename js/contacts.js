function applyContact(contactID) {
	var contact = getContact(contactID);
	var data = {};

	if (!contact) {
		return;
	}

	setValue('#taboverview #contactid, #tabcustomer #contactid', contact.contactid);
	setValue('#taboverview #customerid, #tabcustomer #customerid', contact.contactid);

	setValue('#taboverview #billingname1, #tabcustomer #billingname1', contact.name1);
	setValue('#taboverview #billingname2, #tabcustomer #billingname2', contact.name2);
	setValue('#taboverview #billingstreet, #tabcustomer #billingstreet', contact.street);
	setValue('#taboverview #billingdepartment, #tabcustomer #billingdepartment', contact.department);
	setValue('#taboverview #billingpostcode, #tabcustomer #billingpostcode', contact.postcode);
	setValue('#taboverview #billingcity, #tabcustomer #billingcity', contact.city);
	setValue('#taboverview #billingcountry, #tabcustomer #billingcountry', contact.country);
	setCheckbox('#taboverview #taxfree, #tabcustomer #taxfree', contact.taxfree);
	setValue('#contactinfo', contact.info);

	data.contactid = contact.contactid;
	data.billingname1 = contact.name1;
	data.billingname2 = contact.name2;
	data.billingstreet = contact.street;
	data.billingdepartment = contact.department;
	data.billingpostcode = contact.postcode;
	data.billingcity = contact.city;
	data.billingcountry = contact.country;
	data.taxfree = contact.taxfree ? 1 : 0;

	if (contact.shippingname1) {
		setValue('#shippingname1', contact.shippingname1);
		setValue('#shippingname2', contact.shippingname2);
		setValue('#shippingdepartment', contact.shippingdepartment);
		setValue('#shippingstreet', contact.shippingstreet);
		setValue('#shippingpostcode', contact.shippingpostcode);
		setValue('#shippingcity', contact.shippingcity);
		setValue('#shippingcountry', contact.shippingcountry);
		setValue('#shippingphone', contact.shippingphone);

		data.shippingname1 = contact.shippingname1;
		data.shippingname2 = contact.shippingname2;
		data.shippingdepartment = contact.shippingdepartment;
		data.shippingstreet = contact.shippingstreet;
		data.shippingpostcode = contact.shippingpostcode;
		data.shippingcity = contact.shippingcity;
		data.shippingcountry = contact.shippingcountry;
		data.shippingphone = contact.shippingphone;
	} else if (controller === 'deliveryorder') {
		setValue('#shippingname1', contact.name1);
		setValue('#shippingname2', contact.name2);
		setValue('#shippingdepartment', contact.department);
		setValue('#shippingstreet', contact.street);
		setValue('#shippingpostcode', contact.postcode);
		setValue('#shippingcity', contact.city);
		setValue('#shippingcountry', contact.country);

		data.shippingname1 = contact.name1;
		data.shippingname2 = contact.name2;
		data.shippingdepartment = contact.department;
		data.shippingstreet = contact.street;
		data.shippingpostcode = contact.postcode;
		data.shippingcity = contact.city;
		data.shippingcountry = contact.country;
	}

	refreshContactInfoList('#phones', contact.phones);
	refreshContactInfoList('#emails', contact.emails);
	refreshContactInfoList('#internets', contact.internets);

	modalWindowClose();

	if (module === 'processes' || module === 'tasks') {
		data.customerid = data.contactid;
		delete data.contactid;
	}

	isDirty = true;
	edit(data);

	$('#tabfiles iframe').each(function () {
		$(this).attr('src', $(this).attr('src'));
	});

	$('#tabfiles #messages').hide();
	$('#tabfiles iframe').show();
}

function setValue(selector, value) {
	$(selector).val(value != null ? value : '');
}

function setCheckbox(selector, value) {
	$(selector).prop('checked', Number(value) === 1);
}

function refreshContactInfoList(selector, value) {
	var $target = $(selector);

	if (!$target.length) {
		return;
	}

	$target.empty();

	if (!value) {
		return;
	}

	var items = String(value).split(',');

	for (var i = 0; i < items.length; i++) {
		var item = $.trim(items[i]);

		if (item !== '') {
			$target.append('<label>' + item + '</label><br>');
		}
	}
}

function getContact(contactID) {
	var data = null;

	$.ajax({
		type: 'POST',
		async: false,
		url: baseUrl + '/contacts/contact/get/id/' + contactID,
		cache: false,
		dataType: 'json',
		success: function (response) {
			data = response;
		},
		error: function (xhr) {
			console.log('getContact failed', xhr.responseText);
		}
	});

	return data;
}
