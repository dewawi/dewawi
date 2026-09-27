<?php

class DEEC_Feedback
{
	public const TYPE_QUOTE = 'quote';
	private const EMAIL_MARKER_START = '<!-- DEWAWI_FEEDBACK_START -->';
	private const EMAIL_MARKER_END = '<!-- DEWAWI_FEEDBACK_END -->';

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

	public function buildQuoteFollowupEmail(int $quoteId, string $locale = ''): array
	{
		$translator = $this->getTranslator($locale);

		return [
			'subject' => $translator->t('FEEDBACK_EMAIL_QUOTE_SUBJECT', [$quoteId]),
			'body' => $translator->t('FEEDBACK_EMAIL_QUOTE_FOLLOWUP', [$quoteId]),
		];
	}

	public function buildEmailBlock(array $feedback, string $baseUrl, string $locale = ''): string
	{
		if(empty($feedback['token']) || empty($feedback['type'])) return '';

		$config = $this->getTypeConfig((string)$feedback['type']);
		if(!$config) return '';

		$translator = $this->getTranslator($locale);
		$feedbackUrl = rtrim($baseUrl, '/') . '/feedback/index/token/' . rawurlencode((string)$feedback['token']);

		$buttons = '';
		foreach($config['statuses'] as $status => $labelKey) {
			$url = $feedbackUrl . '/status/' . rawurlencode($status);
			$buttons .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 14px;border:1px solid #ccc;border-radius:4px;color:#222;text-decoration:none;background:#f5f5f5;">' . htmlspecialchars($translator->t($labelKey), ENT_QUOTES, 'UTF-8') . '</a>';
		}

		$url = htmlspecialchars($feedbackUrl, ENT_QUOTES, 'UTF-8');

		return self::EMAIL_MARKER_START
			. '<div style="margin:24px 0;padding:18px;border:1px solid #ddd;border-radius:6px;">'
			. '<p style="margin:0 0 8px;"><strong>' . htmlspecialchars($translator->t('FEEDBACK_EMAIL_QUOTE_TITLE'), ENT_QUOTES, 'UTF-8') . '</strong></p>'
			. '<p style="margin:0 0 14px;">' . htmlspecialchars($translator->t('FEEDBACK_EMAIL_QUOTE_TEXT'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<div>' . $buttons . '</div>'
			. '<p style="margin:8px 0 0;font-size:12px;"><a href="' . $url . '">' . htmlspecialchars($translator->t('FEEDBACK_EMAIL_OPEN'), ENT_QUOTES, 'UTF-8') . '</a><br>' . $url . '</p>'
			. '</div>'
			. self::EMAIL_MARKER_END;
	}

	public function removeEmailBlock(string $body): string
	{
		$result = preg_replace('/\s*<!-- DEWAWI_FEEDBACK_START -->.*?<!-- DEWAWI_FEEDBACK_END -->\s*/s', '', $body);
		return $result !== null ? $result : $body;
	}

	public function insertEmailBlock(string $body, string $block): string
	{
		if($block === '') return $body;

		$body = $this->removeEmailBlock($body);

		if(strpos($body, '[SIGNATURE]') !== false) {
			return str_replace('[SIGNATURE]', $block . '<br><br>[SIGNATURE]', $body);
		}

		return rtrim($body) . '<br><br>' . $block;
	}

	public function deleteRequest(array $feedback): void
	{
		if(empty($feedback['id']) || empty($feedback['clientid'])) return;

		$db = new Application_Model_DbTable_Feedback();
		$db->setClientId((int)$feedback['clientid']);
		$db->deleteById((int)$feedback['id']);
	}

	private function getTranslator(string $locale): DEEC_Translate
	{
		$locale = trim($locale);

		if($locale !== '' && is_dir(BASE_PATH . '/languages/' . $locale . '/default')) {
			$translator = new DEEC_Translate($locale);
			$translator->loadDir('default', BASE_PATH . '/languages/' . $locale . '/default');
			return $translator;
		}

		return Zend_Registry::get('DEEC_Translate');
	}
}
