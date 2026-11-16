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
		$visible = '(e.ownerid = ' . $db->quote($this->actor) . " OR e.visibility = 'team')";
		$columns = ['id', 'ownerid', 'allday', 'start', 'end', 'startutc', 'endutc', 'timezone', 'visibility'];
		$columns['reminderminutes'] = new Zend_Db_Expr('CASE WHEN e.ownerid = ' . $db->quote($this->actor) . ' THEN e.reminderminutes ELSE NULL END');
		foreach (['title', 'description', 'location'] as $field) $columns[$field] = new Zend_Db_Expr('CASE WHEN ' . $visible . ' THEN e.' . $field . ' ELSE NULL END');
		return $db->select()->from(['e' => $this->_name], $columns)
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

	public function events(array $period, int $ownerid = 0): array
	{
		$this->authorize('view');
		if ($ownerid && !array_key_exists($ownerid, $this->members())) throw new InvalidArgumentException('CALENDAR_INVALID_MEMBER');
		$db = $this->getAdapter();
		$timed = $db->quoteInto('e.startutc < ?', $period['end']->format('Y-m-d H:i:s')) . ' AND ' . $db->quoteInto('e.endutc > ?', $period['start']->format('Y-m-d H:i:s'));
		$allDay = $db->quoteInto('e.start < ?', $period['until'] . ' 00:00:00') . ' AND ' . $db->quoteInto('e.end > ?', $period['first'] . ' 00:00:00');
		$select = $this->readSelect()->where('(e.allday = 1 AND (' . $allDay . ')) OR (e.allday = 0 AND ((' . $timed . ') OR e.startutc IS NULL))');
		if ($ownerid) $select->where('e.ownerid = ?', $ownerid);
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
			return (int)$this->insert($data + ['reminderdue' => $this->reminderDue($data), 'reminderrevision' => 1, 'clientid' => $this->clientid, 'ownerid' => $this->actor, 'created' => $now, 'createdby' => $this->actor, 'modified' => $now, 'modifiedby' => $this->actor]);
		}
		$db = $this->getAdapter();
		$db->beginTransaction();
		try {
			$row = $db->fetchRow($this->select()->where('id = ?', $id)->where('clientid = ?', $this->clientid)->where('ownerid = ?', $this->actor)->where('deleted = ?', 0)->forUpdate());
			if (!$row) throw new RuntimeException('CALENDAR_NOT_FOUND', 404);
			if (!array_key_exists('reminderminutes', $data)) $data['reminderminutes'] = $row['reminderminutes'];
			$changed = false;
			foreach (['startutc', 'endutc', 'timezone', 'allday', 'reminderminutes'] as $field) if ((string)$data[$field] !== (string)$row[$field]) $changed = true;
			if ($changed) $data += ['reminderdue' => $this->reminderDue($data), 'reminderdone' => null];
			$data['reminderrevision'] = (int)$row['reminderrevision'] + 1;
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
		return ['reminderminutes IS NOT NULL', 'reminderdone IS NULL', 'startutc IS NOT NULL', $this->getAdapter()->quoteInto('reminderdue <= ?', $now)];
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

	private function ownedWhere(int $id): array
	{
		$db = $this->getAdapter();
		return [$db->quoteInto('id = ?', $id), $db->quoteInto('clientid = ?', $this->clientid), $db->quoteInto('ownerid = ?', $this->actor), 'deleted = 0'];
	}
}
