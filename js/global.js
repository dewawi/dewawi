var isDirty = false;
var timeout = 0;

//Date picker options
var datePickerOptions = {
	autoHide: true,
	language: 'de-DE'
	/*numberOfMonths: 1,
	showWeek: true,
	dateFormat: 'yy-mm-dd',
	monthNames: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
	monthNamesShort: ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'],
	dayNames: ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'],
	dayNamesShort: ['So','Mo','Di','Mi','Do','Fr','Sa'],
	dayNamesMin: ['So','Mo','Di','Mi','Do','Fr','Sa'],
	weekHeader: 'Wo',
	firstDay: 1,
	changeMonth: true,
	changeYear: true,
	onClose: function(){$(this).trigger('change')}*/
};

$(document).ready(function(){
	//Keep alive
	setInterval(function(){
		if(action == 'edit') {
			$.get(baseUrl+'/'+module+'/'+controller+'/keepalive/id/'+id);
		} else if(action = 'index') {
			//Keep alive editable elements
			$('.editableValue:visible').each(function(e) {
				lock($(this).closest('tr').find('input.id').val());
			});
		}
console.log(isDirty);
	}, 3000); // 60 seconds

	//Auto validate and save
	$('input.required').blur(function() {
		if(!$(this).val()) $(this).addClass('error');
		else $(this).removeClass('error');
	});
	$('.add form').on('change', 'input, textarea, select', function() {
		isDirty = true;
	});
	$('.edit form').on('change', 'input, textarea, select', function() {
		isDirty = true;
		var data = {};
		var params = {};
		var value = this.value;
		//If the element is a checkbox
		if($(this).is(':checkbox')) {
			if($(this).is(':checked')) value = 1;
			else value = 0;
		}
		data[this.name] = value;
		//Check dataset info on the element
		if(typeof this.dataset.id !== 'undefined') params['id'] = this.dataset.id;
		if(typeof this.dataset.action !== 'undefined') params['action'] = this.dataset.action;
		if(typeof this.dataset.controller !== 'undefined') params['controller'] = this.dataset.controller;
		if(typeof this.dataset.module !== 'undefined') params['module'] = this.dataset.module;
		if(typeof this.dataset.ordering !== 'undefined') data['ordering'] = this.dataset.ordering;
		edit(data, params);
		//validate(data, params);
	});
	$('.edit form input').on('textchange', function() {
		isDirty = true;
	});
	$('.edit form textarea').on('textchange', function() {
		isDirty = true;
	});
	//$('.edit form select').on('textchange', function() {
	//	isDirty = true;
	//});

	//Positions
	$('#positions').on('change', 'input:not(.id), textarea, select', function() {
		if($(this).hasClass('number')) $(this).formatCurrency({ region: language });
		var data = {};
		var params = {};
		params['id'] = $(this).closest('tr.wrap').find('input.id').val();
        console.log(params['id']);
		params[controller+'id'] = id;
		params['element'] = this.name;
		data[this.name] = this.value;
        //console.log(data);
        //console.log(params);
		if(this.name == 'ordering') sort(params['id'], this.value);
		else editPosition(data, params);
	});

	//Editable
	var previousValue;
	if(action == 'index') {
		$('#data .editable').each(function() {
			$(this).wrap('<div class="editableContainer"></div>');
			if(!$(this).text()) {
				$(this).html('&nbsp;');
				$(this).attr('data-empty', 'true');
			}
			
		});
	}
	$('#content').on('click', '.editable', function() {
		var id = $(this).closest('tr').find('input.id').val();
		var type = $(this).data('type') || 'input';
		//Close and unlock all other elements
		var editableValue = $(this).next('.editableValue');
		$('.editable').not(this).show();
		$('.editableValue:visible').each(function(e) {
			if($(this)[0] != editableValue[0]) {
				$(this).hide();
				if(id != $(this).closest('tr').find('input.id').val()) {
					unlock($(this).closest('tr').find('input.id').val());
				}
			}
		});
		//Lock
		var lockMessage = lock(id);
		//Check if there is a message
		if(typeof lockMessage !== 'undefined') {
			pushMessages(lockMessage);
		} else {
			$(this).hide();
			if(!$(this).next(type).length) {
				//Create a new input with attributes
				if(type == 'input') {
					$(this).after('<input type="text">');
					var input = $(this).next(type);
					if(!$(this).data('empty') && (input.data('action') != 'add')) input.val($(this).text());
				} else if(type == 'select') {
					$(this).after('<select></select>');
					var input = $(this).next(type);
					//Get dropdown options from page if it exists
					if($('select#'+$(this).data('name')).length && $('select#'+$(this).data('name')).html()) {
						input.html($('select#'+$(this).data('name')).html());
					} else {
						var response = {};
						$.ajax({
							type: 'POST',
							async: false,
							url: baseUrl+'/'+module+'/'+controller+'/get/element/'+$(this).data('name'),
							cache: false,
							success: function(json){
								response = json;
							}
						});
						//Add options to dropdown
						$.each(response, function(key, value) {
							input.append(
								$('<option></option>').val(key).html(value)
							);
						});
					}
					input.val($(this).data('value'));
				} else if(type == 'textarea') {
					$(this).after('<textarea style="height:'+$(this).height()+'px;"></textarea>');
					var input = $(this).next(type);
					if(!$(this).data('empty') && (input.data('action') != 'add')) input.val($(this).text());
				}
				input.attr('class', 'editableValue');
				input.attr('name', $(this).data('name'));
				previousValue = input.val();
			} else {
				editableValue.show();
			}
			$(this).next(type).focus();
		}
	});
	$(document).on('click', 'html', function(e) {
		if((e.target.className != 'editableValue') && (e.target.className != 'editable') && ($(e.target).parent().attr('class') != 'editableValue')) {
			$('.editable').show();
			//Hide and unlock visible elements
			$('.editableValue:visible').each(function(index) {
				$(this).hide();
				unlock($(this).closest('tr').find('input.id').val());
			});
		}
	});
	$('#content').on('change', '.editableValue', function() {
		isDirty = true;
		var data = {};
		var params = {};
		params['id'] = $(this).closest('tr').find('input.id').val();
		var value = this.value;
		//If the element is a checkbox
		if($(this).is(':checkbox')) {
			if($(this).is(':checked')) value = 1;
			else value = 0;
		}
		data[this.name] = value;
		//Check dataset info on the element
		if(typeof this.dataset.id !== 'undefined') params['id'] = this.dataset.id;
		if(typeof this.dataset.action !== 'undefined') params['action'] = this.dataset.action;
		if(typeof this.dataset.controller !== 'undefined') params['controller'] = this.dataset.controller;
		if(typeof this.dataset.module !== 'undefined') params['module'] = this.dataset.module;
		if(typeof this.dataset.ordering !== 'undefined') data['ordering'] = this.dataset.ordering;
		if(params['action'] == 'add') {
			var response = add(data, params);
			this.dataset.action = '';
		} else {
			var response = edit(data, params);
		}
		//validate(data, params);
		//Check if there is a message
		if((typeof response !== 'undefined') && (typeof response.message !== 'undefined')) {
			pushMessages(response);
		} else {
			//Replace the text with new value
			if(this.nodeName == 'SELECT') {
				var value = response[this.name];
				//$(this).parent().hide();
				if(value.match(/^\d+$/)) { //If value is numeric
					$(this).closest('tr').removeClass(this.name+previousValue);
					$(this).closest('tr').addClass(this.name+value);
				} else {
					$(this).closest('tr').removeClass(previousValue);
					$(this).closest('tr').addClass(value);
				}
				var value = $(this).find('option[value="'+response[this.name]+'"]').text();
				$(this).prev('.editable').text(value);
				previousValue = response[this.name];
				if(this.name == 'parentid') search();
			} else {
				$(this).prev('.editable').text(response[this.name]);
			}
			//Unlock
			unlock(params['id']);
		}
	});

	//Change title
	if(controller != 'category') {
		$('form #title').blur(function() {
			if(this.value) $('h2').text(this.value);
		});
	}

	//Focus on keyword
	if(action == 'index') {
		$('#keyword').focus().select();
	}

	//Auto search
	$('.toolbar #keyword').on('textchange', function() {
		var keyword = this.value;
		if(keyword) {
			var date = new Date();
			date.setTime(date.getTime() + (5 * 60 * 1000)); // expire after 5 minutes
			$.cookie('keyword', keyword, { expires: date, path: cookiePath });
		} else {
			$.cookie('keyword', '', { path: cookiePath });
		}
		search();
	});

	//Toolbar
	$('.toolbar').on('change', 'input:not(#keyword), select', function() {
		var element = this.name;
		var value = this.value;
		if(module == 'statistics') {
			$.cookie(element, this.value, { path: cookiePath });
			location.reload();
		} else {
			if(action == 'edit') {
				data = {};
				data[element] = value;
				edit(data);
			} else {
				if(element == 'states[]') {
					states = [];
					$('#state input[type="checkbox"]').each(function() {
						if($(this).prop('checked')) states.push(this.value);
					});
					$.cookie('states', JSON.stringify(states), { path: cookiePath });
				} else if(element == 'daterange') {
					if(this.value == 'custom') $('#filter .daterange').show();
					else $('#filter .daterange').hide();
					$.cookie(element, this.value, { path: cookiePath });
				} else if(element == 'paymentstatus[]') {
					paymentstatus = [];
					$('input[type="checkbox"][name="paymentstatus[]"]').each(function() {
						if($(this).prop('checked')) paymentstatus.push(this.value);
					});
					$.cookie('paymentstatus', JSON.stringify(paymentstatus), { path: cookiePath });
				} else {
					$.cookie(element, this.value, { path: cookiePath });
				}
				search();
			}
		}
	});

	$('.toolbar').on('click', 'button.filter', function() {
		$('#filter').show();
	});

	$(document).mouseup(function (e) {
		var container = $("#filter");
		if(!container.is(e.target) // if the target of the click isn't the container...
		&& container.has(e.target).length === 0) { // ... nor a descendant of the container
			container.hide();
		}
	});

	$('#filter').on('click', '.all', function() {
		$('input[name="states[]"]').prop('checked', true);
		states = [];
		$('#state input:checked').each(function() {
			states.push(this.value);
		});
		$.cookie('states', JSON.stringify(states), { path: cookiePath });
		search();
	});

	$('#filter').on('click', '.none', function() {
		$('input[name="states[]"]').prop('checked', false);
		$.cookie('states', '', { path: cookiePath });
		search();
	});

	$('#filter #state label').each(function(){
		$(this).contents().last().wrap('<span class="only" />');
	});
	$('#filter').on('click', '.only', function() {
		$('input[name="states[]"]').prop('checked', false);
		$(this).prop('checked', true);
		states = [];
		states[0] = $(this).val();
		$.cookie('states', JSON.stringify(states), { path: cookiePath });
		search();
	});

	//Select template or language
	$('.edit form').on('change', '#templateid, #language', function() {
		previewPdf();
	});

	//Mainmenu
	$('ul#mainmenu li').hover(function(){

		$(this).addClass('hover');
		$('ul:first',this).css('visibility', 'visible');

	}, function(){

		$(this).removeClass('hover');
		$('ul:first',this).css('visibility', 'hidden');

	});

	$('ul#mainmenu li ul li:has(ul)').find('a:first').append(' &raquo; ');

	//Topnav
	$('ul.dropdown li').hover(function(){
		$(this).addClass('hover');
		$('ul:first',this).css('visibility', 'visible');
	}, function(){
		$(this).removeClass('hover');
		$('ul:first',this).css('visibility', 'hidden');
	});
	$('ul.dropdown li ul li:has(ul)').find('a:first').append(' &raquo; ');

	//Treemenu
	$('#treemenu li a').click(function(event) {
		event.preventDefault();
		$('#treemenu li a').removeClass('active');
		$(this).addClass('active');
		$('#catid').val($(this).attr('id'));
		$.cookie('catid', $('#catid').val(), { path: cookiePath });
		search();
	});

	//Modal window
	$('button.poplight').click(modalWindow);
	$(document).on('click', 'a.close, #fade', modalWindowClose);

	//Loading
	/*$(document)
		.hide()
		.ajaxStart(function() {
			if(action != 'edit') $('#loading').show();
		})
		.ajaxStop(function() {
			$('#loading').hide();
	});*/

	//Tabs
	//$('.tab_content').hide(); //Hide all content
	$('ul.tabs:not(:has(li.active))').children('li:first-child').addClass('active').show();
	//$('ul.tabs li:first').addClass('active').show(); //Activate first tab
	$('.tab_container:not(:has(.tab_content.active))').children(':first-child').addClass('active').show();
	//$('.tab_content:first').show(); //Show first tab content
	$('ul.tabs').on('click', 'li', function(event) {
		$('ul.tabs li').removeClass('active'); //Remove any 'active' class
		$(this).addClass('active'); //Add 'active' class to selected tab
	    //$('.datepicker-container').addClass('datepicker-hide').off('click.datepicker', $('.datePicker').click); //Hide date picker
		$('.tab_content').hide(); //Hide all tab content

		if(($(this).find('a').attr('href') == '#tabFinish') || ($(this).find('a').attr('href') == '#tabDocument') || ($(this).find('a').attr('href') == '#tabFiles')) {
			$.cookie('tab', '#tabOverview', { path: cookiePath+'/'+action });
		} else {
			$.cookie('tab', $(this).find('a').attr('href'), { path: cookiePath+'/'+action });
		}

		var activeTab = $(this).find('a').attr('href'); //Find the href attribute value to identify the active tab + content
		$(activeTab).fadeIn(); //Fade in the active ID content
		if(activeTab == '#tabFiles' || activeTab == '#tabImages') {
			//$('#tabFiles').html('<div id="elfinder"></div>');
			//elfinder();
		}
		//return false;
        event.preventDefault();
	});
	if($.cookie('tab') == '#tabPositions') {
		getPositions();				
	}

	//Prices and quantities
	$('.number').on('blur, change', function() {
		$(this).formatCurrency({ region: language });
	});
	$('#cost, #price').on('textchange', function () {
		var price = $('#price').asNumber({ region: language });
		var cost = $('#cost').asNumber({ region: language });
		if(cost && price) {
			var margin = price-cost;
			$('input#margin').val(margin);
			$('input#margin').formatCurrency({ region: language });
		} else {
			$('input#margin').val(null);
		}
	});

	//Buttons
	$(document).on('click', 'button', function() {
		if(!$(this).attr('onclick')) {
			var classes = $(this).attr('class');
			if(classes) {
				var className = classes.split(' ')[0];
				if(className == 'add') {
					var url = baseUrl+'/'+module+'/'+controller+'/add';
					if($('#catid').val() > 0) url += '/catid/'+$('#catid').val();
					setLocation(url);
				} else if(className == 'save') {
					save();
				} else if(className == 'addPosition') {
					addPosition();
				} else if(className == 'clear') {
					var element = $(this).attr('rel');
					clear(element);
				} else if(className == 'reset') {
					reset();
				} else if($(this).parents('.toolbar').length == 1) {
					$('table#data tr').each(function(){
						$(this).find('td input.id:checkbox').each(function() {
							if(this.checked) {
								switch(className) {
									case 'edit':
										var url = baseUrl+'/'+module+'/'+controller+'/edit/id/'+$(this).val();
										setLocation(url);
										break;
									case 'copy':
										copy($(this).val());
										break;
									case 'cancel':
										cancel($(this).val());
										break;
									case 'delete':
										del($(this).val(), deleteConfirm);
										break;
								}
							}
						});
					});
					$('table#positions tr').each(function(){
						$(this).find('td input.id:checkbox').each(function() {
							if(this.checked) {
								switch(className) {
									case 'copyPosition':
										copyPosition($(this).val());
										break;
									case 'applyPosition':
										applyPosition($(this).val());
										break;
									case 'deletePosition':
										deletePosition($(this).val());
										break;
								}
							}
						});
					});
                    //copy and delete function on edit page
					var id = $(this).closest('.toolbar').find('input.id').val();
					if(id) {
						if(className == 'copy') {
							copy(id);
						} else if(className == 'delete') {
							del(id, deleteConfirm);
						}
					}
				} else if(className == 'up') {
					var id = $(this).closest('tr').find('input.id').val();
					sort(id, 'up');
				} else if(className == 'down') {
					var id = $(this).closest('tr').find('input.id').val();
					sort(id, 'down');
				} else if(className == 'copyPosition') {
					var positionID = $(this).closest('tr').find('input.id').val();
					copyPosition(positionID);
				} else if(className == 'deletePosition') {
					/*var ids = $('input:checkbox:checked').map(function () {
						return this.value;
					}).get();*/
					var positionID = $(this).closest('tr').find('input.id').val();
					deletePosition(positionID);
				} else {
					var cid = $(this).closest('tr').find('input.id').val() || id;
					if(cid) {
						var cmodule = $(this).closest('tr').find('input.module').val() || module;
						var ccontroller = $(this).closest('tr').find('input.controller').val() || controller;
						if(className == 'edit') {
							setLocation(baseUrl+'/'+cmodule+'/'+ccontroller+'/edit/id/'+cid);
						} else if(className == 'view') {
							setLocation(baseUrl+'/'+cmodule+'/'+ccontroller+'/view/id/'+cid);
						} else if(className == 'pdf') {
							setLocation(baseUrl+'/'+cmodule+'/'+ccontroller+'/download/id/'+cid);
						} else if(className == 'copy') {
							copy(cid, cmodule, ccontroller);
						} else if(className == 'delete') {
							del(cid, deleteConfirm);
						} else if(className == 'export') {
							var ids = $('input:checkbox:checked').map(function () {
								return this.value;
							}).get();
							exportData(ids);
						}
					}
				}
			}
		}
	});

	//Date picker
	$('.datePicker').datepicker(datePickerOptions);
	$(document).on('click', '.datePickerLive', function() {
		$(this).datepicker(datePickerOptions);
	});

	var from = new Date($('#from').val());
	var to = new Date($('#to').val());

	if($('#fromDatePicker').length) {
		var dateFromPickerOptions = {
	        language: 'de-DE',
			inline: true,
            container: $('#fromDatePicker'),
			/*
			defaultDate: $('#from').val(),
			beforeShowDay: function(date) {
				return [true, from <= date && date <= to ? 'active' : ''];
			},
			onSelect: function( selectedDate ) {
				var option = this.id == 'from' ? 'minDate' : 'maxDate',
					instance = $( this ).data( 'datepicker' ),
					date = $.datepicker.parseDate(
						instance.settings.dateFormat ||
						$.datepicker._defaults.dateFormat,
						selectedDate, instance.settings );
				datesFrom.not( this ).datepicker( 'option', option, date );
				$.cookie('from', $('#from').val(), { path: cookiePath });
				$.cookie('daterange', 'custom', { path: cookiePath });
				if(action == 'index') search();
			}*/
		}
		//$.extend(dateFromPickerOptions, datePickerOptions)
		var datesFrom = $('#from').datepicker(dateFromPickerOptions);
	}

	if($('#toDatePicker').length) {
		var dateToPickerOptions = {
	        language: 'de-DE',
			inline: true,
            container: $('#toDatePicker'),
			/*
			defaultDate: $('#to').val(),
			beforeShowDay: function(date) {
				return [true, from <= date && date <= to ? 'active' : ''];
			},
			onSelect: function( selectedDate ) {
				var option = this.id == 'from' ? 'minDate' : 'maxDate',
					instance = $( this ).data( 'datepicker' ),
					date = $.datepicker.parseDate(
						instance.settings.dateFormat ||
						$.datepicker._defaults.dateFormat,
						selectedDate, instance.settings );
				datesTo.not( this ).datepicker( 'option', option, date );
				$.cookie('to', $('#to').val(), { path: cookiePath });
				$.cookie('daterange', 'custom', { path: cookiePath });
				if(action == 'index') search();
			}*/
		};
		//$.extend(dateToPickerOptions, datePickerOptions)
		var datesTo = $('#to').datepicker(dateToPickerOptions);
	}

	//Table alt
	$('#data tbody tr:nth-child(2n+1)').addClass('alt');

	//Table highlight
	$('#data').on('mouseover mouseout', 'tbody tr', function(event) {
		if (event.type == 'mouseover') {
			$(this).addClass('highlight');
		} else {
			$(this).removeClass('highlight');
		}
	});

	//Remove messages
	removeMessages();

	//Check all
	$('#content, #positions').on('click', '.checkall', function() {
		$(this).parents('div').find('input.id:checkbox').prop('checked', this.checked);
	});

	//Auto size textarea
	autosize($('textarea#description'));
});

