$(document).on('click', '.dw-alert__close', function () {
	$(this).closest('.dw-alert').remove();
});

$(document).ready(function () {
	$('[data-albis-url]').each(function () {
		const $container = $(this);
		$.getJSON($container.attr('data-albis-url')).done(function (response) {
			if (response && response.price === Number($container.attr('data-albis-price')) && typeof response.html === 'string' && response.html) $container.html(response.html).prop('hidden', false);
		});
	});

	const $cartState = $('#shop-cart-state');
	if ($cartState.length) {
		const config = JSON.parse($cartState.attr('data-config'));
		const $items = $('#cart-items');
		let revision = config.revision;
		let busy = false;
		let focusId = null;
		let focusRemove = false;
		const currency = new Intl.NumberFormat(document.documentElement.lang || 'de-DE', { style: 'currency', currency: 'EUR' });

		function feedback(message, error = false) {
			$('#cart-feedback').text(message).prop('hidden', !message).toggleClass('alert-danger', error).toggleClass('alert-info', !error);
		}

		function setBusy(value) {
			busy = value;
			$('#cart-container').attr('aria-busy', String(value));
			$('.add-to-cart, .quantity-input, .remove-item, #clear-cart, #checkout-btn').prop('disabled', value);
		}

		function render(response) {
			if (!response || !response.cart || !Number.isInteger(response.revision)) return false;
			revision = response.revision;
			const links = {};
			$items.find('.cart-item').each(function () { links[$(this).attr('data-id')] = $(this).find('.product-details a').attr('href'); });
			$items.empty();
			$.each(response.cart, function (id, item) {
				const $row = $('<div>', { class: 'cart-item' }).attr('data-id', id);
				const $details = $('<div>', { class: 'product-details' });
				const $title = $('<strong>', { class: 'product-title' }).text(item.title);
				$details.append(links[id] ? $('<a>').attr('href', links[id]).append($title) : $title);
				$details.append($('<div>', { class: 'product-sku text-muted' }).text(item.sku));
				$row.append($details);
				$row.append($('<div>', { class: 'cart-price' }).attr('data-label', config.price).text(currency.format(item.price)));
				const $input = $('<input>', { type: 'number', class: 'form-control quantity-input', min: 0, max: 100000, step: 0.0001 })
					.val(item.quantity).attr('data-id', id).attr('aria-label', config.quantity + ' ' + item.title);
				$row.append($('<div>', { class: 'cart-quantity' }).attr('data-label', config.quantity).append($input, $('<span>', { class: 'cart-uom' }).text(item.uom || '')));
				$row.append($('<div>', { class: 'item-total' }).attr('data-label', config.total).text(currency.format(item.total)));
				const $remove = $('<button>', { type: 'button', class: 'btn btn-danger btn-sm remove-item' }).attr('data-id', id)
					.attr('aria-label', config.remove + ' ' + item.title).text(config.remove);
				$row.append($('<div>', { class: 'cart-actions' }).append($remove));
				$items.append($row);
			});
			const empty = Object.keys(response.cart).length === 0;
			$('#cart-empty').prop('hidden', !empty);
			$('.cart-header, .cart-summary').prop('hidden', empty);
			$('#cart-total').text(currency.format(response.total));
			$('#cart-count').text(new Intl.NumberFormat(document.documentElement.lang || 'de-DE', { maximumFractionDigits: 4 }).format(response.cartItemCount));
			$('#checkout-btn').prop('hidden', empty || !response.checkoutEnabled);
			return true;
		}

		function restoreFocus() {
			if (!focusId) return;
			let $target = $items.find(focusRemove ? '.remove-item' : '.quantity-input').filter(function () { return $(this).attr('data-id') === focusId; });
			if (!$target.length) $target = $items.find('.quantity-input').first();
			if (!$target.length) $target = $('#cart-feedback');
			$target.trigger('focus');
			focusId = null;
		}

		function complete(response) {
			if (!render(response)) return false;
			feedback([response.message].concat(response.notices || []).filter(Boolean).join(' '), !response.success);
			setBusy(false);
			restoreFocus();
			return true;
		}

		function reconcile() {
			// A failed response may still have committed. Read the cart before allowing another write.
			$.ajax({ url: config.countUrl, dataType: 'json', timeout: 20000, cache: false }).done(function (response) {
				if (render(response)) {
					feedback([config.failed].concat(response.notices || []).join(' '), true);
					setBusy(false);
					restoreFocus();
				} else unavailable();
			}).fail(unavailable);
		}

		function unavailable() {
			feedback(config.unknown, true);
			$('#cart-reload').prop('hidden', false).trigger('focus');
		}

		function mutate(action, data, element) {
			if (busy) return;
			focusId = $(element).attr('data-id') || (action === 'clear' ? 'clear' : null);
			focusRemove = $(element).hasClass('remove-item');
			setBusy(true);
			feedback(config.saving);
			$.ajax({ url: config[action + 'Url'], method: 'POST', dataType: 'json', timeout: 20000,
				data: Object.assign({}, data, { csrf_token: config.token, revision: revision })
			}).done(function (response) {
				if (action === 'add' && response && response.success) {
					window.location.assign(config.url);
					return;
				}
				if (!complete(response)) reconcile();
			}).fail(function (xhr) {
				if (!complete(xhr.responseJSON)) reconcile();
			});
		}

		$(document).on('click', '.add-to-cart', function () { mutate('add', { id: $(this).attr('data-id'), quantity: 1 }, this); });
		$items.on('change', '.quantity-input', function () {
			if (busy) return;
			const value = this.value;
			if (!/^[0-9]+(?:\.[0-9]{1,4})?$/.test(value) || !this.checkValidity()) {
				feedback(config.invalid, true);
				$(this).attr('aria-invalid', 'true');
				return;
			}
			$(this).removeAttr('aria-invalid');
			mutate('update', { id: $(this).attr('data-id'), quantity: value }, this);
		});
		$items.on('click', '.remove-item', function () { mutate('remove', { id: $(this).attr('data-id') }, this); });
		$('#clear-cart').on('click', function () { mutate('clear', {}, this); });
		$('#checkout-btn').on('click', function () {
			if (!busy && !$items.find('[aria-invalid="true"]').length && config.checkout) window.location.assign(config.checkout);
		});
		$('#cart-reload').on('click', function () { window.location.reload(); });
		$(window).on('pageshow', function (event) { if (event.originalEvent.persisted) window.location.reload(); });
	}

	$('#edit-btn').on('click', function () {
		const config = $cartState.length ? JSON.parse($cartState.attr('data-config')) : null;
		if (config) window.location.assign(config.url);
	});

	// Add a change event listener to the checkbox
	$(document).on('change', 'form#checkout [name="differentshippingaddress"]', toggleShippingFields);
	toggleShippingFields();

	toggleDependentFields();

	// Gerät in Feld "geraetevorschlag" einfügen
	$(document).on('click', '.select-suggestion', function () {
		const selectedSku = $(this).data('sku');
		const selectedTitle = $(this).data('title');
		const $inputSku = $('[name="sku"]');
		const $inputTitle = $('[name="titel"]');

		if ($inputTitle.length) {
			$inputSku.val(selectedSku).focus();
			$inputTitle.val(selectedTitle).focus();
		} else {
			console.warn('Feld nicht gefunden.');
		}
	});

	function setMegaMenu($item, open) {
		const $toggle = $item.children('[data-mega-toggle]');
		const $panel = $item.children('.dw-mega-menu');
		$item.toggleClass('is-open', open);
		$toggle.attr('aria-expanded', open ? 'true' : 'false');
		$panel.prop('hidden', !open);
	}

	function closeMegaMenus($except) {
		$('.dw-mega-nav-item.is-open').each(function () {
			const $item = $(this);
			if (!$except || !$item.is($except)) setMegaMenu($item, false);
		});
	}

	function setSubmenu($item, open) {
		const $toggle = $item.children('.dw-submenu-toggle');
		const panelId = $toggle.attr('aria-controls');
		const $panel = panelId ? $(document.getElementById(panelId)) : $();
		$item.toggleClass('is-open', open);
		$toggle.attr('aria-expanded', open ? 'true' : 'false');
		$panel.prop('hidden', !open);
	}

	function closeSubmenus($scope) {
		($scope || $(document)).find('.dw-submenu-item.is-open').get().reverse().forEach(function (item) {
			setSubmenu($(item), false);
		});
	}

	$(document).on('click', '[data-mega-toggle]', function (event) {
		event.preventDefault();

		const $item = $(this).closest('.dw-mega-nav-item');
		const open = !$item.hasClass('is-open');

		closeMegaMenus($item);
		setMegaMenu($item, open);
	});

	$(document).on('click', '.dw-submenu-toggle', function (event) {
		event.preventDefault();
		setSubmenu($(this).closest('.dw-submenu-item'), $(this).attr('aria-expanded') !== 'true');
	});

	$(document).on('click', function (event) {
		if (!$(event.target).closest('.dw-mega-nav-item').length) closeMegaMenus();
		if (!$(event.target).closest('.dw-submenu-item').length) closeSubmenus($('.dw-main-nav'));
	});

	$(document).on('keydown', function (event) {
		if (event.key !== 'Escape') return;

		const $submenuItem = $(event.target).closest('.dw-submenu-item.is-open');
		if ($submenuItem.length) {
			event.preventDefault();
			const $toggle = $submenuItem.children('.dw-submenu-toggle');
			setSubmenu($submenuItem, false);
			$toggle.trigger('focus');
			return;
		}

		const $megaItem = $(event.target).closest('.dw-mega-nav-item.is-open');
		if ($megaItem.length) {
			event.preventDefault();
			const $toggle = $megaItem.children('[data-mega-toggle]');
			setMegaMenu($megaItem, false);
			$toggle.trigger('focus');
			return;
		}

		if ($('#navbarNav').hasClass('show')) {
			event.preventDefault();
			$('#navbarNav').data('restore-focus', true).collapse('hide');
		}
	});

	$('.dw-main-nav').on('focusout', function () {
		const navigation = this;
		setTimeout(function () {
			if (!navigation.contains(document.activeElement)) {
				closeMegaMenus();
				closeSubmenus($(navigation));
			}
		}, 0);
	});

	$('.dw-main-nav').on('focusout', '.dw-mega-nav-item, .dw-submenu-item', function () {
		const item = this;
		setTimeout(function () {
			if (item.contains(document.activeElement)) return;
			const $item = $(item);
			if ($item.hasClass('dw-mega-nav-item')) setMegaMenu($item, false);
			else setSubmenu($item, false);
		}, 0);
	});

	$('.dw-main-nav').on('mouseenter', '.dw-mega-nav-item, .dw-submenu-item', function () {
		if (!window.matchMedia('(min-width: 1200px) and (hover: hover)').matches) return;
		const $item = $(this);
		if ($item.hasClass('dw-mega-nav-item')) {
			closeMegaMenus($item);
			setMegaMenu($item, true);
		} else {
			setSubmenu($item, true);
		}
	}).on('mouseleave', '.dw-mega-nav-item, .dw-submenu-item', function () {
		if (!window.matchMedia('(min-width: 1200px) and (hover: hover)').matches) return;
		const $item = $(this);
		if ($item.hasClass('dw-mega-nav-item')) setMegaMenu($item, false);
		else setSubmenu($item, false);
	});

	$('#navbarNav').on('hidden.bs.collapse', function () {
		closeMegaMenus();
		closeSubmenus($(this));
		if ($(this).data('restore-focus')) {
			$(this).removeData('restore-focus');
			$('.navbar-toggler').trigger('focus');
		}
	});

	$(document).on('click', '[data-carousel-toggle]', function () {
		const $button = $(this);
		const $carousel = $(document.getElementById($button.attr('aria-controls')));
		const paused = $button.attr('data-paused') !== 'true';
		$carousel.carousel(paused ? 'pause' : 'cycle');
		$button.attr('data-paused', paused ? 'true' : 'false')
			.text($button.data(paused ? 'play-label' : 'pause-label'));
	});

	$('.carousel[data-ride="carousel"]').each(function () {
		if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
		$(this).carousel('pause');
		$(this).find('[data-carousel-toggle]').attr('data-paused', 'true').each(function () {
			$(this).text($(this).data('play-label'));
		});
	});

	$('.carousel').on('slid.bs.carousel', function () {
		$(this).find('.dw-carousel-indicator').removeAttr('aria-current').filter('.active').attr('aria-current', 'true');
	});

	let consentOpener = null;
	$(document).on('click', '[data-consent-open]', function () {
		consentOpener = this;
		$(this).attr('aria-expanded', 'true');
		$('[data-consent-panel]').prop('hidden', false).trigger('focus');
	});

	function closeConsentPanel() {
		$('[data-consent-panel]').prop('hidden', true);
		$('[data-consent-open]').attr('aria-expanded', 'false');
		if (consentOpener) $(consentOpener).trigger('focus');
	}

	$(document).on('click', '[data-consent-close]', function () {
		closeConsentPanel();
	});

	$(document).on('keydown', '[data-consent-panel]:not([data-consent-initial="true"])', function (event) {
		if (event.key !== 'Escape') return;
		event.preventDefault();
		closeConsentPanel();
	});

	const $initialConsent = $('[data-consent-panel][data-consent-initial="true"]');
	if ($initialConsent.length) setTimeout(function () { $initialConsent.trigger('focus'); }, 0);

	enhancePublicForms(document);
	enhanceCheckout();
});

