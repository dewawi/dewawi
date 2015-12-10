

	//New
	$(document).on('click', 'a#new'+controller, function() {
		$('a#new'+controller).slideUp();
		$('div#new'+controller).slideDown();
		$('#toolbar').hide();
		//$('#toolbar').html($('div#new'+controller+' #toolbar').html());
		$('#content table').hide();
	});

	$(document).on('click', 'button#new'+controller, function() {
		$('a#new'+controller).slideUp();
		$('div#new'+controller).slideDown();
		$('#toolbar').hide();
		$('#content table').hide();
	});

	$(document).on('click', '#new'+controller+' a#back', function() {
		$('div#new'+controller).slideUp();
		$('a#new'+controller).slideDown();
		$('#content table').show();
		$('#toolbar').show();
		$('#content table').show();
	});



//Get content
function getContent(parameters){
	parameters = parameters || null;
	var url = baseUrl+'/'+module+'/'+controller+'/index';
	if(parameters) url += '/'+parameters;
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		success: function(data){
			$('#content').html(data);
		}
	});
}



//Get content
function getData(parameters){
	parameters = parameters || null;
	var url = baseUrl+'/'+module+'/'+controller+'/index';
	if(parameters) url += '/'+parameters;
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		success: function(data){
			$('#table').html(data);
		}
	});
}


//Save
function save(id, value, element){
	id = id || null;
	value = value || null;
	element = element || null;
	if(id && value && element) {
		var data = {'id': id, 'value': value, 'element': element};
		var url = baseUrl+'/'+module+'/'+controller+'/edit/id/'+id;
		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			cache: false,
			success: function(){
				isDirty = false;
			}
		});
	} else if(action == 'add') {
		var error = false;
		$('input.required').each(function(index) {
			if(!$(this).val()) {
				$(this).addClass('error');
				error = true;
			}
			else $(this).removeClass('error');
		});
		if(!error) {
			isDirty = false;
			document.getElementById(controller).submit();
		}
	} else {
		$('.number').formatCurrency({ region: language });
		var data = $('form').serialize();
		$.ajax({
			type: 'POST',
			//async: false,
			data: data,
			cache: false,
			success: function(){
				$('#status #warning').hide();
				$('#status #success').show();
				isDirty = false;
			}
		});
	}
}



//Save Position
function savePosition(positionID, formElementId){
	formElementId = formElementId || null;
	if(action == 'index') {
		var data = {'id': positionID};
		var url = baseUrl+'/'+module+'/'+controller+'pos/edit/id/'+positionID;
		$('tr.position'+positionID+' input[type=text], tr.position'+positionID+' textarea, tr.position'+positionID+' select').each(function() {
			data[$(this).attr('name')] = $(this).val();
		});
		$('tr.position'+positionID+' input[type=checkbox]').not('#id').each(function() {
			data[$(this).attr('name')] = $(this).is(':checked') ? 1 : 0;
		});
		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			cache: false,
			success: function(){
				//$('#status #warning').hide();
				//$('#status #success').show();
				isDirty = false;
			}
		});
		closePosition(positionID);
		//Mark the line green
		$('tr.position'+id+' td').animate({
			backgroundColor: '#cef6ce'
		}, 100 );
		$('tr.position'+id+' td').delay(10000).animate({
			backgroundColor: 'transparent'
		}, 1000 );
	} else {
		if(formElementId == 'ordering') {
			var oldpos = $('.position'+positionID+' .position span').text();
			var newpos = $('.position'+positionID+' .position select#ordering').val();
			if(oldpos != newpos) sortPosition(positionID, (oldpos - newpos));
		} else {
			var data={};
			data[controller+'id'] = id;
			data['element'] = formElementId;
			data[formElementId] = $('.position'+positionID+' [name='+formElementId+']').val();
			$.ajax({
				type: 'POST',
				url: baseUrl+'/'+window.parent.module+'/'+controller+'pos/edit/id/'+positionID,
				data: data,
				cache: false,
				success: function(data){
					$('#status #warning').hide();
					$('#status #success').show();
					isDirty = false;

					if((formElementId == 'price') || (formElementId == 'quantity')) {
						$('table#total #subtotal').text(data['subtotal']);
						$('table#total #taxes').text(data['taxes']);
						$('table#total #total').text(data['total']);
						$('tr.position'+positionID).find('.total').text(data[positionID]['total']);
					}
				}
			});
		}
	}
}


	$('.edit form input, .edit form textarea, .edit form select').not('#id').change(function() {
		isDirty = true;
		var formElementId = $(this).attr('name');
		if(controller == 'item') {
			validate(formElementId);
			edit(formElementId);
		} else if(controller == 'creditnote') {
			save(id, this.value, formElementId);
		} else {
			validateForm(formElementId);
			save();
		}
		$('#loading').hide();
		$('#status #success').hide();
		$('#status #warning').show();
	});

	$('.add form input, .add form textarea, .add form select').change(function() {
		isDirty = true;
		$('#status #success').hide();
		$('#status #warning').show();
	});

	//Positions
	$(document).on('change', '#positions input[type=text], #positions textarea, #positions select', function() {
		if($(this).hasClass('number')) $(this).formatCurrency({ region: language });
		var positionID = $(this).closest('tr').find('input.id').val();
		var formElementId = $(this).attr('name');
		validateForm(formElementId, positionID);
	});

	$(document).on('click', 'tbody tr', function(event) {
		$(this).toggleClass('selected');
		if ((event.target.nodeName !== 'INPUT') && (event.target.nodeName !== 'A')) {
			$('td#id :checkbox', this).trigger('click');
		}
	});



	$('input[name="file"]').click(function(){
		if (this.checked) {
			var attachment = $('<li><a href="'+$(this).val()+'">'+$(this).next().text()+'</a></li>').hide();
			attachment.appendTo('#attachments ul').slideDown();
		} else {
			$('#attachments ul li a[href="'+$(this).val()+'"]').parent().slideUp('normal', function() {
				$(this).remove();
			});
		}
	});