window.onbeforeunload = function(e) {
	var e = e || window.event;

	if(isDirty) {
		// For IE and Firefox prior to version 4
		if (e) {
			e.returnValue = 'Any string';
		}

		// For Safari
		return 'Any string';
	}

	//Unlock
	if(action == 'edit') unlock(id);
};

//Lock
function lock(id) {
	var response;
	$.ajax({
		type: 'POST',
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/lock/id/'+id,
		cache: false,
		success: function(json){
			response = json;
		}
	});
	return response;
}

//Unlock
function unlock(id) {
	$.ajax({
		type: 'POST',
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/unlock/id/'+id,
		cache: false,
	});
}

//Save
function save() {
	var error = false;
	if(action == 'add') {
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
		var data = $(".add :input")
			.filter(function(index, element) {
			return $(element).val() != "";
		}).serialize();
		if(data) add(data);
	}
}

//Add
function add(data, params) {
    //console.log(data);
	params = params || null;
	if(params) {
		var url = baseUrl;
		params = params || null;
		if(params && params['module']) url += '/'+params['module'];
		else url += '/'+module;
		if(params && params['controller']) url += '/'+params['controller'];
		else url += '/'+controller;
		if(params && params['id']) url += '/add/id/'+params['id'];
		else url += '/add/id/'+id;
		var response;
		$.ajax({
			type: 'POST',
			async: false,
			url: url,
			data: data,
			cache: false,
			success: function(json){
				response = json;
				isDirty = false;
			}
		});
		return response;
	} else {
		data[controller+'id'] = id;
		var url = baseUrl+'/'+module;
		if(data['controller']) url += '/'+data['controller'];
		else url += '/'+controller;
		if(data['id']) url += '/add/id/'+data['id'];
		else url += '/add/id/'+id;
		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			cache: false,
			success: function(response){
				isDirty = false;
				//Append new form from response
				$('div#'+data['controller']+' button.add').before(response);
				//Focus on new element
				$('div#'+data['controller']+' div:last input:first').focus().select();
                //console.log(response);
				if(action == 'index') search();
			}
		});
	}
}