function enhancePublicForms(context) {
	$(context).find('.alert-danger').attr({ role: 'alert', 'aria-live': 'assertive' });
	$(context).find('.dw-field').each(function () {
		const $field = $(this);
		const $control = $field.find('input:not([type="hidden"]), select, textarea').first();
		const $error = $field.find('.dw-field__error').first();
		if (!$control.length || !$error.length || $error.prop('hidden') || !$error.text().trim()) return;

		const controlId = $control.attr('id');
		if (!controlId) return;
		const errorId = controlId + '-error';
		const describedBy = ($control.attr('aria-describedby') || '').split(/\s+/).filter(Boolean);
		if (!describedBy.includes(errorId)) describedBy.push(errorId);
		$error.attr({ id: errorId, role: 'alert' });
		$control.attr({ 'aria-invalid': 'true', 'aria-describedby': describedBy.join(' ') });
	});

	$(context).find('form#contact, form#checkout:not(.dw-checkout-form)').off('submit.dewawiSubmission').on('submit.dewawiSubmission', function (event) {
		const $form = $(this);
		if ($form.data('submitting')) {
			event.preventDefault();
			return;
		}
		$form.data('submitting', true);
		$form.find('button[type="submit"], input[type="submit"]').prop('disabled', true);
	});
}

const siteConversionMarkers = new Set();

