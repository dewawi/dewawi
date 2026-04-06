var isDirty = false;
var timeout = 0;
var setid = 0;

//Date picker options
var datePickerOptions = {
	autoHide: true,
	language: language
};

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
					//isDirty = false;
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
					//isDirty = false;
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
		isDirty = true;
	});
	$('.edit form').on('change', 'input, textarea, select', function() {
		if ($(this).closest('.dw-multiform__item').length) {
			return;
		}
		if((this.name != 'file[]') && (this.name != 'media[]') && (this.name != 'subfolder')) {
			isDirty = true;
			var data = {};
			var params = {};
			var value = this.value;
			//If the element is a checkbox
			if($(this).is(':checkbox')) {
				value = $(this).is(':checked') ? 1 : 0;
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
		}
	});
	$('.edit form').on('change', '.dw-multiform__item input, .dw-multiform__item textarea, .dw-multiform__item select', function() {
		if ((this.name === 'file[]') || (this.name === 'media[]') || (this.name === 'subfolder')) {
			return;
		}

		var $field = $(this);

		var entityId = $field.data('id');
		var entityModule = $field.data('module');
		var entityController = $field.data('controller');

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

		var $container = $field.closest('.multiformContainer');

		var context = {
			parentModule: $container.data('parent-module'),
			parentController: $container.data('parent-controller'),
			parentId: $container.data('parentid')
		};

		if (!context.parentModule || !context.parentController || !context.parentId) {
			markFieldError($field, ['Parent-Kontext fehlt.']);
			return;
		}

		clearFieldState($field);

		var response = saveEntity(payload, target, context);

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
				isDirty = true;
			}
		}
	});
	$('.edit form textarea').on('textchange', function() {
		isDirty = true;
	});
	//$('.edit form select').on('textchange', function() {
	//	isDirty = true;
	//});

	//Handle sub entities
	$('.positionsContainer').on('change', 'input:not(.id), textarea, select', function() {
		if ($(this).hasClass('editableValue')) {
			return;
		}

		if ($(this).hasClass('number')) {
			$(this).formatCurrency({ region: language });
		}

		var $field = $(this);
		var $card = $field.closest('.dw-position-card.wrap');
		var $container = $field.closest('.positionsContainer');
		var $set = $field.closest('.dw-position-set');

		var data = {};
		var params = {};

		params['id'] = $card.find('input.position-id[type="hidden"]').first().val();
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
			var setid = $set.find('input.setid').first().val();
			sort(parent, type, params['id'], setid, this.value);
		} else {
			editPosition(parent, type, data, params);
		}
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
		isDirty = true;
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

	// Auto search with keyword
	$('.dw-toolbar #keyword').on('textchange', function() {
		var keyword = this.value || ''; // Fallback to empty string if undefined
		if(keyword) {
			var date = new Date();
			date.setTime(date.getTime() + (5 * 60 * 1000)); // expire after 5 minutes
			$.cookie('keyword', keyword, { expires: date, path: cookiePath });
		} else {
			$.removeCookie('keyword', { path: cookiePath }); // Remove cookie if empty
		}
		search();
	});

	//Toolbar
	$(document).on('change', '.toolbar input:not(#keyword), .toolbar select', function() {
		var element = this.name;
		var value = this.value || ''; // Fallback to empty string if undefined
		if(module == 'statistics') {
			$.cookie(element, value, { path: cookiePath });
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
						if($(this).prop('checked')) states.push(value);
					});
					$.cookie('states', JSON.stringify(states), { path: cookiePath });
				} else if(element == 'daterange') {
					if(value == 'custom') $('#filter .daterange').show();
					else $('#filter .daterange').hide();
					$.cookie(element, value, { path: cookiePath });
				} else if(element == 'paymentstatus[]') {
					paymentstatus = [];
					$('input[type="checkbox"][name="paymentstatus[]"]').each(function() {
						if($(this).prop('checked')) paymentstatus.push(value);
					});
					$.cookie('paymentstatus', JSON.stringify(paymentstatus), { path: cookiePath });
				} else {
					$.cookie(element, value, { path: cookiePath });
				}
				if(element != 'page') $('#page').val(1);
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
			states.push(this.value || ''); // Fallback to empty string if undefined
		});
		$.cookie('states', JSON.stringify(states), { path: cookiePath });
		search();
	});

	$('#filter').on('click', '.none', function() {
		$('input[name="states[]"]').prop('checked', false);
		$.removeCookie('states', { path: cookiePath }); // Remove cookie if no states are selected
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
		const id = $(this).data('id');
		$('#catid').val(id);
		$.cookie('catid', id, { path: cookiePath });
		$('#page').val(1);
		search();
	});

	//Modal window
	$(document).on('click', 'button.poplight', function() {
		var popID = $(this).attr('rel');
		setid = $(this).closest('div.set').find('input.setid').val();
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
	//if($.cookie('tab') == '#tabPositions') {
		$('div.positionsContainer').each(function() {
			var parent = $(this).closest('div.positionsContainer').data('parent');
			var type = $(this).closest('div.positionsContainer').data('type');
			getPositions(parent, type);
		});
	//}

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
				} else if (className == 'addMulti') {
					var $container = $(this).closest('div.multiformContainer');

					var payload = {};
					if ($container.data('type')) {
						payload.type = $container.data('type');
					}

					var target = {
						module: $(this).data('module'),
						controller: $(this).data('controller'),
						action: 'add',
						id: $container.data('parentid')
					};

					var context = {
						parentModule: $container.data('parent-module'),
						parentController: $container.data('parent-controller'),
						parentId: $container.data('parentid')
					};

					if (!context.parentModule || !context.parentController || !context.parentId) {
						pushMessages(['Parent-Kontext fehlt.']);
						return;
					}

					createEntity(payload, target, context, $container);
				} else if(className == 'save') {
					save();
				} else if(className == 'addPosition') {
					var parent = $(this).closest('div.positionsContainer').data('parent');
					var type = $(this).closest('div.positionsContainer').data('type');
					var setid = $(this).closest('div.set').find('input.setid').val();
					addPosition(parent, type, setid);
				} else if(className == 'addSet') {
					if(action == 'index') {
						var url = baseUrl+'/'+module+'/'+controller+'set/add';
						if($('#catid').val() > 0) url += '/catid/'+$('#catid').val();
						setLocation(url);
					} else {
						var parent = $(this).closest('div.positionsContainer').data('parent');
						var type = $(this).closest('div.positionsContainer').data('type');
						addSet(parent, type);
					}
				} else if(className == 'copySet') {
					var parent = $(this).closest('div.positionsContainer').data('parent');
					var type = $(this).closest('div.positionsContainer').data('type');
					var setid = $(this).closest('div.set').find('input.setid').val();
					if(setid != "0") copySet(parent, type, setid);
				} else if(className == 'deleteSet') {
					var parent = $(this).closest('div.positionsContainer').data('parent');
					var type = $(this).closest('div.positionsContainer').data('type');
					var setid = $(this).closest('div.set').find('input.setid').val();
					if(setid != "0") deleteSet(parent, type, setid);
				} else if(className == 'clear') {
					var element = $(this).attr('rel');
					clear(element);
				} else if(className == 'reset') {
					reset();
				} else if($(this).parents('.toolbar').length == 1) {
					var ids = [];

					// Collect all selected checkboxes once
					$('table#data tr input.id:checked').each(function() {
						ids.push($(this).val());
					});

					switch(className) {
						case 'edit':
							//var url = baseUrl+'/'+module+'/'+controller+'/edit/id/'+$(this).val();
							//setLocation(url);
							break;
						case 'copy':
							//copy($(this).val());
							ids.forEach(function(id) {
								copy(id);
							});
							break;
						case 'cancel':
							//cancel($(this).val());
							ids.forEach(function(id) {
								cancel(id);
							});
							break;
						case 'delete':
							//trash($(this).val(), deleteConfirm);
							//ids.forEach(function(id) {
							//	console.log(id);
							//});
							trash(ids, deleteConfirm);
							break;
					}

					$('table#positions tr').each(function(){
						$(this).find('td input.id:checkbox').each(function() {
							if(this.checked) {
								switch(className) {
									case 'copyPosition':
										var parent = $(this).closest('div.positionsContainer').data('parent');
										var type = $(this).closest('div.positionsContainer').data('type');
										copyPosition(parent, type, $(this).val());
										break;
									case 'applyPosition':
										var parent = $(this).closest('div.positionsContainer').data('parent');
										var type = $(this).closest('div.positionsContainer').data('type');
										//window.parent.console.log(parent);
										//applyPosition(parent, type, $(this).val(), window.parent.setid);
										break;
									case 'deletePosition':
										var parent = $(this).closest('div.positionsContainer').data('parent');
										var type = $(this).closest('div.positionsContainer').data('type');
										var setid = $(this).closest('div.set').find('input.setid').val();
										deletePosition(parent, type, $(this).val(), setid);
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
							trash(id, deleteConfirm);
						}
					}
					var parent = $(this).closest('div.positionsContainer').data('parent');
					var type = $(this).closest('div.positionsContainer').data('type');
					var setid = $(this).closest('div.set').find('input.setid').val();
					if(className == 'up') {
						sort(parent, type, setid, -1, 'up');
					} else if(className == 'down') {
						sort(parent, type, setid, -1, 'down');
					}
				} else if (className == 'up') {
					var $container = $(this).closest('.positionsContainer');
					var $card = $(this).closest('.dw-position-card');
					var $set = $(this).closest('.set');

					var parent = $container.data('parent');
					var type = $container.data('type');
					var id = $card.find('input[type="hidden"].position-id:first').val();
					var setid = $set.find('input.setid').val() || null;

					if (!id) {
						console.warn('sort up: id not found');
						return;
					}

					if ($card.hasClass('is-child')) {
						var masterid = $card.data('masterid');
						sort(parent, type, id, setid, 'up', masterid);
					} else {
						sort(parent, type, id, setid, 'up');
					}

				} else if (className == 'down') {
					var $container = $(this).closest('.positionsContainer');
					var $card = $(this).closest('.dw-position-card');
					var $set = $(this).closest('.set');

					var parent = $container.data('parent');
					var type = $container.data('type');
					var id = $card.find('input[type="hidden"].position-id:first').val();
					var setid = $set.find('input.setid').val() || null;

					if (!id) {
						console.warn('sort down: id not found');
						return;
					}

					if ($card.hasClass('is-child')) {
						var masterid = $card.data('masterid');
						sort(parent, type, id, setid, 'down', masterid);
					} else {
						sort(parent, type, id, setid, 'down');
					}
				} else if(className == 'copyPosition') {
					var parent = $(this).closest('div.positionsContainer').data('parent');
					var type = $(this).closest('div.positionsContainer').data('type');
					var positionID = $(this).closest('tr').find('input.id').val();
					copyPosition(parent, type, positionID);
				} else if(className == 'deletePosition') {
					var $container = $(this).closest('.positionsContainer');
					var $card = $(this).closest('.dw-position-card');
					var $set = $(this).closest('.set');

					var parent = $container.data('parent');
					var type = $container.data('type');
					var positionID = $card.find('.position-id:first').val();
					var setid = $set.find('input.setid').val() || null;

					if (!positionID) {
						console.warn('deletePosition: no hidden position id found');
						return;
					}

					if ($card.hasClass('is-child')) {
						deletePosition(parent, type, positionID, setid, $card.data('masterid'));
					} else {
						deletePosition(parent, type, positionID, setid);
					}
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
							trash(cid, deleteConfirm);
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
	$('#content, .positionsContainer').on('click', '.checkall', function() {
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

//Tabs
function getDwTabPanels($tabs) {
	var hrefs = [];

	$tabs.find('.dw-tabs__link').each(function () {
		var href = $(this).attr('href');
		if (href && href.charAt(0) === '#') {
			hrefs.push(href);
		}
	});

	return hrefs.length ? $(hrefs.join(',')) : $();
}

function activateDwTab($link, saveCookie) {
	if (!$link || !$link.length) return;

	var target = $link.attr('href');
	if (!target || target.charAt(0) !== '#') return;

	var $tabs = $link.closest('.dw-tabs');
	if (!$tabs.length) return;

	var $items = $tabs.find('.dw-tabs__item');
	var $panels = getDwTabPanels($tabs);

	$items.removeClass('is-active');
	$link.closest('.dw-tabs__item').addClass('is-active');

	$panels.removeClass('is-active').hide();
	$(target).addClass('is-active').show();

	if (saveCookie) {
		if (target === '#tabFinish' || target === '#tabDocument' || target === '#tabFiles') {
			$.cookie('tab', '#tabOverview', { path: cookiePath + '/' + action });
		} else {
			$.cookie('tab', target, { path: cookiePath + '/' + action });
		}
	}
}

function initDwTabs(scope) {
	var $scope = scope ? $(scope) : $(document);

	$scope.find('.dw-tabs').each(function () {
		var $tabs = $(this);
		var $links = $tabs.find('.dw-tabs__link');

		if (!$links.length) return;

		var cookieTab = $.cookie('tab');
		var $activeLink = $();

		if (cookieTab) {
			$activeLink = $links.filter('[href="' + cookieTab + '"]').first();
		}

		if (!$activeLink.length) {
			$activeLink = $tabs.find('.dw-tabs__item.is-active .dw-tabs__link').first();
		}

		if (!$activeLink.length) {
			$activeLink = $links.first();
		}

		activateDwTab($activeLink, false);
	});
}

$(document).on('click', '.dw-tabs__link', function (event) {
	event.preventDefault();
	activateDwTab($(this), true);
});

$(function () {
	initDwTabs();
});

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
				isDirty = false;
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
				isDirty = false;

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
				var $container = $('.multiformContainer[data-controller="' + params['controller'] + '"][data-parentid="' + parentId + '"]');

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
			isDirty = false;
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
				isDirty = false;
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
	data = {};
	data.states = [];
	$('#state input:checked').each(function() {
		data.states.push(this.value);
	});
	data.keyword = $('#keyword').val();
	data.type = $('#type').val();
	data.country = $('#country').val();
	data.catid = $('#catid').val();
	data.tagid = $('#tagid').val();
	data.shopid = $('#shopid').val();
	data.category = $('#category').val();
	data.daterange = $('#daterange input:checked').val();
	data.from = $('#from').val();
	data.to = $('#to').val();
	data.limit = $('#limit').val();
	data.page = $('#page').val();
	data.order = $('#order').val();
	data.sort = $('#sort').val();
	data.controller = $('#controller').val();
	data.clientid = $('#clientid').val();
	if(typeof window.parent.controller !== 'undefined') data.parent = window.parent.controller;
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

	//Reset page if search parameters changed
	if(typeof data.page !== 'undefined') {
		$.cookie('page', data.page, { path: cookiePath });
	}

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
					initDwTabs('#content');
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

				//Hide loading
				$('#loading').hide();

				//Load map
				if(module == 'contacts') contactsMap();
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
		$('#catid').val('all');
		$.cookie('catid', 'all', { path: cookiePath });
		search();
	} else if(element == 'tagid') {
		$('#tagid').val(0);
		$.cookie('tagid', 0, { path: cookiePath });
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
		//$('div#'+type+id).remove();
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
					//$('div#'+type+id).remove();
					ids.forEach(function(singleId) {
						$('div#' + type + singleId).remove();
					});
					//Reload and calculate positions after a price rule is deleted
					if(type == 'pricerulepos') getPositions(type, 'pos', window.pageYOffset);
					//Return to the main page after the entity itself is deleted
					if(type == controller) window.location = baseUrl+'/'+cmodule+'/'+controller;
				} else {
					search();
					console.log('Deleted successfully');
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
			//$('div#'+type+id).remove();
			ids.forEach(function(singleId) {
				$('div#' + type + singleId).remove();
			});
		}
	});
}

//Apply position
function applyPosition(parent, type, itemId, setid) {
	$.ajax({
		type: 'POST',
		url: window.parent.baseUrl+'/'+window.parent.module+'/position/apply/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+window.parent.id+'/itemid/'+itemId,
		cache: false,
		success: function(){
			window.parent.getPositions(parent, type, $(parent.document).height());
			if(action == 'apply') {
				history.go(-1);
			}
		}
	});
	//console.log(parent);
}

//Edit position
function editPosition(parent, type, data, params) {
	if(params['controller'] == 'pricerulepos') {
		var url = baseUrl+'/'+params['module']+'/'+params['controller']+'/edit/id/'+params['id'];
	} else {
		var url = baseUrl+'/'+module+'/position';
		if(params['id']) url += '/edit/id/'+params['id'];
		if(params['parentid']) url += '/parent/'+parent+'/type/'+type+'/parentid/'+params['parentid'];
	}
	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		cache: false,
		success: function(response){
			isDirty = false;
			if((params['element'] == 'price') || (params['element'] == 'quantity') || (params['element'] == 'priceruleamount') || (params['element'] == 'priceruleaction')) {
				$('table#total #subtotal').text(response['subtotal']);
				$('table#total #total').text(response['total']);
				$('tr.position'+params['id']+'.wrap').find('.total').text(response[params['id']]['total']);
								$.each(response['taxes'], function(key, val) {
										if(key != 'total') $('td[data-rate="'+key+'"]').text(val);
								});
			} else if((params['element'] == 'taxrate') || (params['controller'] == 'pricerulepos')) {
				getPositions(parent, type, window.pageYOffset);
			}
		}
	});
}

//Add position
function addPosition(parent, type, setid) {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id,
		cache: false,
		success: function(){
			$('#status #warning').hide();
			$('#status #success').show();
			isDirty = false;
			getPositions(parent, type, $(document).height());
		}
	});
}

