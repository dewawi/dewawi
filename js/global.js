var isDirty = false;
var timeout = 0;
var setid = 0;

//Date picker options
var datePickerOptions = {
	autoHide: true,
	language: language
};

var Dewawi = {
	isDirty: false,
	searchTimeout: 0,
	sessionExpired: false,

	url: function (moduleName, controllerName, actionName, id) {
		var url = baseUrl + '/' + moduleName + '/' + controllerName + '/' + actionName;

		if (id !== undefined && id !== null && id !== '') {
			url += '/id/' + id;
		}

		return url;
	},

	setDirty: function (dirty) {
		isDirty = !!dirty;
		this.isDirty = !!dirty;
	},

	handleSessionExpired: function () {
		if (this.sessionExpired) {
			return;
		}

		this.sessionExpired = true;
		this.setDirty(false);

		window.location.reload();
	}
};

$.ajaxPrefilter(function (options, originalOptions, jqXHR) {
	jqXHR.statusCode({
		401: function () {
			Dewawi.handleSessionExpired();
		}
	});
});

$(document).ready(function(){
	//Keep alive
	setInterval(function(){
		if(action == 'edit') {
			$.get(baseUrl+'/'+module+'/'+controller+'/keepalive/id/'+id);
		} else if(action == 'index') {
			//Keep alive editable elements
			$('.editableValue:visible').each(function(e) {
				if(action == 'index') {
					lock($(this).closest('tr').find('input.id').val());
				}
			});
		}
	}, 60000); // 60 seconds

	//pushMessages(['Datensatz nicht gefunden oder nicht mehr verfügbar.']);

	//setInterval(function(){
	//		console.log(isDirty);
	//}, 1000); // 1 seconds

	//Client switcher
	$('#clientid').on('change', '', function() {
		//console.log($('#client').val());
			$.ajax({
				type: 'POST',
				async: true,
				url: baseUrl+'/users/user/client/clientid/'+$('#clientid').val(),
				cache: false,
				success: function(json){
					//response = json;
					//Dewawi.setDirty(false);
								//console.log(isDirty);
								location.reload();
				}
			});
	});

	//Language switcher
	$('#userinfo #language').on('change', '', function() {
		//console.log($('#language').val());
			$.ajax({
				type: 'POST',
				async: true,
				url: baseUrl+'/users/user/language/language/'+$('#language').val(),
				cache: false,
				success: function(json){
					//response = json;
					//Dewawi.setDirty(false);
								//console.log(isDirty);
								location.reload();
				}
			});
	});

	//Auto validate and save
	$('input.required').blur(function() {
		if(!$(this).val()) $(this).addClass('error');
		else $(this).removeClass('error');
	});

	$('.add form').on('change', 'input, textarea, select', function() {
		Dewawi.setDirty(true);
	});

	$('.edit form').on('change', 'input, textarea, select', function() {
		var $field = $(this);

		if($field.closest('form').is('[data-autosave="false"]')) {
			return;
		}

		if($field.hasClass('dw-permission-all')) {
			return;
		}

		if($field.closest('.dw-multiform__item').length) {
			return;
		}

		if($field.data('autocomplete-skip-autosave')) {
			return;
		}

		if((this.name != 'file[]') && (this.name != 'media[]') && (this.name != 'subfolder')) {
			Dewawi.setDirty(true);

			var data = {};
			var params = {};
			var value = this.value;

			if($field.is(':checkbox')) {
				value = $field.is(':checked') ? 1 : 0;
			}

			data[this.name] = value;

			if(typeof this.dataset.id !== 'undefined') params['id'] = this.dataset.id;
			if(typeof this.dataset.action !== 'undefined') params['action'] = this.dataset.action;
			if(typeof this.dataset.controller !== 'undefined') params['controller'] = this.dataset.controller;
			if(typeof this.dataset.module !== 'undefined') params['module'] = this.dataset.module;
			if(typeof this.dataset.ordering !== 'undefined') data['ordering'] = this.dataset.ordering;

			edit(data, params);
		}
	});

	$('.edit form').on('change', '.dw-multiform__item input, .dw-multiform__item textarea, .dw-multiform__item select', function() {
		if ((this.name === 'file[]') || (this.name === 'media[]') || (this.name === 'subfolder')) {
			return;
		}

		var $field = $(this);

		var $item = $field.closest('.dw-multiform__item');

		var entityId = $field.data('id') || $item.data('id');
		var entityModule = $field.data('module') || $item.data('module');
		var entityController = $field.data('controller') || $item.data('controller');

		if (!entityId || !entityModule || !entityController) {
			return;
		}

		var payload = {};
		var value = this.value;

		if ($field.is(':checkbox')) {
			value = $field.is(':checked') ? 1 : 0;
		}

		payload[this.name] = value;

		if (typeof this.dataset.ordering !== 'undefined') {
			payload.ordering = this.dataset.ordering;
		}

		var target = {
			module: entityModule,
			controller: entityController,
			action: 'edit',
			id: entityId
		};

		clearFieldState($field);

		var response = saveEntity(payload, target);

		if (!response) {
			markFieldError($field, ['Speichern fehlgeschlagen.']);
			return;
		}

		if (response.ok === false) {
			if (response.errors && response.errors[this.name]) {
				markFieldError($field, response.errors[this.name]);
			} else if (response.message) {
				markFieldError($field, [response.message]);
			} else {
				markFieldError($field, ['Speichern fehlgeschlagen.']);
			}
			return;
		}

		markFieldSaved($field);
	});
	$('.edit form input').on('textchange', function() {
		if((this.name != 'file[]') && (this.name != 'media[]') && (this.name != 'subfolder')) {
			if(!$(this).hasClass('datePicker')) {
				Dewawi.setDirty(true);
			}
		}
	});
	$('.edit form textarea').on('textchange', function() {
		Dewawi.setDirty(true);
	});
	//$('.edit form select').on('textchange', function() {
	//	Dewawi.setDirty(true);
	//});

	//Handle sub entities
	$('.dw-positions').on('change', 'input, textarea, select', function() {
		var $field = $(this);

		if (isSelectionField($field)) {
			return;
		}

		if ($field.hasClass('editableValue')) {
			return;
		}

		if ($field.hasClass('number')) {
			$field.formatCurrency({ region: language });
		}

		var $card = $field.closest('.dw-position-card');
		var $container = $field.closest('.dw-positions');
		var $set = $field.closest('.dw-position-set');

		var data = {};
		var params = {};

		params['id'] = $card.data('id');
		params['parentid'] = id;
		params['element'] = this.name;

		if (typeof this.dataset.id !== 'undefined') params['id'] = this.dataset.id;
		if (typeof this.dataset.action !== 'undefined') params['action'] = this.dataset.action;
		if (typeof this.dataset.controller !== 'undefined') params['controller'] = this.dataset.controller;
		if (typeof this.dataset.module !== 'undefined') params['module'] = this.dataset.module;
		if (typeof this.dataset.ordering !== 'undefined') data['ordering'] = this.dataset.ordering;

		var value = this.value;

		if ($field.is(':checkbox')) {
			value = $field.is(':checked') ? 1 : 0;
		}

		data[this.name] = value;

		var parent = $container.data('parent');
		var type = $container.data('type');

		if (this.name === 'ordering') {
			var ordering = parseInt(this.value, 10);
			var positionId = parseInt(params.id, 10);

			if (!positionId || !ordering) {
				return;
			}

			sortEntity({
				url: getSortUrl(
					'position',
					positionId,
					{
						parent: parent,
						type: type,
						parentid: window.id
					}
				),
				id: positionId,
				ordering: ordering,
				success: function () {
					getPositions(
						parent,
						type,
						window.pageYOffset
					);
				}
			});

			return;
		} else {
			editPosition(parent, type, data, params);
		}
	});
	$('.edit form').on('change', '.dw-permission-all', function() {
		var $all = $(this);
		var moduleName = $all.data('permission-module');
		var actionName = $all.data('permission-action');
		var checked = $all.is(':checked');

		var $items = $(
			'.dw-permission-item'
			+ '[data-permission-module="' + moduleName + '"]'
			+ '[data-permission-action="' + actionName + '"]'
		);

		var items = $items.filter(function() {
			return $(this).is(':checked') !== checked;
		}).toArray();

		function saveNext() {
			var element = items.shift();

			if (!element) {
				$all.prop('disabled', false);
				return;
			}

			var $element = $(element);
			var data = {};

			data[element.name] = checked ? 1 : 0;

			$element.prop('checked', checked);

			$.ajax({
				type: 'POST',
				url: Dewawi.url(
					$element.data('module'),
					$element.data('controller'),
					'edit',
					$element.data('id')
				),
				data: data,
				dataType: 'json',
				cache: false
			}).done(function(response) {
				if (response && response.ok === false) {
					$element.prop('checked', !checked);
				}
			}).fail(function() {
				$element.prop('checked', !checked);
			}).always(function() {
				saveNext();
			});
		}

		$all.prop('disabled', true);
		saveNext();
	});

	//Editable
	var previousValue;
	$('#content').on('click', '.editable', function() {
		var $editable = $(this);
		if(typeof id === 'undefined') {
			var id = $(this).closest('tr').find('input.id').val();
		}
		var type = $(this).data('type') || 'input';
		//Close and unlock all other elements
		var $editableValue = $(this).next('.editableValue');
		$('.editable').not(this).show();
		$('.editableValue:visible').each(function(e) {
			if($(this)[0] != $editableValue[0]) {
				$(this).hide();
				if(action == 'index') {
					if(id != $(this).closest('tr').find('input.id').val()) {
						unlock($(this).closest('tr').find('input.id').val());
					}
				}
			}
		});
		//Lock
		//if(action == 'index') {
			var lockResp = lock(id);
		//}
		//Check if there is a message
		if (lockResp && lockResp.ok === false && lockResp.message) {
			pushMessages(lockResp.message);
			return;
		} else {
			$(this).hide();
			if (!$editable.next(type).length) {
				var $input;

				if (type === 'input') {
					if ($editable.data('name') === 'password') {
						$editable.after('<input type="password">');
					} else {
						$editable.after('<input type="text">');
					}

					$input = $editable.next(type);

					if (!$editable.data('empty')) {
						$input.val($editable.text());
					}

				} else if (type === 'select') {
					$editable.after('<select></select>');
					$input = $editable.next(type);

					var pageSelect = $('select#' + $editable.data('name'));
					if (pageSelect.length && pageSelect.html()) {
						$input.html(pageSelect.html());
					} else {
						var response = {};

						$.ajax({
							type: 'POST',
							async: false,
							url: baseUrl + '/' + module + '/' + controller + '/get/element/' + $editable.data('name'),
							cache: false,
							dataType: 'json',
							success: function(json) {
								response = json;
							},
							error: function() {
								response = { ok: false, message: 'network_error' };
							}
						});

						if (response.ok === false) {
							pushMessages(response.message || 'network_error');
							$editable.show();
							$editable.next(type).remove();
							return;
						}

						$.each(response, function(key, value) {
							$input.append(
								$('<option></option>').val(key).html(value)
							);
						});
					}

					$input.val($editable.data('value'));

				} else if (type === 'textarea') {
					$editable.after('<textarea style="height:' + $editable.height() + 'px;"></textarea>');
					$input = $editable.next(type);

					if (!$editable.data('empty')) {
						$input.val($editable.text());
					}
				}

				$input.attr('class', 'editableValue');
				$input.attr('name', $editable.data('name'));
				$input.show();

				previousValue = $input.val();
				$input.focus();
			} else {
				$editableValue.show();
				$editableValue.focus();
			}
		}
	});
	$(document).on('click', function(e) {
		var $target = $(e.target);

		if (
			$target.closest('.editableContainer').length ||
			$target.closest('.editableValue').length
		) {
			return;
		}

		$('.editable').show();

		$('.editableValue:visible').each(function() {
			$(this).hide();
			unlock($(this).closest('tr').find('input.id[type="hidden"]').val() || $(this).closest('tr').find('input.id').not(':checkbox').val());
		});
	});
	$('#content').on('change', '.editableValue', function() {
		Dewawi.setDirty(true);
		var data = {};
		var params = {};

		// Get the value of the input field with the class "id" in the same row
		params['id'] = $(this).closest('tr').find('input.id').val();

		var value = this.value;

		// Handle checkbox value
		if($(this).is(':checkbox')) {
			value = $(this).is(':checked') ? 1 : 0;
		}

		data[this.name] = value;

		// Check dataset information on the current element and previous sibling
		var elements = [this.previousSibling, this]; // Combine current element and previous sibling for iteration
		elements.forEach(function(element) {
			if (element && element.dataset) {
				params['id'] = element.dataset.id || params['id'];
				params['action'] = element.dataset.action || params['action'];
				params['controller'] = element.dataset.controller || params['controller'];
				params['module'] = element.dataset.module || params['module'];
				data['ordering'] = element.dataset.ordering || data['ordering'];
			}
		});

		// Perform actions based on controller or action
		var response;
		if (params['action'] === 'add') {
			response = add(data, params);
			this.dataset.action = ''; // Reset action after add
		} else if (params['controller'] === 'positionset') {
			response = editPositionSet(data, params);
		} else {
			response = edit(data, params);
		}

		// NEU: Fehler sauber behandeln
		if (!response) {
			pushMessages(['Speichern fehlgeschlagen.']);
			return;
		}

		// SELECT update
		if(this.nodeName == 'SELECT') {
			var newValueRaw = (response.values && response.values[this.name] !== undefined)
				? response.values[this.name]
				: (response[this.name] !== undefined ? response[this.name] : this.value);

			// row class update bleibt
			if(String(newValueRaw).match(/^\d+$/)) {
				$(this).closest('tr').removeClass(this.name+previousValue);
				$(this).closest('tr').addClass(this.name+newValueRaw);
			} else {
				$(this).closest('tr').removeClass(previousValue);
				$(this).closest('tr').addClass(newValueRaw);
			}

			// display text
			var shown = getDisplayValue(response, this.name);

			// fallback: option text wenn display leer
			if(!shown) shown = $(this).find('option[value="'+newValueRaw+'"]').text();

			if(this.name == 'tagid') {
				$(this).prev('.editable').before('<span>'+shown+'</span>');
				$('.editable').show();
				$('.editableValue:visible').each(function() {
				$(this).hide();
				unlock($(this).closest('tr').find('input.id').val());
			});
			} else {
				$(this).prev('.editable').text(shown);
				previousValue = newValueRaw;
			}

			if(this.name == 'parentid') search();
		} else {
			// input/textarea
			var shown = getDisplayValue(response, this.name);
			$(this).prev('.editable').text(shown);
		}

		unlock(params['id']);
	});
	$('#data').on('change', '#activated', function() {
		//console.log($('#activated').is(':checked'));
		var data = {};
		var params = {};
		params['id'] = $(this).closest('tr').find('input.id').val();
			if(params['id']) {
			if($(this).is(':checked')) data[this.name] = 1;
				else data[this.name] = 0;
				edit(data, params);
			}
	});
	$('#data.permissions').on('change', 'input', function() {
		//console.log($('#activated').is(':checked'));
		var data = {};
		var params = {};
		params['id'] = $(this).closest('tr').find('input.id').val();
		data['controller'] = $(this).closest('td').find('input.controller').val();
		data['module'] = $(this).closest('td').find('input.module').val();
		data['element'] = this.name;
			if(params['id']) {
			if($(this).is(':checked')) data[this.name] = 1;
				else data[this.name] = 0;
				edit(data, params);
				//console.log(params);
				//console.log(data);
			}
	});

	// Change page title
	if (controller !== 'category') {
		$('.edit form').on('input', '[name="title"]', function () {
			$('.dw-page-header__title h2').text(this.value);
		});
	}

	//Focus on keyword
	if(action == 'index') {
		$('.dw-toolbar [name="keyword"]').first().focus().select();
	}

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
		const id = $(this).data('id');
		$('#catid').val(id);
		$.cookie('catid', id, { path: cookiePath });
		$('#pagination-page').val(1);
		search();
	});

	//Modal window
	$(document).on('click', 'button.poplight', function() {
		var popID = $(this).attr('rel');
		setid = $(this).closest('.dw-position-set').data('setid') || 0;
		modalWindow(popID, setid);
	});
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

	//Date picker
	$('.datePicker:not([readonly]):not(:disabled)').datepicker(datePickerOptions);

	$(document).on('click', '.datePickerLive', function() {
		if(this.readOnly || this.disabled) return;

		$(this).datepicker(datePickerOptions);
	});

	var from = new Date($('#from').val());
	var to = new Date($('#to').val());

	if($('#fromDatePicker').length) {
		var dateFromPickerOptions = {
					language: language,
			inline: true,
						container: $('#fromDatePicker')
		}
		//$.extend(dateFromPickerOptions, datePickerOptions)
		var datesFrom = $('#from').datepicker(dateFromPickerOptions);
	}

	if($('#toDatePicker').length) {
		var dateToPickerOptions = {
					language: language,
			inline: true,
						container: $('#toDatePicker')
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
	$('#content, .dw-positions').on('click', '.checkall', function() {
		$(this).closest('.dw-positions, #content')
			.find('input.position-check, input.check-id, [data-select-id]')
			.prop('checked', this.checked);
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
		dataType: 'json',
		success: function(json){
			response = json;
		},
		error: function(){
			response = { ok: false, message: 'network_error' };
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

//Pin
function pin(id) {
	$.ajax({
		type: 'POST',
		async: false,
		url: baseUrl+'/'+module+'/'+controller+'/pin/id/'+id,
		cache: false,
		success: function(json){
			if(action == 'index') search();
		}
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
			Dewawi.setDirty(false);
			document.getElementById(controller).submit();
		}
	} else if(action == 'password') {
		var data = {};
		data['passwordactual'] = $('#passwordactual').val();
		data['passwordnew'] = $('#passwordnew').val();
		data['passwordconfirm'] = $('#passwordconfirm').val();
		var url = baseUrl+'/'+module+'/'+controller+'/'+action;
		$.ajax({
			type: 'POST',
			async: false,
			url: url,
			data: data,
			cache: false,
			success: function(json){
				response = json;
				Dewawi.setDirty(false);
				//Close the modal window
				window.parent.modalWindowClose();
			}
		});
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
	params = params || null;

	if (params) {
		var url = baseUrl;

		if (params['module'] && params['module'] !== 'default') {
			url += '/' + params['module'];
		} else {
			url += '/' + module;
		}

		if (params['controller']) {
			url += '/' + params['controller'];
		} else {
			url += '/' + controller;
		}

		if (params['id']) {
			url += '/add/id/' + params['id'];
		} else {
			url += '/add/id/' + id;
		}

		var response = null;

		$.ajax({
			type: 'POST',
			async: false,
			url: url,
			data: data,
			cache: false,
			success: function(resp){
				response = resp;
				Dewawi.setDirty(false);

				// JSON error response
				if (typeof resp === 'object' && resp !== null && resp.ok === false) {
					if (resp.message) pushMessages([resp.message]);
					else pushMessages(['Speichern fehlgeschlagen.']);
					return;
				}

				// Try to detect JSON returned as text
				if (typeof resp === 'string' && resp.length && resp.charAt(0) === '{') {
					try {
						var json = JSON.parse(resp);
						if (json && json.ok === false) {
							if (json.message) pushMessages([json.message]);
							else pushMessages(['Speichern fehlgeschlagen.']);
							return;
						}
					} catch (e) {
						// not JSON, continue as HTML
					}
				}

				var parentId = data.parent_id || data.parentid || params['id'] || id;
				var $container = $('.dw-multiform-context[data-controller="' + params['controller'] + '"][data-parentid="' + parentId + '"]');

				if ($container.length) {
					$container.find('button.addMulti').before(resp);
					$container.find('.dw-multiform__item:last input:first, .dw-multiform__item:last textarea:first, .dw-multiform__item:last select:first').focus();
				}

				if (action == 'index') search();
			},
			error: function(){
				pushMessages(['Speichern fehlgeschlagen.']);
			}
		});

		return response;
	}

	// legacy branch unchanged for now
	data[controller+'id'] = id;
	if(data['module']) url = baseUrl+'/'+data['module'];
	else var url = baseUrl+'/'+module;
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
			Dewawi.setDirty(false);
			$('div#'+data['controller']+' button.add').before(response);
			$('div#'+data['controller']+' div:last input:first').focus().select();
			if(action == 'index') search();
		}
	});
}

//Edit
function edit(data, params) {
	var url = baseUrl;
	params = params || null;
	if(params && params['module']) url += '/'+params['module'];
	else url += '/'+module;
	if(data['tagid']) {
		url += '/tag/add/tagid/'+data['tagid'];
		if(params && params['id']) data['parentid'] = params['id'];
	} else {
		if(params && params['controller']) url += '/'+params['controller'];
		else url += '/'+controller;
		if(params && params['id']) url += '/edit/id/'+params['id'];
		else url += '/edit/id/'+id;
	}
	var response = null;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		data: data,
		cache: false,
		dataType: 'json',
		success: function(json){
			response = json;
			if (response && response.ok === false) {
				for (var field in data) {
					if (!data.hasOwnProperty(field)) continue;
					$('form #'+field).addClass('error');
					//console.log('form #'+field);
				}
				if (response.message === 'save_failed') pushMessages(['Speichern fehlgeschlagen.']);
				else if (response.message === 'not_found') pushMessages(['Datensatz nicht gefunden oder nicht mehr verfügbar.']);
				else pushMessages([response.message]);
			} else {
				Dewawi.setDirty(false);

				if (response.calculation || response.reloadPositions) {
					applySaveResponse(response, controller, 'pos', window.pageYOffset);
				}
			}
		},
		error: function(xhr){
			pushMessages(['Speichern fehlgeschlagen.']);
		}
	});
	//console.log(data);
	//console.log(params);
	return response;
}

//Search
function search() {
	var data = collectToolbarData();

	if (action === 'select') {
		data.parent = DewawiToolbar.getUrlParam('parent');
		data.setid = DewawiToolbar.getUrlParam('setid');
	}

	clearTimeout(Dewawi.searchTimeout);

	if (Dewawi.searchRequest) {
		Dewawi.searchRequest.abort();
		Dewawi.searchRequest = null;
	}

	Dewawi.searchTimeout = setTimeout(function () {
		$('#loading').show();

		Dewawi.searchRequest = $.ajax({
			type: 'POST',
			url: baseUrl + '/' + module + '/' + controller + '/search',
			data: data,
			cache: false,

			success: function (response) {
				$('#content').html(response);
				initDwTabs('#content');
			},

			error: function (xhr, status) {
				if (status === 'abort' || xhr.status === 401) {
					return;
				}

				pushMessages([
					'Suche konnte nicht ausgeführt werden.'
				]);
			},

			complete: function () {
				Dewawi.searchRequest = null;
				$('#loading').hide();
			}
		});
	}, 300);
}

function collectToolbarData() {
	var data = {};

	$('.dw-toolbar input, .dw-toolbar select, .dw-filter-panel input, .dw-filter-panel select, .dw-pagination input, .dw-pagination select').each(function () {
		var $field = $(this);
		var name = String($field.attr('name') || '');

		if (!name) {
			return;
		}

		if (name.slice(-2) === '[]') {
			name = name.slice(0, -2);

			if (!data[name]) {
				data[name] = [];
			}

			if ($field.is(':checked')) {
				data[name].push($field.val());
			}

			return;
		}

		if ($field.is(':radio')) {
			if ($field.is(':checked')) {
				data[name] = $field.val();
			}

			return;
		}

		if ($field.is(':checkbox')) {
			data[name] = $field.is(':checked') ? 1 : 0;
			return;
		}

		data[name] = $field.val();
	});

	return data;
}

//Trash
function trash(ids, message, type, cmodule) {
	type = type || controller;
	cmodule = cmodule || module;

	if (!Array.isArray(ids)) {
		ids = [ids]; // ensure it's an array
	}

	if (ids.length === 0) return;

	var answer = confirm(message);
	if (!answer) return;

	if(action == 'add') {
		ids.forEach(function(singleId) {
			$('div#' + type + singleId).remove();
		});
	} else {
		//console.log(ids);
		$.ajax({
			type: 'POST',
			url: baseUrl+'/trash/add/',
			contentType: 'application/json',
			data: JSON.stringify({
				module: cmodule,
				controller: type,
				id: ids
			}),
			cache: false,
			success: function(data){
				if(action == 'edit') {
					ids.forEach(function(singleId) {
						$('div#' + type + singleId).remove();
					});
					if (module === 'contacts'
						&& controller === 'contact'
						&& $('#tabhistory').hasClass('is-active')) {
						reloadHistory();
						return;
					}
					if(type == controller) window.location = baseUrl+'/'+cmodule+'/'+controller;
				} else {
					search();
				}
			}
		});
	}
}

function deleteAttachment(ids, message, type, cmodule) {
	type = type || controller;
	cmodule = cmodule || module;

	if (!Array.isArray(ids)) {
		ids = [ids]; // ensure it's an array
	}

	if (ids.length === 0) return;

	var answer = confirm(message);
	if (!answer) return;

	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+cmodule+'/'+type+'/delete/',
		contentType: 'application/json',
		data: JSON.stringify({
			id: ids
		}),
		cache: false,
		success: function(data){
			ids.forEach(function(singleId) {
				$('div#' + type + singleId).remove();
			});
		}
	});
}

