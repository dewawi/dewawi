$(document).ready(function(){
	//Contact
	//$(document).on('blur', '#address input', function() {
	//	var formElementId = $(this).attr('name');
	//	validateAddress(formElementId);
	//});
});

//Apply Contact
function applyContact(contactID) {
	var data = {};
	var contact = getContact(contactID);
	$('#tabOverview #contactid').val(contact['id']);
	$('#tabCustomer #contactid').val(contact['id']);
	$('#tabOverview #customerid').val(contact['id']);
	$('#tabCustomer #customerid').val(contact['id']);
	$('#tabOverview #billingname1').val(contact['name1']);
	$('#tabCustomer #billingname1').val(contact['name1']);
	$('#billingname2').val(contact['name2']);
	$('#billingstreet').val(contact['street']);
	$('#billingdepartment').val(contact['department']);
	$('#billingpostcode').val(contact['postcode']);
	$('#billingcity').val(contact['city']);
	$('#billingcountry').val(contact['country']);
	$('#taxfree').val(contact['taxfree']);
	$('#contactinfo').val(contact['info']);

	data['contactid'] = contact['id'];
	data['billingname1'] = contact['name1'];
	data['billingname2'] = contact['name2'];
	data['billingstreet'] = contact['street'];
	data['billingdepartment'] = contact['department'];
	data['billingpostcode'] = contact['postcode'];
	data['billingcity'] = contact['city'];
	data['billingcountry'] = contact['country'];
	data['taxfree'] = contact['taxfree'];

	if(contact['shippingname1']) {
		$('#shippingname1').val(contact['shippingname1']);
		$('#shippingname2').val(contact['shippingname2']);
		$('#shippingdepartment').val(contact['shippingdepartment']);
		$('#shippingstreet').val(contact['shippingstreet']);
		$('#shippingpostcode').val(contact['shippingpostcode']);
		$('#shippingcity').val(contact['shippingcity']);
		$('#shippingcountry').val(contact['shippingcountry']);
		$('#shippingphone').val(contact['shippingphone']);

		data['shippingname1'] = contact['shippingname1'];
		data['shippingname2'] = contact['shippingname2'];
		data['shippingdepartment'] = contact['shippingdepartment'];
		data['shippingstreet'] = contact['shippingstreet'];
		data['shippingpostcode'] = contact['shippingpostcode'];
		data['shippingcity'] = contact['shippingcity'];
		data['shippingcountry'] = contact['shippingcountry'];
		data['shippingphone'] = contact['shippingphone'];
	}

	//Refresh contact data
	$('#phones').empty();
	$('#emails').empty();
	$('#internets').empty();
	if(contact['phones']) {
		var phones = contact['phones'].split(',');
		for(i = 0; typeof phones[i] !== 'undefined'; i++)
			$('#phones').append('<label>'+phones[i]+'</label><br>');
	}
	if(contact['emails']) {
		var emails = contact['emails'].split(',');
		for(i = 0; typeof emails[i] !== 'undefined'; i++)
			$('#emails').append('<label>'+emails[i]+'</label><br>');
	}
	if(contact['internets']) {
		var internets = contact['internets'].split(',');
		for(i = 0; typeof internets[i] !== 'undefined'; i++)
			$('#internets').append('<label>'+internets[i]+'</label><br>');
	}

	//Close the modal window
	modalWindowClose();

	isDirty = true;
	edit(data);

    //Refresh file manager
    $('#tabFiles iframe').attr('src', function (i, val) { return val; });
}

//Get contact data
function getContact(contactID){
	var data;
	$.ajax({
		type: 'POST',
		async: false,
		url: baseUrl+'/contacts/contact/get/id/'+contactID,
		cache: false,
		success: function(response){
			data = response;
		}
	});
	return data;
}
