$(document).ready(function(){
	$('#settings button.edit').on('click', function() {
		$('#settings').find('div').show();
		$('#settings').find('input, .save, .cancel').hide();
		$('#settings').find('.edit, .delete').show();
		$(this).closest('tr').find('div').hide();
		$(this).closest('tr').find('input, .save, .cancel').show();
		$(this).closest('tr').find('.edit, .delete').hide();
	});
});

//Settings
function addSetting(){
	$("#settings #line0, #settings #line0 input, #settings #line0 button").show();
	window.scrollTo(0, $(document).height());
}

function saveSetting(id){
	var data = {};
	$("#settings #line"+id+" input").each(function()
	{
		data[$(this).attr('name')] = $(this).val();
	});
	$.ajax({
		type: "POST",
		//async: false,
		data: data,
		cache: false,
		success: function(data){
			//$('#settings').html(data);
			$('#settings').find('div').show();
			$('#settings').find('input, .save, .cancel').hide();
			$('#settings').find('.edit, .delete').show();
			$("#settings #line"+id+" input").each(function()
			{
				$("#settings #line"+id+" #"+$(this).attr('name')+" div").text(data[$(this).attr('name')]);
				if(!id) location.reload(true);
			});
		}
	});
}

function deleteSetting(id, message){
	var answer = confirm(message);
	if (answer == true) {
		$.ajax({
			type: "POST",
			url: baseUrl+"/"+module+"/"+controller+"/delete"+action+"/id/"+id,
			cache: false,
			success: function(){
				$("#settings #line"+id).hide();
			}
		});
	}
}

/*function addNewRow(){
	var html = '<tr><td><div></div></td></td>';
	$("#settings tr input").each(function()
	{
		html += $(this).attr('name');

	});
	var html += '</tr>';
	$('#settings tr:last').after('<tr></tr>');
}*/

//Apply Contact
function applyContact(contactId) {
	contactId = contactId || null;
	if(contactId) {
		var data = get("contacts", "contact", contactId);
		$('#tabOverview #contactid').val(data['id']);
		$('#tabCustomer #contactid').val(data['id']);
		$('#tabOverview #billingname1').val(data['name1']);
		$('#tabCustomer #billingname1').val(data['name1']);
		$('#billingname2').val(data['name2']);
		$('#billingstreet').val(data['street']);
		$('#billingdepartment').val(data['department']);
		$('#billingpostcode').val(data['postcode']);
		$('#billingcity').val(data['city']);
		$('#billingcountry').val(data['country']);
		if(data['shippingname1'] && data['shippingstreet']) {
			$('#shippingname1').val(data['shippingname1']);
			$('#shippingname2').val(data['shippingname2']);
			$('#shippingdepartment').val(data['shippingdepartment']);
			$('#shippingstreet').val(data['shippingstreet']);
			$('#shippingpostcode').val(data['shippingpostcode']);
			$('#shippingcity').val(data['shippingcity']);
			$('#shippingcountry').val(data['shippingcountry']);
			$('#shippingphone').val(data['shippingphone']);
		}
		$('#contactinfo').val(data['info']);
		$('#taxfree').val(data['taxfree']);

		//Refresh contact data
		if(data['phone1']) {
			$('dd#phone1').text(data['phone1']);
			$('dt#phone1, dd#phone1').show();
		} else {
			$('dd#phone1').text(data['phone1']);
			$('dt#phone1, dd#phone1').hide();
		}
		if(data['phone2']) {
			$('dd#phone2').text(data['phone2']);
			$('dt#phone2, dd#phone2').show();
		} else {
			$('dd#phone2').text(data['phone2']);
			$('dt#phone2, dd#phone2').hide();
		}
		if(data['phone3']) {
			$('dd#phone3').text(data['phone3']);
			$('dt#phone3, dd#phone3').show();
		} else {
			$('dd#phone3').text(data['phone3']);
			$('dt#phone3, dd#phone3').hide();
		}
		if(data['fax']) {
			$('dd#fax').text(data['fax']);
			$('dt#fax, dd#fax').show();
		} else {
			$('dd#fax').text(data['fax']);
			$('dt#fax, dd#fax').hide();
		}
		if(data['mobile']) {
			$('dd#mobile').text(data['mobile']);
			$('dt#mobile, dd#mobile').show();
		} else {
			$('dd#mobile').text(data['mobile']);
			$('dt#mobile, dd#mobile').hide();
		}
		if(data['email1']) {
			$('dd#email1').text(data['email1']);
			$('dt#email1, dd#email1').show();
		} else {
			$('dd#email1').text(data['email1']);
			$('dt#email1, dd#email1').hide();
		}
		if(data['email2']) {
			$('dd#email2').text(data['email2']);
			$('dt#email2, dd#email2').show();
		} else {
			$('dd#email2').text(data['email2']);
			$('dt#email2, dd#email2').hide();
		}
		if(data['email3']) {
			$('dd#email3').text(data['email3']);
			$('dt#email3, dd#email3').show();
		} else {
			$('dd#email3').text(data['email3']);
			$('dt#email3, dd#email3').hide();
		}
		if(data['internet']) {
			$('dd#internet').text(data['internet']);
			$('dt#internet, dd#internet').show();
		} else {
			$('dd#internet').text(data['internet']);
			$('dt#internet, dd#internet').hide();
		}

		//Reload elFinder
		$("#tabFiles").empty();
		$("#tabFiles").append('<div id="elfinder'+data['id']+'"></div>');
		$('#elfinder'+data['id']).elfinder({
			lang: 'de',
			url : baseUrl+'/library/elFinder/php/connector.php',
			customData : { contactid : data['id'] },
			rememberLastDir : false,
			defaultView: 'list'
		});

		//Close the modal window
		window.parent.modalWindowClose();

		isDirty = true;

		var formElementId = $(this).attr('name');
		validateForm(formElementId);
		save();
		$('#loading').hide();
		$("#status #success").hide();
		$("#status #warning").show();
	} else {
		var data = $("form").serialize();
		$.ajax({
			type: "POST",
			data: data,
			url: baseUrl+"/contacts/contact/apply/id/"+id+"/"+window.parent.controller+"id/"+window.parent.id,
			cache: false,
			success: function(data){
				window.parent.getContact();
				//history.back();
				window.parent.modalWindowClose();
			}
		});
	}
}

