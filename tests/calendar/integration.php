<?php

require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function rejects(callable $run, string $message, ?int $code = null): void {
	try { $run(); } catch (InvalidArgumentException | RuntimeException $e) { check($code === null || $e->getCode() === $code, $message . ': ' . $e->getMessage()); return; }
	throw new RuntimeException($message);
}
// Run the original calendar creation and the official K1 migration on legacy records.
$pdo = $db->getConnection();
$pdo->exec('CREATE DATABASE calendar_fresh');
preg_match('/CREATE TABLE IF NOT EXISTS `calendarevent` .*?;/s', file_get_contents($root . '/install/config/structure.sql'), $fresh);
$pdo->exec(str_replace('`calendarevent`', 'calendar_fresh.`calendarevent`', $fresh[0]));
preg_match('/CREATE TABLE IF NOT EXISTS `calendarevent` .*?;/s', file_get_contents($root . '/install/config/update-1.1.2-1.1.3.sql'), $old);
$pdo->exec($old[0]);
$pdo->exec("INSERT INTO calendarevent (id,title,description,start,end,clientid,createdby) VALUES (1,'Legacy private','Legacy secret','2026-03-28 09:00:00','2026-03-28 10:00:00',1,1)");
$sql = explode('-- K1 team calendar:', file_get_contents($root . '/install/config/update-1.1.7-1.1.8.sql'), 2)[1];
$sql = substr($sql, strpos($sql, 'ALTER TABLE'));
foreach (explode(';', $sql) as $query) if (trim($query)) $pdo->exec($query);
$columns = $db->fetchAll('SHOW FULL COLUMNS FROM calendarevent');
check($columns === $db->fetchAll('SHOW FULL COLUMNS FROM calendar_fresh.calendarevent'), 'Upgrade/fresh columns differ');
$index = static function ($rows) { foreach ($rows as &$row) { unset($row['Cardinality']); } unset($row); return $rows; };
check($index($db->fetchAll('SHOW INDEX FROM calendarevent')) === $index($db->fetchAll('SHOW INDEX FROM calendar_fresh.calendarevent')), 'Upgrade/fresh indexes differ');
$legacy = $db->fetchRow('SELECT * FROM calendarevent WHERE id=1');
check($legacy['start'] === '2026-03-28 09:00:00' && $legacy['startutc'] === null && $legacy['visibility'] === 'private' && (int)$legacy['ownerid'] === 1, 'Legacy data changed or exposed');
// A site with K1 already installed needs only the new official K2 block.
$pdo->exec('CREATE DATABASE calendar_k1');
$pdo->exec(str_replace('`calendarevent`', 'calendar_k1.`calendarevent`', $old[0]));
[$k1, $k2] = explode('-- K2 calendar reminders:', $sql, 2);
foreach (explode(';', $k1) as $query) if (trim($query)) $pdo->exec(str_replace('`calendarevent`', 'calendar_k1.`calendarevent`', $query));
$pdo->exec("INSERT INTO calendar_k1.calendarevent (title,start,end,startutc,endutc,timezone,ownerid,clientid,visibility) VALUES ('K1 record','2026-11-16 10:00:17','2026-11-16 11:00:29','2026-11-16 09:00:17','2026-11-16 10:00:29','Europe/Berlin',1,1,'team')");
$k2 = substr($k2, strpos($k2, 'ALTER TABLE'));
foreach (explode(';', $k2) as $query) if (trim($query)) $pdo->exec(str_replace('`calendarevent`', 'calendar_k1.`calendarevent`', $query));
check($columns === $db->fetchAll('SHOW FULL COLUMNS FROM calendar_k1.calendarevent'), 'K2-only upgrade differs from fresh schema');
$k1row = $db->fetchRow('SELECT * FROM calendar_k1.calendarevent');
check($k1row['startutc'] === '2026-11-16 09:00:17' && $k1row['reminderminutes'] === null && $k1row['reminderdue'] === null && $k1row['reminderdone'] === null && (int)$k1row['reminderrevision'] === 0, 'K2 silently changed existing appointments or enabled reminders');
// Real user and permission tables, using only the official fresh schema.
foreach (['user', 'permission'] as $table) {
	preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', file_get_contents($root . '/install/config/structure.sql'), $match);
	$pdo->exec($match[0]);
}
foreach ([1 => 1, 2 => 1, 3 => 2, 4 => 3] as $id => $client) {
	$db->insert('user', ['id' => $id, 'name' => 'Member ' . $id, 'clientid' => $client, 'password' => 'fixture', 'activated' => 1]);
	$db->insert('permission', ['userid' => $id, 'clientid' => $client, 'calendar' => json_encode(['index' => ['view', 'add', 'edit', 'delete']])]);
}
$input = ['title' => 'Team meeting <script>', 'description' => 'Normal description', 'location' => 'Office', 'start' => '2026-03-28T10:00', 'end' => '2026-03-28T11:00', 'timezone' => 'Europe/Berlin', 'visibility' => 'team'];
$model = new Calendar_Model_DbTable_Event();
$id = $model->saveEvent($input);
check($model->getEvent($id)['title'] === $input['title'], 'Create/read failed');
check($db->fetchOne('SELECT startutc FROM calendarevent WHERE id=?', [$id]) === '2026-03-28 09:00:00', 'UTC storage differs');
$model->saveEvent(array_replace($input, ['title' => 'Updated title']), $id);
check($model->getEvent($id)['title'] === 'Updated title', 'Edit failed');
$private = $model->saveEvent(array_replace($input, ['visibility' => 'private', 'title' => 'PRIVATE_TITLE', 'description' => 'PRIVATE_DESCRIPTION', 'location' => 'PRIVATE_LOCATION']));
check($model->getEvent($private)['description'] === 'PRIVATE_DESCRIPTION', 'Owner lost private details');
$seconds = $model->saveEvent(array_replace($input, ['start' => '2026-03-28T10:00:17', 'end' => '2026-03-28T11:00:29']));
check($model->getEvent($seconds)['startlocal'] === '2026-03-28T10:00:17', 'Second precision lost on edit');
$db->update('calendarevent', ['start' => '2026-03-28 09:00:17', 'end' => '2026-03-28 10:00:29'], 'id=1');
check($model->getEvent(1)['startlocal'] === '2026-03-28T09:00:17', 'Legacy seconds silently changed');
calendarActor(2);
$other = new Calendar_Model_DbTable_Event();
$otherId = $other->saveEvent($input);
check($other->getEvent($id)['title'] === 'Updated title' && !$other->getEvent($id)['own'], 'Team read failed');
$hidden = $other->getEvent($private);
foreach (['title', 'description', 'location', 'timezone', 'startlocal', 'endlocal', 'visibility'] as $field) check(!array_key_exists($field, $hidden), 'Private field exposed: ' . $field);
check($hidden['private'] && isset($hidden['start'], $hidden['end']), 'Busy interval missing');
rejects(fn() => $other->saveEvent($input, $id), 'Other owner edit', 403);
rejects(fn() => $other->deleteEvent($private), 'Other owner delete', 403);
calendarActor(3, 2);
$foreign = new Calendar_Model_DbTable_Event();
rejects(fn() => $foreign->getEvent($id), 'Cross-tenant read', 404);
rejects(fn() => $foreign->saveEvent($input, $id), 'Cross-tenant edit', 404);
rejects(fn() => $foreign->deleteEvent($id), 'Cross-tenant delete', 404);
$zone = new DateTimeZone('Europe/Berlin');
$period = Calendar_Model_Event::period('2026-03-28', 'month', $zone);
check($foreign->events($period) === [], 'Parent-child calendar sharing leaked data');
rejects(fn() => $foreign->events($period, 2), 'Foreign employee filter');
calendarActor(1);
$model = new Calendar_Model_DbTable_Event();
$before = $db->fetchAll('SELECT * FROM calendarevent ORDER BY id');
foreach ([['title' => ''], ['title' => str_repeat('x', 256)], ['location' => str_repeat('x', 256)], ['description' => str_repeat('x', 60001)], ['start' => '2026-02-30T12:00'], ['end' => $input['start']], ['end' => '2026-01-01T00:00'], ['start' => []], ['allday' => '2'], ['timezone' => '+02:00'], ['timezone' => 'bad'], ['visibility' => 'public'], ['ownerid' => '2'], ['clientid' => '2'], ['deleted' => '1'], ['start' => '0000-01-01T00:00'], ['title' => "invalid\0"]] as $invalid) rejects(fn() => $model->saveEvent(array_replace($input, $invalid)), 'Invalid event accepted: ' . json_encode($invalid));
check($before === $db->fetchAll('SELECT * FROM calendarevent ORDER BY id'), 'Invalid writes changed records');
rejects(fn() => Calendar_Model_Event::localTime('2026-03-29T02:30', $zone), 'DST gap accepted');
rejects(fn() => Calendar_Model_Event::localTime('2026-10-25T02:30', $zone), 'Ambiguous time guessed');
$early = Calendar_Model_Event::localTime('2026-10-25T02:30', $zone, 'earlier');
$late = Calendar_Model_Event::localTime('2026-10-25T02:30', $zone, 'later');
check($late->getTimestamp() - $early->getTimestamp() === 3600, 'DST folds wrong');
$foldInput = array_replace($input, ['start' => '2026-10-25T02:30', 'end' => '2026-10-25T02:30', 'startfold' => 'earlier', 'endfold' => 'later']);
$foldId = $model->saveEvent($foldInput);
$fold = $model->getEvent($foldId);
check($fold['startfold'] === 'earlier' && $fold['endfold'] === 'later' && str_ends_with($fold['start'], '+02:00') && str_ends_with($fold['end'], '+01:00'), 'Fold round trip lost offsets');
foreach (['2026-03-29' => 23, '2026-10-25' => 25] as $date => $hours) {
	$day = Calendar_Model_Event::period($date, 'day', $zone);
	check(($day['end']->getTimestamp() - $day['start']->getTimestamp()) / 3600 === $hours, 'Day length wrong');
	$all = $model->saveEvent(array_replace($input, ['start' => $date, 'end' => (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'), 'allday' => '1']));
	check(in_array($all, array_column($model->events($day), 'id')), 'All-day event missing');
	$next = Calendar_Model_Event::period($day['until'], 'day', $zone);
	check(!in_array($all, array_column($model->events($next), 'id')), 'Exclusive all-day end leaks to next day');
}
$tokyo = $model->saveEvent(array_replace($input, ['timezone' => 'Asia/Tokyo', 'start' => '2026-03-28T18:00', 'end' => '2026-03-28T19:00']));
check(substr($model->getEvent($tokyo)['start'], 11, 5) === '10:00', 'Cross-zone display failed');
$midnightZone = new DateTimeZone('America/Sao_Paulo');
$midnightDay = Calendar_Model_Event::period('2018-11-04', 'day', $midnightZone);
check($midnightDay['end']->getTimestamp() - $midnightDay['start']->getTimestamp() === 23 * 3600, 'Midnight DST boundary rejected or shifted');
$midnightId = $model->saveEvent(array_replace($input, ['start' => '2018-11-04', 'end' => '2018-11-05', 'allday' => '1', 'timezone' => 'America/Sao_Paulo']));
check($model->getEvent($midnightId)['startlocal'] === '2018-11-04', 'All-day midnight DST gap changed date');
$boundary = Calendar_Model_Event::period('2026-03-28', 'day', $zone);
$edge = $model->saveEvent(array_replace($input, ['start' => '2026-03-27T23:00', 'end' => '2026-03-28T00:00']));
check(!in_array($edge, array_column($model->events($boundary), 'id')), 'Exclusive midnight end included');
$allTokyo = $model->saveEvent(array_replace($input, ['start' => '2026-03-28', 'end' => '2026-03-29', 'allday' => '1', 'timezone' => 'Pacific/Kiritimati']));
check(substr($model->getEvent($allTokyo)['start'], 0, 10) === '2026-03-28' && in_array($allTokyo, array_column($model->events($boundary), 'id')), 'All-day dates shifted across zones');
check(Calendar_Model_Event::period('2026-01-31', 'month', $zone)['next'] === '2026-02-01', 'Month end navigation skipped month');
check(Calendar_Model_Event::period('2024-02-29', 'week', $zone)['first'] === '2024-02-26', 'Leap week boundary wrong');
rejects(fn() => Calendar_Model_Event::period('2026-02-29', 'day', $zone), 'Non-leap day accepted');
rejects(fn() => Calendar_Model_Event::period('2026-01-01', 'year', $zone), 'Unbounded period accepted');
rejects(fn() => $model->saveEvent(array_replace($input, ['start' => '1000-01-01T00:00', 'end' => '1000-01-02T00:00', 'timezone' => 'Asia/Tokyo'])), 'UTC below SQL date range accepted');
$model->deleteEvent($id);
rejects(fn() => $model->getEvent($id), 'Deleted event readable', 404);
check((int)$db->fetchOne('SELECT deleted FROM calendarevent WHERE id=?', [$id]) === 1, 'Soft deletion failed');
foreach ([[], ['view'], ['add', 'edit', 'delete']] as $rights) {
	$db->update('permission', ['calendar' => json_encode(['index' => $rights])], 'userid=2');
	calendarActor(2);
	$limited = new Calendar_Model_DbTable_Event();
	if (!in_array('view', $rights)) rejects(fn() => $limited->events($period), 'Missing view rights accepted', 403);
	rejects(fn() => $limited->saveEvent($input), 'Missing create or view rights accepted', 403);
	rejects(fn() => $limited->saveEvent($input, $otherId), 'View-only own edit allowed', 403);
	rejects(fn() => $limited->deleteEvent($otherId), 'View-only own delete allowed', 403);
	rejects(fn() => $limited->deleteEvent($private), 'Missing delete/view rights accepted', 403);
}
$db->update('calendarevent', ['deleted' => 1], $db->quoteInto('id = ?', $otherId));
$db->update('permission', ['calendar' => json_encode(['index' => ['view', 'add', 'edit', 'delete']])], 'userid=2');
// A parent user who legitimately switched to a child owns only their events there.
calendarActor(1, 2);
$switched = new Calendar_Model_DbTable_Event();
$childId = $switched->saveEvent($input);
check((int)$db->fetchOne('SELECT clientid FROM calendarevent WHERE id=?', [$childId]) === 2, 'Selected tenant lost on create');
check(array_key_exists(1, $switched->members()), 'Switching owner missing from filter');
calendarActor(3, 2);
check((new Calendar_Model_DbTable_Event())->getEvent($childId)['title'] === $input['title'], 'Legitimate selected-tenant event unreadable');
calendarActor(1);
rejects(fn() => (new Calendar_Model_DbTable_Event())->getEvent($childId), 'Selected tenant scope not enforced', 404);
Zend_Registry::set('Client', ['id' => 2]);
rejects(fn() => new Calendar_Model_DbTable_Event(), 'Mismatched tenant context accepted', 403);
calendarActor();
Zend_Auth::getInstance()->clearIdentity();
rejects(fn() => new Calendar_Model_DbTable_Event(), 'Guest model access', 403);
calendarActor();
// The existing header adds the calendar only when its view permission is present.
require_once $root . '/application/views/helpers/MainMenu.php';
$menuView = new Zend_View();
$menuView->addScriptPath($root . '/application/views/scripts');
$menuView->assign(['module' => 'default', 'controller' => 'index']);
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(['default' => $root . '/application/controllers', 'calendar' => $root . '/application/modules/calendar/controllers']);
$front->setRequest(new Zend_Controller_Request_Http());
$front->getRouter()->addDefaultRoutes();
$menu = (new Zend_View_Helper_MainMenu())->setView($menuView);
ob_start(); $menu->MainMenu(); $html = ob_get_clean();
check(str_contains($html, '/calendar') && str_contains($html, '/tasks/task') && str_contains($html, '/users') === false, 'Calendar navigation changed unrelated links');
$db->update('permission', ['calendar' => null], 'userid=2');
calendarActor(2);
ob_start(); $menu->MainMenu(); $html = ob_get_clean();
check(!str_contains($html, '/calendar') && str_contains($html, '/tasks/task'), 'Calendar menu ignores rights');
$db->update('permission', ['calendar' => json_encode(['index' => ['view', 'add', 'edit', 'delete']])], 'userid=2');
calendarActor();
file_put_contents($work . '/events.json', json_encode(['private' => $private, 'fold' => $foldId]));
echo "PASS: $checks calendar database, validation, timezone, privacy and migration checks\n";