//Edit
function edit(data, params) {
	var url = baseUrl;
	params = params || null;
	if(params && params['module']) url += '/'+params['module'];
	else url += '/'+module;
	if(params && params['controller']) url += '/'+params['controller'];
	else url += '/'+controller;
	if(params && params['id']) url += '/edit/id/'+params['id'];
	else url += '/edit/id/'+id;
	var response;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		data: data,
		cache: false,
		success: function(json){
			response = json;
			isDirty = false;
            //console.log(isDirty);
		}
	});
    //console.log(data);
    //console.log(response);
	return response;
}

function editPosition(data, params) {
	var url = baseUrl+'/'+module+'/'+controller+'pos';
	if(params['id']) url += '/edit/id/'+params['id'];
	if(params[controller+'id']) url += '/'+controller+'id/'+params[controller+'id'];
	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		cache: false,
		success: function(response){
			isDirty = false;
			if((params['element'] == 'price') || (params['element'] == 'quantity')) {
				$('table#total #subtotal').text(response['subtotal']);
				$('table#total #total').text(response['total']);
				$('tr.position'+params['id']+'.wrap').find('.total').text(response[params['id']]['total']);
                $.each(response['taxes'], function(key, val) {
                    if(key != 'total') $('td[data-rate="'+key+'"]').text(val);
                });
			} else if(params['element'] == 'taxrate') {
				//$('table#total #taxes').text(response['taxes']);
				//$('tr.position'+params['id']+'.wrap').find('.total').text(response[params['id']]['total']);
                /*$.each(response['taxes'], function(key, val) {
                    if(key != 'total') {
                        if($('td[data-rate="'+key+'"]').length) {
                            $('td[data-rate="'+key+'"]').text(val);
                        } else {
			                getPositions($(document).height());
                        }
                    }
                });*/
			    getPositions($(document).height());
			}
		}
	});
}