/*function quickapplyContact(id, name1, name2, department, street, postcode, city, country, info, shippingname1, shippingname2, shippingdepartment, shippingstreet, shippingpostcode, shippingcity, shippingcountry, shippingphone, taxfree){
	window.parent.modalWindowClose();
	$('#contactid', window.parent.document).val(id);
	$('#contactname', window.parent.document).val(name1);
	$('#billingname1', window.parent.document).val(name1);
	$('#billingname2', window.parent.document).val(name2);
	$('#billingstreet', window.parent.document).val(street);
	$('#billingdepartment', window.parent.document).val(department);
	$('#billingpostcode', window.parent.document).val(postcode);
	$('#billingcity', window.parent.document).val(city);
	$('#billingcountry', window.parent.document).val(country);
	$('#shippingname1', window.parent.document).val(shippingname1);
	$('#shippingname2', window.parent.document).val(shippingname2);
	$('#shippingdepartment', window.parent.document).val(shippingdepartment);
	$('#shippingstreet', window.parent.document).val(shippingstreet);
	$('#shippingpostcode', window.parent.document).val(shippingpostcode);
	$('#shippingcity', window.parent.document).val(shippingcity);
	$('#shippingcountry', window.parent.document).val(shippingcountry);
	$('#shippingphone', window.parent.document).val(shippingphone);
	$('#contactinfo', window.parent.document).val(info);
	$('#taxfree', window.parent.document).val(taxfree);
	var formElementId = $(this).attr('name');
	window.parent.validateForm(formElementId);
	//window.parent.save(window.parent.id, window.parent.controller, window.parent.baseUrl);
	window.parent.document.modalWindowClose;
}*/

//Get contact data
function getContact(){
	$.ajax({
		type: "POST",
		url: baseUrl+"/"+module+"/"+controller+"/get/id/"+id,
		cache: false,
		success: function(data){
			$('#contactid').val(data['contactid']);
			$('#contactname').val(data['billingname1']);
			$('#billingname1').val(data['billingname1']);
			$('#billingname2').val(data['billingname2']);
			$('#billingdepartment').val(data['billingdepartment']);
			$('#billingstreet').val(data['billingstreet']);
			$('#billingpostcode').val(data['billingpostcode']);
			$('#billingcity').val(data['billingcity']);
			$('#billingcountry').val(data['billingcountry']);
			if(data['shippingname1'] && data['shippingstreet']) {
				$('#shippingname1').val(data['shippingname1']);
				$('#shippingname2').val(data['shippingname2']);
				$('#shippingdepartment').val(data['shippingdepartment']);
				$('#shippingstreet').val(data['shippingstreet']);
				$('#shippingpostcode').val(data['shippingpostcode']);
				$('#shippingcity').val(data['shippingcity']);
				$('#shippingcountry').val(data['shippingcountry']);
				$('#shippingphone').val(data['shippingphone']);
			}
			$('#contactinfo').val(null);
			$('#taxfree').val(data['taxfree']);
			$("#status #success").hide();
			$("#status #warning").show();
			save();
			isDirty = true;
		}
	});
}