function dispatchSiteConversions(events) {
	if (!Array.isArray(events)) return 0;

	let dispatched = 0;
	events.forEach(function (event) {
		if (!event || typeof event !== 'object') return;
		const marker = String(event.id || '');
		const name = String(event.name || '');
		const analyticsInterface = String(event.interface || '');
		const parameters = event.parameters;
		if (!/^[a-f0-9]{32}$/.test(marker) || siteConversionMarkers.has(marker)) return;
		if (!['contact_submitted', 'technical_inquiry_submitted', 'order_submitted'].includes(name)) return;
		if (!parameters || typeof parameters !== 'object' || Array.isArray(parameters)) return;

		const keys = Object.keys(parameters).sort();
		const expectedKeys = name === 'order_submitted' ? ['currency', 'shop_id', 'value'] : ['shop_id'];
		if (keys.length !== expectedKeys.length || keys.some(function (key, index) { return key !== expectedKeys[index]; })) return;
		if (!Number.isInteger(parameters.shop_id) || parameters.shop_id <= 0) return;
		if (name === 'order_submitted'
			&& (!Number.isFinite(parameters.value) || parameters.value < 0
				|| typeof parameters.currency !== 'string' || !/^[A-Z]{3}$/.test(parameters.currency))) return;

		siteConversionMarkers.add(marker);
		if (analyticsInterface === 'gtag' && typeof window.gtag === 'function') {
			window.gtag('event', name, parameters);
			dispatched++;
		} else if (analyticsInterface === 'dataLayer' && Array.isArray(window.dataLayer)) {
			window.dataLayer.push(Object.assign({ event: name }, parameters));
			dispatched++;
		}
	});

	return dispatched;
}

