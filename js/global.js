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
	emailRequestPending: false,
	actionRequests: {},

	message: function (key) {
		return window.dwMessages && Object.prototype.hasOwnProperty.call(window.dwMessages, key) ? window.dwMessages[key] : key;
	},

	url: function (moduleName, controllerName, actionName, id) {
		var url = baseUrl + '/' + moduleName + '/' + controllerName + '/' + actionName;

		if (id !== undefined && id !== null && id !== '') {
			url += '/id/' + id;
		}

		return url;
	},

	setDirty: function (dirty) {
		isDirty = !!dirty || $('[data-autosave-unsaved]').length > 0;
		this.isDirty = isDirty;
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
				url: baseUrl+'/users/user/client',
				data: {
					clientid: $('#clientid').val(),
					client_csrf: $('#client_csrf').val()
				},
				cache: false,
				success: function(json){
					//response = json;
					//Dewawi.setDirty(false);
								//console.log(isDirty);
								location.reload();
				},
				error: function(){
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

	function initFileManagerFields(context) {
		$(context).find('input[data-filemanager]').each(function() {
			var $field = $(this);

			if($field.parent('.dw-filemanager-field').length) return;

			var label = $field.closest('.dw-field').find('.dw-label').text().trim();

			$field.wrap('<div class="dw-filemanager-field"></div>');

			$('<button type="button" class="dw-btn dw-filemanager-select">…</button>')
				.attr('data-field-id', this.id)
				.attr('aria-label', label)
				.attr('title', label)
				.insertAfter($field);
		});
	}

	initFileManagerFields(document);

	$(document).on('click', '.dw-filemanager-select', function() {
		var fieldId = $(this).data('field-id');

		if(!fieldId) return;

		var lang = typeof language !== 'undefined' ? language : 'en';
		var url = baseUrl
			+ '/library/FileManager/dialog.php'
			+ '?type=1'
			+ '&popup=1'
			+ '&field_id=' + encodeURIComponent(fieldId)
			+ '&lang=' + encodeURIComponent(lang);

		window.open(
			url,
			'dewawiFileManager',
			'width=1200,height=800,resizable=yes,scrollbars=yes'
		);
	});

	$(document).on(
		'change',
		'.add form input, .add form textarea, .add form select',
		function() {
			Dewawi.setDirty(true);
		}
	);

	$(document).on(
		'submit',
		'.add form',
		function(event) {
			if (this.id !== controller || event.isDefaultPrevented()) return;
			var submitter = event.originalEvent && event.originalEvent.submitter;
			var complete = prepareFormSubmit($(this), $(submitter), submitter);
			if (!complete) event.preventDefault();
			else setTimeout(function () { if (event.isDefaultPrevented()) complete(); }, 0);
		}
	);

	$(document).on('change', '[data-document-note]', function() {
		Dewawi.setDirty(true);
		edit({ notes: $(this).val() }, {
			module: $(this).data('module'),
			controller: $(this).data('controller'),
			id: $(this).data('id')
		}, undefined, $(this));
	});

	$('.edit form').on('change', 'input, textarea, select', function() {
		var $field = $(this);

		if($field.closest('form').is('[data-autosave="false"]')) {
			return;
		}

		if($field.hasClass('dw-permission-all')) {
			return;
		}

		if($field.closest('.dw-multiform__item, .dw-positions').length || $field.is('.editableValue, [data-document-note]')) {
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
			var fieldName = this.name;
			var isMultiCheckbox = $field.is(':checkbox') && fieldName.slice(-2) === '[]';

			if(isMultiCheckbox) {
				var baseName = fieldName.slice(0, -2);
				var selected = $field.closest('form').find('input[type="checkbox"][name="' + fieldName + '"]:checked').map(function() {
					return this.value;
				}).get();
				data[baseName] = selected.length ? selected : [''];
			} else if($field.is(':checkbox')) {
				value = $field.is(':checked') ? 1 : 0;
			}

			if($field.data('filemanager') && value) {
				try {
					var fileUrl = new URL(value, window.location.origin);

					if(fileUrl.origin === window.location.origin) {
						value = fileUrl.pathname + fileUrl.search + fileUrl.hash;
						$field.val(value);
					}
				} catch(e) {}
			}

			if(!isMultiCheckbox) {
				data[fieldName] = value;
			}

			if(typeof this.dataset.id !== 'undefined') params['id'] = this.dataset.id;
			if(typeof this.dataset.action !== 'undefined') params['action'] = this.dataset.action;
			if(typeof this.dataset.controller !== 'undefined') params['controller'] = this.dataset.controller;
			if(typeof this.dataset.module !== 'undefined') params['module'] = this.dataset.module;
			if(typeof this.dataset.ordering !== 'undefined') data['ordering'] = this.dataset.ordering;

			edit(data, params, undefined, $field);
		}
	});

	$('.edit form').on('change', '.dw-multiform__item input, .dw-multiform__item textarea, .dw-multiform__item select', function() {
		if ((this.name === 'file[]') || (this.name === 'media[]') || (this.name === 'subfolder')) {
			return;
		}

		var $field = $(this);
		if ($field.is('.editableValue') || $field.closest('.dw-positions').length || $field.closest('form').is('[data-autosave="false"]')) return;

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

		saveEntity(payload, target, undefined, $field);
	});
	$('.edit form input').on('textchange', function() {
		if((this.name != 'file[]') && (this.name != 'media[]') && (this.name != 'subfolder')) {
			if(!$(this).hasClass('datePicker')) {
				Dewawi.setDirty(true);
				if (!$(this).closest('form').is('[data-autosave="false"]')) $(this).attr('data-autosave-unsaved', 'true');
			}
		}
	});
	$('.edit form textarea').on('textchange', function() {
		Dewawi.setDirty(true);
		if (!$(this).closest('form').is('[data-autosave="false"]')) $(this).attr('data-autosave-unsaved', 'true');
	});
	//$('.edit form select').on('textchange', function() {
	//	Dewawi.setDirty(true);
	//});

	$(document).on(
		'submit',
		'.dw-variant-add',
		function(event) {
			event.preventDefault();

			var $form = $(this);

			$.ajax({
				type: 'POST',
				url: $form.attr('action'),
				data: $form.serialize(),
				dataType: 'json'
			})
			.done(function(response) {
				if(
					!response
					|| response.ok !== true
				) {
					pushMessages([
						'Variante konnte nicht erstellt werden.'
					]);

					return;
				}

				Dewawi.setDirty(false);
				window.location.href = Dewawi.url(
					'items',
					'item',
					'edit',
					response.id
				);
			})
			.fail(function() {
				pushMessages([
					'Variante konnte nicht erstellt werden.'
				]);
			});
		}
	);

	$(document).on(
		'submit',
		'.dw-variant-edit',
		function(event) {
			event.preventDefault();

			var $form = $(this);

			$.ajax({
				type: 'POST',
				url: $form.attr('action'),
				data: $form.serialize(),
				dataType: 'json'
			})
			.done(function(response) {
				if(
					!response
					|| response.ok !== true
				) {
					pushMessages([
						response && response.message
							? response.message
							: 'Variantenoptionen konnten nicht gespeichert werden.'
					]);

					return;
				}

				window.location.reload();
			});
		}
	);

	$(document).on('input', '.edit input, .edit textarea, .dw-positions input, .dw-positions textarea', function () {
		var $field = $(this);
		if (isSelectionField($field) || this.name === 'ordering' || $field.closest('form').is('[data-autosave="false"]') || /^(file|media|subfolder)(\[\])?$/.test(this.name)) return;
		$field.attr('data-autosave-unsaved', 'true');
		Dewawi.setDirty(true);
	});

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
			$field.formatCurrency({
				region: language,
				roundToDecimalPlace: /^(price|quantity)$/.test(this.name) ? 4 : 2
			});
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
			editPosition(parent, type, data, params, $field);
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
		$('.editable').not(this).filter(function () { return !$(this).next('.editableValue').is('[data-autosave-unsaved]'); }).show();
		$('.editableValue:visible').not('[data-autosave-unsaved]').each(function(e) {
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

		$('.editable').filter(function () { return !$(this).next('.editableValue').is('[data-autosave-unsaved]'); }).show();

		$('.editableValue:visible').not('[data-autosave-unsaved]').each(function() {
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
				if (element.dataset.ordering !== undefined) data['ordering'] = element.dataset.ordering;
			}
		});

		// Perform actions based on controller or action
		var response;
		if (params['action'] === 'add') {
			markFieldSaving($(this));
			response = add(data, params);
			var created = getCreateResponse(response, {module: params.module || module, controller: params.controller || controller}, data.itemid || params.id);
			if (finishFieldSave($(this), created)) this.dataset.action = '';
		} else if (params['controller'] === 'positionset') {
			response = editPositionSet(data, params, $(this));
		} else {
			response = edit(data, params, undefined, $(this));
		}

		if (!response) return;

		if (response.ok === false || response.saved === false || $(this).hasClass('is-invalid')) return;

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
				$('.editable').filter(function () { return !$(this).next('.editableValue').is('[data-autosave-unsaved]'); }).show();
				$('.editableValue:visible').not('[data-autosave-unsaved]').each(function() {
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
		$('.dw-toolbar [name="catid"]').val(id);
		$.cookie('catid', id, { path: cookiePath });
		$('#pagination-page').val(1);
		search();
	});

	//Modal window
	$(document).on('click', 'button.poplight', function() {
		var popID = $(this).attr('rel');
		var setid = $(this).closest('.dw-position-set').data('setid') || 0;
		modalWindow(popID, setid, this);
	});
	$(document).on('click', '.popup_block > .close, #fade', function (event) { event.preventDefault(); modalWindowClose(); });

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
		$(this).formatCurrency({
			region: language,
			roundToDecimalPlace: $(this).closest('.dw-positions').length && /^(price|quantity)$/.test(this.name) ? 4 : 2
		});
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
		$('.dw-filter-panel [name="from"]').datepicker({
			language: language,
			inline: true,
			container: $('#fromDatePicker')
		});
	}

	if($('#toDatePicker').length) {
		$('.dw-filter-panel [name="to"]').datepicker({
			language: language,
			inline: true,
			container: $('#toDatePicker')
		});
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

	if(isDirty && !Dewawi.nativeSubmission && !Dewawi.sessionExpired) {
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

function showSaveError(response, creating) {
	var validation = response && (response.errors || response.message === 'validation_failed' || response.message === 'Form is invalid'
		|| response.message === 'MESSAGES_FORM_IS_INVALID' || response.message === Dewawi.message('MESSAGES_FORM_IS_INVALID'));
	var failed = response && (response.ok === false || response.saved === false);
	var key = validation ? 'MESSAGES_FORM_IS_INVALID' : (failed ? (creating ? 'MESSAGES_CREATE_FAILED' : 'MESSAGES_SAVE_FAILED') : 'MESSAGES_ACTION_STATUS_UNKNOWN');
	pushMessages(Dewawi.message(key), 'danger', '#messages');
}

function prepareFormSubmit($form, $button, submitter) {
	var invalid = false;
	$form.find('input.required').each(function () {
		$(this).toggleClass('error', !$(this).val());
		if (!$(this).val()) invalid = true;
	});
	if (!$form.length || invalid || (!$form[0].noValidate && !(submitter && submitter.formNoValidate) && !$form[0].reportValidity())) {
		pushMessages(Dewawi.message('MESSAGES_FORM_IS_INVALID'), 'danger', '#messages');
		return;
	}
	var release = beginActionRequest('submit', module, controller, [$form.attr('id')], $button);
	if (!release) return;
	var $submitValue = $();
	if (submitter && submitter.name) $submitValue = $('<input>', {type: 'hidden', name: submitter.name, value: submitter.value}).appendTo($form);
	Dewawi.nativeSubmission = true;
	var complete = function () {
		$submitValue.remove();
		Dewawi.nativeSubmission = false;
		$form.removeData('dw-submit-complete');
		release();
	};
	$form.data('dw-submit-complete', complete);
	return complete;
}

$(window).on('pageshow', function () {
	$('form').each(function () {
		var complete = $(this).data('dw-submit-complete');
		if (complete) complete();
	});
});

$(document).on('click', '.dw-toolbar button.save:not([data-action])', function () { save(this); });

//Save
function save(button) {
	var $button = $(button);
	if (action === 'add') {
		var $form = $(document.getElementById(controller));
		var complete = prepareFormSubmit($form, $button);
		if (!complete) return;
		try {
			HTMLFormElement.prototype.submit.call($form[0]);
		} catch (error) {
			complete();
			showSaveError({ok: false}, false);
		}
	} else if (action === 'password') {
		var $passwordForm = $('form#password');
		if (!$button.length) $button = $passwordForm.find('button.password');
		if (!$passwordForm.length || !$passwordForm[0].reportValidity() || $passwordForm.find('[name="passwordnew"]').val() !== $passwordForm.find('[name="passwordconfirm"]').val()) {
			pushMessages(Dewawi.message('MESSAGES_FORM_IS_INVALID'), 'danger', '#messages');
			return;
		}
		var complete = beginActionRequest('save', module, controller, ['password'], $button);
		if (!complete) return;
		$.ajax({
			type: 'POST',
			async: true,
			url: baseUrl + '/' + module + '/' + controller + '/' + action,
			data: {
				passwordactual: $passwordForm.find('[name="passwordactual"]').val(),
				passwordnew: $passwordForm.find('[name="passwordnew"]').val(),
				passwordconfirm: $passwordForm.find('[name="passwordconfirm"]').val()
			},
			cache: false,
			dataType: 'json',
			timeout: 120000,
			success: function (response) {
				if (!response || response.saved !== true || response.ok === false || response.errors) {
					showSaveError(response, false);
					return;
				}
				Dewawi.setDirty(false);
				window.parent.modalWindowClose(true);
			},
			error: function (xhr) { showSaveError(xhr.responseJSON, false); },
			complete: complete
		});
	} else {
		var data = $('.add :input').filter(function () { return $(this).val() !== ''; }).serialize();
		if (data) add(data, null, $button);
	}
}

// Accept the observed JSON contracts or one correctly scoped server-rendered row.
function getCreateResponse(response, target, parentId) {
	if (typeof response === 'string') {
		var text = $.trim(response);
		if (text.charAt(0) === '{' || text.charAt(0) === '[') {
			try { response = JSON.parse(text); } catch (error) { return null; }
		} else {
			var $html = $($.parseHTML(text, document, false));
			var $item = $html.filter('.dw-multiform__item');
			if ($html.length === 1 && $html.hasClass('dw-multiform')) $item = $html.find('> .dw-multiform__list > .dw-multiform__item');
			if ($html.length !== 1 || $item.length !== 1 || !/^[1-9][0-9]*$/.test($item.attr('data-id') || '')
				|| $item.attr('data-module') !== target.module || $item.attr('data-controller') !== target.controller) return null;
			return {ok: true, item: $item};
		}
	}
	if (response && typeof response === 'object' && target.module === 'items' && target.controller === 'tag' && !('ok' in response) && response.saved !== false && !response.errors
		&& /^[1-9][0-9]*$/.test(String(response.id)) && response.module === 'items' && response.controller === 'item'
		&& String(response.parentid) === String(parentId) && Number(response.tagid) > 0) return {ok: true};
	return response && typeof response === 'object' && !Array.isArray(response) ? response : null;
}

//Add
function add(data, params, button) {
	var sync = !!params;
	var target = {module: sync ? (params.module && params.module !== 'default' ? params.module : module) : data.module || module, controller: sync ? params.controller || controller : data.controller || controller};
	var parentId = data.parent_id || data.parentid || data.itemid || params && params.id || window.id;
	var url = baseUrl + '/' + target.module + '/' + target.controller + '/add/id/' + (sync ? params.id || window.id : data.id || window.id);
	if (!sync && typeof data === 'object') data[controller + 'id'] = window.id;
	var complete = beginActionRequest('create', target.module, target.controller, [JSON.stringify([String(parentId), data.parent_module || '', data.parent_controller || ''])], $(button));
	if (!complete) return sync ? null : undefined;
	var response = null;
	$.ajax({
		type: 'POST',
		async: !sync,
		url: url,
		data: data,
		cache: false,
		timeout: 120000,
		success: function (raw) {
			response = raw;
			var result = getCreateResponse(raw, target, parentId);
			if (!result || result.ok !== true || result.saved === false || result.errors) {
				showSaveError(result, true);
				return;
			}
			if (result.item instanceof jQuery) {
				var $container = sync ? $('.dw-multiform-context').filter(function () {
					var $context = $(this);
					var contextController = $context.attr('data-controller') || $context.children('.dw-multiform').attr('data-controller');
					return contextController === target.controller && String($context.attr('data-parentid')) === String(parentId)
						&& (!data.parent_module || $context.attr('data-parent-module') === data.parent_module)
						&& (!data.parent_controller || $context.attr('data-parent-controller') === data.parent_controller);
				}).first() : $('div#' + target.controller);
				var $list = $container.find('> .dw-multiform > .dw-multiform__list').first();
				if (!$list.length) $list = $container.find('> .dw-multiform__list').first();
				var $anchor = $container.find(sync ? 'button.addMulti' : 'button.add').first();
				if (!$list.length && !$anchor.length) {
					showSaveError(null, true);
					return;
				}
				if (!$container.find('.dw-multiform__item').filter(function () { return $(this).attr('data-id') === result.item.attr('data-id') && $(this).attr('data-controller') === target.controller; }).length) {
					if ($list.length) $list.append(result.item);
					else $anchor.before(result.item);
				}
				result.item.find('input, textarea, select').first().focus().select();
			}
			Dewawi.setDirty(false);
			if (result.calculation || result.reloadPositions) applySaveResponse(result, controller, 'pos', window.pageYOffset);
			var notify = function () { pushMessages(Dewawi.message('MESSAGES_CREATED'), 'success'); };
			notify();
			if (action === 'index') search(notify);
		},
		error: function (xhr) { showSaveError(xhr.responseJSON, true); },
		complete: complete
	});
	if (sync) return response;
}

//Edit
function edit(data, params, button, fields) {
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
	var explicit = button !== undefined;
	var complete = explicit ? beginActionRequest('save', params && params.module || module, params && params.controller || controller, [params && params.id || window.id], $(button)) : null;
	if (explicit && !complete) return null;
	var $fields = getSaveFields(data, params, fields);
	markFieldSaving($fields);
	var $positions = $('.dw-positions[data-parent="' + controller + '"]').first();
	if ($positions.length) $positions.data('dw-position-revision', ($positions.data('dw-position-revision') || 0) + 1);
	var response = null;
	$.ajax({
		type: 'POST',
		async: false,
		url: url,
		data: data,
		cache: false,
		dataType: 'json',
		timeout: explicit ? 120000 : 0,
		success: function(json) {
			response = json;
			var result = json;
			if (data.tagid) result = getCreateResponse(json, {module: params && params.module || module, controller: 'tag'}, data.parentid);
			if (!finishFieldSave($fields, result)) {
				if (explicit) showSaveError(response, false);
				else if (!$fields.length) pushMessages(Dewawi.message(response && (response.ok === false || response.saved === false) ? 'MESSAGES_SAVE_FAILED' : 'MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
				return;
			}
			if (explicit) pushMessages(Dewawi.message('MESSAGES_SAVED'), 'success');
			applySaveResponse(response, controller, 'pos', window.pageYOffset);
		},
		error: function(xhr) {
			finishFieldSave($fields, xhr.responseJSON && xhr.responseJSON.ok === false ? xhr.responseJSON : null);
			if (explicit) showSaveError(xhr.responseJSON, false);
			else if (!$fields.length) pushMessages(Dewawi.message('MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
		},
		complete: complete
	});
	//console.log(data);
	//console.log(params);
	return response;
}

//Search
function search(onSuccess) {
	var data = collectToolbarData();
	data.context_action = action === 'select' ? 'select' : 'index';

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
			dataType: 'json',

			success: function (response) {
				$('#content').html(response.content || '');
				DewawiToolbar.refresh(response.toolbar || '');
				initDwTabs('#content');

				if (typeof onSuccess === 'function') {
					onSuccess(response);
				}
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

// Lock individual records so overlapping batches cannot repeat the same action.
function beginActionRequest(name, cmodule, type, ids, $button, message) {
	var keys = ids.map(function (id) { return JSON.stringify([name, cmodule, type, String(id)]); });
	if ($button.data('dw-loading-state') || keys.some(function (key) { return Dewawi.actionRequests[key]; })) return;
	if (message !== undefined && !confirm(message)) return;

	keys.forEach(function (key) { Dewawi.actionRequests[key] = true; });
	setButtonLoading($button, true);
	return function () {
		keys.forEach(function (key) { delete Dewawi.actionRequests[key]; });
		setButtonLoading($button, false);
	};
}

function getDeletedIds(response, ids) {
	if (!response || typeof response.ok !== 'boolean' || !Array.isArray(response.deletedIds)) return null;
	if (response.deletedIds.some(function (id) { return (typeof id !== 'number' && typeof id !== 'string') || !/^[1-9][0-9]*$/.test(String(id)); })) return null;
	return ids.filter(function (id) {
		return /^[1-9][0-9]*$/.test(String(id)) && response.deletedIds.some(function (deletedId) { return String(deletedId) === String(id); });
	});
}

function showDeleteResult(response, ids, deletedIds) {
	var success = response.ok === true && deletedIds.length === ids.length;
	var key = success ? (ids.length === 1 ? 'MESSAGES_SUCCESFULLY_DELETED' : 'MESSAGES_RECORDS_SUCCESFULLY_DELETED')
		: (deletedIds.length ? 'MESSAGES_PARTIALLY_DELETED' : 'MESSAGES_CANNOT_BE_DELETED');
	pushMessages(Dewawi.message(key), success ? 'success' : 'danger', '#messages');
}

function showActionRequestError(response, failureKey) {
	pushMessages(Dewawi.message(response && response.ok === false ? failureKey : 'MESSAGES_ACTION_STATUS_UNKNOWN'), 'danger', '#messages');
}

//Trash
function trash(ids, message, type, cmodule, button) {
	type = type || controller;
	cmodule = cmodule || module;
	ids = (Array.isArray(ids) ? ids : [ids]).filter(function (id) { return id !== undefined && id !== null && id !== ''; });
	ids = ids.map(String).filter(function (id, index, values) { return values.indexOf(id) === index; });
	if (!ids.length) return;

	if (action === 'add') {
		if (!confirm(message)) return;
		ids.forEach(function (singleId) {
			$('.dw-multiform__item[data-module="' + cmodule + '"][data-controller="' + type + '"][data-id="' + singleId + '"]').remove();
		});
		return;
	}

	var complete = beginActionRequest('delete', cmodule, type, ids, $(button), message);
	if (!complete) return;
	return $.ajax({
		type: 'POST',
		url: baseUrl + '/trash/add/',
		contentType: 'application/json',
		dataType: 'json',
		data: JSON.stringify({module: cmodule, controller: type, id: ids}),
		cache: false,
		timeout: 120000,
		success: function (response) {
			var deletedIds = getDeletedIds(response, ids);
			if (deletedIds === null) {
				showActionRequestError(response, 'MESSAGES_CANNOT_BE_DELETED');
				return;
			}

			var notify = function () { showDeleteResult(response, ids, deletedIds); };
			notify();
			if (!deletedIds.length) return;

			if (cmodule === 'admin' && type === 'pageblock') {
				location.reload();
				return;
			}

			if (action === 'edit') {
				deletedIds.forEach(function (singleId) {
					$('.dw-multiform__item[data-module="' + cmodule + '"][data-controller="' + type + '"][data-id="' + singleId + '"]').remove();
				});

				if (module === 'contacts' && controller === 'contact' && $('#tabhistory').hasClass('is-active')) {
					reloadHistory(notify);
					return;
				}

				if (type === controller) setLocation(baseUrl + '/' + cmodule + '/' + controller);
			} else {
				search(notify);
			}
		},
		error: function (xhr) { showActionRequestError(xhr.responseJSON, 'MESSAGES_CANNOT_BE_DELETED'); },
		complete: complete
	});
}

function deleteAttachment(ids, message, type, cmodule, button) {
	type = type || controller;
	cmodule = cmodule || module;
	ids = (Array.isArray(ids) ? ids : [ids]).filter(function (id) { return id !== undefined && id !== null && id !== ''; });
	ids = ids.map(String).filter(function (id, index, values) { return values.indexOf(id) === index; });
	if (!ids.length) return;

	var complete = beginActionRequest('delete', cmodule, type, ids, $(button), message);
	if (!complete) return;
	return $.ajax({
		type: 'POST',
		url: baseUrl + '/' + cmodule + '/' + type + '/delete/',
		contentType: 'application/json',
		dataType: 'json',
		data: JSON.stringify({id: ids}),
		cache: false,
		timeout: 120000,
		success: function (response) {
			var deletedIds = getDeletedIds(response, ids);
			if (deletedIds === null) {
				showActionRequestError(response, 'MESSAGES_CANNOT_BE_DELETED');
				return;
			}
			showDeleteResult(response, ids, deletedIds);
			deletedIds.forEach(function (singleId) { $('#' + type + singleId).remove(); });
		},
		error: function (xhr) { showActionRequestError(xhr.responseJSON, 'MESSAGES_CANNOT_BE_DELETED'); },
		complete: complete
	});
}

//Apply position
function applyPosition(parent, type, itemIds, setid) {
	var ids = Array.isArray(itemIds) ? itemIds.slice() : [itemIds];
	var lastResponse = null;
	var $dialog = window.parent.modalWindow && window.parent.modalWindow.current;
	if ($dialog && $dialog.data('dw-modal-pending')) return;
	if ($dialog) $dialog.data('dw-modal-pending', true);

	setid = setid || 0;

	function applyNext() {
		var itemId = ids.shift();

		if (!itemId) {
			if ($dialog) $dialog.removeData('dw-modal-pending');
			if (lastResponse) {
				window.parent.applyCalculation(
					lastResponse.calculation
				);
			}

			window.parent.getPositions(parent, type, window.parent.pageYOffset);

			if (typeof window.parent.modalWindowClose === 'function' && (!$dialog || window.parent.modalWindow.current === $dialog)) {
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
					if ($dialog) $dialog.removeData('dw-modal-pending');
					pushMessages([
						'Position konnte nicht hinzugefügt werden.'
					]);
					return;
				}

				lastResponse = response;

				applyNext();
			},
			error: function() {
				if ($dialog) $dialog.removeData('dw-modal-pending');
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

	if (response.reloadPositions || positionArea(parent, type).data('dw-positions-refresh-needed')) {
		getPositions(parent, type, scrollTo);
	}
}

function applyCalculation(calculation) {
	if (!calculation || !Object.keys(calculation).length) {
		return;
	}

	$('[id="subtotal"]').text(calculation.subtotal);
	$('[id="total"]').text(calculation.total);

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
function editPosition(parent, type, data, params, fields) {
	var url;
	var $fields = fields === undefined ? positionArea(parent, type).find('.dw-position-card[data-id="' + params.id + '"] input, .dw-position-card[data-id="' + params.id + '"] textarea, .dw-position-card[data-id="' + params.id + '"] select').filter(function () { return Object.prototype.hasOwnProperty.call(data, this.name); }) : $(fields);
	var $area = positionArea(parent, type);
	$area.data('dw-position-revision', ($area.data('dw-position-revision') || 0) + 1);
	markFieldSaving($fields);
	Dewawi.setDirty(true);

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
		async: false,
		url: url,
		data: data,
		cache: false,
		dataType: 'json',
		success: function(response) {
			if (finishFieldSave($fields, response)) applySaveResponse(response, parent, type, window.pageYOffset);
			else if (!$fields.length) pushMessages(Dewawi.message('MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
		},
		error: function(xhr) {
			finishFieldSave($fields, xhr.responseJSON && xhr.responseJSON.ok === false ? xhr.responseJSON : null);
			if (!$fields.length) pushMessages(Dewawi.message('MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
		}
	});
}

function positionArea(parent, type) {
	return $('.dw-positions').filter(function () { return String($(this).data('parent')) === String(parent) && String($(this).data('type') || 'pos') === String(type); });
}

// Serialize explicit mutations per document; synchronous field saves retain their existing contract.
function requestPositionAction(name, parent, type, requests, button, failureKey, scrollTo) {
	if (!requests.length) return;
	var $area = positionArea(parent, type), $queue = $area.length ? $area.first() : $(document);
	var complete = beginActionRequest(name, module, parent + '/' + type + '/' + id, requests.map(function (request) { return request.key; }), $(button));
	if (!complete) return;
	var queueName = 'dw-positions-' + parent + '-' + type + '-' + id;
	$queue.queue(queueName, function (next) {
		var lastResponse = null, lastRevision, index = 0, failed = false;
		function finish() {
			if (lastResponse || $area.data('dw-positions-refresh-needed')) {
				if (lastResponse && ($area.data('dw-position-revision') || 0) === lastRevision) applyCalculation(lastResponse.calculation);
				getPositions(parent, type, scrollTo === undefined ? window.pageYOffset : scrollTo);
			}
			complete();
			next();
		}
		function sendNext() {
			if (failed || index >= requests.length) { finish(); return; }
			var request = requests[index++];
			var revision = ($area.data('dw-position-revision') || 0) + 1;
			$area.data('dw-position-revision', revision).data('dw-position-pending', true);
			$.ajax({
				type: 'POST', url: request.url, data: request.data, dataType: 'json', cache: false, timeout: 120000,
				success: function (response) {
					if (!response || response.ok !== true || response.saved === false || response.errors) {
						failed = true;
						showActionRequestError(response, failureKey);
						return;
					}
					lastResponse = response;
					lastRevision = revision;
				},
				error: function (xhr) { failed = true; showActionRequestError(xhr.responseJSON, failureKey); },
				complete: function () { $area.removeData('dw-position-pending'); sendNext(); }
			});
		}
		sendNext();
	});
	if (!$queue.data(queueName)) {
		$queue.data(queueName, true);
		$queue.promise(queueName).always(function () { $queue.removeData(queueName); });
		$queue.dequeue(queueName);
	}
}

function addPosition(parent, type, setid, button) {
	setid = setid || 0;
	return requestPositionAction('position-add', parent, type, [{key: setid, url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id}], button, 'MESSAGES_POSITION_ADD_FAILED');
}

function copyPosition(parent, type, positionID, button) {
	return changeSelectedPositions('copy', [{parent: parent, type: type, id: positionID}], button);
}

function deletePosition(parent, type, positionID, setid, masterid, button) {
	return changeSelectedPositions('delete', [{parent: parent, type: type, id: positionID, setid: setid, masterid: masterid}], button);
}

function changeSelectedPositions(actionName, positions, button) {
	if (!positions.length) return;
	var parent = positions[0].parent, type = positions[0].type;
	var requests = positions.map(function (position) {
		return {key: position.id, url: baseUrl+'/'+module+'/position/'+actionName+'/parent/'+parent+'/type/'+type+'/id/'+position.id+'/parentid/'+id,
			data: actionName === 'delete' ? {id: position.id, setid: position.setid, masterid: position.masterid || null, parentid: id, delete: 'Yes'} : undefined};
	});
	return requestPositionAction('position-' + actionName, parent, type, requests, button, actionName === 'delete' ? 'MESSAGES_POSITION_DELETE_FAILED' : 'MESSAGES_POSITION_COPY_FAILED');
}

//Get Positions
function getPositions(parent, type, scrollTo) {
	scrollTo = scrollTo || null;
	Dewawi.positionRequests = Dewawi.positionRequests || {};
	var $area = positionArea(parent, type);
	var revision = $area.data('dw-position-revision') || 0;
	var requestKey = parent + '/' + type;
	var requestId = (Dewawi.positionRequests[requestKey] || 0) + 1;
	Dewawi.positionRequests[requestKey] = requestId;
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/position/index/parent/'+parent+'/type/'+type+'/parentid/'+id,
		cache: false,
		success: function(data){
			if (Dewawi.positionRequests[requestKey] !== requestId || ($area.data('dw-position-revision') || 0) !== revision) return;
			if ($area.data('dw-position-pending') || $area.find('[data-autosave-unsaved]').length) {
				$area.data('dw-positions-refresh-needed', true);
				return;
			}
			$area.html(data).removeData('dw-positions-refresh-needed');
			autosize($area.find('textarea'));
			if(data) $('#tabpositions .toolbar.positions.bottom').show();
			else $('#tabpositions .toolbar.positions.bottom').hide();
			if(scrollTo) {
				/*$('html, body').animate({
					scrollTop: $('#position'+scrollTo).offset().top
				}, 2000);*/
				window.scrollTo(0, scrollTo);
			}
			$('.datePickerLive').datepicker(datePickerOptions);
		},
		error: function () {
			if (Dewawi.positionRequests[requestKey] !== requestId || ($area.data('dw-position-revision') || 0) !== revision) return;
			$area.data('dw-positions-refresh-needed', true);
			pushMessages(Dewawi.message('MESSAGES_REQUEST_FAILED'), 'warning', '#messages');
		}
	});
}

function refreshSection(section, scrollTop) {
	var $section = $('[data-section="' + section + '"]');

	if (!$section.length) {
		return;
	}

	var refresh = String(
		$section.data('refresh') || ''
	);

	if (refresh === 'positions') {
		getPositions(
			String($section.data('parent') || ''),
			String($section.data('type') || ''),
			scrollTop
		);

		return;
	}

	if (refresh === 'html') {
		var url = String(
			$section.data('refresh-url') || ''
		);

		if (!url) {
			return;
		}

		$.ajax({
			type: 'GET',
			url: url,
			cache: false,
			success: function (html) {
				$section.html(html);

				if (scrollTop !== undefined) {
					window.scrollTo(0, scrollTop);
				}
			}
		});
	}
}

//Add option
function addOption(parent, type, optionid, setid, masterid, button) {
	return requestPositionAction('position-option', parent, type, [{key: [setid, masterid, optionid].join('/'), url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id+'/optionid/'+optionid+'/masterid/'+masterid}], button, 'MESSAGES_POSITION_ADD_FAILED');
}

function addSet(parent, type, button) {
	return requestPositionAction('positionset-add', parent, type, [{key: 'new', url: baseUrl+'/'+module+'/positionset/add/parent/'+parent+'/type/'+type+'/parentid/'+id}], button, 'MESSAGES_POSITION_SET_FAILED', $(document).height());
}

function copySet(parent, type, setid, button) {
	return requestPositionAction('positionset-copy', parent, type, [{key: setid, url: baseUrl+'/'+module+'/positionset/copy/parent/'+parent+'/type/'+type+'/id/'+setid+'/parentid/'+id}], button, 'MESSAGES_POSITION_SET_FAILED');
}

function deleteSet(parent, type, setid, button) {
	return requestPositionAction('positionset-delete', parent, type, [{key: setid, url: baseUrl+'/'+module+'/positionset/delete/parent/'+parent+'/type/'+type,
		data: {id: setid, parentid: id, delete: 'Yes'}}], button, 'MESSAGES_POSITION_SET_FAILED');
}

function editPositionSet(data, params, fields) {
	var response;
	var $fields = getSaveFields(data, {controller: 'positionset', id: params.id}, fields);
	var $area = $fields.closest('.dw-positions');
	var parent = $area.data('parent') || controller, type = $area.data('type') || 'pos';
	$area.data('dw-position-revision', ($area.data('dw-position-revision') || 0) + 1);
	markFieldSaving($fields);
	Dewawi.setDirty(true);
	$.ajax({
		type: 'POST', async: false, url: baseUrl+'/'+module+'/positionset/edit/id/'+params.id+'/parent/'+parent+'/type/'+type+'/parentid/'+id,
		data: data, cache: false, dataType: 'json',
		success: function (json) {
			response = json;
			if (finishFieldSave($fields, json)) applySaveResponse(json, parent, type, window.pageYOffset);
			else if (!$fields.length) pushMessages(Dewawi.message('MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
		},
		error: function (xhr) {
			finishFieldSave($fields, xhr.responseJSON && xhr.responseJSON.ok === false ? xhr.responseJSON : null);
			if (!$fields.length) pushMessages(Dewawi.message('MESSAGES_CHANGE_STATUS_UNKNOWN'), 'danger', '#messages');
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
function getEmailmessages(scrollTo, reportError) {
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
		},
		error: function () {
			if (reportError) pushMessages(Dewawi.message('MESSAGES_EMAIL_HISTORY_FAILED'), 'warning', '#output');
		}
	});
}

function sendMessage() {
	var $form = $('#emailmessage');
	if (!$form.length || Dewawi.emailRequestPending) return;

	var bodyElement = $form
		.find('[name="body"]')
		.get(0);

	var editor = bodyElement && typeof tinymce !== 'undefined' ? tinymce.get(bodyElement.id) : null;

	var contactid = 0;
	var campaignid = 0;
	var url = '';

	var data = {
		recipient: $form.find('[name="recipient"]').val() || '',
		cc: $form.find('[name="cc"]').val() || '',
		bcc: $form.find('[name="bcc"]').val() || '',
		replyto: $form.find('[name="replyto"]').val() || '',
		subject: $form.find('[name="subject"]').val() || '',
		body: editor ? editor.getContent() : ($form.find('[name="body"]').val() || ''),
		feedback: Number($form.find('[name="feedback"]').val()) || 0,
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
		pushMessages(Dewawi.message('MESSAGES_CONTACT_NOT_FOUND'), 'danger', '#output');
		return;
	}

	requestEmailSend(url, data, $form.find('button.send'), '#output');
}

function resendMessage(messageid, button) {
	if (Dewawi.emailRequestPending) return;
	if (!window.confirm(Dewawi.message('MESSAGES_EMAIL_RESEND_CONFIRM'))) return;

	requestEmailSend(baseUrl + '/contacts/email/send/messageid/' + messageid, {}, $(button), $('#output').length ? '#output' : '#messages');
}

function requestEmailSend(url, data, $button, target) {
	if (Dewawi.emailRequestPending) return;
	Dewawi.emailRequestPending = true;
	setButtonLoading($button, true, Dewawi.message('MESSAGES_EMAIL_SENDING'));
	if (target === '#output') $(target).children('.dw-alert--warning').remove();
	pushMessages(Dewawi.message('MESSAGES_EMAIL_SENDING'), 'info', target);

	return $.ajax({
		type: 'POST',
		url: url,
		data: data,
		dataType: 'json',
		cache: false,
		timeout: 120000,
		success: function (response) {
			if (!response || response.ok !== true) {
				pushMessages(Dewawi.message(response && response.ok === false ? 'MESSAGES_EMAIL_SENT_ERROR' : 'MESSAGES_EMAIL_STATUS_UNKNOWN'), 'danger', target);
				return;
			}

			pushMessages(Dewawi.message('MESSAGES_EMAIL_SENT_SUCCESS'), 'success', target);
			if (module === 'campaigns' && controller === 'campaign') {
				getCampaignRecipients();
				getCampaignErrors();
			} else {
				getEmailmessages(window.pageYOffset, true);
			}
		},
		error: function (xhr) {
			var response = xhr.responseJSON;
			pushMessages(Dewawi.message(response && response.ok === false ? 'MESSAGES_EMAIL_SENT_ERROR' : 'MESSAGES_EMAIL_STATUS_UNKNOWN'), 'danger', target);
		},
		complete: function () {
			setButtonLoading($button, false);
			Dewawi.emailRequestPending = false;
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

function pushMessages(messages, type, target) {
	var texts = [];
	function collect(value) {
		if (value == null) return;
		if (typeof value === 'object') {
			if (value.errors) collect(value.errors);
			else if (value.messages) collect(value.messages);
			else if (value.message) collect(value.message);
			else $.each(value, function (_, item) { collect(item); });
			return;
		}
		var text = String(value);
		var keys = {
			send_failed: 'MESSAGES_EMAIL_SENT_ERROR', save_failed: 'MESSAGES_SAVE_FAILED',
			network_error: 'MESSAGES_REQUEST_FAILED', validation_failed: 'MESSAGES_FORM_IS_INVALID',
			pending: 'MESSAGES_PENDING', 'SMTP accepted': 'MESSAGES_EMAIL_SENT_SUCCESS'
		};
		if (Object.prototype.hasOwnProperty.call(keys, text)) text = Dewawi.message(keys[text]);
		else if (window.dwMessages && Object.prototype.hasOwnProperty.call(window.dwMessages, text)) text = Dewawi.message(text);
		if (text && $.inArray(text, texts) === -1) texts.push(text);
	}
	collect(messages);
	if (!texts.length) return;

	type = type === 'error' ? 'danger' : type;
	if ($.inArray(type, ['success', 'danger', 'warning', 'info']) === -1) type = 'danger';
	var $container = $(target || '#messages').first();
	if (!$container.length) $container = $('#messages').first();
	if (!$container.length) $container = $('<div>', {id: 'messages'}).prependTo('div#content');
	$container.children('.dw-alert--' + type + ', .dw-alert--info').remove();
	if (type === 'success') $container.children('.dw-alert--danger').remove();
	if (type === 'info' || type === 'danger') $container.children('.dw-alert--success').remove();
	var $alert = $('<div>', {
		'class': 'dw-alert dw-alert--' + type,
		role: type === 'danger' ? 'alert' : 'status',
		'aria-live': type === 'danger' ? 'assertive' : 'polite',
		'aria-atomic': 'true'
	});
	var $list = $('<ul>').appendTo($alert);
	$.each(texts, function (_, text) { $('<li>').text(text).appendTo($list); });
	$('<button>', {type: 'button', 'class': 'dw-alert__close', 'aria-label': Dewawi.message('MESSAGES_DISMISS')}).text('×').appendTo($alert);
	$container.append($alert).show();
	if (type === 'danger' && (target === '#output' || target === '#messages') && $container.length) {
		var bounds = $container[0].getBoundingClientRect();
		if (bounds.top < 0 || bounds.bottom > window.innerHeight) $container[0].scrollIntoView({block: 'nearest'});
	}
	return $alert;
}

function setButtonLoading($buttons, loading, label) {
	$buttons.each(function () {
		var $button = $(this);
		var state = $button.data('dw-loading-state');
		if (loading) {
			if (state) return;
			state = {
				disabled: $button.prop('disabled'), ariaDisabled: $button.attr('aria-disabled'),
				ariaBusy: $button.attr('aria-busy'), loading: $button.hasClass('is-loading')
			};
			if (label) {
				state.contents = $button.contents().detach();
				$button.text(label);
			}
			$button.data('dw-loading-state', state).prop('disabled', true).addClass('is-loading').attr({'aria-disabled': 'true', 'aria-busy': 'true'});
		} else if (state) {
			if (state.contents) $button.empty().append(state.contents);
			if (state.disabled === undefined) $button.removeProp('disabled');
			else $button.prop('disabled', state.disabled);
			$button.toggleClass('is-loading', state.loading);
			$.each({'aria-disabled': state.ariaDisabled, 'aria-busy': state.ariaBusy}, function (name, value) {
				if (value === undefined) $button.removeAttr(name);
				else $button.attr(name, value);
			});
			$button.removeData('dw-loading-state');
		}
	});
}

$(document).on('click', '.dw-alert__close', function () {
	$(this).closest('.dw-alert').remove();
});

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

function removeMessages(target) {
	$(target || '#messages').empty().hide();
}

function getDocumentUrl(value) {
	if (typeof value !== 'string' || !value || /[\u0000-\u0020\\]/.test(value)) return '';
	if (value.charAt(0) === '/' && value.charAt(1) !== '/') return value;
	try {
		var url = new URL(value);
		return /^https?:$/.test(url.protocol) && url.origin === window.location.origin && !url.username && !url.password ? value : '';
	} catch (error) {
		return '';
	}
}

//Make PDF
function previewPdf(button) {
	var $button = $(button);
	var complete = $button.length ? beginActionRequest('preview', module, controller, [id], $button) : null;
	if ($button.length && !complete) return;
	var requestId = (Dewawi.previewRequestId || 0) + 1;
	Dewawi.previewRequestId = requestId;
	if (Dewawi.previewStatus) Dewawi.previewStatus.remove();
	var $status = $button.length ? pushMessages(Dewawi.message('DOCUMENTS_PREVIEW_RUNNING'), 'info', '#messages') : $();
	Dewawi.previewStatus = $status;

	return $.ajax({
		type: 'POST',
		url: baseUrl + '/' + module + '/' + controller + '/preview/id/' + id + '/templateid/' + $('#templateid').val(),
		dataType: 'json',
		timeout: 120000,
		success: function(response) {
			if (Dewawi.previewRequestId !== requestId) return;
			if (response && response.validation) updateDocumentValidation(response.validation);
			var url = response && response.ok === true ? getDocumentUrl(response.url) : '';
			if (!url) {
				pushMessages(Dewawi.message('DOCUMENTS_PREVIEW_FAILED'), 'danger', '#messages').attr('data-document-preview-error', 'true');
				return;
			}

			$('#messages > [data-document-preview-error]').remove();
			var $output = $('.dw-finish__preview').first();
			if (!$output.length) $output = $('#output');
			$output.empty().append($('<iframe>', {src: url, width: '100%', height: '700', title: Dewawi.message('DOCUMENTS_PREVIEW_PDF')})).show();
		},
		error: function () {
			if (Dewawi.previewRequestId === requestId) pushMessages(Dewawi.message('DOCUMENTS_PREVIEW_FAILED'), 'danger', '#messages').attr('data-document-preview-error', 'true');
		},
		complete: function () {
			$status.remove();
			if (complete) complete();
		}
	});
}

function escapeDocumentValidationText(value) {
	return $('<div>').text(value == null ? '' : String(value)).html();
}

function updateDocumentValidation(validation) {
	var $container = $('.dw-document-validation').first();
	if (!$container.length || !validation || $.inArray(validation.status, ['success', 'warning', 'error']) === -1 || typeof validation.title !== 'string') return false;
	if ((validation.errors !== undefined && !Array.isArray(validation.errors)) || (validation.warnings !== undefined && !Array.isArray(validation.warnings))) return false;
	var issues = (validation.errors || []).concat(validation.warnings || []);
	if (issues.some(function (issue) { return !issue || typeof (issue.text || issue.message) !== 'string'; })) return false;

	var status = validation.status || 'success';
	var alertClass = status === 'error' ? 'danger' : status;
	var html = '<div class="dw-alert dw-alert--' + alertClass + ' dw-document-validation__summary">';
	html += '<strong>' + escapeDocumentValidationText(validation.title || '') + '</strong></div>';
	if (issues.length) {
		html += '<ul class="dw-document-validation__issues">';
		$.each(issues, function(_, issue) {
			html += '<li><a href="#tab' + escapeDocumentValidationText(issue.tab || 'overview') + '" data-document-validation-target';
			if (issue.field) html += ' data-field="' + escapeDocumentValidationText(issue.field) + '"';
			if (issue.positionId) html += ' data-position-id="' + parseInt(issue.positionId, 10) + '"';
			html += '>' + escapeDocumentValidationText(issue.text || issue.message || '') + '</a></li>';
		});
		html += '</ul>';
	}
	$container.html(html);
	return true;
}

function showDocumentFinalizeError(response) {
	var key = 'DOCUMENT_STATUS_UNKNOWN';
	if (response && response.ok === false) {
		key = 'DOCUMENT_VALIDATION_REQUEST_FAILED';
		if (response.validation && response.validation.status === 'error' && updateDocumentValidation(response.validation)) key = 'DOCUMENT_VALIDATION_INPUT_INVALID';
	}
	pushMessages(Dewawi.message(key), 'danger', '#messages');
}

$(document).on('click', '[data-document-finalize]', function(event) {
	event.preventDefault();
	var $button = $(this);
	var url = getDocumentUrl($button.attr('href'));
	if (!url) {
		showDocumentFinalizeError({ok: false});
		return;
	}
	var complete = beginActionRequest('finalize', module, controller, [new URL(url, document.baseURI).href], $button);
	if (!complete) return;
	var $status = pushMessages(Dewawi.message('DOCUMENTS_FINALIZING'), 'info', '#messages');

	return $.ajax({
		type: 'POST',
		url: url,
		dataType: 'json',
		timeout: 120000,
		success: function(response) {
			var redirect = response && response.ok === true ? getDocumentUrl(response.redirect) : '';
			if (redirect) setLocation(redirect);
			else showDocumentFinalizeError(response);
		},
		error: function(xhr) { showDocumentFinalizeError(xhr.responseJSON); },
		complete: function() {
			$status.remove();
			complete();
		}
	});
});

// Native navigation has no reliable completion event; downloads use a short click guard.
function beginDocumentNavigation($button, name, url) {
	url = getDocumentUrl(url);
	if (!url) return;
	var complete = beginActionRequest(name, module, controller, [new URL(url, document.baseURI).href], $button);
	if (!complete) return;
	var $status = name === 'generate' ? pushMessages(Dewawi.message('DOCUMENTS_GENERATING'), 'info', '#messages') : $();
	var timer, released = false;
	var release = function () {
		if (released) return;
		released = true;
		clearTimeout(timer);
		$status.remove();
		$button.removeData('dw-document-navigation');
		complete();
	};
	$button.data('dw-document-navigation', release);
	if (name === 'download') timer = setTimeout(release, 1000);
	return release;
}

$(document).on('click', '[data-document-generate], [data-document-download]', function(event) {
	if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (event.which && event.which !== 1)) return;
	if (event.isDefaultPrevented()) return;
	var $button = $(this);
	var complete = beginDocumentNavigation($button, $button.is('[data-document-generate]') ? 'generate' : 'download', $button.attr('href'));
	if (!complete) event.preventDefault();
	else setTimeout(function () { if (event.isDefaultPrevented()) complete(); }, 0);
});

$(window).on('pageshow', function () {
	$('[data-document-generate], [data-document-download], [data-action="pdf"]').each(function () {
		var complete = $(this).data('dw-document-navigation');
		if (complete) complete();
	});
});

$(document).on('click', '[data-document-validation-target]', function(event) {
	event.preventDefault();
	var $target = $(this);
	var $tab = $('.dw-tabs__link[href="' + $target.attr('href') + '"]').first();
	if (!$tab.length) return;
	activateDwTab($tab, true);
	runDwTabAction($tab, false);

	var attempts = 0;
	(function focusField() {
		var positionId = $target.data('position-id');
		var $scope = positionId ? $('.dw-position-card[data-id="' + positionId + '"]') : $($target.attr('href'));
		var $field = $target.data('field') ? $scope.find('[name="' + $target.data('field') + '"]').first() : $scope;
		if ($field.length) {
			$field.focus();
			$field[0].scrollIntoView({behavior: 'smooth', block: 'center'});
			return;
		}
		if (++attempts < 10) window.setTimeout(focusField, 100);
	})();
});

// Reuse the existing popup element and keep state scoped to the active dialog.
function modalWindowBusy($dialog) {
	if (!$dialog || !$dialog.length) return false;
	var busy = !!$dialog.data('dw-modal-pending') || $dialog.find('.is-loading[aria-busy="true"]').length > 0;
	$dialog.find('iframe').each(function () {
		try { if ($(this).contents().find('.is-loading[aria-busy="true"]').length) busy = true; } catch (error) { /* Cross-origin frames retain their own keyboard handling. */ }
	});
	return busy;
}

function modalWindowMessages($dialog) {
	$dialog = $dialog || modalWindow.current;
	if (!$dialog || !$dialog.length) return '#messages';
	var $messages = $dialog.children('[data-modal-messages]');
	if (!$messages.length) $messages = $('<div>', {'data-modal-messages': ''}).prependTo($dialog);
	return $messages;
}

function modalWindowControls($scope) {
	return $scope.find('a[href], button, input:not([type="hidden"]), select, textarea, iframe, [tabindex]').filter(':visible').not(':disabled, [tabindex="-1"]');
}

function modalWindowKeydown(event) {
	var $dialog = modalWindow.current;
	if (!$dialog || !$dialog.is(':visible')) return;
	if (event.key === 'Escape' || event.which === 27) {
		event.preventDefault();
		modalWindowClose();
		return;
	}
	if (event.key !== 'Tab' && event.which !== 9) return;
	var $controls = modalWindowControls($dialog), $scope = $dialog;
	var owner = event.target.ownerDocument;
	if (owner && owner !== document) {
		$scope = $(owner);
		var $inner = modalWindowControls($scope);
		if ($inner.length && ((event.shiftKey && event.target !== $inner[0]) || (!event.shiftKey && event.target !== $inner[$inner.length - 1]))) return;
		var $frame = $dialog.find('iframe').filter(function () { try { return this.contentDocument === owner; } catch (error) { return false; } });
		var index = $controls.index($frame);
		event.preventDefault();
		$controls.eq((index + (event.shiftKey ? -1 : 1) + $controls.length) % $controls.length).focus();
		return;
	}
	if (!$controls.length) { event.preventDefault(); $dialog.focus(); return; }
	if (!$dialog[0].contains(event.target) || (event.shiftKey && event.target === $controls[0]) || (!event.shiftKey && event.target === $controls[$controls.length - 1])) {
		event.preventDefault();
		$controls.eq(event.shiftKey ? -1 : 0).focus();
	}
}

function modalWindow(popID, setid, opener) {
	var $dialog = $(document.getElementById(popID));
	if (!$dialog.length || modalWindowBusy(modalWindow.current)) return false;
	if (modalWindow.current && modalWindow.current[0] === $dialog[0]) return false;
	var trigger = $(opener)[0] || document.activeElement;
	if (modalWindow.current) modalWindowClose();
	modalWindow.opener = trigger;
	modalWindow.overflow = document.body.style.overflow;
	modalWindow.current = $dialog;

	var $heading = $dialog.find('h1, h2, h3, [role="heading"]').first();
	var label = $dialog.attr('aria-label') || $heading.text() || $(modalWindow.opener).attr('aria-label') || $(modalWindow.opener).attr('title') || $(modalWindow.opener).text() || Dewawi.message('MESSAGES_DIALOG');
	if (!$dialog.attr('aria-labelledby') && !$dialog.attr('aria-label')) {
		if ($heading.length) {
			if (!$heading.attr('id')) $heading.attr('id', popID + '-title');
			$dialog.attr('aria-labelledby', $heading.attr('id'));
		} else $dialog.attr('aria-label', $.trim(label));
	}
	$dialog.attr({role: 'dialog', 'aria-modal': 'true', 'aria-hidden': 'false', tabindex: '-1'});
	var $close = $dialog.children('.close').first();
	if (!$close.length) $close = $('<button>', {type: 'button', 'class': 'close'}).text('×').prependTo($dialog);
	$close.attr('aria-label', Dewawi.message('MESSAGES_DIALOG_CLOSE'));
	modalWindowMessages($dialog);
	$dialog.stop(true, true).show().css('margin-left', -$dialog.outerWidth() / 2);
	$('body').css('overflow', 'hidden');
	$('[id="fade"]').remove();
	$('<div>', {id: 'fade', 'aria-hidden': 'true'}).appendTo('body').show();

	var $frames = $dialog.find('iframe');
	$frames.each(function () { if (!$(this).attr('title')) $(this).attr('title', $.trim(label)); });
	$frames.off('load.dw-modal').on('load.dw-modal', function () {
		if (!modalWindow.current || modalWindow.current[0] !== $dialog[0]) return;
		try {
			var $contents = $(this).contents();
			$contents.off('keydown.dw-modal').on('keydown.dw-modal', modalWindowKeydown);
			var $keyword = $contents.find('#keyword');
			if ($keyword.length) $keyword.focus().select();
		} catch (error) { /* A cross-origin frame can still receive focus as a single control. */ }
	});
	var $first = modalWindowControls($dialog).not('.close').first();
	($first.length ? $first : $dialog).focus();
	$frames.each(function () { $(this).triggerHandler('load.dw-modal'); });
	if (action === 'index' && $frames.filter('#edit').length) {
		var rowId = $(modalWindow.opener).data('id') || $(modalWindow.opener).closest('tr').find('input.id, input#id').val();
		if (rowId) $frames.filter('#edit').attr('src', baseUrl+'/'+module+'/'+controller+'/edit/id/'+rowId);
	}
	return false;
}

function modalWindowClose(force) {
	var $dialog = modalWindow.current;
	if (!$dialog || (force !== true && modalWindowBusy($dialog))) return false;
	$dialog.find('iframe').off('load.dw-modal').each(function () {
		try { $(this).contents().off('keydown.dw-modal'); } catch (error) { /* No access to cross-origin frame events. */ }
	});
	if (action === 'index') $dialog.find('iframe#edit').attr('src', baseUrl+'/index/blank');
	$dialog.stop(true, true).hide().attr('aria-hidden', 'true').removeAttr('aria-modal');
	$dialog.children('[data-modal-messages]').empty();
	$('[id="fade"]').remove();
	document.body.style.overflow = modalWindow.overflow;
	var opener = modalWindow.opener;
	modalWindow.current = null;
	modalWindow.opener = null;
	if (opener && document.documentElement.contains(opener)) $(opener).focus();
	return false;
}

$(document).on('keydown.dw-modal', modalWindowKeydown);

//Set location
function setLocation(location){
	window.location = location;
}

function saveEntity(payload, target, button, fields) {
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

	return edit(requestPayload, params, button, fields);
}

function createEntity(payload, target, context, $container, button) {
	payload = payload || {};
	target = target || {};
	context = context || {};
	var requestPayload = $.extend({}, payload);
	if (context.parentModule) requestPayload.parent_module = context.parentModule;
	if (context.parentController) requestPayload.parent_controller = context.parentController;
	if (context.parentId) requestPayload.parentid = context.parentId;

	var url = baseUrl + (target.module && target.module !== 'default' ? '/' + encodeURIComponent(target.module) : '');
	url += '/' + encodeURIComponent(target.controller) + '/' + encodeURIComponent(target.action || 'add');
	if (target.id !== undefined && target.id !== null && target.id !== '') url += '/id/' + encodeURIComponent(target.id);
	var complete = beginActionRequest('create', target.module, target.controller, [JSON.stringify([String(context.parentId || target.id || window.id), context.parentModule || '', context.parentController || ''])], $(button));
	if (!complete) return;

	$.ajax({
		type: 'POST',
		url: url,
		data: requestPayload,
		cache: false,
		timeout: 120000,
		success: function (raw) {
			var response = getCreateResponse(raw, target, context.parentId);
			if (!response || response.ok !== true || response.saved === false || response.errors) {
				showSaveError(response, true);
				return;
			}
			if (response.item instanceof jQuery) {
				var $list = $container && $container.find('> .dw-multiform > .dw-multiform__list').first();
				if (!$list || !$list.length) {
					showSaveError(null, true);
					return;
				}
				if (!$list.children('.dw-multiform__item').filter(function () { return $(this).attr('data-id') === response.item.attr('data-id'); }).length) $list.append(response.item);
				response.item.find('input, textarea, select').filter(':visible').first().focus().select();
			}
			Dewawi.setDirty(false);
			if (response.calculation || response.reloadPositions) {
				var $positions = $container && $container.closest('.dw-positions');
				applySaveResponse(response, $positions && $positions.data('parent'), $positions && $positions.data('type'), window.pageYOffset);
			}
			var notify = function () { pushMessages(Dewawi.message('MESSAGES_CREATED'), 'success'); };
			notify();
			if (action === 'index') search(notify);
		},
		error: function (xhr) { showSaveError(xhr.responseJSON, true); },
		complete: complete
	});
}

function deleteEntity(ids, target, context, button) {
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
		requestTarget.module,
		button
	);
}

function getSaveFields(data, params, fields) {
	if (fields !== undefined) return $(fields);
	params = params || {};
	return $('input, textarea, select').filter(function () {
		var $field = $(this), name = (this.name || '').replace(/\[\]$/, '');
		if (!Object.prototype.hasOwnProperty.call(data, name) || $field.closest('form').is('[data-autosave="false"]')) return false;
		var $item = $field.closest('.dw-multiform__item');
		var fieldController = $field.data('controller') || $item.data('controller');
		var fieldId = $field.data('id') || $item.data('id');
		if (fieldController) return fieldController === (params.controller || controller) && String(fieldId) === String(params.id || window.id);
		return (!params.controller || params.controller === controller) && (!params.id || String(params.id) === String(window.id)) && !$field.closest('.dw-positions').length;
	});
}

function markFieldSaving($fields) {
	$fields.each(function () {
		var $field = $(this);
		if (!$field.data('dw-autosave-state')) $field.data('dw-autosave-state', {busy: $field.attr('aria-busy')});
		var $status = $field.data('dw-autosave-status');
		if ($status) $status.remove();
		$status = $('<span>', {'class': 'dw-field__description', role: 'status', 'aria-live': 'polite'}).text(Dewawi.message('MESSAGES_SAVING'));
		$field.removeClass('is-valid').attr({'aria-busy': 'true', 'data-autosave-unsaved': 'true'}).after($status).data('dw-autosave-status', $status);
	});
}

function finishFieldSave($fields, response) {
	var saved = !!response && response.ok !== false && response.saved !== false && !response.errors && (response.ok === true || response.saved === true);
	if (saved) {
		markFieldSaved($fields);
		Dewawi.setDirty(false);
	} else {
		var failed = response && (response.ok === false || response.saved === false || response.errors);
		var message = Dewawi.message(failed ? 'MESSAGES_SAVE_FAILED' : 'MESSAGES_CHANGE_STATUS_UNKNOWN');
		$fields.each(function () {
			var messages = response && response.errors && response.errors[(this.name || '').replace(/\[\]$/, '')];
			markFieldError($(this), messages || [message]);
		});
		Dewawi.setDirty(true);
	}
	return saved;
}

function clearFieldState($field) {
	$field.each(function () {
		var $field = $(this), state = $field.data('dw-autosave-state');
		var $status = $field.data('dw-autosave-status'), $error = $field.data('dw-field-error');
		if ($status) $status.remove();
		if ($error) {
			var errorId = $error.attr('id');
			var describedBy = ($field.attr('aria-describedby') || '').split(/\s+/).filter(function (id) { return id && id !== errorId; }).join(' ');
			if (describedBy) $field.attr('aria-describedby', describedBy);
			else $field.removeAttr('aria-describedby');
			$error.remove();
		}
		$field.removeClass('is-invalid is-valid').removeAttr('aria-invalid').removeData('dw-field-error dw-autosave-status dw-autosave-state');
		if (state && state.busy !== undefined) $field.attr('aria-busy', state.busy);
		else $field.removeAttr('aria-busy');
	});
}

function markFieldError($field, messages) {
	$field.each(function () {
		var $field = $(this);
		clearFieldState($field);
		markFieldError.sequence = (markFieldError.sequence || 0) + 1;
		var errorId = 'dw-field-error-' + markFieldError.sequence;
		var $error = $('<div>', {id: errorId, 'class': 'dw-field-error', role: 'alert', 'aria-live': 'assertive'});
		function append(value) {
			if (typeof value === 'string') $('<div>').text(value).appendTo($error);
			else if (value && typeof value === 'object') $.each(value, function (_, item) { append(item); });
		}
		append(messages);
		$field.addClass('is-invalid').attr({'aria-invalid': 'true', 'data-autosave-unsaved': 'true', 'aria-describedby': $.trim(($field.attr('aria-describedby') || '') + ' ' + errorId)}).after($error).data('dw-field-error', $error);
	});
}

function markFieldSaved($field) {
	clearFieldState($field);
	$field.removeAttr('data-autosave-unsaved').addClass('is-valid');
	window.setTimeout(function () { $field.removeClass('is-valid'); }, 1200);
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
		var items = $list.data('items') || [];
		var item = items[Number($item.data('index'))] || {};

		var id = Number(item.id);
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

		if (!apply && $input && $input.length) {
			applyAutocompleteValue($input, item);
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
				+ ' data-index="' + i + '"'
				+ '>';

			var label = item.label || '';

			if(!label && (item.sku || item.title)) {
				label = $.trim(
					(item.sku || '')
					+ ' · '
					+ (item.title || '')
				);
			}

			html += '<div class="autocomplete__label">'
				+ escapeHtml(label)
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
		$list.data('items', items);

		$list.css({
			position: 'absolute',
			top: offset.top + $input.outerHeight(),
			left: offset.left,
			width: inputWidth,
			zIndex: 9999
		});
	}

	function applyAutocompleteValue($input, item) {
		var valueField = String(
			$input.data('autocomplete-value') || 'value'
		);

		var target = String(
			$input.data('autocomplete-target') || ''
		);

		var targetValueField = String(
			$input.data('autocomplete-target-value') || 'id'
		);

		var value = item[valueField];

		if (value === undefined) {
			value = item.value || item.label || '';
		}

		$input.val(value);

		var $position = $input.closest('.ledger-position');

		if($position.length) {
			if(item.sku !== undefined) {
				$position
					.find('.ledger-position-sku')
					.val(item.sku);
			}

			if(item.title !== undefined) {
				$position
					.find('.ledger-position-title')
					.val(item.title);
			}

			if(item.id !== undefined) {
				$position
					.find('.ledger-position-itemid')
					.val(item.id);
			}
		}

		if (!target) {
			return;
		}

		var $target = $input
			.closest('form')
			.find('[name="' + target + '"]');

		if (!$target.length) {
			return;
		}

		var targetValue = item[targetValueField];

		if (targetValue === undefined) {
			targetValue = '';
		}

		$target.val(targetValue);

		if (action !== 'edit') {
			return;
		}

		var data = {};

		data[$input.attr('name')] = value;
		data[target] = targetValue;

		edit(data, {});
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
			save: { selection: 'none' },
			index: { selection: 'none' },
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
			export: { selection: 'none' },
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

		refresh: function (html) {
			var $toolbar = $('.dw-page-header__toolbar .dw-toolbar').first();

			if(!$toolbar.length) return;

			var $response = $('<div>').html(html || '');
			var $activeFilters = $response.find('.dw-active-filters').first();

			$toolbar.children('.dw-active-filters').remove();

			if(!$activeFilters.length) return;

			var $filterPanel = $toolbar.children('.dw-filter-panel').first();

			if($filterPanel.length) $activeFilters.insertBefore($filterPanel);
			else $toolbar.append($activeFilters);
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

		submitExport: function (extraData) {
			var data = $.extend({}, collectToolbarData(), extraData || {});
			var $form = $('<form>', {
				method: 'post',
				action: Dewawi.url(module, controller, 'export')
			}).hide();

			$.each(data, function(name, value) {
				var values = Array.isArray(value) ? value : [value];

				values.forEach(function(item) {
					$('<input>', {
						type: 'hidden',
						name: Array.isArray(value) ? name + '[]' : name,
						value: item
					}).appendTo($form);
				});
			});

			$form.appendTo('body');
			$form[0].submit();
			$form.remove();
		},

		actions: {
			add: function (selection, $button) {
				var targetModule = String($button.data('route-module') || module);
				var targetController = String($button.data('route-controller') || controller);
				var url = Dewawi.url(targetModule, targetController, 'add');
				var routeType = String($button.data('route-type') || '');
				var routeReason = String($button.data('route-reason') || '');
				var toolbarData = collectToolbarData();

				if (routeType) url += '/type/' + encodeURIComponent(routeType);
				if (routeReason) url += '/reason/' + encodeURIComponent(routeReason);

				var inheritedParams = [
					'catid',
					'parentid',
					'shopid',
					'itemid',
					'warehouseid'
				];

				if (!routeType) inheritedParams.unshift('type');

				inheritedParams.forEach(function (key) {
					var value = Dewawi.getParam(key) || toolbarData[key];

					if (value && value !== '0') {
						url += '/' + key + '/' + encodeURIComponent(value);
					}
				});

				setLocation(url);
			},

			save: function (selection, $button) { save($button); },

			index: function (selection, $button) {
				var targetModule = String($button.data('route-module') || module);
				var targetController = String($button.data('route-controller') || controller);

				setLocation(Dewawi.url(targetModule, targetController, 'index'));
			},

			copy: function (selection) {
				this.copyEntities(selection);
			},

			'add-position': function (selection, $button) {
				var $container = $button.closest('.dw-positions');
				addPosition(String($container.data('parent') || controller), String($container.data('type') || 'pos'), String($button.closest('.dw-position-set').data('setid') || 0), $button);
			},

			'delete-position': function (selection, $button) {
				deletePosition(selection.parent, selection.type, selection.id, selection.setid, selection.masterid, $button);
			},

			'copy-position': function (selection, $button) {
				copyPosition(selection.parent, selection.type, selection.id, $button);
			},

			'copy-selected-position': function (selection, $button) {
				changeSelectedPositions('copy', this.resolvePositions($button), $button);
			},

			'delete-selected-position': function (selection, $button) {
				changeSelectedPositions('delete', this.resolvePositions($button), $button);
			},

			'add-position-set': function (selection, $button) {
				var $container = $button.closest('.dw-positions');
				addSet(String($container.data('parent') || controller), String($container.data('type') || 'pos'), $button);
			},

			'copy-position-set': function (selection, $button) {
				copySet(selection.parent, selection.type, selection.setid, $button);
			},

			'delete-position-set': function (selection, $button) {
				deleteSet(selection.parent, selection.type, selection.setid, $button);
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
					$container,
					$button
				);
			},

			filter: function () {
				$('.dw-filter-panel').toggleClass('is-open');
			},

			reset: function () {
				this.resetAll();
				search();
			},

			export: function () {
				this.submitExport();
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
				var complete = beginActionRequest('cancel', selection.module, selection.controller, [selection.ids[0]], $button, $button.data('message') || 'Wirklich stornieren?');
				if (!complete) return;

				return $.ajax({
					type: 'POST',
					url: Dewawi.url(selection.module, selection.controller, 'cancel', selection.ids[0]),
					dataType: 'json',
					cache: false,
					timeout: 120000,
					success: function (response) {
						if (!response || response.ok !== true) {
							showActionRequestError(response, 'MESSAGES_CANCEL_FAILED');
							return;
						}

						var notify = function () { pushMessages(Dewawi.message('MESSAGES_SUCCESFULLY_CANCELLED'), 'success'); };
						notify();
						if (action === 'view' || action === 'edit') {
							location.reload();
							return;
						}

						search(notify);
					},
					error: function (xhr) { showActionRequestError(xhr.responseJSON, 'MESSAGES_CANCEL_FAILED'); },
					complete: complete
				});
			},

			delete: function (selection, $button) {
				return trash(selection.ids, deleteConfirm, selection.controller, selection.module, $button);
			},

			pdf: function (selection, $button) {
				var url = Dewawi.url(selection.module, selection.controller, 'download', selection.ids[0]);
				var complete = beginDocumentNavigation($button, 'download', url);
				if (!complete) return;
				if (!window.open(url, '_blank')) complete();
			},

			apply: function (selection, $button) {
				var context = this.getSelectContext();

				if (selection.module === 'contacts' && selection.controller === 'contact') {
					if (window.parent && typeof window.parent.applyContact === 'function') {
						window.parent.applyContact(selection.ids[0], $button);
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
					Number($button.data('masterid') || 0),
					$button
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
			$(document).on('change', '.dw-toolbar select, .dw-filter-panel input, .dw-filter-panel select', function () {
				if(action !== 'index' && action !== 'select') {
					return;
				}

				var $field = $(this);

				DewawiToolbar.persistField($field);

				if ($field.attr('name') === 'daterange') {
					DewawiToolbar.toggleDateRange($field.val());
				}

				$('#pagination-page').val(1);
				search();
			});

			$(document).on('change', '.dw-pagination select', function () {
				var $field = $(this);

				var paginationHandler = String(
					$field.closest('[data-pagination-change]').data('pagination-change') || ''
				);

				if(paginationHandler) {
					var fn = window[paginationHandler];

					if(typeof fn === 'function') {
						fn($field);
					}

					return;
				}

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

function reloadHistory(onSuccess) {
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
			if (typeof onSuccess === 'function') onSuccess();
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

function renderXRechnungResults($form, response) {
	var $results = $('[data-xrechnung-results]');
	$results.empty();
	$form.find('.is-invalid').removeClass('is-invalid');
	$form.find('.dw-field-error').remove();

	var errors = response && Array.isArray(response.errors) ? response.errors : [];
	var warnings = response && Array.isArray(response.warnings) ? response.warnings : [];
	var $errorList = $('<ul>');

	$.each(errors, function (index, error) {
		var message = error && error.message ? String(error.message) : $form.data('request-failed');
		var rule = error && error.rule ? String(error.rule) : '';
		var field = error && error.field ? String(error.field) : '';
		var $field = $form.find('[name]').filter(function () { return this.name === field; }).first();
		var label = '';

		if ($field.length) {
			$field.addClass('is-invalid');
			label = $field.closest('.dw-field').find('.dw-label').first().text().trim();
			$('<div>', {'class': 'dw-field-error', text: message}).insertAfter($field);
		}

		var summary = label ? label + ': ' + message : message;
		if (rule) summary += ' (' + rule + ')';
		$errorList.append($('<li>', {text: summary}));
	});

	if (errors.length) {
		$results.append($('<div>', {'class': 'dw-alert dw-alert--danger'}).append($errorList));
	}

	if (response && response.valid === true) {
		$results.append($('<div>', {'class': 'dw-alert dw-alert--success', text: $form.data('success')}));
	}

	if (warnings.length) {
		var $warningList = $('<ul>');
		$.each(warnings, function (index, warning) {
			if (warning && warning.message) $warningList.append($('<li>', {text: String(warning.message)}));
		});
		if ($warningList.children().length) {
			$results.append($('<div>', {'class': 'dw-alert dw-alert--warning'}).append($warningList));
		}
	}
}

$(document).on('input change', '#xrechnung-export-form :input', function () {
	$('#xrechnung-export-form [data-xrechnung-download]').prop('disabled', true);
});

$(document).on('submit', '#xrechnung-export-form', function (event) {
	event.preventDefault();
	var $form = $(this);
	var $buttons = $form.find('button');
	$buttons.prop('disabled', true);

	$.ajax({
		type: 'POST',
		url: $form.attr('action'),
		data: $form.serialize(),
		dataType: 'json'
	}).done(function (response) {
		renderXRechnungResults($form, response || {});
		$form.find('[data-xrechnung-download]').prop('disabled', !(response && response.valid === true));
	}).fail(function (xhr) {
		var response = xhr.responseJSON || {
			valid: false,
			errors: [{message: $form.data('request-failed')}],
			warnings: []
		};
		renderXRechnungResults($form, response);
	}).always(function () {
		$form.find('[data-xrechnung-check]').prop('disabled', false);
	});
});

$(document).on('click', '#xrechnung-export-form [data-xrechnung-download]', function () {
	var $form = $('#xrechnung-export-form');
	var $buttons = $form.find('button');
	var request = new XMLHttpRequest();
	$buttons.prop('disabled', true);
	$('[data-xrechnung-results]').empty().append(
		$('<div>', {'class': 'dw-alert dw-alert--warning', text: $form.data('downloading')})
	);

	request.open('POST', $form.attr('action'), true);
	request.responseType = 'blob';
	request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.onload = function () {
		var contentType = request.getResponseHeader('Content-Type') || '';
		if (request.status >= 200 && request.status < 300 && contentType.indexOf('application/xml') === 0) {
			var disposition = request.getResponseHeader('Content-Disposition') || '';
			var match = disposition.match(/filename="([^"]+)"/i);
			var filename = match ? match[1] : 'XRechnung.xml';
			var url = URL.createObjectURL(request.response);
			var link = document.createElement('a');
			link.href = url;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
			$form.find('[data-xrechnung-download]').prop('disabled', false);
			$form.find('[data-xrechnung-check]').prop('disabled', false);
			return;
		}

		var reader = new FileReader();
		reader.onload = function () {
			var response = null;
			try { response = JSON.parse(reader.result); } catch (error) {}
			renderXRechnungResults($form, response || {
				valid: false,
				errors: [{message: $form.data('request-failed')}],
				warnings: []
			});
			$form.find('[data-xrechnung-check]').prop('disabled', false);
		};
		reader.readAsText(request.response);
	};
	request.onerror = function () {
		renderXRechnungResults($form, {
			valid: false,
			errors: [{message: $form.data('request-failed')}],
			warnings: []
		});
		$form.find('[data-xrechnung-check]').prop('disabled', false);
	};
	request.send($form.serialize() + '&download=1');
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

$(document).on('change', '.dw-toolbar select[name="state"]', function () {
	if(action !== 'edit') {
		return;
	}

	edit({
		state: $(this).val()
	}, {
		id: id
	});
});

function getCampaignRecipients($field)
{
	var $container = $('#campaign-recipients');
	var $pagination = $container.find('.dw-pagination');

	var page = 1;
	var limit = 25;

	if($pagination.length) {
		if($field && $field.jquery && $field.attr('name') === 'limit') {
			$pagination.find('[name="page"]').val(1);
		}

		page = parseInt($pagination.find('[name="page"]').val(), 10) || 1;
		limit = parseInt($pagination.find('[name="limit"]').val(), 10) || 25;
	}

	$.ajax({
		type: 'POST',
		url: baseUrl + '/campaigns/campaign/recipients/id/' + id,
		data: {
			page: page,
			limit: limit
		},
		cache: false,
		success: function(response) {
			$container.html(response);
		}
	});
}

function getCampaignErrors()
{
	var $container = $('#campaign-errors');

	if(!$container.length) return;

	$.ajax({
		type: 'GET',
		url: baseUrl + '/campaigns/campaign/errors/id/' + id,
		cache: false,
		success: function(response) {
			$container.html(response);
		}
	});
}
