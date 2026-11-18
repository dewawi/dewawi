<?php

class Sales_Service_DocumentRevisionService
{
	public static function token(string $type, int $id): string
	{
		$session = new Zend_Session_Namespace('DocumentChanges');
		$key = Zend_Registry::get('Client')['id'] . '/' . $type . '/' . $id . '/' . Zend_Registry::get('User')['id'];
		$tokens = $session->tokens ?? [];
		if (!isset($tokens[$key])) { $tokens[$key] = bin2hex(random_bytes(32)); $session->tokens = $tokens; }
		return $tokens[$key];
	}

	public function context(int $id, string $type, string $operation = 'view'): array
	{
		if (!in_array($type, ['quote', 'salesorder'], true)) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		$user = (array)Zend_Registry::get('User'); $client = Zend_Registry::get('Client');
		$permission = new DEEC_Permission($user);
		$context = $permission->getObjectContext($id, 'sales', $type);
		$required = $operation === 'view' ? ['view'] : ($operation === 'create' ? ['view', 'add', 'edit'] : ['view', 'edit']);
		if (!$context || (int)$context['clientid'] !== (int)$client['id'] || (int)$user['clientid'] !== (int)$client['id']) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		foreach ($required as $right) if (!$permission->hasPermission('sales', $type, $right)) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
		if ($operation === 'approve' && empty($user['admin'])) throw new RuntimeException('DOCUMENT_CHANGES_ADMIN_REQUIRED');
		return $context['parent'];
	}

	public function relation(int $id, string $type, int $clientId): ?array
	{
		if (!in_array($type, ['quote', 'salesorder'], true)) return null;
		return (new Application_Model_DbTable_Documentrevision())->getForDocument($id, $type, $clientId);
	}

	public function history(int $id, string $type): array
	{
		$document = $this->context($id, $type); $db = Zend_Db_Table::getDefaultAdapter(); $client = (int)$document['clientid'];
		$relation = $this->relation($id, $type, $client); $root = (int)($relation['rootdocumentid'] ?? $id);
		return $db->fetchAll("SELECT r.*, d.`{$type}id` AS document_number, d.completed, d.completeddate, d.completedby, d.deleted, u.name AS editor FROM documentrevision r JOIN `$type` d ON d.id = r.documentid AND d.clientid = r.clientid LEFT JOIN user u ON u.id = r.createdby WHERE r.clientid = ? AND r.controller = ? AND r.rootdocumentid = ? ORDER BY r.sequence", [$client, $type, $root]);
	}

	public function create(int $id, string $type, string $reason, string $requestKey): int
	{
		$document = $this->context($id, $type, 'create'); $reason = trim($reason);
		if ($reason === '' || mb_strlen($reason) > 2000 || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) throw new RuntimeException('DOCUMENT_CHANGES_REASON_REQUIRED');
		if (!DEEC_Permission::isDocumentReadonly('sales', $type, $document) || empty($document[$type . 'id']) || !empty($document['cancelled'])) throw new RuntimeException('DOCUMENT_CHANGES_FINALIZED_REQUIRED');
		$db = Zend_Db_Table::getDefaultAdapter(); $class = 'Sales_Model_DbTable_' . ucfirst($type); $table = new $class();
		$client = (int)$document['clientid']; $user = Zend_Registry::get('User'); $relation = $this->relation($id, $type, $client); $root = (int)($relation['rootdocumentid'] ?? $id);
		$db->beginTransaction();
		try {
			if (!$table->getByIdForUpdate($root)) throw new RuntimeException('DOCUMENT_CHANGES_ACCESS_DENIED');
			$document = $table->getByIdForUpdate($id);
			$existing = $db->fetchRow('SELECT * FROM documentrevision WHERE clientid = ? AND createdby = ? AND request_key = ?', [$client, $user['id'], $requestKey]);
			if ($existing) {
				if ($existing['controller'] !== $type || (int)$existing['sourcedocumentid'] !== $id || $existing['reason'] !== $reason) throw new RuntimeException('DOCUMENT_CHANGES_STALE');
				$db->commit(); return (int)$existing['documentid'];
			}
			$sequence = 2 + (int)$db->fetchOne('SELECT COUNT(*) FROM documentrevision WHERE clientid = ? AND controller = ? AND rootdocumentid = ?', [$client, $type, $root]);
			$newId = $table->copyById($id);
			$table->updateById($newId, ['title' => $document['title'], $type . 'date' => date('Y-m-d')]);
			$positionsClass = $class . 'pos'; $positions = (new $positionsClass())->getPositions($id);
			Zend_Controller_Action_HelperBroker::getStaticHelper('Position')->copyPositions($positions, $newId, 'sales', $type, date('Y-m-d H:i:s'));
			$db->insert('documentrevision', ['clientid' => $client, 'controller' => $type, 'rootdocumentid' => $root, 'sourcedocumentid' => $id, 'documentid' => $newId, 'sequence' => $sequence, 'reason' => $reason, 'created' => date('Y-m-d H:i:s'), 'createdby' => (int)$user['id'], 'request_key' => $requestKey]);
			$db->commit(); return $newId;
		} catch (Throwable $error) { $db->rollBack(); throw $error; }
	}
}
