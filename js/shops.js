$(document).on('click', '.dw-alert__close', function () {
	$(this).closest('.dw-alert').remove();
});

$(document).ready(function () {
	const cartItemsContainer = $('#cart-items');
	const cartTotal = $('#cart-total');
	const cartCount = $('#cart-count');

	// Define the locale and currency
	const userLocale = 'de-DE'; // German locale
	const userCurrency = 'EUR'; // Euro currency

	function formatCurrency(value, locale, currency) {
		return new Intl.NumberFormat(locale, {
			style: 'currency',
			currency: currency,
		}).format(value);
	}

	// Function to render cart items dynamically
	function renderCartItems(cart) {
		const cartItemsContainer = $('#cart-items'); // Ensure the container is correctly selected
		const quantityLabel = cartItemsContainer.data('quantity-label') || 'Quantity';
		const removeLabel = cartItemsContainer.data('remove-label') || 'Remove';
		const emptyLabel = cartItemsContainer.data('empty-label') || 'Your cart is empty.';
		cartItemsContainer.empty();

		if (Object.keys(cart).length === 0) {
			cartItemsContainer.append($('<p>').text(emptyLabel));
		} else {
			$.each(cart, function (id, item) {
				const title = String(item.title ?? '');
				const sku = String(item.sku ?? '');
				const $row = $('<div>', { class: 'cart-item d-flex justify-content-between align-items-center' }).attr('data-id', id);
				const $details = $('<div>', { class: 'product-details' }).css({ flex: 2, display: 'flex', flexDirection: 'column', justifyContent: 'center' });
				$details.append($('<strong>', { class: 'product-title' }).text(title));
				$details.append($('<div>', { class: 'product-sku text-muted' }).css('font-size', '0.9rem').text('SKU: ' + sku));
				$row.append($details);
				$row.append($('<div>').css({ flex: 1, textAlign: 'center' }).text(formatCurrency(item.price, userLocale, userCurrency)));
				const $quantity = $('<input>', { type: 'number', class: 'form-control quantity-input', min: 1 })
					.val(item.quantity).attr('data-id', id).attr('aria-label', quantityLabel + ' ' + title);
				$row.append($('<div>').css({ flex: 1, textAlign: 'center' }).append($quantity));
				$row.append($('<div>', { class: 'item-total' }).css({ flex: 1, textAlign: 'center' }).text(formatCurrency(item.price * item.quantity, userLocale, userCurrency)));
				const $remove = $('<button>', { type: 'button', class: 'btn btn-danger btn-sm remove-item' })
					.attr('data-id', id).text(removeLabel);
				$row.append($('<div>').css({ flex: 1, textAlign: 'center' }).append($remove));
				cartItemsContainer.append($row);
			});
		}
	}

	// Function to update cart summary
	function updateCartSummary(total) {
		cartTotal.text(formatCurrency(total, userLocale, userCurrency));
	}

	// Function to update cart count
	function updateCartCount(count) {
		cartCount.text(count || 0);
	}

	// Add to Cart
	$('.add-to-cart').on('click', function () {
		const id = $(this).data('id');

		$.post('/cart/add', { id, quantity: 1 }, function (response) {
			if (response.success) {
				renderCartItems(response.cart);
				updateCartSummary(response.total);
				updateCartCount(response.cartItemCount);
				window.location.href = '/cart';
			} else {
				console.error('Failed to add to cart:', response.message);
			}
		}).fail(function () {
			console.error('Error adding item to cart.');
		});
	});

	// Update Cart Item
	cartItemsContainer.on('click', '.update-item', function () {
		const id = $(this).data('id');
		const quantity = $(this).data('quantity');

		if (quantity <= 0) {
			console.log('Quantity must be at least 1.');
			return;
		}

		$.post('/cart/update', { id, quantity }, function (response) {
			if (response.success) {
				renderCartItems(response.cart);
				updateCartSummary(response.total);
				updateCartCount(response.cartItemCount);
			} else {
				console.log('Failed to update item quantity.');
			}
		});
	});

	// Update Item Quantity
	cartItemsContainer.on('change', '.quantity-input', function () {
		const id = $(this).data('id');
		const quantity = $(this).val();

		if (quantity <= 0) {
			console.error('Quantity must be greater than zero.');
			return;
		}

		$.post('/cart/update', { id, quantity }, function (response) {
			if (response.success) {
				renderCartItems(response.cart);
				updateCartSummary(response.total);
				updateCartCount(response.cartItemCount);
			}
		});
	});

	// Remove from Cart
	cartItemsContainer.on('click', '.remove-item', function () {
		const id = $(this).data('id');

		$.post('/cart/remove', { id }, function (response) {
			if (response.success) {
				renderCartItems(response.cart);
				updateCartSummary(response.total);
				updateCartCount(response.cartItemCount);
			} else {
				console.log('Failed to remove item from the cart.');
			}
		});
	});

	// Clear Cart
	$('#clear-cart').on('click', function () {
		$.post('/cart/clear', function (response) {
			if (response.success) {
				renderCartItems(response.cart);
				updateCartSummary(response.total);
				updateCartCount(0);
				console.log('Cart cleared.');
			} else {
				console.log('Failed to clear the cart.');
			}
		});
	});

	$('#checkout-btn').on('click', function () {
		window.location.href = 'checkout';
	});

	$('#edit-btn').on('click', function () {
		window.location.href = 'cart';
	});

	// Add a change event listener to the checkbox
	$('#differentshippingaddress').change(function () {
		toggleShippingFields();
	});

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
});