//Copy Position
function copyPosition(parent, type, positionID){
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/position/copy/parent/'+parent+'/type/'+type+'/id/'+positionID+'/parentid/'+id,
		cache: false,
		success: function(){
			getPositions(parent, type, window.pageYOffset);
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
		cache: false,
		data: data,
		success: function(){
			getPositions(parent, type, window.pageYOffset);
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
			$('.positionsContainer[data-parent="'+parent+'"]').html(data);
			autosize($('.positionsContainer').find('textarea'));
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

//Add option
function addOption(parent, type, optionid, setid, masterid) {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/position/add/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+id+'/optionid/'+optionid+'/masterid/'+masterid,
		cache: false,
		success: function(){
			$('#status #warning').hide();
			$('#status #success').show();
			isDirty = false;
			getPositions(parent, type, window.pageYOffset);
		}
	});
}

//Add Set
function addSet(parent, type) {
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+module+'/positionset/add/parent/'+parent+'/type/'+type+'/parentid/'+id,
		cache: false,
		success: function(){
			$('#status #warning').hide();
			$('#status #success').show();
			isDirty = false;
			getPositions(parent, type, $(document).height());
		}
	});
}

//Copy Set
function copySet(parent, type, setid){
	$.ajax({
		type: 'POST',
		url: baseUrl+'/'+window.parent.module+'/positionset/copy/parent/'+parent+'/type/'+type+'/id/'+setid+'/parentid/'+id,
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
			isDirty = false;
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

//Get Email Messages
function getEmailmessages(scrollTo) {
	if(module == 'contacts') var contactid = $('#id').val();
	else var contactid = $('#contactid').val();
	var data = {};
	if(controller != 'contact') {;
		data.documentid = id;
		data.module = module;
		data.controller = controller
	}
	scrollTo = scrollTo || null;
	$.ajax({
		type: 'POST',
		url: baseUrl+'/contacts/email/index/contactid/'+contactid,
		cache: false,
		data: data,
		success: function(data){
			$('#emailmessages').html(data);
			autosize($('#emailmessages').find('textarea'));
			if(data) $('#tabPositions .toolbar.emailmessages.bottom').show();
			else $('#tabPositions .toolbar.emailmessages.bottom').hide();
			if(scrollTo) {
				/*$('html, body').animate({
					scrollTop: $('#position'+scrollTo).offset().top
				}, 2000);*/
				window.scrollTo(0, scrollTo);
			}
			//$('.datePickerLive').datepicker(datePickerOptions);
		}
	});
}

function sendMessage(){
	var data = {};
	data.recipient = $('#recipient').val();
	data.cc = $('#cc').val();
	data.bcc = $('#bcc').val();
	data.replyto = $('#replyto').val();
	data.subject = $('#subject').val();
	data.body = tinymce.get('body').getContent();
	data.module = module;
	data.controller = controller;

	data.files = {};

	$('#attachments .file input[type="checkbox"]').each(function(index, element) {
		if($(this).is(':checked')) data.files[$(this).val()] = $(this).val();
	});

	if(module == 'contacts') {
		var contactid = $('#id').val();
		var url = baseUrl+'/contacts/email/send/contactid/'+contactid;
	} else if(module == 'campaigns') {
		var campaignid = $('#id').val();
		var url = baseUrl+'/contacts/email/send/campaignid/'+campaignid;
	} else {
		var contactid = $('#contactid').val();
		var url = baseUrl+'/contacts/email/send/contactid/'+contactid+'/documentid/'+id;
	}

	if((contactid > 0) || (campaignid > 0)) {
		$.ajax({
			type: 'POST',
			url: url,
			cache: false,
			data: data,
			success: function(response){
				getEmailmessages(window.pageYOffset);
			}
		});
	} else {
		$('#output').html('<b>Bitte vor dem speichern dem Beleg einen Kontakt zuweisen.</b>');
	}
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
function sort(parent, type, id, setid, ordering, masterid){
	var data = {};
	data.id = id;
	data.ordering = ordering;
	masterid = masterid || null;
	var url = baseUrl+'/'+module+'/';
	if(action == 'edit') {
		if(setid == -1) {
			url += 'positionset/sort/id/'+id+'/parent/'+parent+'/type/'+type+'/parentid/'+window.id;
		} else if(masterid) {
			url += 'position/sort/id/'+id+'/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+window.id+'/masterid/'+masterid;
		} else {
			url += 'position/sort/id/'+id+'/setid/'+setid+'/parent/'+parent+'/type/'+type+'/parentid/'+window.id;
		}
	} else {
		url += controller+'/sort/id/'+id;
	}
	$.ajax({
		type: 'POST',
		url: url,
		cache: false,
		data: data,
		success: function(response){
			if(action == 'edit') {
				getPositions(parent, type, window.pageYOffset);
			} else {
				search();
			}
		}
	});
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

function saveEntity(payload, target, context) {
	payload = payload || {};
	target = target || {};
	context = context || {};

	var requestPayload = $.extend({}, payload);

	if (context.parentModule) requestPayload.parent_module = context.parentModule;
	if (context.parentController) requestPayload.parent_controller = context.parentController;
	if (context.parentId) requestPayload.parent_id = context.parentId;

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
	if (context.parentId) requestPayload.parent_id = context.parentId;

	var url = baseUrl;

	if (target.module) url += '/' + target.module;
	else url += '/' + module;

	if (target.controller) url += '/' + target.controller;
	else url += '/' + controller;

	if (target.id !== undefined && target.id !== null && target.id !== '') {
		url += '/add/id/' + target.id;
	} else {
		url += '/add/id/' + id;
	}

	$.ajax({
		type: 'POST',
		url: url,
		data: requestPayload,
		cache: false,
		success: function(response) {
			isDirty = false;

			if ($container && $container.length) {
				var $target = $container.find('> .multiform');
				if (!$target.length) {
					$target = $container.find('.multiform').first();
				}

				if ($target.length) {
					var $addButton = $target.find('button.addMulti').first();
					$(response).insertBefore($addButton);

					var $newItem = $addButton.prev();
					$newItem.find('input, textarea, select').filter(':visible').first().focus().select();
				}
			}

			if (action == 'index') search();
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