//Apply position
function applyPosition(parent, type, itemIds, setid) {
	var ids = Array.isArray(itemIds) ? itemIds.slice() : [itemIds];
	var lastResponse = null;

	setid = setid || 0;

	function applyNext() {
		var itemId = ids.shift();

		if (!itemId) {
			if (lastResponse) {
				window.parent.applyCalculation(
					lastResponse.calculation
				);
			}

			window.parent.getPositions(parent, type, window.parent.pageYOffset);

			if (typeof window.parent.modalWindowClose === 'function') {
				window.parent.modalWindowClose();
			}

			return;
		}

		$.ajax({
			type: 'POST',
			url: window.parent.baseUrl
				+ '/' + window.parent.module
				+ '/position/add/setid/' + setid
				+ '/parent/' + parent
				+ '/type/' + type
				+ '/parentid/' + window.parent.id
				+ '/itemid/' + itemId,
			dataType: 'json',
			cache: false,
			success: function(response) {
				if (!response || response.ok !== true) {
					pushMessages([
						'Position konnte nicht hinzugefügt werden.'
					]);
					return;
				}

				lastResponse = response;

				applyNext();
			},
			error: function() {
				pushMessages([
					'Position konnte nicht hinzugefügt werden.'
				]);
			}
		});
	}

	applyNext();
}