//Search
function search() {
	data = {};
	data.states = [];
	$('#state input:checked').each(function() {
		data.states.push(this.value);
	});
	data.keyword = $('#keyword').val();
	data.type = $('#type').val();
	data.country = $('#country').val();
	data.catid = $('#catid').val();
	data.category = $('#category').val();
	data.daterange = $('#daterange input:checked').val();
	data.from = $('#from').val();
	data.to = $('#to').val();
	data.limit = $('#limit').val();
	data.order = $('#order').val();
	data.sort = $('#sort').val();
	data.clientid = $('#clientid').val();
	data.paymentstatus = [];
	$('input[name="paymentstatus[]"]:checked').each(function() {
		data.paymentstatus.push(this.value);
	});
	data.deliverystatus = [];
	$('#filter input[name="deliverystatus"]:checked').each(function() {
		data.deliverystatus.push(this.value);
	});
	data.supplierorderstatus = [];
	$('#filter input[name="supplierorderstatus"]:checked').each(function() {
		data.supplierorderstatus.push(this.value);
	});

	var url = baseUrl+'/'+module+'/'+controller+'/search';
	//if(parent.location != window.location) url += '/parent/'+window.parent.module+'|'+window.parent.controller;

	clearTimeout(timeout);
	timeout = setTimeout(function () {
		$('#loading').show();
		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			cache: false,
			success: function(response){
				$('#content').html(response);
				if(action == 'select') {
					$('.tab_content').hide();
					$('ul.tabs li:first').addClass('active').show();
					$('.tab_content:first').show();
				}
				$('#data tbody tr:nth-child(2n+1)').addClass('alt');
				$('#data').on('mouseover mouseout', 'tbody tr', function(event) {
					if (event.type == 'mouseover') {
						$(this).addClass('highlight');
					} else {
						$(this).removeClass('highlight');
					}
				});

				//Remove messages
				removeMessages();

				//Load editable
				$('#data .editable').each(function() {
					$(this).wrap('<div class="editableContainer"></div>');
				});
				$('#loading').hide();
			}
		});
	}, 500);
}

