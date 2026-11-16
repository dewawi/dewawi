jQuery(function ($) {
	var $widget = $('[data-calendar-reminders]');
	if (!$widget.length) return;
	var $toggle = $widget.find('.dw-reminders-toggle'), $panel = $widget.find('.dw-reminders-panel');
	var $list = $widget.find('[data-reminder-list]'), $status = $widget.find('[data-reminder-status]'), $count = $widget.find('[data-reminder-count]');
	var texts = $widget.data('texts'), timer, busy = false, stopped = false, csrf = '', lastActivity = Date.now(), lastRequest = 0, retryDelay = 60000;

	function active() {
		return !stopped && !document.hidden && navigator.onLine !== false && Date.now() - lastActivity < 300000 && !(window.Dewawi && Dewawi.sessionExpired);
	}
	function schedule(delay) {
		clearTimeout(timer);
		timer = null;
		if (active()) timer = setTimeout(function () { timer = null; request(); }, delay);
	}
	function clear() {
		$list.empty(); $count.text('0').prop('hidden', true);
		$toggle.removeClass('has-reminders').attr('aria-label', texts.title);
	}
	function stop() {
		stopped = true; clearTimeout(timer); clear();
		$status.text(texts.stopped);
	}
	function render(data) {
		if (data.context !== $widget.attr('data-context')) { stop(); return; }
		csrf = data.csrf_token;
		$list.empty();
		$count.text(data.count).prop('hidden', !data.count);
		$toggle.toggleClass('has-reminders', data.count > 0).attr('aria-label', texts.title + ': ' + data.count);
		$status.text(!data.count ? texts.empty : (data.count > data.reminders.length ? texts.more : ''));
		$.each(data.reminders, function (_, item) {
			var $row = $('<li>').attr({'data-reminder-id': item.id, 'data-reminder-revision': item.revision});
			$('<a>').attr('href', $widget.attr('data-event-url') + '/id/' + item.id).text(item.title).appendTo($row);
			var time = item.allday ? texts.allday + ' · ' + item.start.slice(0, 10) : new Date(item.start).toLocaleString(document.documentElement.lang || 'de-DE', {timeZone: data.timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZoneName: 'short'});
			$('<time>').attr('datetime', item.start).text(time).appendTo($row);
			var $actions = $('<div class="dw-reminder-actions">').appendTo($row);
			$('<button type="button" class="dw-btn" data-reminder-action="done">').text(texts.done).appendTo($actions);
			var $select = $('<select class="dw-select">').attr('aria-label', texts.snooze).appendTo($actions);
			$.each([5, 15, 60], function (_, minutes) { $('<option>').val(minutes).text(texts[minutes]).appendTo($select); });
			$('<button type="button" class="dw-btn" data-reminder-action="snooze">').text(texts.snooze).appendTo($actions);
			$list.append($row);
		});
	}
	function request(post) {
		if (busy || !active()) return;
		busy = true; lastRequest = Date.now();
		clearTimeout(timer); timer = null;
		$widget.attr('aria-busy', 'true').find('[data-reminder-action]').prop('disabled', true);
		var conflict = false;
		$.ajax({url: $widget.attr('data-endpoint'), type: post ? 'POST' : 'GET', data: post, dataType: 'json', cache: false, timeout: 15000, dewawiBackground: true})
			.done(function (data) {
				if (!data || data.ok !== true || !Array.isArray(data.reminders)) { clear(); $status.text(texts.failed); return; }
				retryDelay = 60000; render(data);
			})
			.fail(function (xhr) {
				if (xhr.status === 401 || xhr.status === 403) { stop(); return; }
				clear();
				conflict = xhr.status === 409 && !!post;
				$status.text(conflict ? texts.changed : texts.failed);
				if (!conflict) retryDelay = Math.min(retryDelay * 2, 300000);
			})
			.always(function () {
				busy = false; $widget.removeAttr('aria-busy');
				if (conflict) request(); else schedule(retryDelay);
			});
	}
	$toggle.on('click', function () {
		lastActivity = Date.now();
		var open = $panel.prop('hidden');
		$panel.prop('hidden', !open); $toggle.attr('aria-expanded', String(open));
		if (open) request();
	});
	$widget.on('click', '[data-reminder-action]', function () {
		var $row = $(this).closest('[data-reminder-id]'), operation = $(this).attr('data-reminder-action');
		var post = {id: $row.attr('data-reminder-id'), revision: $row.attr('data-reminder-revision'), operation: operation, csrf_token: csrf};
		if (operation === 'snooze') post.minutes = $row.find('select').val();
		lastActivity = Date.now(); request(post);
	});
	$(document).on('keydown', function (event) {
		if (event.key === 'Escape' && !$panel.prop('hidden')) { $panel.prop('hidden', true); $toggle.attr('aria-expanded', 'false').focus(); }
	}).on('pointerdown', function (event) {
		if (!$widget[0].contains(event.target)) { $panel.prop('hidden', true); $toggle.attr('aria-expanded', 'false'); }
	});
	function wake() {
		lastActivity = Date.now();
		if (!busy && !timer) schedule(Math.max(0, retryDelay - (Date.now() - lastRequest)));
	}
	$(document).on('pointerdown keydown scroll touchstart', wake).on('visibilitychange', function () {
		if (document.hidden) { clearTimeout(timer); timer = null; } else wake();
	});
	$(window).on('focus online pageshow', wake).on('offline pagehide', function () { clearTimeout(timer); timer = null; });
	schedule(0);
});