function applySaveResponse(response, parent, type, scrollTo) {
	applyCalculation(response.calculation);

	if (response.reloadPositions) {
		getPositions(parent, type, scrollTo);
	}
}

function applyCalculation(calculation) {
	if (!calculation || !Object.keys(calculation).length) {
		return;
	}

	$('#subtotal').text(calculation.subtotal);
	$('#total').text(calculation.total);

	$.each(calculation.taxes, function(rate, value) {
		if (rate !== 'total') {
			$('[data-rate="' + rate + '"]').text(value);
		}
	});

	$.each(calculation, function(positionId, values) {
		if (!values || typeof values !== 'object' || values.total === undefined) {
			return;
		}

		$('.dw-position-card[data-id="' + positionId + '"] .total').text(values.total);
	});
}

//Edit position
function editPosition(parent, type, data, params) {
	var url;

	if (params.controller === 'pricerulepos') {
		url = baseUrl + '/' + params.module + '/' + params.controller + '/edit/id/' + params.id;
	} else {
		url = baseUrl + '/' + module + '/position/edit';

		if (params.id) {
			url += '/id/' + params.id;
		}

		url += '/parent/' + parent + '/type/' + type + '/parentid/' + params.parentid;
	}

	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		cache: false,
		dataType: 'json',
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Speichern fehlgeschlagen.'
				]);
				return;
			}

			Dewawi.setDirty(false);

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Add position
function addPosition(parent, type, setid) {
	if (typeof setid === 'undefined' || setid === null || setid === '') {
		setid = 0;
	}
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id,
		dataType: 'json',
		cache: false,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Position konnte nicht hinzugefügt werden.'
				]);
				return;
			}

			Dewawi.setDirty(false);

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Copy Position
function copyPosition(parent, type, positionID){
	if (typeof setid === 'undefined' || setid === null || setid === '') {
		setid = 0;
	}
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/position/copy/parent/'+parent+'/type/'+type+'/id/'+positionID+'/parentid/'+id,
		dataType: 'json',
		cache: false,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Position konnte nicht kopiert werden.'
				]);
				return;
			}

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Delete Position
function deletePosition(parent, type, positionID, setid, masterid){
	var data = {};
	data.id = positionID;
	data.setid = setid;
	data.masterid = masterid || null;
	data.parentid = id;
	data.delete = 'Yes';
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/position/delete/parent/'+parent+'/type/'+type,
		dataType: 'json',
		cache: false,
		data: data,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Position konnte nicht gelöscht werden.'
				]);
				return;
			}

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Get Positions
function getPositions(parent, type, scrollTo) {
	scrollTo = scrollTo || null;
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/position/index/parent/'+parent+'/type/'+type+'/parentid/'+id,
		cache: false,
		success: function(data){
			$('.dw-positions[data-parent="'+parent+'"]').html(data);
			autosize($('.dw-positions').find('textarea'));
			if(data) $('#tabpositions .toolbar.positions.bottom').show();
			else $('#tabpositions .toolbar.positions.bottom').hide();
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

//Add option
function addOption(parent, type, optionid, setid, masterid) {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id+'/optionid/'+optionid+'/masterid/'+masterid,
		dataType: 'json',
		cache: false,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Position konnte nicht hinzugefügt werden.'
				]);
				return;
			}

			$('#status #warning').hide();
			$('#status #success').show();

			Dewawi.setDirty(false);

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Add Set
function addSet(parent, type) {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/positionset/add/parent/'+parent+'/type/'+type+'/parentid/'+id,
		dataType: 'json',
		cache: false,
		success: function(){
			$('#status #warning').hide();
			$('#status #success').show();
			Dewawi.setDirty(false);
			getPositions(parent, type, $(document).height());
		}
	});
}