//Clear
function clear(element) {
	if(element == 'keyword') {
		$('#keyword').val('');
		$.cookie('keyword', '', { path: cookiePath });
		search();
	} else if(element == 'catid') {
		$('#catid').val(0);
		$.cookie('catid', 0, { path: cookiePath });
		search();
	} else if(element == 'country') {
		$('#country').val(0);
		$.cookie('country', 0, { path: cookiePath });
		search();
	} else if(element == 'order') {
		$('#order option[default="default"]').prop('selected', true);
		$.cookie('order', $('#order option[default="default"]').val(), { path: cookiePath });
		search();
	} else if(element == 'sort') {
		$('#sort option[default="default"]').prop('selected', true);
		$.cookie('sort', $('#sort option[default="default"]').val(), { path: cookiePath });
		search();
	} else if(element == 'states') {
		$('#state input[type="checkbox"]').prop('checked', false);
		$('#state input[type="checkbox"][default="default"]').prop('checked', true);
		states = [];
		$('#state input[type="checkbox"]').each(function() {
			if($(this).prop('checked')) states.push(this.value);
		});
		$.cookie(element, JSON.stringify(states), { path: cookiePath });
		search();
	} else if(element == 'daterange') {
		$('#daterange input[value=0]').prop('checked', true);
		$.cookie('daterange', 0, { path: cookiePath });
		$('#filter .daterange').hide();
		search();
	}
}

