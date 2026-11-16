<?php

require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function rejects(callable $run, int $code = 0): void {
	try { $run(); } catch (InvalidArgumentException | RuntimeException $e) { check($e->getCode() === $code, 'Unexpected rejection: ' . $e->getMessage()); return; }
	throw new RuntimeException('Reminder operation unexpectedly accepted');
}
function dueItem(Calendar_Model_DbTable_Event $model, int $id): ?array {
	foreach ($model->dueReminders()['reminders'] as $item) if ($item['id'] === $id) return $item;
	return null;
}
$now = time();
$input = ['title' => 'Reminder owner only', 'description' => 'Reminder description', 'location' => 'Office', 'start' => gmdate('Y-m-d\TH:i:s', $now - 60), 'end' => gmdate('Y-m-d\TH:i:s', $now + 3600), 'timezone' => 'UTC', 'visibility' => 'private'];
$model = new Calendar_Model_DbTable_Event();
$id = $model->saveEvent($input);
check(dueItem($model, $id) === null && $model->getEvent($id)['reminderminutes'] === 'none', 'Reminder default changed');
$model->deleteEvent($id);
foreach ([0, 5, 15, 30, 60, 1440] as $minutes) {
	$event = array_replace($input, ['reminderminutes' => (string)$minutes, 'start' => gmdate('Y-m-d\TH:i:s', $now + $minutes * 60), 'end' => gmdate('Y-m-d\TH:i:s', $now + $minutes * 60 + 3600)]);
	$id = $model->saveEvent($event);
	check($db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$id]) === gmdate('Y-m-d H:i:s', $now), 'Wrong UTC due time for ' . $minutes);
	check(dueItem($model, $id) !== null, 'Exact due boundary omitted');
	$model->deleteEvent($id);
	$id = $model->saveEvent(array_replace($event, ['start' => gmdate('Y-m-d\TH:i:s', $now + $minutes * 60 + 60)]));
	check(dueItem($model, $id) === null, 'Reminder delivered before due time');
	rejects(fn() => $model->updateReminder($id, 1, null), 409);
	$model->deleteEvent($id);
}
foreach (['-1', '1', '1441', '5.0', '', 'null', '05'] as $invalid) rejects(fn() => $model->saveEvent($input + ['reminderminutes' => $invalid]));
rejects(fn() => $model->saveEvent(array_replace($input, ['start' => '1000-01-01T00:00', 'end' => '1000-01-02T00:00', 'reminderminutes' => '1440'])));
$id = $model->saveEvent($input + ['reminderminutes' => '0']);
$item = dueItem($model, $id);
check($item && $item['title'] === $input['title'] && !isset($item['description'], $item['location']), 'Own reminder projection incorrect');
$revision = $item['revision'];
$before = $db->fetchRow('SELECT * FROM calendarevent WHERE id=?', [$id]);
for ($i = 0; $i < 10; $i++) check(dueItem($model, $id)['revision'] === $revision, 'Polling changed reminder generation');
check($before === $db->fetchRow('SELECT * FROM calendarevent WHERE id=?', [$id]), 'Polling writes to appointments');
$model->updateReminder($id, $revision, null);
check(dueItem($model, $id) === null && $db->fetchOne('SELECT reminderdone FROM calendarevent WHERE id=?', [$id]) !== null, 'Acknowledgement not persistent');
rejects(fn() => $model->updateReminder($id, $revision, null), 409);
$model->saveEvent(array_replace($input, ['title' => 'Text changed']), $id);
check(dueItem($model, $id) === null && $model->getEvent($id)['reminderminutes'] === '0', 'Text edit rearmed or removed reminder');
$future = array_replace($input, ['start' => gmdate('Y-m-d\TH:i:s', $now + 1800)]);
$model->saveEvent($future, $id);
check(dueItem($model, $id) === null && $db->fetchOne('SELECT reminderdone FROM calendarevent WHERE id=?', [$id]) === null, 'Schedule edit did not rearm');
$model->saveEvent($input, $id);
$revision = dueItem($model, $id)['revision'];
foreach ([5, 15, 60] as $minutes) {
	$beforeTime = time();
	$model->updateReminder($id, $revision, $minutes);
	$due = strtotime($db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$id]) . ' UTC');
	check($due >= $beforeTime + $minutes * 60 && $due <= time() + $minutes * 60 && dueItem($model, $id) === null, 'Snooze interval wrong');
	rejects(fn() => $model->updateReminder($id, $revision, $minutes), 409);
	$db->update('calendarevent', ['reminderdue' => gmdate('Y-m-d H:i:s', time() - 1)], $db->quoteInto('id = ?', $id));
	$revision = dueItem($model, $id)['revision'];
	check($revision > 0, 'Snoozed reminder never due again');
}
$snoozedDue = $db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$id]);
$model->saveEvent(array_replace($input, ['description' => 'Only a text edit']), $id);
check($db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$id]) === $snoozedDue, 'Text edit reset snooze');
rejects(fn() => $model->updateReminder($id, $revision, null), 409);
foreach ([0, 1, -5, 1440] as $invalid) rejects(fn() => $model->updateReminder($id, $revision, $invalid));
$model->saveEvent($input + ['reminderminutes' => 'none'], $id);
check(dueItem($model, $id) === null && $db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$id]) === null, 'Disabled reminder remained due');
$model->saveEvent($input + ['reminderminutes' => '5'], $id);
$revision = dueItem($model, $id)['revision'];
calendarActor(2);
$other = new Calendar_Model_DbTable_Event();
check(dueItem($other, $id) === null && !isset($other->getEvent($id)['reminderminutes']), 'Foreign private reminder leaked');
rejects(fn() => $other->updateReminder($id, $revision, null), 409);
calendarActor(3, 2);
$foreign = new Calendar_Model_DbTable_Event();
check(dueItem($foreign, $id) === null, 'Tenant reminder leaked');
rejects(fn() => $foreign->updateReminder($id, $revision, 5), 409);
calendarActor(1, 2);
$child = new Calendar_Model_DbTable_Event();
$childId = $child->saveEvent($input + ['reminderminutes' => '0']);
check(dueItem($child, $childId) !== null, 'Selected tenant reminder lost');
$child->deleteEvent($childId);
calendarActor();
$model = new Calendar_Model_DbTable_Event();
check(dueItem($model, $childId) === null, 'Same actor reminder crossed tenants');
$db->update('permission', ['calendar' => json_encode(['index' => ['view']])], 'userid=1');
$route = (new Zend_Controller_Request_Http())->setModuleName('calendar')->setControllerName('index')->setActionName('reminders');
$response = new Zend_Controller_Response_Http();
$acl = new Application_Plugin_Acl(); $acl->setResponse($response); $acl->routeShutdown($route);
check($response->getHttpResponseCode() === 200 && $route->getModuleName() === 'calendar', 'Reminder route does not reuse calendar view permission');
$readOnly = new Calendar_Model_DbTable_Event();
$readOnly->updateReminder($id, $revision, null);
check(dueItem($readOnly, $id) === null, 'View-only owner cannot manage personal reminder state');
$db->update('permission', ['calendar' => null], 'userid=1');
$route = (new Zend_Controller_Request_Http())->setModuleName('calendar')->setControllerName('index')->setActionName('reminders');
$response = new Zend_Controller_Response_Http(); $acl->setResponse($response); $acl->routeShutdown($route);
check($response->getHttpResponseCode() === 403 && $route->getControllerName() === 'error', 'Reminder route bypassed ACL');
$denied = new Calendar_Model_DbTable_Event();
rejects(fn() => $denied->dueReminders(), 403);
rejects(fn() => $denied->updateReminder($id, $revision, null), 403);
$db->update('permission', ['calendar' => json_encode(['index' => ['view', 'add', 'edit', 'delete']])], 'userid=1');
$model = new Calendar_Model_DbTable_Event();
$model->saveEvent($input + ['reminderminutes' => '0'], $id);
$revision = dueItem($model, $id)['revision'];
// Changing the reminder offset rearms it, and deletion excludes even overdue reminders.
$model->deleteEvent($id);
check(dueItem($model, $id) === null, 'Deleted event still notifies');
rejects(fn() => $model->updateReminder($id, $revision, null), 409);
foreach (['earlier', 'later'] as $fold) {
	$dst = $model->saveEvent(array_replace($input, ['start' => '2026-10-25T02:30', 'end' => '2026-10-25T04:30', 'startfold' => $fold, 'timezone' => 'Europe/Berlin', 'reminderminutes' => '1440']));
	$row = $db->fetchRow('SELECT * FROM calendarevent WHERE id=?', [$dst]);
	check(strtotime($row['startutc'] . ' UTC') - strtotime($row['reminderdue'] . ' UTC') === 86400, 'DST reminder did not subtract 24 UTC hours');
	$model->deleteEvent($dst);
}
$spring = $model->saveEvent(array_replace($input, ['start' => '2026-03-29', 'end' => '2026-03-30', 'allday' => '1', 'timezone' => 'Europe/Berlin', 'reminderminutes' => '60']));
check($db->fetchOne('SELECT reminderdue FROM calendarevent WHERE id=?', [$spring]) === '2026-03-28 22:00:00', 'All-day reminder wrong at DST boundary');
$model->deleteEvent($spring);