//Copy Set
function copySet(parent, type, setid){
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/positionset/copy/parent/'+parent+'/type/'+type+'/id/'+setid+'/parentid/'+id,
		dataType: 'json',
		cache: false,
		success: function(){
			getPositions(parent, type, window.pageYOffset);
		}
	});
}

//Delete Set
function deleteSet(parent, type, setid){
	var data = {};
	data.id = setid;
	data.parentid = id;
	data.delete = 'Yes';
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/positionset/delete/parent/'+parent+'/type/'+type,
		cache: false,
		data: data,
		success: function(){
			getPositions(parent, type, window.pageYOffset);
		}
	});
}

//Edit position
function editPositionSet(data, params) {
	var response;
	var url = baseUrl+'/'+module+'/positionset';
	if(params['id']) url += '/edit/id/'+params['id'];
	url += '/parent/'+controller+'/parentid/'+id;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		data: data,
		cache: false,
		success: function(json){
			Dewawi.setDirty(false);
			if((params['element'] == 'price') || (params['element'] == 'quantity') || (params['element'] == 'priceruleamount') || (params['element'] == 'priceruleaction')) {
				$('table#total #subtotal').text(json['subtotal']);
				$('table#total #total').text(json['total']);
				$('tr.position'+params['id']+'.wrap').find('.total').text(json[params['id']]['total']);
								$.each(json['taxes'], function(key, val) {
										if(key != 'total') $('td[data-rate="'+key+'"]').text(val);
								});
			} else if(params['element'] == 'taxrate') {
				getPositions(controller, $(document).height());
			}
			response = json;
		}
	});
	return response;
}

function addPriceRule(parent, type, positionId) {
	$.ajax({
		type: 'POST',
		url: Dewawi.url('items', 'pricerulepos', 'add'),
		data: {
			parentid: positionId,
			parent_module: module,
			parent_controller: parent + type
		},
		dataType: 'json',
		cache: false,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Preisregel konnte nicht hinzugefügt werden.'
				]);
				return;
			}

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

function deletePriceRule(parent, type, priceRuleId) {
	$.ajax({
		type: 'POST',
		url: Dewawi.url('items', 'pricerulepos', 'delete', priceRuleId),
		dataType: 'json',
		cache: false,
		success: function(response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Preisregel konnte nicht gelöscht werden.'
				]);
				return;
			}

			applySaveResponse(response, parent, type, window.pageYOffset);
		}
	});
}

//Get Email Messages
function getEmailmessages(scrollTo) {
	var contactid = 0;

	if (module === 'contacts') {
		contactid = Number(id) || 0;
	} else {
		contactid = Number(
			$('[name="contactid"]').first().val()
		) || 0;
	}

	if (contactid <= 0) {
		$('#emailmessages').html('');
		return;
	}

	var data = {};

	if (controller !== 'contact') {
		data.documentid = id;
		data.module = module;
		data.controller = controller;
	}

	scrollTo = scrollTo || null;

	$.ajax({
		type: 'POST',
		url: baseUrl
			+ '/contacts/email/index/contactid/'
			+ contactid,
		data: data,
		cache: false,
		success: function (response) {
			$('#emailmessages').html(response);

			autosize(
				$('#emailmessages').find('textarea')
			);

			if (response) {
				$('#tabpositions .toolbar.emailmessages.bottom')
					.show();
			} else {
				$('#tabpositions .toolbar.emailmessages.bottom')
					.hide();
			}

			if (scrollTo) {
				window.scrollTo(0, scrollTo);
			}
		}
	});
}

function sendMessage() {
	var $form = $('#emailmessage');

	var bodyElement = $form
		.find('[name="body"]')
		.get(0);

	var editor = bodyElement
		? tinymce.get(bodyElement.id)
		: null;

	var contactid = 0;
	var campaignid = 0;
	var url = '';

	$('#output').hide().html('');

	var data = {
		recipient:
			$form.find('[name="recipient"]').val() || '',
		cc:
			$form.find('[name="cc"]').val() || '',
		bcc:
			$form.find('[name="bcc"]').val() || '',
		replyto:
			$form.find('[name="replyto"]').val() || '',
		subject:
			$form.find('[name="subject"]').val() || '',
		body: editor
			? editor.getContent()
			: (
				$form.find('[name="body"]').val()
				|| ''
			),
		module: module,
		controller: controller,
		files: {}
	};

	$form
		.find(
			'#attachments input[type="checkbox"]'
			+ '[name="file[]"]:checked'
		)
		.each(function () {
			data.files[this.value] = this.value;
		});

	if (module === 'contacts') {
		contactid = Number(id) || 0;

		url = baseUrl
			+ '/contacts/email/send/contactid/'
			+ contactid;
	} else if (module === 'campaigns') {
		campaignid = Number(id) || 0;

		url = baseUrl
			+ '/contacts/email/send/campaignid/'
			+ campaignid;
	} else {
		contactid = Number(
			$('[name="contactid"]').first().val()
		) || 0;

		url = baseUrl
			+ '/contacts/email/send/contactid/'
			+ contactid
			+ '/documentid/'
			+ id;
	}

	if (contactid <= 0 && campaignid <= 0) {
		$('#output')
			.html(
				'Nachricht konnte nicht gesendet werden: '
				+ 'Kontakt fehlt.'
			)
			.show();

		return;
	}

	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		dataType: 'json',
		cache: false,
		success: function (response) {
			if (
				response
				&& response.ok === false
			) {
				$('#output')
					.html(
						response.message
						|| 'Nachricht konnte nicht gesendet werden.'
					)
					.show();

				return;
			}

			getEmailmessages(
				window.pageYOffset
			);
		},
		error: function (xhr) {
			$('#output')
				.html(
					'Nachricht konnte nicht gesendet werden.'
				)
				.show();

			console.log(
				'sendMessage error',
				xhr.responseText
			);
		}
	});
}