//Reset
function reset() {
	$('#keyword').val('');
	$.cookie('keyword', '', { path: cookiePath });
	$('.toolbar select').each(function(){
		if(this.name) {
			$(this).val($(this).attr('default'));
			$.cookie(this.name, $(this).attr('default'), { path: cookiePath });
		}
	});
	$('.toolbar input[type="radio"]').each(function(){
		if(this.name) {
			//console.log($(this).attr('default'));
			if($(this).attr('default') == $(this).val()) $(this).prop('checked', true);
			//else $(this).prop('checked', false);
			$.cookie(this.name, $(this).attr('default'), { path: cookiePath });
		}
	});
	$('.toolbar input[type="checkbox"]').each(function(){
		if(this.name) {
			var elements = $(this).attr('default');
			var elementsArray = elements.split(' ');
			//console.log(elementsArray);
			//console.log($(this).val());
			//console.log(elementsArray.indexOf($(this).val()));
			if(elementsArray.indexOf($(this).val()) != -1) $(this).prop('checked', true);
			else $(this).prop('checked', false);
			$.cookie(this.name.replace('[]', ''), JSON.stringify(elementsArray), { path: cookiePath });
		}
	});
	search();
}

//Copy
function copy(cid, cmodule, ccontroller){
	cid = cid || id;
    //console.log(cid);
    //console.log(cmodule);
    //console.log(baseUrl+'/'+cmodule+'/'+ccontroller+'/copy/id/'+cid);
	cmodule = cmodule || module;
	ccontroller = ccontroller || controller;
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+cmodule+'/'+ccontroller+'/copy/id/'+cid,
		cache: false,
		success: function(response){
            //console.log(response);
			if(id && response) setLocation(baseUrl+'/'+cmodule+'/'+ccontroller+'/edit/id/'+response);
			else search();
		}
	});
}

