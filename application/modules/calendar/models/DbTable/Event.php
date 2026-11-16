<?php

class Calendar_Model_DbTable_Event extends Zend_Db_Table_Abstract
{
	protected $_name = 'calendarevent';
	private int $clientid;
	private int $actor;
	private DEEC_Permission $permission;
	private DateTimeZone $zone;

	public function init()
	{
		$user = Zend_Registry::isRegistered('User') ? Zend_Registry::get('User') : [];
		$client = Zend_Registry::isRegistered('Client') ? Zend_Registry::get('Client') : [];
		if (!Zend_Auth::getInstance()->hasIdentity() || empty($user['id']) || empty($user['clientid']) || (int)$user['clientid'] !== (int)($client['id'] ?? 0)) throw new RuntimeException('CALENDAR_DENIED', 403);
		$this->clientid = (int)$user['clientid'];
		$this->actor = (int)$user['id'];
		$this->permission = new DEEC_Permission((array)$user);
		$config = Zend_Registry::get('Config');
		$this->zone = Calendar_Model_Event::timezone($config['timezone']);
	}

	public function authorize(string $action): void
	{
		if (!$this->permission->hasPermission('calendar', 'index', 'view') || !$this->permission->hasPermission('calendar', 'index', $action)) throw new RuntimeException('CALENDAR_DENIED', 403);
	}

	public function can(string $action): bool
	{
		return $this->permission->hasPermission('calendar', 'index', 'view') && $this->permission->hasPermission('calendar', 'index', $action);
	}

	public function members(): array
	{
		$this->authorize('view');
		$db = $this->getAdapter();
		$select = $db->select()->from(['u' => 'user'], ['id', 'name'])
			->where('(u.clientid = ? AND u.deleted = 0)', $this->clientid)
			->orWhere('u.id = ?', $this->actor)
			->orWhere('u.id IN (SELECT ownerid FROM calendarevent WHERE clientid = ? AND deleted = 0)', $this->clientid)
			->order(['u.name', 'u.id']);
		return $db->fetchPairs($select);
	}

	private function readSelect(): Zend_Db_Select
	{
		$db = $this->getAdapter();
		// Private content never leaves SQL for another owner, including single-record reads.
		$visible = '(e.ownerid = ' . $db->quote($this->actor) . " OR e.visibility = 'team' OR invitation.id IS NOT NULL)";
		$columns = ['id', 'ownerid', 'allday', 'start', 'end', 'startutc', 'endutc', 'timezone', 'visibility', 'cancelled', 'icalsequence'];
		$columns['reminderminutes'] = new Zend_Db_Expr('CASE WHEN e.ownerid = ' . $db->quote($this->actor) . ' THEN e.reminderminutes ELSE NULL END');
		foreach (['title', 'description', 'location'] as $field) $columns[$field] = new Zend_Db_Expr('CASE WHEN ' . $visible . ' THEN e.' . $field . ' ELSE NULL END');
		return $db->select()->from(['e' => $this->_name], $columns)
			->joinLeft(['invitation' => 'calendarparticipant'], 'invitation.eventid = e.id AND invitation.clientid = e.clientid AND invitation.removed = 0 AND invitation.userid = ' . $db->quote($this->actor), ['participation' => 'status'])
			->joinLeft(['u' => 'user'], 'u.id = e.ownerid', ['owner' => 'name'])
			->where('e.clientid = ?', $this->clientid)->where('e.deleted = ?', 0);
	}

	public function getEvent(int $id): array
	{
		$this->authorize('view');
		$row = $this->getAdapter()->fetchRow($this->readSelect()->where('e.id = ?', $id));
		if (!$row) throw new RuntimeException('CALENDAR_NOT_FOUND', 404);
		return Calendar_Model_Event::present($row, $this->actor, $this->zone);
	}