function resendMessage(messageid){
	var url = baseUrl+'/contacts/email/send/messageid/'+messageid;
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		success: function(response){
			getEmailmessages(window.pageYOffset);
		}
	});
}

// helper: display bevorzugen
function getDisplayValue(resp, field) {
	if(resp && resp.display && resp.display[field] !== undefined) return resp.display[field];
	if(resp && resp[field] !== undefined) return resp[field];
	if(resp && resp.values && resp.values[field] !== undefined) return resp.values[field];
	return '';
}

//Ordering
var sortRequestPending = false;

function sortEntity(options) {
	if (sortRequestPending) {
		return;
	}

	if (!options || !options.url || !options.id) {
		pushMessages([
			'Sortierparameter fehlen.'
		]);
		return;
	}

	var data = {
		id: options.id
	};

	if (
		options.direction === 'up'
		|| options.direction === 'down'
	) {
		data.direction = options.direction;
	} else if (parseInt(options.ordering, 10) > 0) {
		data.ordering = parseInt(
			options.ordering,
			10
		);
	} else {
		pushMessages([
			'Ungültiges Sortierziel.'
		]);
		return;
	}

	sortRequestPending = true;

	$.ajax({
		type: 'POST',
		url: options.url,
		data: data,
		dataType: 'json',
		cache: false,
		success: function (response) {
			if (!response || response.ok !== true) {
				pushMessages([
					response && response.message
						? response.message
						: 'Sortierung fehlgeschlagen.'
				]);

				return;
			}

			if (typeof options.success === 'function') {
				options.success(response);
			}
		},
		error: function (xhr) {
			pushMessages([
				'Sortierung fehlgeschlagen.'
			]);

			console.log(
				'sortEntity error',
				xhr.responseText
			);
		},
		complete: function () {
			sortRequestPending = false;
		}
	});
}

function getSortUrl(controller, id, params) {
	var url = Dewawi.url(
		module,
		controller,
		'sort',
		id
	);

	$.each(params || {}, function (name, value) {
		if (
			value === undefined
			|| value === null
			|| value === ''
		) {
			return;
		}

		url += '/'
			+ encodeURIComponent(name)
			+ '/'
			+ encodeURIComponent(value);
	});

	return url;
}

function pushMessages(messages){
	// normalize to array of strings
	if (messages == null) return;

	if (typeof messages === 'string') {
		messages = [messages];
	} else if (Array.isArray(messages)) {
	// ok
	} else if (typeof messages === 'object') {
		// support {message:".."} or {messages:[..]}
		if (messages.messages) messages = messages.messages;
		else if (messages.message) messages = [messages.message];
		else return;
	} else {
		messages = [String(messages)];
	}

	$.each(messages, function(key, value) {
		$('div#content').prepend('<div id="messages"><ul><li>'+value+'</li></ul></div>');
	});
	removeMessages();
}

function handleEditError(resp, params) {
	// 1) not_found -> Meldung + optional redirect
	if (resp && resp.message === 'not_found') {
		pushMessages(['Datensatz nicht gefunden oder nicht mehr verfügbar.']);
		// optional: sofort zurück zur Liste
		// setLocation(baseUrl+'/'+module+'/'+controller);
		unlock(params['id']);
		return true;
	}

	// 2) errors (Validierung)
	if (resp && resp.errors) {
		// hier werden alle Fehlertexte gesammelt und angezeigt
		pushMessages({ errors: resp.errors });
		unlock(params['id']);
		return true;
	}

	// 3) message (save_failed etc.)
	if (resp && resp.message) {
		// du kannst hier übersetzen, wenn du willst
		if (resp.message === 'save_failed') pushMessages(['Speichern fehlgeschlagen.']);
		else pushMessages([resp.message]);
		unlock(params['id']);
		return true;
	}

	// 4) unbekannt
	pushMessages(['Speichern nicht möglich.']);
	unlock(params['id']);
	return true;
}