function dispatchSiteConversionElement(element) {
	if (!element || element.dataset.dispatched === 'true') return 0;
	element.dataset.dispatched = 'true';

	try {
		return dispatchSiteConversions(JSON.parse(element.textContent || '[]'));
	} catch (error) {
		return 0;
	}
}

function toggleDependentFields() {
	$('form').each(function () {
		const $form = $(this);
		if (!$form.data('inquiry-details-handler')) {
			this.addEventListener('invalid', function (event) {
				$(event.target).parents('details').prop('open', true);
			}, true);
			$form.data('inquiry-details-handler', true);
		}
		function isActive($group, visited) {
			if (!$group.length) return true;
			const parent = $group.attr('data-depends-on');
			if (visited.includes(parent)) return false;
			const $triggers = $form.find(':input').filter(function () { return this.name === parent; });
			if (!$triggers.length) return false;
			if (!isActive($triggers.first().closest('.dependent-field'), visited.concat(parent))) return false;
			const $choices = $triggers.filter(':radio, :checkbox');
			const value = $choices.length ? ($choices.filter(':checked').val() || '0') : $triggers.first().val();
			const values = $group.attr('data-depends-values');
			return values ? JSON.parse(values).includes(String(value == null ? '' : value)) : String(value == null ? '' : value) === $group.attr('data-depends-value');
		}
		function update() {
			$form.find('.dependent-field').each(function () {
				const $group = $(this);
				const active = isActive($group, []);
				$group.toggle(active).attr('aria-hidden', String(!active));
				$group.find(':input').each(function () {
					const $input = $(this);
					if ($input.data('dependency-disabled') === undefined) $input.data('dependency-disabled', this.disabled);
					$input.prop('disabled', !active || $input.data('dependency-disabled'));
				});
			});
			$form.find('input[data-min-exclusive]').each(function () {
				const minimum = this.getAttribute('data-min-exclusive');
				this.setCustomValidity(!this.disabled && this.value !== '' && Number(this.value) <= Number(minimum) ? 'Bitte geben Sie einen Wert größer als ' + minimum + ' ein.' : '');
			});
			$form.find('details.inquiry-details').each(function () {
				$(this).toggle($(this).find(':input:enabled').length > 0);
			});
		}
		$form.off('.inquiryDependencies').on('change.inquiryDependencies input.inquiryDependencies', ':input', update);
		update();
		$form.find('.select-catalog-suggestion').each(function () {
			const name = $(this).attr('data-item-field');
			const $input = $form.find(':input').filter(function () { return this.name === name; });
			if ($input.val() === $(this).attr('data-itemid')) selectInquiryCatalogItem(this, false);
		});
	});
}

