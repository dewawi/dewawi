<?php

class DEEC_Feedback
{
	public const TYPE_QUOTE = 'quote';

	public function createRequest(string $module, string $controller, int $parentId, int $clientId, string $type, array $data = []): array
	{
		if(trim($module) === '' || trim($controller) === '' || $parentId <= 0 || $clientId <= 0 || !$this->getTypeConfig($type)) throw new InvalidArgumentException('Invalid feedback request');

		$db = new Application_Model_DbTable_Feedback();
		$db->setClientId($clientId);

		$id = $db->create([
			'module' => $module,
			'controller' => $controller,
			'parentid' => $parentId,
			'emailmessageid' => !empty($data['emailmessageid']) ? (int)$data['emailmessageid'] : null,
			'contactid' => !empty($data['contactid']) ? (int)$data['contactid'] : null,
			'type' => $type,
			'token' => bin2hex(random_bytes(32)),
		]);

		return $db->getById($id);
	}

	public function attachEmailMessage(array $feedback, int $emailMessageId): void
	{
		if(empty($feedback['id']) || empty($feedback['clientid']) || $emailMessageId <= 0) return;

		$db = new Application_Model_DbTable_Feedback();
		$db->setClientId((int)$feedback['clientid']);
		$db->updateById((int)$feedback['id'], ['emailmessageid' => $emailMessageId]);
	}

	public function getPublic(string $token): ?array
	{
		$db = new Application_Model_DbTable_Feedback();
		$feedback = $db->getByToken($token);
		if(!$feedback) return null;

		$db->setClientId((int)$feedback['clientid']);
		return $db->getById((int)$feedback['id']);
	}

	public function submit(string $token, string $status, ?string $reason = null, ?string $message = null): ?array
	{
		$feedback = $this->getPublic($token);
		if(!$feedback) return null;
		if(!empty($feedback['responded'])) return $feedback;

		$config = $this->getTypeConfig((string)$feedback['type']);
		if(!isset($config['statuses'][$status])) throw new InvalidArgumentException('Invalid feedback status');

		$reason = trim((string)$reason);
		if($status !== 'declined') $reason = '';
		if($reason !== '' && !isset($config['reasons'][$reason])) throw new InvalidArgumentException('Invalid feedback reason');

		$message = trim((string)$message);
		if(mb_strlen($message) > 5000) $message = mb_substr($message, 0, 5000);

		$db = new Application_Model_DbTable_Feedback();
		$db->setClientId((int)$feedback['clientid']);
		$db->updateById((int)$feedback['id'], [
			'status' => $status,
			'reason' => $reason !== '' ? $reason : null,
			'message' => $message !== '' ? $message : null,
			'responded' => date('Y-m-d H:i:s'),
		]);

		return $db->getById((int)$feedback['id']);
	}

	public function getTypeConfig(string $type): array
	{
		if($type !== self::TYPE_QUOTE) return [];

		return [
			'statuses' => [
				'interested' => 'FEEDBACK_QUOTE_STATUS_INTERESTED',
				'question' => 'FEEDBACK_QUOTE_STATUS_QUESTION',
				'pending' => 'FEEDBACK_QUOTE_STATUS_PENDING',
				'declined' => 'FEEDBACK_QUOTE_STATUS_DECLINED',
			],
			'reasons' => [
				'technical' => 'FEEDBACK_QUOTE_REASON_TECHNICAL',
				'price' => 'FEEDBACK_QUOTE_REASON_PRICE',
				'project_cancelled' => 'FEEDBACK_QUOTE_REASON_PROJECT_CANCELLED',
				'awarded_elsewhere' => 'FEEDBACK_QUOTE_REASON_AWARDED_ELSEWHERE',
				'delivery_time' => 'FEEDBACK_QUOTE_REASON_DELIVERY_TIME',
				'other' => 'FEEDBACK_QUOTE_REASON_OTHER',
			],
		];
	}
}
