<?php

class DEEC_Sns
{
	public function verify(array $message, string $expectedTopicArn): bool
	{
		if(($message['TopicArn'] ?? '') !== $expectedTopicArn) return false;

		$version = (string)($message['SignatureVersion'] ?? '');
		if(!in_array($version, ['1', '2'], true)) return false;

		$certUrl = trim((string)($message['SigningCertURL'] ?? ''));
		if(!$this->isValidSnsUrl($certUrl, $expectedTopicArn, true)) return false;

		$signature = base64_decode((string)($message['Signature'] ?? ''), true);
		if($signature === false || $signature === '') return false;

		$certificate = $this->loadUrl($certUrl);
		if($certificate === false) return false;

		$publicKey = openssl_pkey_get_public($certificate);
		if(!$publicKey) return false;

		$string = $this->buildStringToSign($message);
		if($string === null) return false;

		$algorithm = $version === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

		return openssl_verify($string, $signature, $publicKey, $algorithm) === 1;
	}

	public function confirm(array $message, string $expectedTopicArn): bool
	{
		if(($message['Type'] ?? '') !== 'SubscriptionConfirmation') return false;

		$url = trim((string)($message['SubscribeURL'] ?? ''));
		if(!$this->isValidSnsUrl($url, $expectedTopicArn, false)) return false;

		$query = [];
		parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

		if(($query['Action'] ?? '') !== 'ConfirmSubscription') return false;
		if(($query['TopicArn'] ?? '') !== $expectedTopicArn) return false;

		return $this->loadUrl($url) !== false;
	}

	private function buildStringToSign(array $message): ?string
	{
		$type = $message['Type'] ?? '';

		if($type === 'Notification') {
			$fields = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
		} elseif($type === 'SubscriptionConfirmation' || $type === 'UnsubscribeConfirmation') {
			$fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
		} else {
			return null;
		}

		$string = '';

		foreach($fields as $field) {
			if($field === 'Subject' && !array_key_exists($field, $message)) continue;
			if(!array_key_exists($field, $message)) return null;

			$string .= $field."\n".$message[$field]."\n";
		}

		return $string;
	}

	private function isValidSnsUrl(string $url, string $topicArn, bool $certificate): bool
	{
		$parts = explode(':', $topicArn);
		if(count($parts) < 6 || $parts[2] !== 'sns' || empty($parts[3])) return false;

		$parsed = parse_url($url);
		if(!$parsed || ($parsed['scheme'] ?? '') !== 'https') return false;

		$expectedHost = 'sns.'.$parts[3].'.amazonaws.com';
		if(strtolower((string)($parsed['host'] ?? '')) !== $expectedHost) return false;

		if(isset($parsed['user']) || isset($parsed['pass'])) return false;
		if(isset($parsed['port']) && (int)$parsed['port'] !== 443) return false;

		if($certificate) {
			return preg_match('#^/SimpleNotificationService-[A-Za-z0-9_-]+\.pem$#', (string)($parsed['path'] ?? '')) === 1;
		}

		return true;
	}

	private function loadUrl(string $url)
	{
		$context = stream_context_create([
			'http' => [
				'timeout' => 5,
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true,
			],
		]);

		return @file_get_contents($url, false, $context);
	}
}
