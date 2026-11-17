<?php

require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function rejects(callable $run, string $key): void {
	try { $run(); } catch (InvalidArgumentException | RuntimeException $e) { check($e->getMessage() === $key, 'Wrong error: ' . $e->getMessage()); return; }
	throw new RuntimeException('Invalid action accepted: ' . $key);
}
$base = ['start' => '2026-11-17T16:00', 'end' => '2026-11-17T17:00', 'newstart' => '2026-11-18T16:00', 'timezone' => 'Europe/Berlin'];
foreach ([
	[[], '2026-11-18T17:00'],
	[['newstart' => '2026-11-17T20:00'], '2026-11-17T21:00'],
	[['end' => '2026-11-19T17:00'], '2026-11-20T17:00'],
	[['start' => '2026-03-28T16:00', 'end' => '2026-03-30T16:00', 'newstart' => '2026-11-18T16:00'], '2026-11-20T15:00'],
	[['start' => '2026-03-28T16:00', 'end' => '2026-03-28T17:00', 'newstart' => '2026-03-29T01:30'], '2026-03-29T03:30'],
	[['newstart' => '2026-10-25T02:30', 'newstartfold' => 'earlier'], '2026-10-25T02:30'],
	[['newstart' => '2026-10-25T02:30', 'newstartfold' => 'later'], '2026-10-25T03:30'],
	[['timezone' => 'Asia/Tokyo'], '2026-11-18T17:00'],
	[['start' => '2026-11-17T16:00:17', 'end' => '2026-11-17T17:00:29'], '2026-11-18T17:00:12'],
	[['start' => '2026-03-28', 'end' => '2026-03-30', 'newstart' => '2026-10-24', 'allday' => '1'], '2026-10-26'],
] as [$changes, $expected]) {
	$result = Calendar_Model_Event::shiftEnd(array_replace($base, $changes));
	check($result['end'] === $expected, 'Duration shifted incorrectly: ' . json_encode($result));
	if (($changes['newstartfold'] ?? '') === 'earlier') check($result['endfold'] === 'later', 'Repeated end hour lost UTC duration');
}
rejects(fn() => Calendar_Model_Event::shiftEnd(array_replace($base, ['newstart' => '2026-03-29T02:30'])), 'CALENDAR_DST_GAP');
rejects(fn() => Calendar_Model_Event::shiftEnd(array_replace($base, ['newstart' => '2026-10-25T02:30'])), 'CALENDAR_DST_FOLD');
rejects(fn() => Calendar_Model_Event::shiftEnd(array_replace($base, ['end' => $base['start']])), 'CALENDAR_INVALID_RANGE');
rejects(fn() => Calendar_Model_Event::shiftEnd(array_replace($base, ['newstart' => '9998-12-31T23:30'])), 'CALENDAR_INVALID_DATE');
rejects(fn() => Calendar_Model_Event::shiftEnd($base + ['ownerid' => '2']), 'CALENDAR_INVALID_INPUT');
$model = new Calendar_Model_DbTable_Event();
$input = ['title' => 'K4 fixture', 'description' => '', 'location' => '', 'start' => $base['start'], 'end' => $base['end'], 'timezone' => $base['timezone'], 'visibility' => 'private'];
$id = $model->saveEvent($input);
$sequence = fn() => $model->getEvent($id)['sequence'];
$add = fn($data) => $model->addParticipant($id, ['kind' => 'external'] + $data, $sequence());
$before = count(glob($work . '/smtp-*.eml'));
$result = $add(['email' => 'MEMBER2@example.invalid', 'name' => 'Manually typed']);
$row = $model->participants($id)[0];
check((int)$row['userid'] === 2 && $row['name'] === 'Member 2' && $result['id'] == $row['id'] && $result['sequence'] === 1, 'Existing user address not resolved');
rejects(fn() => $add(['email' => 'member2@example.invalid', 'name' => 'Duplicate']), 'CALENDAR_PARTICIPANT_DUPLICATE');
$add(['email' => 'contact101@example.invalid', 'name' => 'Manual contact']);
check((int)$model->participants($id)[1]['contactemailid'] === 101, 'Existing contact address not resolved');
$add(['email' => 'member3@example.invalid', 'name' => 'External address']);
check($model->participants($id)[2]['userid'] === null, 'Foreign tenant user discovered through email');
rejects(fn() => $add(['email' => 'bad', 'name' => 'Name']), 'CALENDAR_PARTICIPANT_ADDRESS');
rejects(fn() => $add(['email' => "bad\r\n@example.invalid", 'name' => 'Name']), 'CALENDAR_PARTICIPANT_ADDRESS');
rejects(fn() => $add(['email' => 'valid@example.invalid', 'name' => '']), 'CALENDAR_PARTICIPANT_NAME_REQUIRED');
rejects(fn() => $add(['email' => 'member1@example.invalid', 'name' => 'Organizer']), 'CALENDAR_PARTICIPANT_SELF');
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '3'], $sequence()), 'CALENDAR_PARTICIPANT_USER');
$db->insert('user', ['id' => 20, 'name' => 'Ambiguous member', 'clientid' => 1, 'password' => 'fixture', 'activated' => 1, 'email' => 'member2@example.invalid']);
rejects(fn() => $add(['email' => 'member2@example.invalid', 'name' => 'Ambiguous']), 'CALENDAR_PARTICIPANT_AMBIGUOUS');
$db->delete('user', 'id=20');
$db->insert('email', ['id' => 103, 'parentid' => 101, 'clientid' => 1, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'contact101@example.invalid', 'password' => 'fixture']);
rejects(fn() => $add(['email' => 'contact101@example.invalid', 'name' => 'Ambiguous']), 'CALENDAR_PARTICIPANT_AMBIGUOUS');
$db->delete('email', 'id=103');
rejects(fn() => $add(['contactemailid' => '101', 'email' => 'changed@example.invalid', 'name' => 'Changed']), 'CALENDAR_CONTACT_CHANGED');
$db->update('permission', ['contacts' => null], 'userid=1');
$restricted = new Calendar_Model_DbTable_Event();
$restrictedId = $restricted->saveEvent($input);
$restricted->addParticipant($restrictedId, ['kind' => 'external', 'email' => 'contact101@example.invalid', 'name' => 'Manual external'], 0);
check($restricted->participants($restrictedId)[0]['contactemailid'] === null && $restricted->participants($restrictedId)[0]['name'] === 'Manual external', 'Private contact discovered without contact permission');
$restricted->deleteEvent($restrictedId);
$db->update('permission', ['contacts' => json_encode(['contact' => ['view']])], 'userid=1');
check(count(glob($work . '/smtp-*.eml')) === $before, 'Model lookup or unchecked addition sent email');
$model->deleteEvent($id);
// The same add-and-send request in independent processes can only add and mail once.
$id = $model->saveEvent($input); $children = [];
for ($i = 0; $i < 4; $i++) {
	$process = proc_open([PHP_BINARY, __DIR__ . '/reminder-worker.php', '40', (string)$i, (string)$id, '0', 'addmail'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$children[] = [$process, $pipes];
}
$deadline = microtime(true) + 10;
while (count(glob($work . '/ready-40-*')) !== 4) { if (microtime(true) > $deadline) throw new RuntimeException('Worker timeout'); usleep(10000); }
file_put_contents($work . '/go-40', '1'); $codes = []; $sent = 0;
foreach ($children as [$process, $pipes]) {
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	check(proc_close($process) === 0, 'Concurrent add failed: ' . $error);
	$result = json_decode($out, true, 512, JSON_THROW_ON_ERROR); $codes[] = $result['code']; $sent += $result['mail']['sent'] ?? 0;
}
check(count(array_filter($codes, fn($code) => $code === 200)) === 1 && count(array_filter($codes, fn($code) => $code === 409)) === 3, 'Repeated add was applied more than once');
check($sent === 1 && count(glob($work . '/smtp-*.eml')) === $before + 1 && count($model->participants($id)) === 1, 'Concurrent addition duplicated participant or invitation');
$model->deleteEvent($id);
foreach ([5 => ['view'], 6 => ['view', 'add', 'edit']] as $uid => $rights) {
	$db->insert('user', ['id' => $uid, 'name' => 'Member ' . $uid, 'clientid' => 1, 'password' => 'fixture', 'activated' => 1]);
	$db->insert('permission', ['userid' => $uid, 'clientid' => 1, 'calendar' => json_encode(['index' => $rights])]);
}
calendarActor(6); $editOnly = new Calendar_Model_DbTable_Event(); $editOnlyId = $editOnly->saveEvent($input);
$db->update('permission', ['calendar' => json_encode(['index' => ['view', 'edit']])], 'userid=6');
file_put_contents($work . '/usability-fixture.json', json_encode(['edit_only_id' => $editOnlyId]));
calendarActor();
echo "PASS: $checks usability date, resolution, validation, privacy and parallel add/send checks\n";