function enhancePublicForms(context) {
	$(context).find('.alert-danger').attr({ role: 'alert', 'aria-live': 'assertive' });
	$(context).find('.dw-field').each(function () {
		const $field = $(this);
		const $control = $field.find('input, select, textarea').first();
		const $error = $field.find('.dw-field__error').first();
		if (!$control.length || !$error.length) return;

		const controlId = $control.attr('id');
		if (!controlId) return;
		const errorId = controlId + '-error';
		const describedBy = ($control.attr('aria-describedby') || '').split(/\s+/).filter(Boolean);
		if (!describedBy.includes(errorId)) describedBy.push(errorId);
		$error.attr({ id: errorId, role: 'alert' });
		$control.attr({ 'aria-invalid': 'true', 'aria-describedby': describedBy.join(' ') });
	});

	$(context).find('form#contact, form#checkout').off('submit.dewawiSubmission').on('submit.dewawiSubmission', function (event) {
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
		function isActive($group, visited) {
			if (!$group.length) return true;
			const parent = $group.attr('data-depends-on');
			if (visited.includes(parent)) return false;
			const $triggers = $form.find(':input').filter(function () { return this.name === parent; });
			if (!$triggers.length) return false;
			if (!isActive($triggers.first().closest('.dependent-field'), visited.concat(parent))) return false;
			const $choices = $triggers.filter(':radio, :checkbox');
			const value = $choices.length ? ($choices.filter(':checked').val() || '0') : $triggers.first().val();
			return String(value == null ? '' : value) === $group.attr('data-depends-value');
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
		}
		$form.off('change.inquiryDependencies').on('change.inquiryDependencies', ':input', update);
		update();
	});
}

// Function to show or hide shipping fields
function toggleShippingFields() {
	if ($('#differentshippingaddress').is(':checked')) {
		// Show shipping fields if checkbox is checked
		$('#shipping-container').slideDown();
	} else {
		// Hide shipping fields if checkbox is unchecked
		$('#shipping-container').slideUp();
	}
}

function submitStep(formElement, currentStep) {
	if (!formElement.checkValidity()) {
		formElement.reportValidity();
		return false;
	}

	const $form = $(formElement);
	if ($form.data('submitting')) return false;
	$form.data('submitting', true);
	const $buttons = $form.find('button, input[type="submit"]');
	$buttons.prop('disabled', true);
	const formData = $(formElement).serialize() + '&step=' + encodeURIComponent(currentStep);

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
			window.alert((response && response.message) || 'Das Formular konnte nicht verarbeitet werden.');
		}
	}, 'json').fail(function (xhr) {
		const response = xhr.responseJSON || {};
		window.alert(response.message || 'Das Formular konnte nicht verarbeitet werden. Bitte laden Sie die Seite neu.');
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
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	section.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
	section.style.transition = 'background-color 0.5s ease';
	section.style.backgroundColor = '#f8f9fa';
	setTimeout(function () {
		section.style.backgroundColor = '';
	}, 2000);
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
		window.alert(response.message || 'Das Formular konnte nicht verarbeitet werden. Bitte laden Sie die Seite neu.');
	}).always(function () {
		$form.data('submitting', false);
		$buttons.prop('disabled', false);
	});

	return false;
}