function selectInquiryCatalogItem(button, notify = true) {
	const $button = $(button);
	const $form = $button.closest('form');
	const field = $button.attr('data-item-field');
	const $input = $form.find(':input').filter(function () { return this.name === field; });
	if (!$input.length || $input.prop('disabled')) return false;
	$input.val($button.attr('data-itemid'));
	$form.find('[name="sku"]').val($button.attr('data-sku'));
	$form.find('[name="titel"]').val($button.attr('data-title'));
	$form.find('.select-catalog-suggestion').attr('aria-pressed', 'false');
	if ($button.hasClass('select-catalog-suggestion')) $button.attr('aria-pressed', 'true');
	$form.find('.inquiry-catalog-choice').text($button.attr('data-itemid') ? $form.attr('data-inquiry-selected') + ' ' + $button.attr('data-sku') + ' – ' + $button.attr('data-title') : $form.attr('data-inquiry-unselected'));
	if (notify) $input.trigger('change');
	return true;
}

function invalidateInquiryVariant(form) {
	const $form = $(form);
	const $selection = $form.find('.inquiry-variants');
	const name = $selection.attr('data-variant-field');
	$form.find(':input').filter(function () { return this.name === name; }).val('');
	$selection.find('.select-inquiry-variant').attr('aria-pressed', 'false');
	$selection.find('.variant-current-selection, .variant-pricing, .variant-review-reasons').hide();
	$selection.find('.variant-pending').text($form.attr('data-inquiry-changed'));
}