	public function events(array $period, int $ownerid = 0, string $scope = 'team'): array
	{
		$this->authorize('view');
		if (!in_array($scope, ['team', 'mine', 'invitations'], true)) throw new InvalidArgumentException('CALENDAR_INVALID_INPUT');
		if ($ownerid && !array_key_exists($ownerid, $this->members())) throw new InvalidArgumentException('CALENDAR_INVALID_MEMBER');
		$db = $this->getAdapter();
		$timed = $db->quoteInto('e.startutc < ?', $period['end']->format('Y-m-d H:i:s')) . ' AND ' . $db->quoteInto('e.endutc > ?', $period['start']->format('Y-m-d H:i:s'));
		$allDay = $db->quoteInto('e.start < ?', $period['until'] . ' 00:00:00') . ' AND ' . $db->quoteInto('e.end > ?', $period['first'] . ' 00:00:00');
		$select = $this->readSelect()->where('(e.allday = 1 AND (' . $allDay . ')) OR (e.allday = 0 AND ((' . $timed . ') OR e.startutc IS NULL))');
		$select->where('e.cancelled = 0 OR e.ownerid = ' . $db->quote($this->actor) . ' OR invitation.id IS NOT NULL');
		if ($scope === 'mine') $select->where('e.ownerid = ' . $db->quote($this->actor) . ' OR invitation.id IS NOT NULL');
		if ($scope === 'invitations') $select->where('invitation.id IS NOT NULL');
		if ($ownerid) {
			$invited = $db->select()->from(['member_invitation' => 'calendarparticipant'], ['id'])->where('member_invitation.eventid = e.id')->where('member_invitation.clientid = ?', $this->clientid)->where('member_invitation.userid = ?', $ownerid)->where('member_invitation.removed = ?', 0);
			if ($ownerid !== $this->actor) $invited->where('member_invitation.status <> ?', 'declined');
			$select->where($db->quoteInto('e.ownerid = ?', $ownerid) . ' OR EXISTS (' . $invited . ')');
		}
		$result = [];
		foreach ($db->fetchAll($select) as $row) {
			$event = Calendar_Model_Event::present($row, $this->actor, $this->zone);
			if ($event['allday'] || (new DateTimeImmutable($event['start']) < $period['end'] && new DateTimeImmutable($event['end']) > $period['start'])) $result[] = $event;
		}
		usort($result, static fn($a, $b) => ((new DateTimeImmutable($a['start']))->getTimestamp() <=> (new DateTimeImmutable($b['start']))->getTimestamp()) ?: ($a['id'] <=> $b['id']));
		return $result;
	}