function isSelectionField($field) {
	return $field.is('.checkall') || $field.attr('name') === 'ids[]';
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
function previewPdf() {
	$.ajax({
		type: 'POST',
		url: baseUrl + '/' + module + '/' + controller + '/preview/id/' + id + '/templateid/' + $('#templateid').val(),
		dataType: 'json',
		success: function(response) {
			if (!response.ok || !response.url) {
				return;
			}

			$('#output').html(
				'<iframe src="' + response.url + '" width="100%" height="700"></iframe>'
			);
		}
	});
}

//Modal Window
function modalWindow(popID, setid) {
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

//Set location
function setLocation(location){
	window.location = location;
}

function saveEntity(payload, target) {
	payload = payload || {};
	target = target || {};

	var requestPayload = $.extend({}, payload);

	var params = {};

	if (target.id !== undefined && target.id !== null && target.id !== '') {
		params.id = target.id;
	}

	if (target.action) {
		params.action = target.action;
	} else {
		params.action = 'edit';
	}

	if (target.module) {
		params.module = target.module;
	}

	if (target.controller) {
		params.controller = target.controller;
	}

	return edit(requestPayload, params);
}

function createEntity(payload, target, context, $container) {
	payload = payload || {};
	target = target || {};
	context = context || {};

	var requestPayload = $.extend({}, payload);

	if (context.parentModule) requestPayload.parent_module = context.parentModule;
	if (context.parentController) requestPayload.parent_controller = context.parentController;
	if (context.parentId) requestPayload.parentid = context.parentId;

	var url = baseUrl;

	if (
		target.module
		&& target.module !== 'default'
	) {
		url += '/'
			+ encodeURIComponent(target.module);
	}

	url += '/'
		+ encodeURIComponent(target.controller);

	url += '/'
		+ encodeURIComponent(
			target.action || 'add'
		);

	if (
		target.id !== undefined
		&& target.id !== null
		&& target.id !== ''
	) {
		url += '/id/'
			+ encodeURIComponent(target.id);
	}

	$.ajax({
		type: 'POST',
		url: url,
		data: requestPayload,
		cache: false,
		success: function(response) {
			Dewawi.setDirty(false);

			if (
				response
				&& typeof response === 'object'
				&& response.ok === false
			) {
				pushMessages([
					response.message || 'Speichern fehlgeschlagen.'
				]);

				return;
			}

			if (
				response
				&& response.ok === true
				&& response.reloadPositions
			) {
				var $positions = $container.closest('.dw-positions');

				applySaveResponse(
					response,
					$positions.data('parent'),
					$positions.data('type'),
					window.pageYOffset
				);

				return;
			}

			if (!$container || !$container.length) {
				return;
			}

			var $list = $container.find(
				'> .dw-multiform > .dw-multiform__list'
			).first();

			if (!$list.length) {
				pushMessages([
					'MultiForm-Liste wurde nicht gefunden.'
				]);

				return;
			}

			var $newItem = $(response);

			if (!$newItem.hasClass('dw-multiform__item')) {
				pushMessages([
					'Ungültige MultiForm-Antwort.'
				]);

				return;
			}

			$list.append($newItem);

			$newItem
				.find('input, textarea, select')
				.filter(':visible')
				.first()
				.focus()
				.select();

			if (action === 'index') {
				search();
			}
		},
		error: function() {
			pushMessages(['Speichern fehlgeschlagen.']);
		}
	});
}

function deleteEntity(ids, target, context) {
	target = target || {};
	context = context || {};

	if (!Array.isArray(ids)) {
		ids = [ids];
	}

	ids = ids.filter(function(singleId) {
		return singleId !== undefined && singleId !== null && singleId !== '';
	});

	if (!ids.length) {
		return;
	}

	var requestTarget = {
		module: target.module || module,
		controller: target.controller || controller,
		parent_module: context.parentModule || null,
		parent_controller: context.parentController || null,
		parent_id: context.parentId || null
	};

	return trash(
		ids,
		deleteConfirm,
		requestTarget.controller,
		requestTarget.module
	);
}

function clearFieldState($field) {
	if (!$field || !$field.length) return;

	$field.removeClass('is-invalid is-valid');
	$field.next('.dw-field-error').remove();
}

function markFieldError($field, messages) {
	if (!$field || !$field.length) return;

	clearFieldState($field);
	$field.addClass('is-invalid');

	if (!Array.isArray(messages)) {
		messages = messages ? [messages] : [];
	}

	if (!messages.length) return;

	var html = '<div class="dw-field-error">';
	for (var i = 0; i < messages.length; i++) {
		html += '<div>' + messages[i] + '</div>';
	}
	html += '</div>';

	$field.after(html);
}

function markFieldSaved($field) {
	if (!$field || !$field.length) return;

	clearFieldState($field);
	$field.addClass('is-valid');

	window.setTimeout(function() {
		$field.removeClass('is-valid');
	}, 1200);
}

(function () {
	$(document).on('input', '.autocomplete', function () {
		var $input = $(this);
		var query = $.trim($input.val());
		var minLength =
			Number(
				$input.data('autocomplete-min-length')
			) || 2;

		var timer = $input.data('autocomplete-timer');

		if (timer) {
			clearTimeout(timer);
		}

		if (query.length < minLength) {
			closeAutocomplete($input);
			return;
		}

		timer = setTimeout(function () {
			loadAutocomplete($input, query);
		}, 250);

		$input.data(
			'autocomplete-timer',
			timer
		);
	});

	$(document).on('click', '.autocomplete__item', function () {
		var $item = $(this);
		var $list = $item.closest('.autocomplete__list');
		var $input = $list.data('input');

		var id = Number($item.data('id'));
		var apply = String($list.data('apply') || '');

		if (!id) {
			$list.remove();
			return;
		}

		if (apply === 'contact') {
			applyContact(id);
		}

		if (apply === 'position' && $input && $input.length) {
			var $positionSet = $input.closest('.dw-position-set');

			var parent = String(
				$positionSet.data('parent') || ''
			);

			var type = String(
				$positionSet.data('type') || 'pos'
			);

			var setId = Number(
				$positionSet.data('setid')
			) || 0;

			if (!parent) {
				console.log(
					'position autocomplete parent is missing'
				);

				$list.remove();
				return;
			}

			applyPosition(parent, type, id, setId);

			$input.val('');
		}

		$list.remove();
	});

	$(document).on('click', function (event) {
		if (!$(event.target).closest('.autocomplete, .autocomplete__list').length) {
			$('.autocomplete__list').remove();
		}
	});

	function loadAutocomplete($input, query) {
		var source = String($input.data('autocomplete-source') || '');
		var apply = String($input.data('autocomplete-apply') || '');

		if (!source) {
			return;
		}

		$.ajax({
			type: 'GET',
			url: baseUrl + source,
			data: { q: query },
			dataType: 'json',
			cache: false,
			success: function (response) {
				renderAutocomplete($input, response.items || [], apply);
			},
			error: function (xhr) {
				console.log('autocomplete failed', xhr.responseText);
			}
		});
	}

	function renderAutocomplete($input, items, apply) {
		closeAutocomplete($input);

		if (!items.length) {
			return;
		}

		var offset = $input.offset();
		var inputWidth = $input.outerWidth();

		var html = '<div'
			+ ' class="autocomplete__list"'
			+ ' data-apply="' + escapeHtml(apply) + '"'
			+ '>';

		for (var i = 0; i < items.length; i++) {
			var item = items[i] || {};
			var itemId = Number(item.id);

			if (!itemId) {
				continue;
			}

			html += '<div'
				+ ' class="autocomplete__item"'
				+ ' data-id="' + itemId + '"'
				+ '>';

			html += '<div class="autocomplete__label">'
				+ escapeHtml(item.label || '')
				+ '</div>';

			if (item.subtitle) {
				html += '<div class="autocomplete__subtitle">'
					+ escapeHtml(item.subtitle)
					+ '</div>';
			}

			html += '</div>';
		}

		html += '</div>';

		var $list = $(html).appendTo('body');

		$list.data('input', $input);

		$list.css({
			position: 'absolute',
			top: offset.top + $input.outerHeight(),
			left: offset.left,
			width: inputWidth,
			zIndex: 9999
		});
	}

	function closeAutocomplete($input) {
		$('.autocomplete__list').remove();
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}
})();

// Toolbar
(function ($) {
	'use strict';

	var DewawiToolbar = {
		actionConfig: {
			add: { selection: 'none' },
			'add-position': { selection: 'none' },
			'add-position-set': { selection: 'none' },
			'copy-position': { selection: 'position' },
			'delete-position': { selection: 'position' },
			'copy-selected-position': { selection: 'none' },
			'delete-selected-position': { selection: 'none' },
			'copy-position-set': { selection: 'position-set' },
			'delete-position-set': { selection: 'position-set' },

			'multi-add': { selection: 'none' },
			filter: { selection: 'none' },
			reset: { selection: 'none' },
			clear: { selection: 'none' },
			'clear-filter': { selection: 'none' },

			edit: { selection: 'single' },
			view: { selection: 'single' },
			pdf: { selection: 'single' },
			cancel: { selection: 'single' },

			apply: { selection: 'multiple' },
			copy: { selection: 'multiple' },
			delete: { selection: 'multiple' },

			'media-delete': { selection: 'single' },
			'add-option': { selection: 'none' },
			'sortup': { selection: 'single' },
			'sortdown': { selection: 'single' },
			'sort-position-up': { selection: 'position' },
			'sort-position-down': { selection: 'position' },
			'sort-position-set-up': { selection: 'position-set' },
			'sort-position-set-down': { selection: 'position-set' }
		},

		init: function () {
			this.bindActions();
			this.bindFilters();
			this.bindKeyword();
		},

		bindActions: function () {
			$(document).on('click', '[data-action]', function (event) {
				var $button = $(this);
				var actionName = String($button.data('action') || '');

				if (!actionName) {
					return;
				}

				event.preventDefault();
				DewawiToolbar.runAction(actionName, $button);
			});
		},

		runAction: function (actionName, $button) {
			var config = this.actionConfig[actionName];
			var handler = this.actions[actionName];
			var selection;

			if (!config || !handler) {
				return;
			}

			if (config.selection === 'position') {
				selection = this.resolvePosition($button);

				if (!selection.id) {
					return;
				}

				handler.call(this, selection, $button);
				return;
			}

			if (config.selection === 'position-set') {
				selection = this.resolvePositionSet($button);

				if (!selection.setid && actionName !== 'add-position-set') {
					return;
				}

				handler.call(this, selection, $button);
				return;
			}

			selection = this.resolveSelection($button);

			if (!this.validateSelection(config.selection, selection)) {
				return;
			}

			handler.call(this, selection, $button);
		},

		resolveSelection: function ($button) {
			var row = this.getButtonTarget($button);

			if (row.id) {
				return {
					type: 'row',
					ids: [row.id],
					module: row.module,
					controller: row.controller
				};
			}

			return {
				type: 'bulk',
				ids: this.getSelectedIds(),
				module: module,
				controller: controller
			};
		},

		validateSelection: function (mode, selection) {
			var count = selection.ids.length;

			if (mode === 'none') {
				return true;
			}

			if (mode === 'single') {
				return count === 1;
			}

			if (mode === 'multiple') {
				return count >= 1;
			}

			return false;
		},

		resolvePosition: function ($button) {
			var $card = $button.closest('.dw-position-card');

			if (!$card.length) {
				var $checked = $button
					.closest('.dw-position-set')
					.find('.position-check:checked')
					.first();

				$card = $checked.closest('.dw-position-card');
			}

			return {
				id: String($card.data('id') || ''),
				parent: String($card.data('parent') || controller),
				type: String($card.data('type') || 'pos'),
				setid: String($card.data('setid') || '0'),
				masterid: String($card.data('masterid') || '')
			};
		},

		resolvePositions: function ($button) {
			var positions = [];

			$button
				.closest('.dw-position-set')
				.find('.position-check:checked')
				.each(function () {
					var $card = $(this).closest('.dw-position-card');

					positions.push({
						id: String($card.data('id') || this.value || ''),
						parent: String($card.data('parent') || controller),
						type: String($card.data('type') || 'pos'),
						setid: String($card.data('setid') || '0'),
						masterid: String($card.data('masterid') || '')
					});
				});

			return positions;
		},

		resolvePositionSet: function ($button) {
			var $container = $button.closest(
				'.dw-positions'
			);
			var $set = $button.closest(
				'.dw-position-set'
			);
			var setId = String(
				$set.data('setid') || ''
			);

			return {
				id: setId,
				setid: setId,
				parent: String(
					$container.data('parent')
					|| controller
				),
				type: String(
					$container.data('type')
					|| 'pos'
				)
			};
		},

		actions: {
			add: function () {
				var url = Dewawi.url(module, controller, 'add');

				[
					'type',
					'catid',
					'parentid',
					'shopid'
				].forEach(function (key) {
					var value = Dewawi.getParam(key);

					if (value && value !== '0') {
						url += '/' + key + '/' + encodeURIComponent(value);
					}
				});

				setLocation(url);
			},

			copy: function (selection) {
				this.copyEntities(selection);
			},

			'add-position': function (selection, $button) {
				var $container = $button.closest('.dw-positions');
				var $set = $button.closest('.dw-position-set');

				addPosition(
					String($container.data('parent') || controller),
					String($container.data('type') || 'pos'),
					String($set.data('setid') || 0)
				);
			},

			'delete-position': function (selection) {
				deletePosition(
					selection.parent,
					selection.type,
					selection.id,
					selection.setid,
					selection.masterid
				);
			},

			'copy-position': function (selection) {
				copyPosition(
					selection.parent,
					selection.type,
					selection.id
				);
			},

			'copy-selected-position': function (selection, $button) {
				this.resolvePositions($button).forEach(function (position) {
					copyPosition(position.parent, position.type, position.id);
				});
			},

			'delete-selected-position': function (selection, $button) {
				var positions = this.resolvePositions($button);

				if (!positions.length) {
					return;
				}

				positions.forEach(function (position) {
					deletePosition(
						position.parent,
						position.type,
						position.id,
						position.setid,
						position.masterid
					);
				});
			},

			'add-position-set': function (selection, $button) {
				var $container = $button.closest('.dw-positions');

				addSet(
					String($container.data('parent') || controller),
					String($container.data('type') || 'pos')
				);
			},

			'copy-position-set': function (selection) {
				copySet(selection.parent, selection.type, selection.setid);
			},

			'delete-position-set': function (selection) {
				deleteSet(selection.parent, selection.type, selection.setid);
			},

			'multi-add': function (selection, $button) {
				var $container = $button.closest('.dw-multiform-context');

				var target = {
					module: $button.data('module'),
					controller: $button.data('controller'),
					action: 'add'
				};

				var context = {
					parentModule: $container.data('parent-module'),
					parentController: $container.data('parent-controller'),
					parentId: $container.data('parentid')
				};

				if (
					!target.module
					|| !target.controller
					|| !context.parentModule
					|| !context.parentController
					|| !context.parentId
				) {
					console.error(
						'MultiForm context is incomplete.',
						{
							target: target,
							context: context
						}
					);

					return;
				}

				createEntity(
					{},
					target,
					context,
					$container
				);
			},

			filter: function () {
				$('.dw-filter-panel').toggleClass('is-open');
			},

			reset: function () {
				this.resetAll();
				search();
			},

			clear: function (selection, $button) {
				this.resetField($button.attr('rel'));
				search();
			},

			'clear-filter': function (selection, $button) {
				this.resetField($button.data('filter'));
				search();
			},

			edit: function (selection) {
				setLocation(
					Dewawi.url(selection.module, selection.controller, 'edit', selection.ids[0])
				);
			},

			view: function (selection) {
				setLocation(
					Dewawi.url(selection.module, selection.controller, 'view', selection.ids[0])
				);
			},

			cancel: function (selection, $button) {
				if (!confirm($button.data('message') || 'Wirklich stornieren?')) {
					return;
				}

				$.ajax({
					type: 'POST',
					url: Dewawi.url(selection.module, selection.controller, 'cancel', selection.ids[0]),
					dataType: 'json',
					cache: false,
					success: function (response) {
						if (!response || response.ok === false) {
							pushMessages([response && response.message ? response.message : 'Stornieren fehlgeschlagen.']);
							return;
						}

						if (action === 'view' || action === 'edit') {
							location.reload();
							return;
						}

						search();
					},
					error: function () {
						pushMessages(['Stornieren fehlgeschlagen.']);
					}
				});
			},

			delete: function (selection) {
				trash(selection.ids, deleteConfirm, selection.controller, selection.module);
			},

			pdf: function (selection) {
				window.open(
					Dewawi.url(selection.module, selection.controller, 'download', selection.ids[0]),
					'_blank'
				);
			},

			apply: function (selection) {
				var context = this.getSelectContext();

				if (selection.module === 'contacts' && selection.controller === 'contact') {
					if (window.parent && typeof window.parent.applyContact === 'function') {
						window.parent.applyContact(selection.ids[0]);
					}

					return;
				}

				applyPosition(context.parent, 'pos', selection.ids, context.setid);
			},

			'media-delete': function (selection, $button) {
				this.deleteMedia(selection, $button);
			},

			'add-option': function (selection, $button) {
				addOption(
					String($button.data('parent')),
					String($button.data('type') || 'pos'),
					Number($button.data('option-id')),
					Number($button.data('setid') || 0),
					Number($button.data('masterid') || 0)
				);
			},

			sortup: function (selection) {
				this.sortEntitySelection(
					selection,
					{
						direction: 'up'
					}
				);
			},

			sortdown: function (selection) {
				this.sortEntitySelection(
					selection,
					{
						direction: 'down'
					}
				);
			},

			'sort-position-up': function (selection) {
				this.sortPositionSelection(
					selection,
					'position',
					{
						direction: 'up'
					}
				);
			},

			'sort-position-down': function (selection) {
				this.sortPositionSelection(
					selection,
					'position',
					{
						direction: 'down'
					}
				);
			},

			'sort-position-set-up': function (selection) {
				this.sortPositionSelection(
					selection,
					'positionset',
					{
						direction: 'up'
					}
				);
			},

			'sort-position-set-down': function (selection) {
				this.sortPositionSelection(
					selection,
					'positionset',
					{
						direction: 'down'
					}
				);
			}
		},

		bindFilters: function () {
			$(document).on('change', '.dw-toolbar select, .dw-filter-panel select', function () {
				var $field = $(this);

				DewawiToolbar.persistField($field);

				if ($field.attr('name') === 'daterange') {
					DewawiToolbar.toggleDateRange($field.val());
				}

				if ($field.attr('name') !== 'page') {
					$('#pagination-page').val(1);
				}

				search();
			});

			$(document).on('change', '.dw-pagination select', function () {
				var $field = $(this);

				DewawiToolbar.persistField($field);

				if ($field.attr('name') === 'limit') {
					$('#pagination-page').val(1);
					$.cookie('page', 1, { path: cookiePath });
				}

				search();
			});

			$(document).on('click', '[data-action="filter-check-all"]', function (event) {
				event.preventDefault();

				var $card = $(this).closest('.dw-filter-card');
				var $boxes = $card.find('input[type="checkbox"]');

				$boxes.prop('checked', true);
				DewawiToolbar.persistField($boxes.first());
				$('#pagination-page').val(1);
				search();
			});

			$(document).on('click', '[data-action="filter-check-values"]', function (event) {
				event.preventDefault();

				var $card = $(this).closest('.dw-filter-card');
				var values = String($(this).data('values') || '').split(',');
				var $boxes = $card.find('input[type="checkbox"]');

				$boxes.each(function () {
					this.checked = values.indexOf(String(this.value)) !== -1;
				});

				DewawiToolbar.persistField($boxes.first());
				$('#pagination-page').val(1);
				search();
			});

			$(document).on('click', '[data-action="filter-check-only"]', function (event) {
				event.preventDefault();
				event.stopPropagation();

				var $card = $(this).closest('.dw-filter-card');
				var value = String($(this).data('value'));
				var $boxes = $card.find('input[type="checkbox"]');

				$boxes.each(function () {
					this.checked = String(this.value) === value;
				});

				DewawiToolbar.persistField($boxes.first());
				$('#pagination-page').val(1);
				search();
			});
		},

		bindKeyword: function () {
			$(document).on('input', '.dw-toolbar #keyword, .dw-toolbar [name="keyword"]', function () {
				DewawiToolbar.persistField($(this));
				$('#pagination-page').val(1);
				search();
			});
		},

		getSelectedIds: function () {
			var ids = [];

			$('[data-select-id]:checked, .check-id:checked').each(function () {
				ids.push(String($(this).val()));
			});

			return ids;
		},

		getButtonTarget: function ($button) {
			return {
				id: String($button.data('id') || ''),
				module: String($button.data('module') || module),
				controller: String($button.data('controller') || controller)
			};
		},

		persistField: function ($field) {
			var name = String($field.attr('name') || '').replace('[]', '');

			if (!name) {
				return;
			}

			if ($field.is(':checkbox')) {
				var values = [];

				$('input[name="' + name + '[]"]:checked').each(function () {
					values.push(this.value);
				});

				$.cookie(name, JSON.stringify(values), { path: cookiePath });
				return;
			}

			if ($field.val() === '') {
				$.removeCookie(name, { path: cookiePath });
				return;
			}

			$.cookie(name, $field.val(), { path: cookiePath });
		},

		resetField: function (name) {
			name = String(name || '').replace('[]', '');

			if (!name) {
				return;
			}

			var $fields = $('[name="' + name + '"], [name="' + name + '[]"]');

			if (!$fields.length) {
				$.removeCookie(name, { path: cookiePath });
				return;
			}

			if ($fields.first().is(':checkbox')) {
				var defaultValues = this.getDefaultArray($fields.first());

				$fields.each(function () {
					$(this).prop('checked', defaultValues.indexOf(String(this.value)) !== -1);
				});

				$.cookie(name, JSON.stringify(defaultValues), { path: cookiePath });
				return;
			}

			var defaultValue = this.getDefaultValue($fields.first());

			$fields.val(defaultValue);

			if (defaultValue === '') {
				$.removeCookie(name, { path: cookiePath });
			} else {
				$.cookie(name, defaultValue, { path: cookiePath });
			}

			if (name === 'daterange') {
				this.toggleDateRange(defaultValue);
			}
		},

		resetAll: function () {
			$('.dw-toolbar input, .dw-toolbar select, .dw-filter-panel input, .dw-filter-panel select').each(function () {
				var name = String($(this).attr('name') || '').replace('[]', '');

				if (name) {
					DewawiToolbar.resetField(name);
				}
			});

			$('#pagination-page').val(1);
		},

		copyEntity: function (id, moduleName, controllerName) {
			return $.ajax({
				type: 'POST',
				url: Dewawi.url(moduleName, controllerName, 'copy', id),
				dataType: 'json',
				cache: false
			});
		},

		copyEntities: function (selection) {
			var self = this;
			var requests = selection.ids.map(function (id) {
				return self.copyEntity(id, selection.module, selection.controller);
			});

			$.when.apply($, requests)
				.done(function () {
					self.afterCopy(selection, arguments);
				})
				.fail(function () {
					pushMessages(['Kopieren fehlgeschlagen.']);
				});
		},

		afterCopy: function (selection, responses) {
			var newId = this.getCopiedId(responses);

			if (this.isHistoryContext(selection) && newId > 0) {
				this.afterHistoryCopy(selection, newId);
				return;
			}

			if ((action === 'edit' || action === 'view') && selection.ids.length === 1 && newId > 0) {
				setLocation(Dewawi.url(selection.module, selection.controller, 'edit', newId));
				return;
			}

			if (typeof search === 'function') {
				search();
				return;
			}

			location.reload();
		},

		isHistoryContext: function (selection) {
			return module === 'contacts'
				&& controller === 'contact'
				&& action === 'edit'
				&& selection.type === 'row'
				&& $('#tabhistory').hasClass('is-active');
		},

		afterHistoryCopy: function (selection, newId) {
			var url = Dewawi.url(selection.module, selection.controller, 'edit', newId);

			reloadHistory();

			pushMessages([
				'Kopie wurde erstellt. <a href="' + url + '">Kopie öffnen</a>'
			]);
		},

		getCopiedId: function (responses) {
			if (!responses || !responses.length) {
				return 0;
			}

			var response = responses[0];

			if (response && response.ok === true && response.id) {
				return parseInt(response.id, 10);
			}

			return 0;
		},

		sortEntitySelection: function (selection, sort) {
			sortEntity({
				url: Dewawi.url(
					selection.module,
					selection.controller,
					'sort',
					selection.ids[0]
				),
				id: selection.ids[0],
				direction: sort.direction,
				ordering: sort.ordering,
				success: function () {
					if (
						action === 'edit'
						|| action === 'view'
					) {
						location.reload();
						return;
					}

					search();
				}
			});
		},

		sortPositionSelection: function (selection, controllerName, sort) {
			sortEntity({
				url: getSortUrl(
					controllerName,
					selection.id,
					{
						parent: selection.parent,
						type: selection.type,
						parentid: window.id
					}
				),
				id: selection.id,
				direction: sort.direction,
				ordering: sort.ordering,
				success: function () {
					getPositions(
						selection.parent,
						selection.type,
						window.pageYOffset
					);
				}
			});
		},

		deleteMedia: function (selection, $button) {
			if (!confirm('Are you sure you want to delete this file?')) {
				return;
			}

			var $image = $button.closest('.image');
			var url = $button.data('url');

			if (!url) {
				pushMessages(['Delete URL fehlt.']);
				return;
			}

			$.ajax({
				url: url,
				type: 'POST',
				dataType: 'json',
				cache: false,
				success: function (response) {
					if (!response || response.ok === false) {
						pushMessages([response && response.message ? response.message : 'Delete failed.']);
						return;
					}

					$image.remove();
				},
				error: function () {
					pushMessages(['Delete failed.']);
				}
			});
		},

		getDefaultValue: function ($field) {
			var value = $field.data('default');

			if (value === undefined) {
				value = $field.attr('default');
			}

			return String(value === undefined ? '' : value);
		},

		getDefaultArray: function ($field) {
			var value = $field.data('default');

			if (value === undefined) {
				value = $field.attr('default');
			}

			if ($.isArray(value)) {
				return value.map(String);
			}

			if (typeof value === 'string' && value.length) {
				try {
					var decoded = JSON.parse(value);

					if ($.isArray(decoded)) {
						return decoded.map(String);
					}
				} catch (e) {}

				return value.split(' ').map(String);
			}

			return [];
		},

		getSelectContext: function () {
			var parentValue = this.getUrlParam('parent') || '';
			var setid = Number(this.getUrlParam('setid') || 0);
			var parts = parentValue.split('|');

			return {
				module: parts[0] || '',
				parent: parts[1] || controller,
				parentid: parts[2] || id,
				setid: setid
			};
		},

		getUrlParam: function (name) {
			var regex = new RegExp('[?&]' + name + '=([^&#]*)');
			var match = regex.exec(window.location.search);

			if (match) {
				return decodeURIComponent(match[1].replace(/\+/g, ' '));
			}

			var pathMatch = new RegExp('/' + name + '/([^/]+)').exec(window.location.pathname);

			if (pathMatch) {
				return decodeURIComponent(pathMatch[1]);
			}

			return '';
		},

		toggleDateRange: function (value) {
			$('.dw-filter-card.daterange').toggleClass('is-hidden', value !== 'custom');
		}
	};

	$(function () {
		DewawiToolbar.init();
		window.DewawiToolbar = DewawiToolbar;
	});
})(jQuery);

Dewawi.getParam = function (name) {
	var parts = window.location.pathname.split('/');

	for (var i = 0; i < parts.length; i++) {
		if (parts[i] === name) {
			return parts[i + 1] || '';
		}
	}

	return '';
};

//Tabs
function initDwTabs(scope) {
	var $scope = scope ? $(scope) : $(document);

	$scope.find('.dw-tabs').each(function() {
		var $tabs = $(this);

		var $activeLink = $tabs.find('> .dw-tabs__nav > .dw-tabs__item.is-active > .dw-tabs__link').first();

		if(!$activeLink.length) {
			$activeLink = $tabs.find('> .dw-tabs__nav > .dw-tabs__item > .dw-tabs__link').first();
		}

		if(!$activeLink.length) return;

		activateDwTab($activeLink, false);
		runDwTabAction($activeLink, false);
	});
}

function activateDwTab($link, saveCookie) {
	if(!$link.length) return;

	var target = $link.data('tab-target') || $link.attr('href');
	if(!target) return;
	if(String(target).charAt(0) !== '#') target = '#' + target;

	var $tabs = $link.closest('.dw-tabs');
	var $panels = $tabs.next('.dw-tab-panels');
	if(!$panels.length) return;

	var $targetPanel = $panels.find(target).first();
	if(!$targetPanel.length) return;

	$tabs.find('> .dw-tabs__nav > .dw-tabs__item').removeClass('is-active');
	$link.closest('.dw-tabs__item').addClass('is-active');

	$panels.find('> .dw-tab-panel').removeClass('is-active').hide();
	$targetPanel.addClass('is-active').show();

	if(saveCookie) {
		$.cookie(getDwTabCookieName($tabs), target, {
			path: cookiePath + '/' + action
		});
	}
}

function getDwTabCookieName($tabs) {
	return $tabs.hasClass('dw-tabs--history')
		? 'tab-history'
		: 'tab';
}

function runDwTabAction($link, force) {
	if (!$link.length) return;

	var fnName = $link.data('tab-load');
	if (!fnName) return;

	var fn = window[fnName];
	if (typeof fn !== 'function') return;

	var parent = $link.data('parent');
	var type = $link.data('type');

	// container detection for lazy load
	var $container = $('.dw-positions[data-parent="' + parent + '"][data-type="' + type + '"]');

	// prevent double load
	if (!force && $container.length && $container.data('loaded')) {
		return;
	}

	// call function
	if (parent && type) {
		fn(parent, type);
	} else if (parent) {
		fn(parent);
	} else {
		fn();
	}

	if ($container.length) {
		$container.data('loaded', 1);
	}
}

function reloadHistory() {
	if (module !== 'contacts' || controller !== 'contact' || action !== 'edit') {
		return;
	}

	$.ajax({
		type: 'GET',
		url: Dewawi.url('contacts', 'contact', 'history', id),
		cache: false,
		success: function (html) {
			$('#tabhistory').html(html);
			initDwTabs('#tabhistory');
		},
		error: function () {
			pushMessages(['Historie konnte nicht aktualisiert werden.']);
		}
	});
}

$(document).on('click', '.dw-tabs__link', function (event) {
	event.preventDefault();

	var $link = $(this);

	activateDwTab($link, true);
	runDwTabAction($link, true);

	if ($link.attr('href') === '#tabmessages' && typeof getEmailmessages === 'function') {
		getEmailmessages();
	}

	if ($link.attr('href') === '#tabfiles') {
		refreshFilesTabIfNeeded();
	}
});

$(function () {
	initDwTabs();
});

function refreshFilesTabIfNeeded() {
	var $tab = $('#tabfiles');

	if (!$tab.data('needs-refresh')) {
		return;
	}

	$tab.find('iframe').each(function () {
		var src = $(this).attr('src');

		if (src) {
			$(this).attr('src', src);
		}
	});

	$tab.find('#messages').hide();
	$tab.find('iframe').show();

	$tab.data('needs-refresh', 0);
}

$(document).on('change blur', '.js-media-field', function () {
	var $field = $(this);
	var $box = $field.closest('.media-edit');
	var id = $box.data('id');

	var data = {
		id: id,
		title: $box.find('[name="title"]').val(),
		description: $box.find('[name="description"]').val(),
		target: $box.find('[name="target"]').val(),
		ordering: $box.find('[name="ordering"]').val()
	};

	$.ajax({
		url: '/media/edit',
		type: 'POST',
		dataType: 'json',
		data: data,
		success: function (response) {
			if (!response || !response.ok) {
				alert(response && response.message ? response.message : 'Save failed.');
			}
		},
		error: function () {
			alert('Save failed.');
		}
	});
});