//Cancel
function cancel(id, message){
	var answer = confirm(message);
	if (answer == true) {
		$.ajax({
			type: 'POST',
			url: baseUrl+'/'+module+'/'+controller+'/cancel/id/'+id,
			cache: false,
			success: function(data){
				if(action == 'edit') {
					window.location = baseUrl+'/'+module+'/'+controller;
				} else {
					search();
				}
			}
		});
	}
}

//Delete
function del(id, message, type){
	type = type || controller;
	var answer = confirm(message);
	if (answer == true) {
		if(action == 'add') {
			$('div#'+type+id).remove();
		} else {
			$.ajax({
				type: 'POST',
				url: baseUrl+'/'+module+'/'+type+'/delete/id/'+id,
				cache: false,
				success: function(data){
					if(action == 'edit') {
						$('div#'+type+id).remove();
						if(type == controller) window.location = baseUrl+'/'+module+'/'+controller;
					} else {
						search();
					}
				}
			});
		}
	}
}

//Add position
function addPosition() {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/'+controller+'pos/add/'+controller+'id/'+id,
		cache: false,
		success: function(){
			$('#status #warning').hide();
			$('#status #success').show();
			isDirty = false;
			getPositions($(document).height());
		}
	});
}

//Apply position
function applyPosition(itemId) {
	$.ajax({
		type: 'POST',
		url: window.parent.baseUrl+'/'+window.parent.module+'/'+window.parent.controller+'pos/apply/'+window.parent.controller+'id/'+window.parent.id+'/itemid/'+itemId,
		cache: false,
		success: function(){
			window.parent.getPositions($(parent.document).height());
			if(action == 'apply') {
				history.go(-1);
			}
		}
	});
}

//Get Positions
function getPositions(scrollTo) {
	scrollTo = scrollTo || null;
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/'+controller+'pos/index/'+controller+'id/'+id,
		cache: false,
		success: function(data){
			$('#positions').html(data);
			autosize($('#positions').find('textarea'));
			if(data) $('#tabPositions .toolbar.positions.bottom').show();
			else $('#tabPositions .toolbar.positions.bottom').hide();
			if(scrollTo) {
				/*$('html, body').animate({
					scrollTop: $('#position'+scrollTo).offset().top
				}, 2000);*/
				window.scrollTo(0, scrollTo);
			}
			$('.datePickerLive').datepicker(datePickerOptions);
		}
	});
}

//Copy Position
function copyPosition(positionID){
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/'+controller+'pos/copy/id/'+positionID,
		cache: false,
		success: function(){
			getPositions(window.pageYOffset);
		}
	});
}

//Delete Position
function deletePosition(positionID){
	var data = {};
	data.id = positionID;
	data[controller+'id'] = id;
	data.delete = 'Yes';
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/'+controller+'pos/delete',
		cache: false,
		data: data,
		success: function(){
			getPositions(window.pageYOffset);
		}
	});
}

//Ordering
function sort(id, ordering){
	var data = {};
	data.id = id;
	data.ordering = ordering;
	var url = baseUrl+'/'+module+'/'+controller;
	if(action == 'edit') {
		data[controller+'id'] = window.id;
		url += 'pos/sort/id/'+id;
	} else {
		url += '/sort/id/'+id;
	}
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		data: data,
		success: function(response){
			if(action == 'edit') {
				getPositions();
			} else {
                //console.log(response);
				search();
			}
		}
	});
}

function pushMessages(messages){
	$.each(messages, function(key, value) {
		$('td#content').prepend('<div id="messages"><ul><li>'+value+'</li></ul></div>');
	});
	removeMessages();
}

function removeMessages(){
	if($('#messages').length) {
		/*$('#messages').delay(8000).fadeTo(2000, 0.00, function() {
			$(this).slideUp('slow', function() {
				$(this).remove();
			});
		});*/
	}
}

//Make PDF
function previewPdf(){
	$('#output').html('');
	var url = baseUrl+'/'+module+'/'+controller+'/preview/id/'+id;
	if($('#templateid').val()) url += '/templateid/'+$('#templateid').val();
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		success: function(data){
			$('#output').html(data);
		}
	});
}

function savePdf(){
	$('#output').html('');
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/'+controller+'/save/id/'+id,
		cache: false,
		success: function(data){
			$('#output').html(data);
			if(action != 'view') {
				window.location = baseUrl+'/'+module+'/'+controller+'/view/id/'+id;
				$('ul.tabs li').removeClass('active');
				$(this).addClass('active');
				$('.tab_content').hide();
				var activeTab = $(this).find('a').attr('href');
			}
		}
	});
}

function downloadPdf(){
	$('#output').html('');
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/'+controller+'/download/id/'+id,
		cache: false,
		success: function(data){
			$('#output').html(data);
		}
	});
}

