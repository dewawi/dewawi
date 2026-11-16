<?php

class Calendar_Model_Invitation
{
	private Calendar_Model_DbTable_Event $events;

	public function __construct()
	{
		$this->events = new Calendar_Model_DbTable_Event();
	}

	public function send(int $id, array $selected, int $sequence, string $locale): array
	{
		$event = $this->events->ownedEvent($id);
		$this->events->authorize('add'); // Reuse the existing permission required for sending email.
		$this->events->checkSequence($event, $sequence);
		if (!in_array($locale, ['de_DE', 'en_US'], true) || !$selected || count($selected) > 20) throw new InvalidArgumentException('CALENDAR_MAIL_SELECTION');
		$ids = [];
		foreach ($selected as $value) {
			$pid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if (!$pid || isset($ids[$pid])) throw new InvalidArgumentException('CALENDAR_MAIL_SELECTION');
			$ids[$pid] = $pid;
		}
		try { [$smtp, $sender, $senderName] = Zend_Controller_Action_HelperBroker::getStaticHelper('Email')->getEmailConfig('calendar'); }
		catch (RuntimeException $e) { throw new RuntimeException('CALENDAR_MAIL_CONFIG', 409); }
		$sender = Calendar_Model_Icalendar::email($sender);
		$smtp['timeout'] = 20;
		$tr = new DEEC_Translate($locale); $tr->loadDir('calendar', BASE_PATH . '/languages/' . $locale . '/calendar');
		$user = Zend_Registry::get('User');
		$db = $this->events->getAdapter();
		$messageDb = (new Contacts_Model_DbTable_Emailmessage())->setClientId((int)$user['clientid']);
		$copyDb = (new Contacts_Model_DbTable_Emailmessageattachment())->setClientId((int)$user['clientid']);
		$messages = []; $createdPaths = []; $db->beginTransaction();
		try {
			$event = $this->events->ownedEvent($id, 'edit', true); $this->events->checkSequence($event, $sequence);
			if ($event['organizeremail'] && $event['organizeremail'] !== $sender) throw new RuntimeException('CALENDAR_MAIL_ORGANIZER', 409);
			if (!$event['organizeremail']) {
				$event['organizeremail'] = $sender;
				$db->update('calendarevent', ['organizeremail' => $sender], ['id = ' . (int)$id, 'clientid = ' . (int)$user['clientid'], 'ownerid = ' . (int)$user['id']]);
			}
			$participants = $db->fetchAll($db->select()->from('calendarparticipant')->where('eventid = ?', $id)->where('clientid = ?', (int)$user['clientid'])->where('id IN (?)', array_values($ids)));
			if (count($participants) !== count($ids)) throw new InvalidArgumentException('CALENDAR_MAIL_SELECTION');
			foreach ($participants as $participant) {
				$method = $event['cancelled'] || $participant['removed'] ? 'CANCEL' : 'REQUEST';
				Calendar_Model_Icalendar::email($participant['email'] ?? '');
				if ($method === 'REQUEST' && $participant['contactemailid']) {
					$contact = $this->events->contactEmails($id, '', (int)$participant['contactemailid'])[0] ?? null;
					if (!$contact || Calendar_Model_Icalendar::email($contact['email']) !== $participant['email']) throw new RuntimeException('CALENDAR_CONTACT_CHANGED', 409);
				}
				if ($method === 'REQUEST' && $participant['userid']) {
					$active = $db->fetchRow($db->select()->from('user', ['email'])->where('id = ?', (int)$participant['userid'])->where('clientid = ?', (int)$user['clientid'])->where('activated = ?', 1)->where('deleted = ?', 0));
					if (!$active || Calendar_Model_Icalendar::email($active['email']) !== $participant['email']) throw new RuntimeException('CALENDAR_PARTICIPANT_CHANGED', 409);
				}
				$ical = Calendar_Model_Icalendar::render($event, $participant, $method);
				$key = ['calendareventid' => $id, 'calendarparticipantid' => (int)$participant['id'], 'calendargeneration' => (int)$participant['generation'], 'calendarsequence' => $sequence, 'calendarmethod' => $method];
				$select = $db->select()->from('emailmessage')->where('clientid = ?', (int)$user['clientid']);
				foreach ($key as $field => $value) $select->where($field . ' = ?', $value);
				$existing = $db->fetchRow($select);
				if ($existing) { $messages[] = (int)$existing['id']; continue; }
				$subject = $tr->t($method === 'CANCEL' ? 'CALENDAR_MAIL_CANCEL' : 'CALENDAR_MAIL_REQUEST') . ($method === 'REQUEST' ? ': ' . preg_replace('/[\r\n]/', ' ', $event['title']) : '');
				$subject = mb_substr($subject, 0, 255);
				$start = (new DateTimeImmutable($event['startutc'], new DateTimeZone('UTC')))->setTimezone(Calendar_Model_Event::timezone($event['timezone']));
				$body = $tr->t('CALENDAR_MAIL_BODY') . "\n\n" . $event['title'] . "\n" . ($event['allday'] ? substr($event['start'], 0, 10) : $start->format('Y-m-d H:i P') . ' ' . $event['timezone']) . "\n" . $event['location'] . "\n\n" . $event['description'];
				if ($method === 'CANCEL') $body = $tr->t('CALENDAR_MAIL_CANCEL_BODY') . "\n\nUID: " . $event['icaluid'];
				$messageId = (int)$messageDb->addEmailmessage(DEEC_Email::prepareMessageData(['module' => 'calendar', 'controller' => 'index', 'sender' => $sender, 'recipient' => $participant['email'], 'subject' => $subject, 'body' => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')), 'attachments' => ['invitation.ics']]) + $key);
				$path = tempnam(sys_get_temp_dir(), 'dewawi-calendar-');
				if (!$path) throw new RuntimeException('CALENDAR_MAIL_FAILED');
				try {
					if (file_put_contents($path, $ical) !== strlen($ical)) throw new RuntimeException('CALENDAR_MAIL_FAILED');
					$copy = $copyDb->storeCopy($messageId, ['path' => $path, 'filename' => 'invitation.ics'], 1);
					$createdPaths[] = $copyDb->getPath($copy);
				} finally { unlink($path); }
				$messages[] = $messageId;
			}
			$db->commit();
		} catch (Throwable $e) { $db->rollBack(); foreach ($createdPaths as $path) if (is_file($path) && !is_link($path)) unlink($path); throw $e; }
		$result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
		foreach ($messages as $messageId) {
			$db->beginTransaction();
			try {
				$current = $this->events->ownedEvent($id, 'edit', true);
				$message = $db->fetchRow($db->select()->from('emailmessage')->where('id = ?', $messageId)->where('clientid = ?', (int)$user['clientid'])->forUpdate());
				if ($message['response'] !== 'pending') { $db->commit(); $result[$message['response'] === 'sent' ? 'skipped' : 'failed']++; continue; }
				if ((int)$current['icalsequence'] !== $sequence) { $messageDb->updateEmailmessage($messageId, ['response' => 'obsolete', 'deliverystatus' => 'obsolete']); $db->commit(); $result['skipped']++; continue; }
				$messageDb->updateEmailmessage($messageId, ['response' => 'sending', 'deliverystatus' => 'pending']);
				$db->commit();
			} catch (Throwable $e) { $db->rollBack(); throw $e; }
			try {
				$copy = $copyDb->getMessageAttachments($messageId)[0] ?? null;
				if (!$copy) throw new RuntimeException('CALENDAR_MAIL_FAILED');
				$path = $copyDb->getPath($copy);
				$sent = DEEC_Email::sendMessage($smtp, ['fromEmail' => $sender, 'fromName' => $senderName, 'to' => [$message['recipient']], 'subject' => $message['subject'], 'body' => $message['body'], 'altBody' => html_entity_decode(strip_tags(str_replace('<br />', "\n", $message['body'])), ENT_QUOTES, 'UTF-8'), 'attachments' => [['path' => $path, 'name' => 'invitation.ics']], 'ical' => file_get_contents($path), 'emailmessageId' => $messageId]);
				if (empty($sent['sent'])) throw new RuntimeException('CALENDAR_MAIL_FAILED');
				$messageDb->markAccepted($messageId); $result['sent']++;
			} catch (Throwable $e) {
				error_log('Calendar email acceptance not confirmed for message ' . $messageId);
				$messageDb->updateEmailmessage($messageId, ['response' => 'unknown', 'deliverystatus' => 'unknown']); $result['failed']++;
			}
		}
		return $result;
	}
}