	public function saveEvent(array $input, int $id = 0): int
	{
		$this->authorize($id ? 'edit' : 'add');
		if ($id && !$this->getEvent($id)['own']) throw new RuntimeException('CALENDAR_DENIED', 403);
		$data = Calendar_Model_Event::validate($input);
		$now = gmdate('Y-m-d H:i:s');
		if (!$id) {
			$data['reminderminutes'] = $data['reminderminutes'] ?? null;
			return (int)$this->insert($data + ['icaluid' => bin2hex(random_bytes(16)) . '@dewawi', 'reminderdue' => $this->reminderDue($data), 'reminderrevision' => 1, 'clientid' => $this->clientid, 'ownerid' => $this->actor, 'created' => $now, 'createdby' => $this->actor, 'modified' => $now, 'modifiedby' => $this->actor]);
		}
		$db = $this->getAdapter();
		$db->beginTransaction();
		try {
			$row = $db->fetchRow($this->select()->where('id = ?', $id)->where('clientid = ?', $this->clientid)->where('ownerid = ?', $this->actor)->where('deleted = ?', 0)->forUpdate());
			if (!$row) throw new RuntimeException('CALENDAR_NOT_FOUND', 404);
			if ($row['cancelled']) throw new RuntimeException('CALENDAR_CANCELLED_LOCKED', 409);
			if (!array_key_exists('reminderminutes', $data)) $data['reminderminutes'] = $row['reminderminutes'];
			$changed = false;
			foreach (['startutc', 'endutc', 'timezone', 'allday', 'reminderminutes'] as $field) if ((string)$data[$field] !== (string)$row[$field]) $changed = true;
			if ($changed) $data += ['reminderdue' => $this->reminderDue($data), 'reminderdone' => null];
			$data['reminderrevision'] = (int)$row['reminderrevision'] + 1;
			foreach (['title', 'description', 'location', 'startutc', 'endutc', 'timezone', 'allday', 'visibility'] as $field) {
				if ((string)$data[$field] !== (string)$row[$field]) { $data['icalsequence'] = (int)$row['icalsequence'] + 1; break; }
			}
			$this->update($data + ['modified' => $now, 'modifiedby' => $this->actor], $this->ownedWhere($id));
			$db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
		return $id;
	}

	private function reminderDue(array $data): ?string
	{
		if ($data['reminderminutes'] === null) return null;
		$due = (new DateTimeImmutable($data['startutc'], new DateTimeZone('UTC')))->modify('-' . (int)$data['reminderminutes'] . ' minutes');
		if ($due->format('Y') < '1000') throw new InvalidArgumentException('CALENDAR_INVALID_DATE');
		return $due->format('Y-m-d H:i:s');
	}

	private function dueWhere(string $now): array
	{
		return ['cancelled = 0', 'reminderminutes IS NOT NULL', 'reminderdone IS NULL', 'startutc IS NOT NULL', $this->getAdapter()->quoteInto('reminderdue <= ?', $now)];
	}

	public function dueReminders(): array
	{
		$this->authorize('view');
		$db = $this->getAdapter();
		$select = $this->select()->where('clientid = ?', $this->clientid)->where('ownerid = ?', $this->actor)->where('deleted = ?', 0);
		foreach ($this->dueWhere(gmdate('Y-m-d H:i:s')) as $where) $select->where($where);
		$db->beginTransaction();
		try {
			$count = (int)$db->fetchOne((clone $select)->from($this->_name, [new Zend_Db_Expr('COUNT(*)')]));
			$rows = $db->fetchAll($select->order(['reminderdue', 'id'])->limit(20));
			$db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
		$items = [];
		foreach ($rows as $row) {
			$event = Calendar_Model_Event::present($row, $this->actor, $this->zone);
			$items[] = ['id' => $event['id'], 'revision' => (int)$row['reminderrevision'], 'title' => $event['title'], 'start' => $event['start'], 'allday' => $event['allday']];
		}
		return ['count' => $count, 'reminders' => $items];
	}

	public function updateReminder(int $id, int $revision, ?int $snooze): void
	{
		$this->authorize('view');
		if ($id <= 0 || $revision <= 0 || ($snooze !== null && !in_array($snooze, [5, 15, 60], true))) throw new InvalidArgumentException('CALENDAR_REMINDER_INVALID');
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$data = ['reminderrevision' => new Zend_Db_Expr('reminderrevision + 1')];
		if ($snooze === null) $data['reminderdone'] = $now->format('Y-m-d H:i:s');
		else $data['reminderdue'] = $now->modify('+' . $snooze . ' minutes')->format('Y-m-d H:i:s');
		$where = array_merge($this->ownedWhere($id), $this->dueWhere($now->format('Y-m-d H:i:s')), [$this->getAdapter()->quoteInto('reminderrevision = ?', $revision)]);
		if ($this->update($data, $where) !== 1) throw new RuntimeException('CALENDAR_REMINDER_CHANGED', 409);
	}

	public function deleteEvent(int $id): void
	{
		$this->authorize('delete');
		if (!$this->getEvent($id)['own']) throw new RuntimeException('CALENDAR_DENIED', 403);
		$this->update(['deleted' => 1, 'modified' => gmdate('Y-m-d H:i:s'), 'modifiedby' => $this->actor], $this->ownedWhere($id));
	}

	public function ownedEvent(int $id, string $permission = 'edit', bool $lock = false): array
	{
		$this->authorize($permission);
		$select = $this->select()->where('id = ?', $id)->where('clientid = ?', $this->clientid)->where('ownerid = ?', $this->actor)->where('deleted = ?', 0)->forUpdate($lock);
		$row = $this->getAdapter()->fetchRow($select);
		if (!$row) throw new RuntimeException('CALENDAR_NOT_FOUND', 404);
		return $row;
	}

	public function participants(int $id): array
	{
		$event = $this->getEvent($id);
		if (!$event['own'] && $event['participation'] === null) throw new RuntimeException('CALENDAR_DENIED', 403);
		$select = $this->getAdapter()->select()->from('calendarparticipant')->where('eventid = ?', $id)->where('clientid = ?', $this->clientid)->order(['removed', 'id']);
		if (!$event['own']) $select->where('removed = ?', 0);
		$rows = $this->getAdapter()->fetchAll($select);
		if (!$event['own']) foreach ($rows as &$row) { unset($row['email'], $row['contactemailid'], $row['generation'], $row['created'], $row['modified']); }
		unset($row);
		return $rows;
	}

	public function participantUsers(int $id): array
	{
		$this->ownedEvent($id);
		return $this->getAdapter()->fetchPairs($this->getAdapter()->select()->from('user', ['id', 'name'])->where('clientid = ?', $this->clientid)->where('activated = ?', 1)->where('deleted = ?', 0)->where('id <> ?', $this->actor)->order('name'));
	}

	public function contactEmails(int $id, string $keyword = '', int $emailid = 0): array
	{
		$this->ownedEvent($id);
		if (!$this->permission->hasPermission('contacts', 'contact', 'view')) throw new RuntimeException('CALENDAR_DENIED', 403);
		if (!$emailid && (mb_strlen(trim($keyword)) < 2 || mb_strlen($keyword) > 100)) throw new InvalidArgumentException('CALENDAR_CONTACT_SEARCH');
		$client = Zend_Registry::get('Client');
		$contactClient = (int)($client['modules']['contacts'] ?? $this->clientid);
		$db = $this->getAdapter();
		$select = $db->select()->from(['e' => 'email'], ['id', 'email'])
			->join(['c' => 'contact'], 'c.id = e.parentid AND c.clientid = e.clientid', ['name' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(c.name1, ''), ' ', COALESCE(c.name2, '')))" )])
			->where('e.clientid = ?', $contactClient)->where('e.module = ?', 'contacts')->where('e.controller = ?', 'contact')->where('e.deleted = ?', 0)->where('e.suppressed = ?', 0)->where('c.deleted = ?', 0)->order('c.name1')->limit(20);
		if ($emailid) $select->where('e.id = ?', $emailid);
		else {
			$like = '%' . addcslashes(trim($keyword), '%_\\') . '%';
			$select->where(implode(' OR ', array_map(fn($field) => $db->quoteInto($field . ' LIKE ?', $like), ['c.name1', 'c.name2', 'e.email'])));
		}
		return array_values(array_filter($db->fetchAll($select), fn($row) => DEEC_Filter::isValidEmail($row['email'])));
	}

	public function addParticipant(int $id, array $input, int $sequence): void
	{
		$this->ownedEvent($id);
		if (array_diff(array_keys($input), ['kind', 'userid', 'name', 'email', 'contactemailid'])) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		foreach ($input as $value) if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		$userid = null; $emailid = null;
		if (($input['kind'] ?? '') === 'internal') {
			$userid = filter_var($input['userid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			$user = $userid ? $this->getAdapter()->fetchRow($this->getAdapter()->select()->from('user', ['id', 'name', 'email'])->where('id = ?', $userid)->where('clientid = ?', $this->clientid)->where('activated = ?', 1)->where('deleted = ?', 0)) : null;
			if (!$user || $userid === $this->actor) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
			$name = trim((string)$user['name']) ?: '#' . $userid;
			$email = DEEC_Filter::isValidEmail($user['email']) ? Calendar_Model_Icalendar::email($user['email']) : null;
		} elseif (($input['kind'] ?? '') === 'external') {
			$emailid = filter_var($input['contactemailid'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
			if ($emailid === false) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
			if ($emailid) {
				$contact = $this->contactEmails($id, '', $emailid)[0] ?? null;
				if (!$contact) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
				$name = $contact['name']; $email = Calendar_Model_Icalendar::email($contact['email']);
			} else { $name = trim($input['name'] ?? ''); $email = Calendar_Model_Icalendar::email($input['email'] ?? ''); }
			$emailid = $emailid ?: null;
		} else throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		$user = Zend_Registry::get('User');
		if ($name === '' || mb_strlen($name) > 255 || ($email && in_array($email, array_map('strtolower', [(string)($user['email'] ?? ''), (string)($user['smtpuser'] ?? '')]), true))) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		$db = $this->getAdapter(); $db->beginTransaction();
		try {
			$event = $this->ownedEvent($id, 'edit', true); $this->checkSequence($event, $sequence);
			if ($event['cancelled']) throw new RuntimeException('CALENDAR_CANCELLED_LOCKED', 409);
			$existing = $db->select()->from('calendarparticipant')->where('eventid = ?', $id)->where('clientid = ?', $this->clientid);
			$keys = [];
			if ($userid) $keys[] = $db->quoteInto('userid = ?', $userid);
			if ($email) $keys[] = $db->quoteInto('email = ?', $email);
			$rows = $db->fetchAll($existing->where(implode(' OR ', $keys)));
			if (count($rows) > 1 || ($rows && !$rows[0]['removed'])) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_DUPLICATE');
			if ($rows && $rows[0]['email'] !== $email) {
				$history = $db->select()->from('emailmessage', ['calendarmethod', 'response'])->where('clientid = ?', $this->clientid)->where('calendareventid = ?', $id)->where('calendarparticipantid = ?', (int)$rows[0]['id'])->where('calendargeneration = ?', (int)$rows[0]['generation'])->where('response IN (?)', ['sent', 'sending', 'unknown'])->order('calendarsequence DESC')->limit(1);
				$last = $db->fetchRow($history);
				if ($last && ($last['calendarmethod'] !== 'CANCEL' || $last['response'] !== 'sent')) throw new RuntimeException('CALENDAR_OLD_ADDRESS_CANCEL', 409);
			}
			$count = (int)$db->fetchOne($db->select()->from('calendarparticipant', [new Zend_Db_Expr('COUNT(*)')])->where('eventid = ?', $id)->where('clientid = ?', $this->clientid)->where('removed = ?', 0));
			if ($count >= 100) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_LIMIT');
			$data = ['userid' => $userid, 'name' => $name, 'email' => $email, 'contactemailid' => $emailid, 'status' => 'invited', 'removed' => 0, 'modified' => gmdate('Y-m-d H:i:s')];
			if ($rows) $db->update('calendarparticipant', $data + ['generation' => (int)$rows[0]['generation'] + 1], $this->participantWhere($id, (int)$rows[0]['id']));
			else $db->insert('calendarparticipant', $data + ['eventid' => $id, 'clientid' => $this->clientid, 'created' => gmdate('Y-m-d H:i:s')]);
			$this->touchSequence($event); $db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
	}

	public function checkSequence(array $event, int $sequence): void
	{
		if ((int)$event['icalsequence'] !== $sequence) throw new RuntimeException('CALENDAR_EVENT_CHANGED', 409);
	}

	private function touchSequence(array $event, array $data = []): void
	{
		$this->update($data + ['icalsequence' => (int)$event['icalsequence'] + 1, 'modified' => gmdate('Y-m-d H:i:s'), 'modifiedby' => $this->actor], $this->ownedWhere((int)$event['id']));
	}

	private function participantWhere(int $id, int $participant): array
	{
		$db = $this->getAdapter();
		return [$db->quoteInto('eventid = ?', $id), $db->quoteInto('clientid = ?', $this->clientid), $db->quoteInto('id = ?', $participant)];
	}

	public function removeParticipant(int $id, int $participant, int $sequence): void
	{
		$db = $this->getAdapter(); $db->beginTransaction();
		try {
			$event = $this->ownedEvent($id, 'edit', true); $this->checkSequence($event, $sequence);
			if ($event['cancelled']) throw new RuntimeException('CALENDAR_CANCELLED_LOCKED', 409);
			if (!$db->update('calendarparticipant', ['removed' => 1, 'modified' => gmdate('Y-m-d H:i:s')], array_merge($this->participantWhere($id, $participant), ['removed = 0']))) throw new RuntimeException('CALENDAR_EVENT_CHANGED', 409);
			$this->touchSequence($event); $db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
	}

	public function respond(int $id, string $status, int $sequence, int $external = 0): void
	{
		$this->authorize('view');
		if (!in_array($status, ['invited', 'accepted', 'declined'], true)) throw new InvalidArgumentException('CALENDAR_PARTICIPANT_INVALID');
		$db = $this->getAdapter(); $db->beginTransaction();
		try {
			$event = $external ? $this->ownedEvent($id, 'edit', true) : $db->fetchRow($this->select()->where('id = ?', $id)->where('clientid = ?', $this->clientid)->where('deleted = ?', 0)->forUpdate());
			if (!$event) throw new RuntimeException('CALENDAR_NOT_FOUND', 404);
			$this->checkSequence($event, $sequence);
			if ($event['cancelled']) throw new RuntimeException('CALENDAR_CANCELLED_LOCKED', 409);
			$select = $db->select()->from('calendarparticipant')->where('eventid = ?', $id)->where('clientid = ?', $this->clientid)->where('removed = ?', 0);
			if ($external) $select->where('id = ?', $external)->where('userid IS NULL');
			else $select->where('userid = ?', $this->actor);
			$row = $db->fetchRow($select);
			if (!$row) throw new RuntimeException('CALENDAR_DENIED', 403);
			$db->update('calendarparticipant', ['status' => $status, 'modified' => gmdate('Y-m-d H:i:s')], $this->participantWhere($id, (int)$row['id']));
			$db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
	}

	public function cancelEvent(int $id, int $sequence): void
	{
		$db = $this->getAdapter(); $db->beginTransaction();
		try {
			$event = $this->ownedEvent($id, 'edit', true); $this->checkSequence($event, $sequence);
			if (!$event['cancelled']) $this->touchSequence($event, ['cancelled' => 1]);
			$db->commit();
		} catch (Exception $e) { $db->rollBack(); throw $e; }
	}

	private function ownedWhere(int $id): array
	{
		$db = $this->getAdapter();
		return [$db->quoteInto('id = ?', $id), $db->quoteInto('clientid = ?', $this->clientid), $db->quoteInto('ownerid = ?', $this->actor), 'deleted = 0'];
	}
}