//Modal Window
function modalWindow() {
	var popID = $(this).attr('rel');
	//var popURL = $(this).attr('href');
	//var query= popURL.split('?');
	//var dim= query[1].split('&');
	//var popWidth = dim[0].split('=')[1];

	$('#' + popID).fadeIn().prepend('<a href="#" class="close"></a>');
	//$('#' + popID).fadeIn().css({ 'width': Number( popWidth ) }).prepend('<a href="#" class="close"></a>');

	//var popMargTop = ($('#' + popID).height() + 80) / 2;
	var popMargLeft = ($('#' + popID).width() + 80) / 2;

	$('#' + popID).css({
	//'margin-top' : -popMargTop,
	'margin-left' : -popMargLeft
	});

	$('body').css('overflow', 'hidden');
	$('body').append('<div id="fade"></div>');
	$('#fade').css({'filter' : 'alpha(opacity=80)'}).fadeIn();

	//get the IFRAME element
	var iframeRef = document.getElementById(popID).getElementsByTagName('iframe');
	//focus the IFRAME element
	$(iframeRef).focus();
	//use JQuery to find the control in the IFRAME and set focus
	$(iframeRef).contents().find('#keyword').focus().select();

	if(action == 'index') {
		var id = $(this).closest('tr').find('input#id').val();
		$('iframe#edit').attr('src', baseUrl+'/'+module+'/'+controller+'/edit/id/'+id);
	}

	return false;
}

function modalWindowClose() {
	$('#fade , .popup_block').fadeOut(function() {
		$('#fade, a.close').remove();
	});

	if(action == 'index') {
		$('iframe#edit').attr('src', baseUrl+'/index/blank');
	}
	$('body').css('overflow', 'auto');

	return false;
}

//Validate
function validate(formElementId, params){
	$('#status #success').hide();
	$('#status #warning').show();
	if($('form [name='+formElementId+']').hasClass('number')) {
		$('form [name='+formElementId+']').formatCurrency({ region: language });
	}
	var data={};
	data['element'] = formElementId;
	data[formElementId] = $('form [name='+formElementId+']').val();
	var url = baseUrl+'/'+module+'/'+controller+'/validate/id/'+id;
	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		cache: false,
		success: function(resp){
			$('form #'+formElementId).parent().find('.errors').remove();
			$('form #'+formElementId).removeClass('error');
			$('form#'+controller+' button').removeAttr('disabled');
			if(typeof resp[formElementId] !== 'undefined') {
				$('form #'+formElementId).parent().append(getErrorHtml(resp[formElementId], formElementId));
				$('form #'+formElementId).addClass('error');
				$('form#'+controller+' button').attr('disabled', 'disabled');
			} else {
				edit(data, params);
			}
		}
	});
}

function validateForm(formElementId, positionID){
	$('#status #success').hide();
	$('#status #warning').show();
	var url = baseUrl+'/'+module+'/'+controller+'/validate';
	var data = {};
	positionID = positionID || null;
	if(positionID) {
		url = baseUrl+'/'+window.parent.module+'/'+controller+'pos/validate';
		data['id'] = positionID;
		data['element'] = formElementId;
		data[formElementId] = $('.position'+positionID+' [name='+formElementId+']').val();
		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			cache: false,
			success: function(resp){
				$('.position'+positionID).find('.errors').remove();
				$('.position'+positionID+' #'+formElementId).removeClass('error');
				if(typeof resp[formElementId] !== 'undefined') {
					var error = '<ul id="errors-'+formElementId+'" class="errors">';
					var label = $('.position'+positionID+' #'+formElementId+'-label label').text();
					for(errorKey in resp[formElementId])
					{
						error += '<li>' + label + ': ' + resp[formElementId][errorKey] + '</li>';
					}
					error += '</ul>';
					$('.position'+positionID+' #'+formElementId).append(error);
					$('.position'+positionID+' #'+formElementId).addClass('error');
				} else {
					savePosition(positionID, formElementId);
				}
			}
		});
	} else {
		$('form input').each(function()
		{
			data[$(this).attr('name')] = $(this).val();
		});
		$('form select').each(function()
		{
			data[$(this).attr('name')] = $(this).val();
		});
		$.post(url,data,function(resp)
		{
			$('form #'+formElementId).parent().find('.errors').remove();
			$('form #'+formElementId).removeClass('error');
			$('form#'+controller+' button').removeAttr('disabled');
			if(typeof resp[formElementId] !== 'undefined') {
				$('form #'+formElementId).parent().append(getErrorHtml(resp[formElementId], formElementId));
				$('form #'+formElementId).addClass('error');
				$('form#'+controller+' button').attr('disabled', 'disabled');
			}
			//$('#datacheck').find('.errors').remove();
			//$('#datacheck').append(getErrorHtml(resp[formElementId], formElementId));
			//$('#status').text('Draft');
			//if(action != 'add' && action != 'select') save();
		},'json');
	}
}

function getErrorHtml(formErrors , formElementId){
	var error = '<ul id="errors-'+formElementId+'" class="errors">';
	var label = $('#'+formElementId+'-label label').text();
	for(errorKey in formErrors)
	{
		error += '<li>' + label + ': ' + formErrors[errorKey] + '</li>';
	}
	error += '</ul>';
	return error;
}

//Set location
function setLocation(location){
	window.location = location;
}