function selectInquiryVariant(button) {
	const $button = $(button);
	const $form = $button.closest('form');
	invalidateInquiryVariant($form);
	const values = JSON.parse($button.attr('data-field-values'));
	Object.keys(values).forEach(function (name) {
		$form.find(':input').filter(function () { return this.name === name; }).val(values[name]).trigger('change');
	});
	const name = $button.closest('.inquiry-variants').attr('data-variant-field');
	$form.find(':input').filter(function () { return this.name === name; }).val($button.attr('data-variantid'));
	$button.attr('aria-pressed', 'true');
}

function toggleShippingFields() {
	const $form = $('form#checkout');
	const different = $form.find('[name="differentshippingaddress"]').is(':checked');
	const $shipping = $form.find('#shipping-container');
	$shipping.prop('hidden', !different).attr('aria-hidden', String(!different));
	$shipping.find(':input').prop('disabled', !different);
	$shipping.find('[data-checkout-required]').prop('hidden', !different);
	$shipping.find('[name="shippingname"], [name="shippingstreet"], [name="shippingpostcode"], [name="shippingcity"], [name="shippingcountry"]').prop('required', different);
}

function enhanceCheckout() {
	const $form = $('form#checkout.dw-checkout-form');
	if (!$form.length) return;
	let busy = false;
	let locked = $();
	let uncertainPayload = null;
	let navigating = false;
	function lock() {
		busy = true;
		locked = $form.find(':input:enabled').not(':hidden');
		locked.prop('disabled', true);
		$form.attr('aria-busy', 'true');
	}
	function unlock() {
		busy = false;
		locked.prop('disabled', false);
		$form.attr('aria-busy', 'false');
	}
	function message(text, error = true) {
		$form.find('#checkout-feedback').text(text).prop('hidden', !text).toggleClass('alert-danger', error).toggleClass('alert-info', !error).trigger('focus');
	}
	function edit() {
		$form.find('#checkout-review').empty().prop('hidden', true);
		$form.find('[data-checkout-fields]').prop('hidden', false);
		$form.find('[name="checkout_stage"]').val('preview');
	}
	function redirect(response) {
		if (!response || !response.success || typeof response.redirect !== 'string' || !/^\/(?!\/)/.test(response.redirect)) return false;
		navigating = true;
		window.location.assign(response.redirect);
		return true;
	}
	function csrf(response) {
		if (response && typeof response.csrfToken === 'string') $form.find('[name="csrf_token"]').val(response.csrfToken);
	}
	function recover() {
		$form.find('#checkout-recover').prop('disabled', true).prop('hidden', true);
		$.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', timeout: 20000, data: uncertainPayload + '&status_only=1' }).done(function (response) {
			csrf(response);
			if (response && response.stored && redirect(response)) return;
			if (response && response.success && response.stored === false) {
				uncertainPayload = null;
				unlock();
				message($form.attr('data-retry'));
			} else recoveryFailed();
		}).fail(recoveryFailed);
	}
	function recoveryFailed() {
		message($form.attr('data-unknown'));
		$form.find('#checkout-recover').prop('hidden', false).prop('disabled', false).trigger('focus');
	}
	$form.on('click', '[name="checkout_edit"]', function (event) {
		event.preventDefault();
		if (busy) return;
		edit();
		$form.find('[data-checkout-fields] :input:visible').first().trigger('focus');
	});
	$form.on('input change', '[data-checkout-fields] :input', function () {
		if (busy) return;
		edit();
		$(this).removeAttr('aria-invalid aria-describedby').closest('.dw-field').find('.dw-field__error').text('').prop('hidden', true);
	});
	$form.on('click', '#checkout-recover', recover);
	$form.on('submit.checkout', function (event) {
		event.preventDefault();
		if (busy || navigating) return;
		if (!this.checkValidity()) { this.reportValidity(); return; }
		const final = $form.find('[name="checkout_stage"]').val() === 'confirm';
		const payload = $form.serialize();
		$form.find('[aria-invalid]').removeAttr('aria-invalid aria-describedby');
		$form.find('.dw-field__error').text('').prop('hidden', true);
		lock();
		message($form.attr(final ? 'data-saving' : 'data-previewing'), false);
		function failed(xhr) {
			const response = xhr.responseJSON;
			csrf(response);
			if (final && (!response || response.code === 'SHOPS_CHECKOUT_SAVE_UNCERTAIN')) {
				uncertainPayload = payload;
				recoveryFailed();
				recover();
				return;
			}
			unlock();
			message((response && response.message) || $form.attr('data-failed'));
			if (response && response.code === 'SHOPS_FORM_EXPIRED') $form.find(':submit').prop('disabled', true);
			if (response && ['SHOPS_CART_CHANGED', 'SHOPS_CHECKOUT_REVIEW_CHANGED', 'SHOPS_FORM_INVALID'].includes(response.code)) edit();
			if (response && response.errors) {
				Object.keys(response.errors).forEach(function (name) {
					const $input = $form.find(':input').not('[type="hidden"]').filter(function () { return this.name === name; });
					const $error = $input.closest('.dw-field').find('.dw-field__error');
					$error.text(Array.from(new Set(Object.values(response.errors[name]))).join(' ')).prop('hidden', false).attr('role', 'alert');
					if ($error.length) $input.attr('aria-invalid', 'true').attr('aria-describedby', $error.attr('id'));
				});
				$form.find('[aria-invalid="true"]').first().trigger('focus');
			}
		}
		$.ajax({ url: $form.attr('action'), method: 'POST', dataType: 'json', timeout: 20000, data: payload }).done(function (response) {
			csrf(response);
			if (redirect(response)) return;
			if (!final && response && response.success && response.reviewHtml) {
				$form.find('#checkout-review').html(response.reviewHtml).prop('hidden', false);
				$form.find('[data-checkout-fields]').prop('hidden', true);
				$form.find('[name="checkout_stage"]').val('confirm');
				unlock();
				message('', false);
				$form.find('#checkout-review-title').trigger('focus');
			} else failed({ responseJSON: response });
		}).fail(failed);
	});
}