function parallel(array $jobs, int $batch): array {
	global $work;
	$children = [];
	foreach ($jobs as $i => $job) {
		$process = proc_open(array_merge([PHP_BINARY, __DIR__ . '/reminder-worker.php', (string)$batch, (string)$i], array_map('strval', $job)), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$children[] = [$process, $pipes];
	}
	$deadline = microtime(true) + 10;
	while (count(glob($work . '/ready-' . $batch . '-*')) !== count($jobs)) { if (microtime(true) > $deadline) throw new RuntimeException('Worker readiness timeout'); usleep(10000); }
	file_put_contents($work . '/go-' . $batch, '1');
	$results = [];
	foreach ($children as [$process, $pipes]) {
		$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
		fclose($pipes[1]); fclose($pipes[2]);
		check(proc_close($process) === 0, 'Worker failed: ' . $error);
		$results[] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
	}
	return $results;
}
$parallelId = $model->saveEvent($input + ['reminderminutes' => '0']);
$revision = dueItem($model, $parallelId)['revision'];
$results = parallel(array_fill(0, 4, [$parallelId, $revision, 'snooze']), 1);
check(count(array_filter($results, fn($r) => $r['code'] === 200)) === 1 && count(array_filter($results, fn($r) => $r['code'] === 409)) === 3, 'Parallel snoozes applied more than once');
check((int)$db->fetchOne('SELECT reminderrevision FROM calendarevent WHERE id=?', [$parallelId]) === $revision + 1, 'Parallel request duplicated generation');
$db->update('calendarevent', ['reminderdue' => gmdate('Y-m-d H:i:s', time() - 1)], $db->quoteInto('id = ?', $parallelId));
$revision = dueItem($model, $parallelId)['revision'];
$results = parallel(array_fill(0, 4, [$parallelId, $revision, 'done']), 2);
check(count(array_filter($results, fn($r) => $r['code'] === 200)) === 1 && dueItem($model, $parallelId) === null, 'Parallel confirmations duplicated or failed');
$model->saveEvent($input + ['reminderminutes' => '5'], $parallelId);
$revision = dueItem($model, $parallelId)['revision'];
file_put_contents($work . '/reminder-edit.json', json_encode($future + ['reminderminutes' => '5']));
$results = parallel([[$parallelId, $revision, 'edit'], [$parallelId, $revision, 'done']], 3);
check($results[0]['code'] === 200, 'Concurrent edit failed');
$row = $db->fetchRow('SELECT * FROM calendarevent WHERE id=?', [$parallelId]);
check($row['reminderdone'] === null && strtotime($row['reminderdue'] . ' UTC') > time(), 'Stale confirmation swallowed rescheduled event');
$model->deleteEvent($parallelId);
// A bounded list still reports the complete count, and repeated polls never create records.
$ids = [];
for ($i = 0; $i < 25; $i++) $ids[] = $model->saveEvent($input + ['reminderminutes' => '0']);
$due = $model->dueReminders();
check($due['count'] === 25 && count($due['reminders']) === 20 && count(array_unique(array_column($due['reminders'], 'id'))) === 20, 'Bounded reminder list/count wrong');
foreach ($ids as $id) $model->deleteEvent($id);
// Rendering a shared-parent module must retain the selected tenant in the header context.
require_once $root . '/application/views/helpers/MainMenu.php';
calendarActor(1, 2);
Zend_Registry::set('Client', ['id' => 1, 'modules' => ['items' => 1]]);
$view = new Zend_View(['scriptPath' => $root . '/application/views/scripts']);
$view->assign(['module' => 'items', 'controller' => 'item']);
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(['default' => $root . '/application/controllers', 'calendar' => $root . '/application/modules/calendar/controllers']);
$front->setRequest(new Zend_Controller_Request_Http());
$front->getRouter()->addDefaultRoutes();
ob_start(); (new Zend_View_Helper_MainMenu())->setView($view)->MainMenu(); $header = ob_get_clean();
check(str_contains($header, 'data-context="1:2"') && str_contains($header, 'context=1%3A2'), 'Header inherited parent data-sharing tenant');
calendarActor();
file_put_contents($work . '/reminder-fixture.json', json_encode(['input' => $input]));
echo "PASS: $checks persistent reminder, timing, privacy, permission and parallel-write checks\n";
