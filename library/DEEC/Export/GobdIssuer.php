<?php

class DEEC_Export_GobdIssuer
{
	private array $decisions = [];
	public string $identitySha256;
	public ?string $reviewSha256 = null;

	public static function identity(array $client): array
	{
		$identity = [];
		foreach (['id', 'company', 'address', 'postcode', 'city', 'country'] as $field) $identity[$field] = (string)($client[$field] ?? '');
		return $identity;
	}

	public static function identityHash(array $client): string
	{
		return hash('sha256', json_encode(self::identity($client), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}

	public function __construct(array $client, ?string $path = null)
	{
		$this->identitySha256 = self::identityHash($client);
		$directory = getenv('DEWAWI_GOBD_ISSUER_REVIEW_DIR') ?: null;
		if ($path === null && $directory !== null) {
			DEEC_DocumentReview::privateDirectory($directory);
			$path = $directory . '/' . (int)$client['id'] . '.json';
			if (!file_exists($path) && !is_link($path)) return;
		}
		if ($path === null) return;
		DEEC_DocumentReview::privateFile($path);
		$parent = dirname($path);
		DEEC_DocumentReview::privateDirectory($parent);
		if (filesize($path) > 134217728) throw new RuntimeException('Issuer review exceeds its size limit');
		$bytes = file_get_contents($path);
		$record = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
		if (($record['schema_version'] ?? null) !== 1 || ($record['clientid'] ?? null) !== (int)$client['id'] || ($record['tenant_identity_sha256'] ?? null) !== $this->identitySha256 || !is_array($record['decisions'] ?? null)) throw new RuntimeException('Issuer review tenant or identity differs');
		$this->reviewSha256 = hash('sha256', $bytes);
		foreach ($record['decisions'] as $decision) {
			$identity = $decision['identity'] ?? [];
			if (($identity['clientid'] ?? null) !== (int)$client['id'] || !DEEC_DocumentArchive::validDocumentType($identity['module'] ?? '', $identity['controller'] ?? '') || !is_int($identity['id'] ?? null) || $identity['id'] <= 0 || !is_string($identity['document_number'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $decision['pdf_sha256'] ?? '')) throw new RuntimeException('Invalid issuer review identity');
			$key = $identity['module'] . '/' . $identity['controller'] . '/' . $identity['id'] . '/' . $decision['pdf_sha256'];
			if (isset($this->decisions[$key])) throw new RuntimeException('Ambiguous issuer review decision');
			if (!in_array($decision['decision'] ?? '', ['confirmed_same_issuer', 'confirmed_foreign_issuer', 'unresolved'], true)) throw new RuntimeException('Invalid issuer review decision');
			if ($decision['decision'] !== 'unresolved') {
				foreach (['reviewed_by', 'reviewed_at', 'basis', 'actual_issuer'] as $field) if (!is_string($decision[$field] ?? null) || trim($decision[$field]) === '') throw new RuntimeException('Issuer decision requires actual reviewer, time, basis and issuer');
				$time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $decision['reviewed_at']);
				if (!$time || $time->format('Y-m-d\TH:i:sP') !== $decision['reviewed_at'] || $time->getTimestamp() > time() || ($decision['full_document_reviewed'] ?? false) !== true || ($decision['issuer_compared_with_tenant'] ?? false) !== true) throw new RuntimeException('Issuer comparison is not explicitly attested');
			}
			$this->decisions[$key] = $decision;
		}
	}

	public function check(array $identity, string $sha): array
	{
		$key = $identity['module'] . '/' . $identity['controller'] . '/' . $identity['id'] . '/' . $sha;
		$decision = $this->decisions[$key] ?? null;
		$matches = $decision && count($decision['identity']) === count($identity);
		foreach ($identity as $field => $value) if (($decision['identity'][$field] ?? null) !== $value) $matches = false;
		if (!$matches || $decision['decision'] === 'unresolved') return ['status' => 'review_required', 'reason' => 'issuer_review_required'];
		return ['status' => $decision['decision'] === 'confirmed_same_issuer' ? 'manually_verified' : 'conflict', 'reason' => $decision['decision'] === 'confirmed_same_issuer' ? '' : 'issuer_conflict', 'evidence' => $decision, 'review_sha256' => $this->reviewSha256];
	}
}