function submitStep(formElement, currentStep, preview = false) {
	if (!formElement.checkValidity()) {
		formElement.reportValidity();
		return false;
	}

	const $form = $(formElement);
	if ($form.data('submitting')) return false;
	$form.data('submitting', true);
	const $buttons = $form.find('button, input[type="submit"]');
	$buttons.prop('disabled', true);
	const formData = $(formElement).serialize() + '&step=' + encodeURIComponent(currentStep) + (preview ? '&preview=1' : '');

	$.post(window.location.href, formData, function (response) {
		dispatchSiteConversions(response && response.conversionEvents);
		const newContainer = response && response.formHtml ? $(response.formHtml).find('#form-container') : $();
		if (newContainer.length) {
			$('#form-container').replaceWith(newContainer);
			toggleDependentFields();
			enhancePublicForms(newContainer);
			const $invalid = newContainer.find('.is-invalid').first();
			if ($invalid.length) {
				$invalid.trigger('focus');
			} else {
				newContainer.attr('tabindex', '-1').trigger('focus');
			}
		} else if (!response || response.status !== 'ok') {
			window.alert((response && response.message) || $form.attr('data-inquiry-failed'));
		}
	}, 'json').fail(function (xhr) {
		const response = xhr.responseJSON || {};
		window.alert(response.message || $form.attr('data-inquiry-failed'));
	}).always(function () {
		$form.data('submitting', false);
		$buttons.prop('disabled', false);
	});

	return false;
}