function printPdf(id){
	$('iframe#print').remove();
	$('body').append('<iframe id="print" name="print" src="'+baseUrl+'/cache/'+controller+'/'+id+'.pdf'+'"></iframe>');
	var iframe = document.frames ? document.frames["print"] : document.getElementById("print");
	var ifWin = iframe.contentWindow || iframe;
	iframe.focus();
	ifWin.print();
	return false;
	var iframe = window.frames["print"];
console.log(iframe);
  var pdf = document.getElementById("print").contentWindow;
  pdf.focus();
  pdf.print();
}





/*function edit(id, type) {
	if($('tr.edit').length > 0) {
		close($('tr.edit input#id').val());
	}
	position = $('tr.position'+id).parents('tr').hasClass('positions');
	var url = baseUrl+'/'+module+'/'+controller;
	if(position) url += 'pos';
	url += '/edit/id/'+id+'/type/'+type;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		cache: false,
		success: function(response){
			data = response;
			if(position) {
				$('tr.position'+id).html(data);
				$('tr.position'+id).addClass('edit');
			} else {
				$('tr#'+controller+id).html(data);
				$('tr#'+controller+id).addClass('edit');
			}
		}
	});
	if($('tr#'+controller+id).next().next().hasClass('positions')) {
		$('tr#'+controller+id).next().next().each(function(){
			var positionID = $(this).find('td input#id:checkbox').val();
			var url = baseUrl+'/'+module+'/'+controller+'pos/edit/id/'+positionID+'/type/'+type;
			$.ajax({
				type: 'POST',
				async: false,
				url: url,
				cache: false,
				success: function(response){
					data = response;
					$('tr.position'+positionID).html(data);
					$('tr.position'+positionID).addClass('edit');
				}
			});
			$('.datePickerLive').datepicker(datePickerOptions);
		});
	}
}*/



//Close
function close(id) {
	var url = baseUrl+'/'+module+'/'+controller+'/get/id/'+id+'/type/html';
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		cache: false,
		success: function(response){
			data = response;
			$('tr#'+controller+id).html(data);
		}
	});
	url = baseUrl+'/'+module+'/'+controller+'/checkin/id/'+id;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		cache: false,
		success: function(response){
			data = response;
		}
	});
	$('tr#'+controller+id).removeClass('edit');

	//Close details and positions
	//$('tr#'+controller+id).next().hide();
	//$('tr#'+controller+id).next().next().hide();
}

