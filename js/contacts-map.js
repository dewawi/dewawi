(function ($) {
	'use strict';
	var states = [];
	var filterNames = ['keyword', 'role', 'catid', 'tagid', 'country'];

	function initialize(element) {
		var $root = $(element);
		if ($root.data('dw-map-state')) return;
		var messages = JSON.parse($root.attr('data-messages'));
		if (window.matchMedia('(max-width: 480px)').matches) $root.find('[data-map-filters]').prop('open', false);
		var map = L.map($root.find('[data-map-canvas]')[0]).setView([51.2254, 6.7763], 5);
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		}).addTo(map);
		var layers = L.layerGroup().addTo(map);
		var state = {element: element, map: map, requests: {}, serials: {}, revision: 0, timer: null, filterTimer: null};
		$root.data('dw-map-state', state);
		states.push(state);
		var summary = null;
		var activeFilters = {};

		function filters() {
			var values = {};
			filterNames.forEach(function (name) { values[name] = $root.find('[name="' + name + '"]').val(); });
			return values;
		}

		function navigation() {
			var $link = $root.find('[data-contact-map-list]');
			var query = '?' + $.param(filters());
			$link.attr('href', $link.attr('data-url') + query);
			if (window.action === 'map') window.history.replaceState(null, '', $root.attr('data-page-url') + query);
		}

		function status(text, failed) {
			$root.find('[data-map-status]').text(text).toggleClass('is-error', !!failed).attr('role', failed ? 'alert' : 'status');
			$root.find('[data-contact-map-retry]').prop('hidden', !failed);
		}

		function failure() {
			layers.clearLayers();
			$root.find('[data-map-count="visible"]').text('–');
			status(messages.error, true);
		}

		function request(kind, data, success, failed) {
			var revision = state.revision;
			var serial = (state.serials[kind] || 0) + 1;
			state.serials[kind] = serial;
			if (state.requests[kind]) state.requests[kind].abort();
			state.requests[kind] = $.ajax({
				url: $root.attr('data-url'), data: $.extend({}, activeFilters, data), dataType: 'json', timeout: 20000,
				success: function (response) {
					if (revision !== state.revision || serial !== state.serials[kind]) return;
					if (!response || response.ok !== true) { (failed || failure)(); return; }
					success(response);
				},
				error: function (xhr, reason) {
					if (reason !== 'abort' && revision === state.revision && serial === state.serials[kind]) (failed || failure)();
				}
			});
		}

		function contactLink(contact) {
			var link = document.createElement('a');
			link.href = contact.url;
			link.appendChild(document.createTextNode(String(contact.name1 || '')));
			link.appendChild(document.createElement('br'));
			link.appendChild(document.createTextNode(String(contact.contactid || '')));
			return link;
		}

		function openGroup(marker, group) {
			var $popup = $('<div>', {'class': 'dw-contact-map__popup'});
			$('<small>').text(messages.approximate).appendTo($popup);
			var $notice = $('<p>').appendTo($popup);
			var $list = $('<ul>').appendTo($popup);
			var $more = $('<button>', {type: 'button', 'class': 'dw-btn dw-btn--action'}).text(messages.more).appendTo($popup);
			var cursor = 0;
			marker.bindPopup($popup[0], {autoPan: false, maxWidth: 300}).openPopup();
			function load() {
				$notice.text(messages.loading);
				$more.prop('disabled', true);
				request('popup', {mode: 'contacts', south: group.south, north: group.north, west: group.west, east: group.east, after: cursor}, function (data) {
					if (!map.hasLayer(marker) || !marker.isPopupOpen()) return;
					data.contacts.forEach(function (contact) { $('<li>').append(contactLink(contact)).appendTo($list); });
					$notice.text(data.contacts.length ? '' : messages.empty);
					cursor = data.after;
					$more.text(messages.more).prop('disabled', false).prop('hidden', cursor === null);
					marker.getPopup().update();
				}, function () {
					$notice.text(messages.error);
					$more.text(messages.retry).prop('disabled', false).prop('hidden', false);
				});
			}
			$more.on('click', load);
			load();
		}

		function bounds() {
			var area = map.getBounds();
			var west = area.getWest(), east = area.getEast();
			if (east - west >= 360) { west = -180; east = 180; }
			else {
				west = ((west + 180) % 360 + 360) % 360 - 180;
				east = ((east + 180) % 360 + 360) % 360 - 180;
			}
			return {south: area.getSouth() <= -85 ? -90 : area.getSouth(), north: area.getNorth() >= 85 ? 90 : area.getNorth(), west: west, east: east};
		}

		function loadMarkers() {
			if (!summary || !summary.positioned) return;
			status(messages.loading);
			request('markers', $.extend({mode: 'markers', zoom: map.getZoom()}, bounds()), function (data) {
				layers.clearLayers();
				data.groups.forEach(function (group) {
					var marker;
					var longitude = group.longitude + 360 * Math.round((map.getCenter().lng - group.longitude) / 360);
					if (group.count === 1) {
						marker = L.marker([group.latitude, longitude], {title: (group.name1 || messages.open) + ' · ' + messages.approximate});
						var $single = $('<div>').append(contactLink(group));
						$('<p>').append($('<small>').text(messages.approximate)).appendTo($single);
						marker.bindPopup($single[0], {autoPan: false});
					} else {
						marker = L.marker([group.latitude, longitude], {
							title: group.count + ' · ' + messages.zoom,
							icon: L.divIcon({className: 'dw-contact-map__cluster', html: String(Number(group.count)), iconSize: [38, 38]})
						});
						marker.on('click', function () {
							if (map.getZoom() < 19 && (group.south !== group.north || group.west !== group.east)) {
								map.fitBounds([[group.south, group.west], [group.north, group.east]], {padding: [30, 30], maxZoom: map.getZoom() + 2, animate: false});
							} else openGroup(marker, group);
						});
					}
					layers.addLayer(marker);
				});
				$root.find('[data-map-count="visible"]').text(data.visible);
				status(data.visible ? '' : messages.empty);
			});
		}

		function scheduleMarkers() {
			clearTimeout(state.timer);
			state.timer = setTimeout(loadMarkers, 180);
		}

		function fit() {
			if (!summary || !summary.bounds) return;
			var b = summary.bounds;
			map.fitBounds([[b.south, b.west], [b.north, b.east]], {padding: [30, 30], maxZoom: 14, animate: false});
			scheduleMarkers();
		}

		function loadSummary() {
			clearTimeout(state.filterTimer);
			clearTimeout(state.timer);
			state.revision++;
			$.each(state.requests, function (_, xhr) { xhr.abort(); });
			summary = null;
			layers.clearLayers();
			activeFilters = filters();
			navigation();
			$root.find('[data-map-count]').text('–');
			status(messages.loading);
			request('summary', {mode: 'summary'}, function (data) {
				summary = data;
				['total', 'missing', 'invalid'].forEach(function (key) { $root.find('[data-map-count="' + key + '"]').text(data[key]); });
				$root.find('[data-map-count="visible"]').text(0);
				if (!data.total || !data.positioned) status(data.total ? messages.no_positions : messages.empty);
				else fit();
			});
		}

		map.on('moveend', scheduleMarkers);
		$root.find('form').on('submit', function (event) { event.preventDefault(); loadSummary(); });
		$root.on('input change', '.dw-contact-map__filters :input', function () {
			navigation();
			clearTimeout(state.filterTimer);
			state.filterTimer = setTimeout(loadSummary, 350);
		});
		$root.on('click', '[data-contact-map-fit]', fit).on('click', '[data-contact-map-retry]', loadSummary);
		loadSummary();
	}

	function init(scope) {
		states = states.filter(function (state) {
			if ($.contains(document, state.element)) return true;
			state.revision++;
			clearTimeout(state.timer);
			clearTimeout(state.filterTimer);
			$.each(state.requests, function (_, xhr) { xhr.abort(); });
			state.map.remove();
			$(state.element).removeData('dw-map-state');
			return false;
		});
		$(scope).find('[data-contact-map]').add($(scope).filter('[data-contact-map]')).each(function () { initialize(this); });
	}

	window.DewawiContactsMap = {init: init};
	$(function () {
		init(document);
		new MutationObserver(function () { init(document); }).observe(document.body, {childList: true, subtree: true});
	});
	$(document).on('click', '[data-contact-map-open]', function () {
		var values = typeof collectToolbarData === 'function' ? collectToolbarData() : {};
		var selected = {};
		filterNames.forEach(function (name) { if (values[name] !== undefined) selected[name] = values[name]; });
		if (Object.keys(selected).length) this.href = this.href.split('?')[0] + '?' + $.param(selected);
	});
})(jQuery);
