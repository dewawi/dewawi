<?php

require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) throw new RuntimeException($message); }
function rejects(callable $run, int $code = 0): void {
	try { $run(); } catch (RuntimeException | InvalidArgumentException $e) { check($e->getCode() === $code, 'Wrong failure: ' . $e->getMessage()); return; }
	throw new RuntimeException('Unexpected participant operation success');
}
function seq(Calendar_Model_DbTable_Event $model, int $id): int { return $model->getEvent($id)['sequence']; }
function mails(): array { global $work; return glob($work . '/smtp-*.eml'); }
foreach (['contact', 'email', 'emailmessageattachment'] as $table) {
	preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', file_get_contents($root . '/install/config/structure.sql'), $match);
	$db->query($match[0]);
}
$db->update('user', ['email' => 'member1@example.invalid'], 'id=1');
$db->update('user', ['email' => 'member2@example.invalid'], 'id=2');
$db->update('user', ['email' => 'member3@example.invalid'], 'id=3');
$db->update('permission', ['contacts' => json_encode(['contact' => ['view']])], 'userid=1');
foreach ([101 => 1, 102 => 3] as $id => $client) {
	$db->insert('contact', ['id' => $id, 'catid' => 0, 'contactid' => $id, 'name1' => 'CRM ' . $id, 'name2' => 'Contact', 'clientid' => $client]);
	$db->insert('email', ['id' => $id, 'parentid' => $id, 'clientid' => $client, 'module' => 'contacts', 'controller' => 'contact', 'email' => 'contact' . $id . '@example.invalid', 'password' => 'fixture-only']);
}
$model = new Calendar_Model_DbTable_Event();
$input = ['title' => 'K3 PRIVATE title, semicolon; \\ and ä', 'description' => "Private description\nwith line breaks", 'location' => 'Private office', 'start' => '2026-10-25T02:30', 'startfold' => 'later', 'end' => '2026-10-25T04:30', 'timezone' => 'Europe/Berlin', 'visibility' => 'private', 'reminderminutes' => '15'];
$id = $model->saveEvent($input);
$uid = $model->ownedEvent($id)['icaluid'];
check($uid !== '' && seq($model, $id) === 0, 'Missing stable UID/initial sequence');
$beforeMails = count(mails());
$model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id));
$internal = $model->participants($id)[0];
check((int)$internal['userid'] === 2 && $internal['status'] === 'invited' && count(mails()) === $beforeMails, 'Internal invitation auto-accepted or emailed');
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id)));
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '3'], seq($model, $id)));
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '1'], seq($model, $id)));
$db->update('user', ['activated' => 0], 'id=2');
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id)));
$db->update('user', ['activated' => 1], 'id=2');
foreach (['bad', "bad\r\n@example.invalid", 'one@example.invalid,two@example.invalid'] as $bad) rejects(fn() => $model->addParticipant($id, ['kind' => 'external', 'name' => 'Bad', 'email' => $bad], seq($model, $id)));
$model->addParticipant($id, ['kind' => 'external', 'name' => 'External <script>', 'email' => 'EXTERNAL@example.invalid'], seq($model, $id));
$external = $model->participants($id)[1];
check($external['email'] === 'external@example.invalid' && $external['status'] === 'invited', 'External normalization/status incorrect');
rejects(fn() => $model->addParticipant($id, ['kind' => 'external', 'name' => 'Duplicate', 'email' => 'external@EXAMPLE.invalid'], seq($model, $id)));
rejects(fn() => $model->addParticipant($id, ['kind' => 'external', 'name' => 'Duplicate internal', 'email' => 'member2@example.invalid'], seq($model, $id)));
check(count($model->contactEmails($id, 'CRM')) === 1, 'Foreign contact in picker');
rejects(fn() => $model->addParticipant($id, ['kind' => 'external', 'contactemailid' => '102'], seq($model, $id)));
$model->addParticipant($id, ['kind' => 'external', 'contactemailid' => '101'], seq($model, $id));
$contact = $model->participants($id)[2];
check($contact['email'] === 'contact101@example.invalid' && $contact['name'] === 'CRM 101 Contact', 'Contact snapshot wrong');
$db->update('email', ['suppressed' => 1], 'id=101');
check($model->contactEmails($id, 'CRM') === [], 'Suppressed address selectable');
$db->update('email', ['suppressed' => 0], 'id=101');
calendarActor(2);
$invited = new Calendar_Model_DbTable_Event();
$event = $invited->getEvent($id);
check(!$event['own'] && !$event['private'] && $event['title'] === $input['title'] && $event['participation'] === 'invited', 'Invite cannot read necessary private details');
check(!isset($event['reminderminutes']) && !in_array($id, array_column($invited->dueReminders()['reminders'], 'id')), 'Invite got organizer reminder');
$period = Calendar_Model_Event::period('2026-10-25', 'month', new DateTimeZone('Europe/Berlin'));
foreach (['mine', 'invitations'] as $scope) check(in_array($id, array_column($invited->events($period, 0, $scope), 'id')), 'Personal invitation missing');
check(in_array($id, array_column($invited->events($period, 2), 'id')), 'Employee filter omitted invitations');
foreach ($invited->participants($id) as $row) check(!isset($row['email'], $row['contactemailid']), 'Invite sees unrelated email addresses');
rejects(fn() => $invited->saveEvent($input, $id), 403);
rejects(fn() => $invited->removeParticipant($id, (int)$external['id'], seq($invited, $id)), 404);
rejects(fn() => $invited->cancelEvent($id, seq($invited, $id)), 404);
$invited->respond($id, 'accepted', seq($invited, $id));
check($invited->getEvent($id)['participation'] === 'accepted', 'Own acceptance failed');
$invited->respond($id, 'declined', seq($invited, $id));
check($invited->getEvent($id)['participation'] === 'declined', 'Own decline failed');
rejects(fn() => $invited->respond($id, 'other', seq($invited, $id)));
calendarActor(3, 2);
$foreign = new Calendar_Model_DbTable_Event();
rejects(fn() => $foreign->getEvent($id), 404);
rejects(fn() => $foreign->respond($id, 'accepted', 3), 404);
rejects(fn() => $foreign->participants($id), 404);
calendarActor();
$model = new Calendar_Model_DbTable_Event();
$model->respond($id, 'accepted', seq($model, $id), (int)$external['id']);
rejects(fn() => $model->respond($id, 'accepted', seq($model, $id), (int)$internal['id']), 403);
$revision = $model->ownedEvent($id)['reminderrevision'];
check(count(mails()) === $beforeMails, 'Participant operations emailed automatically');
$sender = new Calendar_Model_Invitation();
$s = seq($model, $id);
$result = $sender->send($id, [(int)$external['id']], $s, 'de_DE');
check($result['sent'] === 1 && count(mails()) === $beforeMails + 1, 'Invitation did not reach isolated SMTP');
$message = $db->fetchRow('SELECT * FROM emailmessage WHERE calendareventid=? ORDER BY id DESC', [$id]);
check($message['response'] === 'sent' && $message['recipient'] === $external['email'] && $message['calendarsequence'] == $s, 'Stored dispatch wrong');
$copies = new Contacts_Model_DbTable_Emailmessageattachment();
$copy = $copies->getMessageAttachments((int)$message['id'])[0];
$ical = file_get_contents($copies->getPath($copy));
check(str_contains($ical, 'METHOD:REQUEST') && str_contains($ical, 'UID:' . $uid) && str_contains($ical, 'DTSTART:20261025T013000Z') && str_contains($ical, 'PARTSTAT=ACCEPTED'), 'REQUEST UID/time/status incorrect');
check(!str_contains($ical, 'member2@example.invalid') && !str_contains($ical, 'contact101@example.invalid'), 'Unselected attendees exposed in ICS');
foreach (explode("\r\n", $ical) as $line) check(strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8'), 'Invalid ICS folding');
$again = $sender->send($id, [(int)$external['id']], $s, 'de_DE');
check($again['sent'] === 0 && $again['skipped'] === 1 && count(mails()) === $beforeMails + 1, 'Repeated request duplicated email');
rejects(fn() => $sender->send($id, [], $s, 'de_DE'));
rejects(fn() => $sender->send($id, [999999], $s, 'de_DE'));
calendarActor(2);
check((new Contacts_Model_DbTable_Emailmessage())->getById((int)$message['id']) === null, 'Invite reads private organizer email history');
check((new Contacts_Model_DbTable_Emailmessageattachment())->getMessageAttachments((int)$message['id']) === [], 'Invite reads organizer archived attachment');
rejects(fn() => (new Calendar_Model_Invitation())->send($id, [(int)$external['id']], $s, 'en_US'), 404);
calendarActor();
$model = new Calendar_Model_DbTable_Event();
$model->saveEvent(array_replace($input, ['title' => 'Updated secret title']), $id);
check(seq($model, $id) === $s + 1 && $model->ownedEvent($id)['icaluid'] === $uid, 'Update changed UID or failed sequence increment');
rejects(fn() => $sender->send($id, [(int)$external['id']], $s, 'en_US'), 409);
$sender->send($id, [(int)$external['id']], seq($model, $id), 'en_US');
$model->removeParticipant($id, (int)$external['id'], seq($model, $id));
$cancelSequence = seq($model, $id);
$sender->send($id, [(int)$external['id']], $cancelSequence, 'en_US');
$cancel = $db->fetchRow('SELECT * FROM emailmessage WHERE calendareventid=? ORDER BY id DESC', [$id]);
$cancelCopy = $copies->getMessageAttachments((int)$cancel['id'])[0];
$cancelIcs = file_get_contents($copies->getPath($cancelCopy));
check($cancel['calendarmethod'] === 'CANCEL' && $cancel['recipient'] === $external['email'], 'Removal notified wrong recipient');
check(str_contains($cancelIcs, 'UID:' . $uid) && str_contains($cancelIcs, 'SEQUENCE:' . $cancelSequence) && !str_contains($cancelIcs, 'STATUS:CANCELLED'), 'Partial CANCEL incorrect');
foreach ([$cancelIcs, $cancel['body'], $cancel['subject']] as $content) check(!str_contains($content, 'Updated secret title') && !str_contains($content, 'Private description') && !str_contains($content, 'Private office'), 'Removal cancellation leaked changed private details');
$model->removeParticipant($id, (int)$internal['id'], seq($model, $id));
calendarActor(2);
$removed = new Calendar_Model_DbTable_Event();
check($removed->getEvent($id)['private'] && !isset($removed->getEvent($id)['title']), 'Removed participant retained private details');
rejects(fn() => $removed->respond($id, 'accepted', seq($model, $id)), 403);
calendarActor();
$model = new Calendar_Model_DbTable_Event();
$model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id));
$restored = $model->participants($id)[0];
check((int)$restored['id'] === (int)$internal['id'] && (int)$restored['generation'] === 2 && $restored['status'] === 'invited', 'Re-invitation did not reset personal response');
check((int)$model->ownedEvent($id)['reminderrevision'] === (int)$revision + 1, 'Participant changes modified K2 reminder state');
$model->cancelEvent($id, seq($model, $id));
$sender->send($id, [(int)$internal['id']], seq($model, $id), 'de_DE');
$cancel = $db->fetchRow('SELECT * FROM emailmessage WHERE calendareventid=? ORDER BY id DESC', [$id]);
$cancelIcs = file_get_contents($copies->getPath($copies->getMessageAttachments((int)$cancel['id'])[0]));
check(str_contains($cancelIcs, 'STATUS:CANCELLED') && $cancel['recipient'] === 'member2@example.invalid', 'Full CANCEL invalid');
check(!in_array($id, array_column($model->dueReminders()['reminders'], 'id')), 'Cancelled event still reminds');
rejects(fn() => $model->saveEvent($input, $id), 409);
calendarActor(2);
rejects(fn() => (new Calendar_Model_DbTable_Event())->respond($id, 'accepted', seq($model, $id)), 409);
calendarActor();
$before = count(mails()); $model->deleteEvent($id); check(count(mails()) === $before, 'Deleting auto-sent email');
// Transport rejection and ambiguous acceptance remain durable and never trigger retries.
foreach (['failure@example.invalid', 'uncertain@example.invalid'] as $address) {
	$id = $model->saveEvent($input);
	$model->addParticipant($id, ['kind' => 'external', 'name' => 'SMTP fixture', 'email' => $address], seq($model, $id));
	$pid = (int)$model->participants($id)[0]['id'];
	$snapshot = $model->getEvent($id);
	$result = $sender->send($id, [$pid], seq($model, $id), 'en_US');
	check($result['failed'] === 1 && $model->getEvent($id) === $snapshot, 'SMTP error damaged saved event');
	$commands = file_get_contents($work . '/smtp-commands.txt');
	$sender->send($id, [$pid], seq($model, $id), 'en_US');
	check(file_get_contents($work . '/smtp-commands.txt') === $commands, 'Unknown SMTP result was retried');
	$model->deleteEvent($id);
}
// Independent processes contend for the same durable dispatch reservation.
$id = $model->saveEvent($input);
$model->addParticipant($id, ['kind' => 'external', 'name' => 'Parallel', 'email' => 'parallel@example.invalid'], seq($model, $id));
$before = count(mails()); $children = [];
for ($i = 0; $i < 4; $i++) {
	$process = proc_open([PHP_BINARY, __DIR__ . '/reminder-worker.php', '30', (string)$i, (string)$id, (string)seq($model, $id), 'mail'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$children[] = [$process, $pipes];
}
$deadline = microtime(true) + 10;
while (count(glob($work . '/ready-30-*')) !== 4) { if (microtime(true) > $deadline) throw new RuntimeException('Mail worker readiness timeout'); usleep(10000); }
file_put_contents($work . '/go-30', '1'); $sent = 0;
foreach ($children as [$process, $pipes]) {
	$out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
	check(proc_close($process) === 0, 'Parallel sender failed: ' . $error);
	$result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
	check(isset($result['sent']), 'Parallel sender returned error: ' . $out); $sent += $result['sent'];
}
check($sent === 1 && count(mails()) === $before + 1, 'Parallel requests duplicated SMTP delivery');
check((int)$db->fetchOne('SELECT COUNT(*) FROM emailmessage WHERE calendareventid=?', [$id]) === 1, 'Parallel requests duplicated archive');
$model->deleteEvent($id);
foreach ([['2026-03-29', '2026-03-30'], ['2026-10-25', '2026-10-26']] as [$start, $end]) {
	$id = $model->saveEvent(array_replace($input, ['start' => $start, 'end' => $end, 'allday' => '1']));
	$row = $model->ownedEvent($id); $row['organizeremail'] = 'organizer@example.invalid';
	$ics = Calendar_Model_Icalendar::render($row, $external, 'REQUEST');
	check(str_contains($ics, 'DTSTART;VALUE=DATE:' . str_replace('-', '', $start)) && str_contains($ics, 'DTEND;VALUE=DATE:' . str_replace('-', '', $end)), 'All-day exclusive dates shifted at DST boundary');
	$row['description'] = str_repeat('ü', 100) . "\r\nBEGIN:ATTACK";
	$ics = Calendar_Model_Icalendar::render($row, $external, 'REQUEST');
	foreach (explode("\r\n", $ics) as $line) check(strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8'), 'Multibyte folding incorrect');
	check(!str_contains($ics, "\r\nBEGIN:ATTACK"), 'ICS property injection');
	$row['title'] = "bad\x00"; rejects(fn() => Calendar_Model_Icalendar::render($row, $external, 'REQUEST'));
	$model->deleteEvent($id);
}
$id = $model->saveEvent($input);
$model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id));
$pid = (int)$model->participants($id)[0]['id'];
$sender->send($id, [$pid], seq($model, $id), 'en_US');
$db->update('user', ['email' => 'changed-member2@example.invalid'], 'id=2');
rejects(fn() => $sender->send($id, [$pid], seq($model, $id), 'en_US'), 409);
$model->removeParticipant($id, $pid, seq($model, $id));
rejects(fn() => $model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id)), 409);
check($model->participants($id)[0]['email'] === 'member2@example.invalid', 'Old recipient lost before cancellation');
$sender->send($id, [$pid], seq($model, $id), 'en_US');
$model->addParticipant($id, ['kind' => 'internal', 'userid' => '2'], seq($model, $id));
check($model->participants($id)[0]['email'] === 'changed-member2@example.invalid', 'Address refresh failed after cancellation');
$db->update('user', ['email' => 'member2@example.invalid'], 'id=2');
$model->cancelEvent($id, seq($model, $id));
rejects(fn() => $model->removeParticipant($id, $pid, seq($model, $id)), 409);
$model->deleteEvent($id);
file_put_contents($work . '/participant-fixture.json', json_encode(['input' => $input, 'mail_count' => count(mails())]));
echo "PASS: $checks participant, invitation privacy, status, ICS, SMTP and durable-send checks\n";