//Close
function closePosition(id) {
	var url = baseUrl+'/'+module+'/'+controller+'pos/get/id/'+id+'/type/html';
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		cache: false,
		success: function(response){
			data = response;
			$('tr.position'+id).html(data);
		}
	});
	//url = baseUrl+'/'+module+'/'+controller+'/checkin/id/'+id;
	/*$.ajax({
		type: 'POST',
		async: false,
		url: url,
		cache: false,
		success: function(response){
			data = response;
		}
	});*/
	$('tr.position'+id).removeClass('edit');

	//Close details and positions
	//$('tr#'+controller+id).next().hide();
	//$('tr#'+controller+id).next().next().hide();
}



//Get toolbar
function getButtons(){
	//var url = baseUrl+'/index/toolbar/cont/'+controller+'/act/'+action+'/state/'+$('#state').val();
	$.ajax({
		type: 'POST',
		//url: url,
		cache: false,
		success: function(data){
			//$('#toolbar #buttons').html(data);
		}
	});
}



function getPaymentStatus() {
	var data;
	$.ajax({
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/paymentstatus',
		success: function(resp){
			data = resp;
		}
	});
	return data;
}

function getDeliveryStatus() {
	var data;
	$.ajax({
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/deliverystatus',
		success: function(resp){
			data = resp;
		}
	});
	return data;
}

function getStates() {
	var data;
	$.ajax({
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/states',
		success: function(resp){
			data = resp;
		}
	});
	return data;
}




$(document).ready(function(){
	//E-Mail button
	$('#email1').change(function () {
		if($('#email1').val()) {
			$("#email1 a").show();
			$("#email1 a").attr("href", "mailto:"+$('#email1').val());
		} else {
			$("#email1 a").hide();
		}
	});
	$('#email2').change(function () {
		if($('#email2').val()) {
			$("#email2 a").show();
			$("#email2 a").attr("href", "mailto:"+$('#email2').val());
		} else {
			$("#email2 a").hide();
		}
	});
	$('#email3').change(function () {
		if($('#email3').val()) {
			$("#email3 a").show();
			$("#email3 a").attr("href", "mailto:"+$('#email3').val());
		} else {
			$("#email3 a").hide();
		}
	});

	$("#email #submit").click(function(){
		var data = {};
		//data = $("#email").serializeArray();
		data.email = $("#email #email").val();
		data.subject = $("#email #subject").val();
		data.body = $("#email #body").val();
		data.files = [];
		var attachments = $("form").serialize();
		var i = 0;
		$('form#attachments li a').each(function() {
			var file = {};
			file.filename = $(this).text();
			file.url = $(this).attr('href');
			data.files[i] = file;
			++i;
		});
		$.ajax({
			type: "POST",
			data: data,
			url: baseUrl+"/email/send/",
			cache: false,
			success: function(){
				window.location.replace(baseUrl+"/email/");
			}
		});
	});

	//Get e-mail
	$("#emails #subject a, #emails #from a, #emails #date a").click(function(event){
		event.preventDefault();
		var url = $(this).attr("href");
		$("#emailframe").attr('src', url);
		/*$.ajax({
			type: "POST",
			url: url,
			cache: false,
			success: function(data){
			}
		});*/
	});
});

//Send e-mail
function email() {
	var data = $("form").serialize();
	if(true) data += "&"+$("#files").serialize();
	$.ajax({
		type: "POST",
		data: data,
		url: baseUrl+"/"+module+"/"+controller+"/email",
		cache: false,
		success: function(data){
		}
	});
}

//Get e-mail
function getEmail(id) {
	var url = $("#emails td#"+id).attr("href");
	$.ajax({
		type: "POST",
		url: url,
		cache: false,
		success: function(data){
		}
	});
}

//Get e-mail
function refreshEmail(folder, page) {
	var url = $("#emails td#"+id).attr("href");
	$.ajax({
		type: "POST",
		url: baseUrl+'/email/refresh/folder/'+folder+'/page/'+page+'/',
		cache: false,
		success: function(data){
			$("#emails").html(data);
		}
	});
}



	//Mailto
	$('#email').on('change', 'input', function() {
		if(this.value) {
			$(this).parent().next('a.mailto').show();
			$(this).parent().next('a.mailto').attr('href', 'mailto:'+this.value);
		} else {
			$(this).parent().next('a.mailto').hide();
			$(this).parent().next('a.mailto').attr('href', '');
		}
	});
