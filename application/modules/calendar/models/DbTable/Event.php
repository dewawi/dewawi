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
			return (int)$this->insert($data + ['clientid' => $this->clientid, 'ownerid' => $this->actor, 'created' => $now, 'createdby' => $this->actor, 'modified' => $now, 'modifiedby' => $this->actor]);
		}
		$this->update($data + ['modified' => $now, 'modifiedby' => $this->actor], $this->ownedWhere($id));
		return $id;
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