$(document).on('submit', '#inquiry-form', function (event) {
	event.preventDefault();
	submitStep(this, String($(this).data('current-step') || ''));
});

$(document).on('click', '#inquiry-form [data-inquiry-back]', function () {
	goBackStep(String($(this).closest('form').attr('data-current-step') || ''));
});

$(document).on('click', '#inquiry-form .select-catalog-suggestion', function () {
	selectInquiryCatalogItem(this);
});

$(document).on('click', '#inquiry-form .clear-catalog-suggestion', function () {
	selectInquiryCatalogItem(this);
});

$(document).on('change input', '#inquiry-form [data-variant-choice]', function () {
	invalidateInquiryVariant($(this).closest('form'));
});

$(document).on('click', '#inquiry-form .select-inquiry-variant', function () {
	selectInquiryVariant(this);
});

$(document).on('click', '#inquiry-form .preview-inquiry-variant', function () {
	const form = $(this).closest('form')[0];
	submitStep(form, String($(form).attr('data-current-step') || ''), true);
});

$(document).on('click', '[data-show-tab]', function () {
	const itemId = String($(this).data('item-id') || '');
	const tabName = String($(this).data('show-tab') || '');
	if (!/^\d+$/.test(itemId) || !/^[a-z0-9_-]+$/i.test(tabName)) return;
	$('#details_' + itemId).collapse('show');
	$('#productTab_' + itemId + ' a[href="#' + tabName + '_' + itemId + '"]').tab('show');
});

$(document).on('click', '[data-show-section]', function () {
	const sectionName = String($(this).data('show-section') || '');
	if (!/^[a-z0-9_-]+$/i.test(sectionName)) return;
	const section = document.getElementById(sectionName);
	if (!section) return;
	const leasingMessage = $(this).attr('data-albis-message');
	if (leasingMessage) {
		const $message = $(section).find('textarea[name="message"]');
		const current = String($message.val() || '');
		if (!current.includes(leasingMessage)) $message.val(current ? current + '\n\n' + leasingMessage : leasingMessage).trigger('change');
	}
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	section.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
	section.style.transition = 'background-color 0.5s ease';
	section.style.backgroundColor = '#f8f9fa';
	setTimeout(function () {
		section.style.backgroundColor = '';
	}, 2000);
});

$(document).on('change', '[data-albis-term]', function () {
	const term = String($(this).val());
	const $leasing = $(this).closest('.dw-leasing');
	$leasing.find('[data-albis-details]').each(function () { this.hidden = $(this).attr('data-albis-details') !== term; });
	$leasing.find('[data-albis-interest]').each(function () { this.hidden = $(this).attr('data-albis-interest') !== term; });
});

function goBackStep(currentStep) {
	var $form = $('#inquiry-form');
	if ($form.data('submitting')) return false;
	$form.data('submitting', true);
	var $buttons = $form.find('button, input[type="submit"]');
	$buttons.prop('disabled', true);
	var url = $form.attr('action') || window.location.href;

	// include existing form fields + back flag + current step
	var formData = $form.serialize() +
				'&step=' + encodeURIComponent(currentStep) +
				'&back=1';

	$.post(url, formData, function (response) {
		const newContainer = response && response.formHtml ? $(response.formHtml).find('#form-container') : $();
		if (response && response.status === 'ok' && newContainer.length) {
			$('#form-container').replaceWith(newContainer);
			toggleDependentFields();
			enhancePublicForms(newContainer);
			newContainer.attr('tabindex', '-1').trigger('focus');
		}
	}, 'json').fail(function (xhr) {
		const response = xhr.responseJSON || {};
		window.alert(response.message || $form.attr('data-inquiry-failed'));
	}).always(function () {
		$form.data('submitting', false);
		$buttons.prop('disabled', false);
	});

	return false;
}

