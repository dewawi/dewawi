<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/tests/documents/document-recovery.php';
set_include_path(dirname(__DIR__) . '/library' . PATH_SEPARATOR . get_include_path());
spl_autoload_register(static function(string $class): void { if ($path = stream_resolve_include_path(str_replace('_', '/', $class) . '.php')) require_once $path; });

function assessmentRows(string $path): Generator
{
	DEEC_DocumentReview::privateFile($path);
	$stream = fopen($path, 'rb');
	try {
		while (($line = fgets($stream, 8388609)) !== false) {
			if (strlen($line) >= 8388608) throw new RuntimeException('Inventory line exceeds the supported size');
			if (trim($line) === '') continue;
			$row = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
			if (!is_array($row)) throw new RuntimeException('Invalid inventory record');
			yield $row;
		}
		if (!feof($stream)) throw new RuntimeException('Inventory read failed');
	} finally { fclose($stream); }
}

function assessmentInventory(string $path, int $clientId): array
{
	DEEC_DocumentReview::privateDirectory(dirname($path));
	DEEC_DocumentReview::privateFile($path);
	$before = hash_file('sha256', $path);
	$seen = []; $counts = ['blocked' => 0, 'ready' => 0, 'complete' => 0]; $types = []; $summary = null;
	foreach (assessmentRows($path) as $row) {
		if ($summary !== null) throw new RuntimeException('Records after inventory summary');
		if (($row['kind'] ?? '') === 'summary') {
			if (!is_array($row['summary'] ?? null)) throw new RuntimeException('Invalid inventory summary');
			$summary = $row['summary']; continue;
		}
		if (($row['kind'] ?? '') !== 'document') {
			if (!in_array($row['kind'] ?? '', ['structured_original', 'unassigned_file', 'excluded_document'], true)) throw new RuntimeException('Unknown inventory record');
			continue;
		}
		$plan = $row['plan'] ?? [];
		if (reviewId($plan['clientid'] ?? null) !== $clientId || !is_string($plan['module'] ?? null) || !is_string($plan['controller'] ?? null)
			|| !array_key_exists('document_number', $plan) || (!is_string($plan['document_number']) && !is_int($plan['document_number']))
			|| ($plan['format'] ?? '') !== 'pdf' || !isset($counts[$plan['status'] ?? '']) || !is_string($plan['reason'] ?? null)
			|| ($plan['status'] === 'blocked' && $plan['reason'] === '')) throw new RuntimeException('Invalid inventory document');
		reviewId($plan['id'] ?? null);
		$key = reviewKey($plan);
		if (!is_array($plan['files'] ?? [])) throw new RuntimeException('Invalid inventory file list');
		foreach (($plan['files'] ?? []) as $file) if (!is_array($file) || (isset($file['path']) && !is_string($file['path'])) || (isset($file['sha256']) && !is_string($file['sha256']))) throw new RuntimeException('Invalid inventory file record');
		if (isset($seen[$key])) throw new RuntimeException('Duplicate inventory document');
		$seen[$key] = true; $counts[$plan['status']]++;
		if ($plan['status'] === 'blocked') $types[$plan['module'] . '/' . $plan['controller']] = [$plan['module'], $plan['controller']];
	}
	if (!is_array($summary) || ($summary['mode'] ?? '') !== 'dry-run' || reviewId($summary['clientid'] ?? null) !== $clientId
		|| ($summary['unassigned_scan'] ?? '') !== 'complete' || ($summary['documents'] ?? -1) !== array_sum($counts)) throw new RuntimeException('A complete tenant dry-run inventory is required');
	foreach ($counts as $status => $count) if (isset($summary[$status]) && $summary[$status] !== $count) throw new RuntimeException('Inventory totals differ');
	if (!hash_equals($before, hash_file('sha256', $path))) throw new RuntimeException('Inventory changed during validation');
	return ['path' => $path, 'sha256' => $before, 'summary' => $summary, 'counts' => $counts, 'blocked_types' => array_values($types)];
}

function assessmentExtract(string $path, string $binary, ?string $sha = null): array
{
	if (!str_starts_with($binary, '/') || !is_file($binary) || !is_executable($binary)) return ['status' => 'unavailable', 'unresolved' => ['PDF text extractor unavailable']];
	reviewSafePath($path);
	$stat = @lstat($path); $file = @fopen($path, 'rb');
	if (!$file) return ['status' => 'unreadable', 'unresolved' => ['Candidate could not be opened']];
	$opened = fstat($file); $hash = hash_init('sha256'); hash_update_stream($hash, $file); rewind($file);
	if (!$stat || $stat['ino'] !== $opened['ino'] || $stat['dev'] !== $opened['dev'] || ($opened['mode'] & 0170000) !== 0100000 || ($sha !== null && !hash_equals($sha, hash_final($hash)))) {
		fclose($file); return ['status' => 'file_changed_during_extraction', 'unresolved' => ['Candidate changed before extraction']];
	}
	// Pass the verified open file, so a path replacement cannot redirect extraction.
	$process = proc_open([$binary, '-layout', '-enc', 'UTF-8', '-nopgbrk', '/proc/self/fd/3', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => $file], $pipes, null, null, ['bypass_shell' => true]);
	if (!is_resource($process)) { fclose($file); return ['status' => 'unavailable', 'unresolved' => ['PDF text extractor could not start']]; }
	fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
	$text = ''; $error = ''; $deadline = microtime(true) + 10; $status = 'ok'; $exit = -1;
	try {
		do {
			$text .= stream_get_contents($pipes[1], max(1, 1048577 - strlen($text)));
			$error .= stream_get_contents($pipes[2], max(1, 8193 - strlen($error)));
			$state = proc_get_status($process);
			if (strlen($text) > 1048576 || strlen($error) > 8192) { $status = 'output_limit'; break; }
			if (!$state['running']) { $exit = $state['exitcode']; break; }
			if (microtime(true) >= $deadline) { $status = 'timeout'; break; }
			usleep(10000);
		} while (true);
		if ($status !== 'ok') proc_terminate($process, 9);
		else { $text .= stream_get_contents($pipes[1], max(1, 1048577 - strlen($text))); $error .= stream_get_contents($pipes[2], max(1, 8193 - strlen($error))); }
	} finally { fclose($pipes[1]); fclose($pipes[2]); $closed = proc_close($process); fclose($file); }
	if ($status === 'ok' && (strlen($text) > 1048576 || strlen($error) > 8192)) $status = 'output_limit';
	if ($status === 'ok' && ($exit < 0 ? $closed : $exit) !== 0) $status = 'failed';
	if ($status === 'ok' && trim($text) === '') $status = 'empty_text';
	if (!preg_match('//u', $text)) $status = 'invalid_text_encoding';
	return $status === 'ok' ? ['status' => 'ok', 'text' => $text, 'diagnostic' => trim($error)] : ['status' => $status, 'diagnostic' => mb_convert_encoding(substr($error, 0, 8192), 'UTF-8', 'UTF-8'), 'unresolved' => ['PDF text unavailable or incomplete; visual review required']];
}

function assessmentDate($value): ?string
{
	if (!is_string($value)) return null;
	foreach (['Y-m-d', 'd.m.Y'] as $format) {
		$date = DateTimeImmutable::createFromFormat('!' . $format, $value);
		if ($date && $date->format($format) === $value) return $date->format('Y-m-d');
	}
	return null;
}

function assessmentFacts(string $text, array $identity, array $basis): array
{
	$types = ['invoice' => 'Rechnung|Invoice', 'quote' => 'Angebot|Quote|Quotation', 'salesorder' => 'Auftragsbestätigung|Sales order', 'deliveryorder' => 'Lieferschein|Delivery note', 'creditnote' => 'Gutschrift|Credit note', 'reminder' => 'Mahnung|Reminder', 'quoterequest' => 'Angebotsanfrage|Request for quotation', 'purchaseorder' => 'Bestellung|Purchase order'];
	$found = []; foreach ($types as $type => $pattern) if (preg_match('/\b(?:' . $pattern . ')\b/iu', $text)) $found[] = $type;
	preg_match_all('/(?:Rechnungsnummer|Angebotsnummer|Belegnummer|Mahnungsnummer|(?:Invoice|Quote|Quotation|Order|Credit note|Reminder|Document)\s*(?:number|no\.?|ID))\s*[:#]?\s*([\pL\pN][\pL\pN\/_.-]*)/iu', $text, $numbers);
	preg_match_all('/(?:Rechnungsdatum|Angebotsdatum|Belegdatum|Ausstellungsdatum|(?:Invoice|Quote|Issue|Document|Order)\s*date)\s*:?\s*(\d{4}-\d{2}-\d{2}|\d{2}[.\/]\d{2}[.\/]\d{4})/iu', $text, $dates);
	$values = static fn(array $items): array => array_slice(array_values(array_unique($items)), 0, 50);
	$numbers = $values($numbers[1]); $rawDates = $values($dates[1]); $dates = $values(array_filter(array_map('assessmentDate', $rawDates)));
	$parties = [];
	foreach (['issuer' => 'Aussteller|Rechnungssteller|Issuer|Seller', 'recipient' => 'Rechnungsempfänger|Empfänger|Recipient|Bill to|Customer'] as $field => $label) {
		preg_match_all('/(?:^|\n)\h*(?:' . $label . ')\h*:\h*([^\r\n]+)/iu', $text, $matches);
		$parties[$field] = $values(array_map('trim', $matches[1]));
	}
	$amounts = [];
	foreach (['subtotal' => 'Zwischensumme|Netto|Subtotal', 'taxes' => 'MwSt\.?|USt\.?|VAT|Taxes?', 'total' => 'Gesamtbetrag|Endbetrag|Total|Gesamt'] as $field => $label) {
		preg_match_all('/(?:^|\n)\h*(?:' . $label . ')\h*:?\h*(?:(?:EUR|USD|GBP|€|£|\$)\h*)?(?![-+\d.,\x{00a0} ]*%)([-+]?\d[\d.,\x{00a0} ]*)/iu', $text, $matches);
		$amounts[$field] = $values(array_map('trim', $matches[1]));
	}
	$compare = [];
	foreach (['number' => [$numbers, (string)$identity['document_number']], 'date' => [$dates, assessmentDate($basis['document_date'] ?? null)], 'issuer' => [$parties['issuer'], $basis['issuer'] ?? null], 'recipient' => [$parties['recipient'], $basis['recipient'] ?? null]] as $field => [$detected, $expected]) {
		$compare[$field] = ['expected' => $expected, 'detected' => $detected, 'status' => count($detected) !== 1 || $expected === null || $expected === '' ? 'unresolved' : (mb_strtolower($detected[0]) === mb_strtolower($expected) ? 'text_matches' : 'text_differs')];
	}
	$compare['type'] = ['expected' => $identity['controller'], 'detected' => $found, 'status' => count($found) !== 1 ? 'unresolved' : ($found[0] === $identity['controller'] ? 'text_matches' : 'text_differs')];
	foreach ($amounts as $field => $detected) {
		$expected = $basis[$field] ?? null; $parsed = null;
		if (count($detected) === 1) {
			$raw = $detected[0];
			if (preg_match('/^[-+]?\d+$/D', $raw)) $parsed = $raw;
			elseif (preg_match('/^[-+]?(?:\d+[.,]\d{2}|\d{1,3}(?:,\d{3})+\.\d{2}|\d{1,3}(?:\.\d{3})+,\d{2}|\d{1,3}(?:[\h\x{00a0}]\d{3})+[.,]\d{2})$/uD', $raw)) {
				$raw = preg_replace('/[\h\x{00a0}]/u', '', $raw); $parsed = str_replace([',', '.'], '', substr($raw, 0, -3)) . '.' . substr($raw, -2);
			}
		}
		$compare[$field] = ['expected_stored' => $expected, 'detected' => $detected, 'parsed_decimal' => $parsed, 'status' => $parsed === null || !is_numeric($expected) ? 'unresolved' : (number_format((float)$parsed, 2, '.', '') === number_format((float)$expected, 2, '.', '') ? 'text_matches_at_two_decimals' : 'text_differs')];
	}
	return ['comparisons' => $compare, 'unparsed_date_values' => array_values(array_filter($rawDates, static fn(string $date): bool => assessmentDate($date) === null)), 'non_original_markers' => (bool)preg_match('/MUSTER\s*[–-]\s*NICHT AUSGESTELLT|KOPIE\s*[–-]\s*REKONSTRUKTION|SAMPLE\s*[–-]\s*NOT ISSUED|COPY\s*[–-]\s*RECONSTRUCTION/iu', $text), 'comparison_is_authenticity_proof' => false];
}

function assessmentDifference(string $reference, string $text): array
{
	$lines = static fn(string $value): array => array_values(array_filter(array_map(static fn(string $line): string => trim(preg_replace('/\h+/u', ' ', $line)), preg_split('/\R/u', $value))));
	$before = $lines($reference); $after = $lines($text);
	$removed = array_values(array_diff($before, $after)); $added = array_values(array_diff($after, $before));
	return ['normalized_text_equal' => $before === $after, 'removed_lines' => array_map(static fn(string $line): string => mb_substr($line, 0, 300), array_slice($removed, 0, 20)), 'added_lines' => array_map(static fn(string $line): string => mb_substr($line, 0, 300), array_slice($added, 0, 20)), 'removed_count' => count($removed), 'added_count' => count($added), 'line_order_changed' => $before !== $after && !$removed && !$added];
}

function assessmentScope(string $base, array $plan, array $source, ?array $context): string
{
	$path = $source['path']; reviewSafePath($path);
	if (!DEEC_DocumentArchive::validDocumentType($plan['module'], $plan['controller'])) throw new RuntimeException('Invalid document type');
	$archive = new DEEC_DocumentArchive($base);
	if (!empty($source['status']) && $source['status'] !== 'message_document_reference_missing') throw new RuntimeException($source['status']);
	if ($path === $archive->path((int)$plan['clientid'], $plan['module'], $plan['controller'], (int)$plan['id'])) return 'canonical_internal_document_id';
	if (!DEEC_Emailattachment::validFilename(basename($path)) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') throw new RuntimeException('Invalid PDF filename');
	if (dirname($path) === DEEC_Emailattachment::getDirectory($plan['module'], $plan['controller'], (int)$plan['id'], (int)$plan['clientid'])) return 'document_attachment_directory';
	$directory = new DEEC_Directory();
	if ($source['source'] === 'emailmessageattachment' && $source['origin'] === 'database') {
		$evidence = $source['evidence']; $messageId = reviewId($evidence['emailmessageid'] ?? null); $order = reviewId($evidence['ordering'] ?? null);
		$expected = $base . '/files/attachments/emailmessage/' . $directory->getShortUrl((string)$plan['clientid']) . '/' . intdiv($messageId, 1000) . '/' . $messageId . '/' . $order . '.pdf';
		if ($path !== $expected || (isset($evidence['clientid']) && (int)$evidence['clientid'] !== (int)$plan['clientid'])) throw new RuntimeException('Email attachment path or tenant differs');
		return $source['status'] === 'message_document_reference_missing' ? 'message_bound_to_document_but_attachment_reference_unresolved' : 'database_message_and_attachment_document_references';
	}
	if (in_array($source['source'], ['current', 'legacy'], true)) {
		$prefix = $base . '/files/contacts/' . $directory->getShortUrl((string)$plan['clientid']) . '/';
		$relative = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : '';
		if (!preg_match('~^[0-9]/[0-9]/([1-9][0-9]*)/[^/]+$~D', $relative, $match) || $path !== $base . '/files/contacts/' . $directory->getUrl($match[1], (string)$plan['clientid']) . '/' . basename($path)) throw new RuntimeException('Contact candidate is outside tenant scope');
		$filename = $source['evidence']['emailattachment']['filename'] ?? $source['evidence']['document_filename'] ?? $plan['filename'] ?? null;
		if ($filename !== basename($path)) throw new RuntimeException('Contact filename association differs');
		if ($context !== null) {
			$document = $context['document'];
			if (($document['contact_match_count'] ?? 0) !== 1 || !in_array((int)$match[1], [(int)$document['contactid'], (int)$document['contact_record_id']], true)) throw new RuntimeException('Contact mapping is missing or ambiguous');
			if (!array_filter($context['sources'], static fn(array $reference): bool => $reference['path'] === $path && $reference['source'] === $source['source'] && $reference['status'] === null)) throw new RuntimeException('Contact association is not independently scoped by current document metadata');
			return 'scoped_contact_path_not_independent_document_proof';
		}
		return 'inventory_contact_association_unverified_without_database';
	}
	throw new RuntimeException('Candidate lacks a tenant and document scoped association');
}

function assessmentDocument(array $plan, string $base, ?callable $load, string $extractor, string $recentSince, int $limit = 200): array
{
	$identity = ['module' => $plan['module'], 'controller' => $plan['controller'], 'id' => (int)$plan['id'], 'clientid' => (int)$plan['clientid'], 'document_number' => (string)$plan['document_number']];
	$valid = DEEC_DocumentArchive::validDocumentType($plan['module'], $plan['controller']);
	$context = $valid && $load ? $load($identity) : null;
	$readAllowed = $valid && ($load === null || ($context && (string)$context['document']['document_number'] === $identity['document_number'] && (int)$context['document']['clientid'] === $identity['clientid']));
	$sources = [];
	foreach (($plan['files'] ?? []) as $label => $file) {
		if (!is_array($file)) continue;
		$sources[] = ['source' => (string)$label, 'path' => $file['path'] ?? null, 'status' => null, 'origin' => 'inventory', 'evidence' => array_intersect_key($file, array_flip(['sha256', 'size', 'status']))];
	}
	if ($valid) $sources[] = ['source' => 'canonical', 'path' => (new DEEC_DocumentArchive($base))->path($identity['clientid'], $identity['module'], $identity['controller'], $identity['id']), 'status' => null, 'origin' => 'derived_internal_id', 'evidence' => []];
	foreach (($context['sources'] ?? []) as $source) {
		if ($source['source'] === 'emailmessageattachment' && is_string($source['path']) && !str_starts_with($source['path'], '/')) $source['path'] = $base . '/' . $source['path'];
		$sources[] = $source + ['origin' => 'database'];
	}
	$byPath = []; $pathless = [];
	foreach ($sources as $source) {
		if (!is_string($source['path']) || $source['path'] === '') { $pathless[] = $source; continue; }
		$byPath[$source['path']][] = $source;
	}
	$truncated = false; $discoveryIssues = [];
	if ($readAllowed) {
		try {
			$directory = DEEC_Emailattachment::getDirectory($identity['module'], $identity['controller'], $identity['id'], $identity['clientid']);
			reviewSafePath($directory);
			if (is_dir($directory)) foreach (new DirectoryIterator($directory) as $file) {
				if ($file->isDot() || strtolower($file->getExtension()) !== 'pdf' || isset($byPath[$file->getPathname()])) continue;
				if (count($byPath) >= $limit) { $truncated = true; break; }
				$byPath[$file->getPathname()][] = ['source' => 'additional_document_attachment', 'path' => $file->getPathname(), 'status' => null, 'origin' => 'scoped_discovery', 'evidence' => ['scope' => 'exact_internal_document_attachment_directory', 'filename_is_not_identity_proof' => true]];
			}
		} catch (RuntimeException | InvalidArgumentException $error) { $discoveryIssues[] = $error->getMessage(); }
	}
	$candidates = []; $groups = []; $bad = !$readAllowed || (bool)$discoveryIssues || $plan['reason'] === 'invalid_document_context'; $additional = false; $known = false; $inspected = 0;
	foreach ($byPath as $path => $references) {
		$entry = ['original_path' => $path, 'references' => $references, 'bindings' => [], 'issues' => [], 'observed' => null, 'original_confirmed' => false];
		foreach ($references as $source) {
			try { if (!$readAllowed) throw new RuntimeException('Document identity unavailable, invalid or changed'); $entry['bindings'][] = assessmentScope($base, $plan, $source, $context); }
			catch (RuntimeException | InvalidArgumentException $error) { $entry['issues'][] = $error->getMessage(); }
		}
		if ($entry['bindings'] && $inspected < $limit) {
			$inspected++;
			$entry['observed'] = (new DEEC_DocumentArchive($base))->inspect($path, 'pdf');
			foreach ($references as $source) {
				$recorded = $source['evidence']['emailattachment'] ?? $source['evidence'];
				if (isset($recorded['sha256']) && (!preg_match('/^[a-f0-9]{64}$/D', $recorded['sha256']) || ($entry['observed']['sha256'] !== null && !hash_equals($recorded['sha256'], $entry['observed']['sha256'])))) $entry['issues'][] = 'recorded_sha256_differs';
				$size = $recorded['filesize'] ?? $recorded['size'] ?? null;
				if ($size !== null && $entry['observed']['size'] !== null && (int)$size !== $entry['observed']['size']) $entry['issues'][] = 'recorded_size_differs';
			}
			if ($entry['observed']['status'] === 'ok') {
				$sha = $entry['observed']['sha256']; $groups[$sha]['paths'][] = $path;
				$groups[$sha]['representative'] ??= $entry['observed'];
				$isAdditional = (bool)array_filter($references, static fn(array $source): bool => $source['origin'] === 'scoped_discovery' || ($source['source'] === 'emailmessageattachment' && $source['status'] === 'message_document_reference_missing'));
				$additional = $additional || $isAdditional; $known = $known || !$isAdditional;
			} elseif ($entry['observed']['status'] !== 'missing') $entry['issues'][] = 'file_' . $entry['observed']['status'];
		} elseif ($entry['bindings']) { $entry['issues'][] = 'candidate_limit_reached'; $truncated = true; }
		$entry['bindings'] = array_values(array_unique($entry['bindings'])); $entry['issues'] = array_values(array_unique($entry['issues']));
		$bad = $bad || (bool)$entry['issues'];
		$entry['unresolved_questions'] = array_merge(['Which independently retained original or verified dispatch evidence establishes origin?'], $entry['issues']);
		$entry['proposed_next_action'] = !$entry['bindings'] ? 'Resolve tenant/document association before opening this path.' : (($entry['observed']['status'] ?? '') === 'missing' ? 'Locate independently retained paper, customer archive or documented dispatch records.' : 'Review content and independent origin evidence; do not select by filename, mtime, copy count or matching hashes.');
		$candidates[] = $entry;
	}
	$basis = $context['comparison_basis'] ?? array_intersect_key($plan, array_flip(['document_date', 'issuer', 'recipient', 'subtotal', 'taxes', 'total']));
	$versions = []; $referenceText = null; $referenceSha = null;
	foreach ($groups as $sha => $group) {
		try {
			$extraction = assessmentExtract($group['representative']['path'], $extractor, $sha);
			$after = (new DEEC_DocumentArchive($base))->inspect($group['representative']['path'], 'pdf');
			if ($after['status'] !== 'ok' || $after['sha256'] !== $sha || $after['size'] !== $group['representative']['size']) $extraction = ['status' => 'file_changed_during_extraction', 'unresolved' => ['Candidate changed during extraction; no text is bound to this SHA']];
		} catch (RuntimeException | InvalidArgumentException $error) { $extraction = ['status' => 'file_changed_during_extraction', 'unresolved' => ['Candidate path changed or became unsafe during extraction']]; }
		$version = ['sha256' => $sha, 'paths' => $group['paths'], 'byte_identity_only' => true, 'extraction' => array_diff_key($extraction, ['text' => true]), 'facts' => null, 'difference' => null];
		if ($extraction['status'] === 'ok') {
			$version['extraction'] += ['text_sha256' => hash('sha256', $extraction['text']), 'characters' => mb_strlen($extraction['text']), 'excerpt' => mb_substr($extraction['text'], 0, 1500)];
			$version['facts'] = assessmentFacts($extraction['text'], $identity, $basis);
			if ($referenceText === null) { $referenceText = $extraction['text']; $referenceSha = $sha; }
			else $version['difference'] = ['comparison_reference_sha256' => $referenceSha, 'reference_is_not_an_original_selection' => true] + assessmentDifference($referenceText, $extraction['text']);
		} elseif (!in_array($extraction['status'], ['unavailable', 'empty_text'], true)) $bad = true;
		$versions[] = $version;
	}
	$ambiguous = str_contains($plan['reason'], 'contact_mapping_ambiguous') || ($context['document']['contact_match_count'] ?? 0) > 1;
	$class = $ambiguous ? 'contact_mapping_ambiguous' : (count($groups) > 1 ? 'conflicting_versions_needing_review' : ($bad ? 'invalid_or_unreadable_candidate' : (!$groups ? 'no_candidate_found' : ($additional && !$known ? 'possible_additional_candidate' : 'consistent_candidates_needing_origin_confirmation'))));
	$date = assessmentDate($basis['document_date'] ?? null);
	$dateBasis = $load ? 'current_stored_document_date_not_origin_proof' : 'inventory_date_or_unresolved';
	if ($date === null && $versions) {
		$hints = [];
		foreach ($versions as $version) {
			$comparisons = $version['facts']['comparisons'] ?? [];
			if (count($comparisons['date']['detected'] ?? []) !== 1 || ($comparisons['number']['status'] ?? '') !== 'text_matches' || ($comparisons['type']['status'] ?? '') !== 'text_matches') { $hints = []; break; }
			$hints[] = $comparisons['date']['detected'][0];
		}
		if (count(array_unique($hints)) === 1) { $date = $hints[0]; $dateBasis = 'unverified_pdf_issue_date_for_triage_only'; }
	}
	$rank = $identity['controller'] === 'invoice' && $date !== null && $date >= $recentSince ? 0 : ($identity['controller'] === 'invoice' && count($groups) > 1 ? 1 : ($identity['module'] === 'sales' ? 2 : 3));
	$pattern = []; $hashLabels = [];
	foreach (['attachment', 'current', 'legacy'] as $label) {
		$values = [];
		foreach ($candidates as $candidate) if (($candidate['observed']['status'] ?? '') === 'ok' && array_filter($candidate['references'], static fn(array $reference): bool => $reference['source'] === $label)) {
			$sha = $candidate['observed']['sha256']; $hashLabels[$sha] ??= count($hashLabels) + 1; $values[] = $hashLabels[$sha];
		}
		$values = array_values(array_unique($values)); sort($values); $pattern[] = $label . '=' . ($values ? implode(',', $values) : 'missing_or_unverified');
	}
	return [
		'schema_version' => 1, 'document' => $identity, 'inventory_reason' => $plan['reason'],
		'inventory_metadata' => array_intersect_key($plan, array_flip(['metadata', 'legacy_attachment_metadata'])),
		'classification' => $class, 'sha_pattern' => implode(';', $pattern),
		'priority' => ['rank' => $rank, 'date' => $date, 'date_basis' => $dateBasis, 'recent_since' => $recentSince],
		'comparison_basis' => $basis, 'database_correlated' => $context !== null, 'pathless_sources' => $pathless, 'discovery_issues' => $discoveryIssues,
		'candidates' => $candidates, 'versions' => $versions, 'additional_candidate_found' => $additional,
		'analysis_complete' => !$truncated, 'original_confirmed' => false, 'approval_allowed' => false,
		'unresolved_questions' => ['Confirm actual issued version using independent origin evidence; equal bytes or text do not establish authenticity.', 'Unlabeled, absent or multiple text values remain unresolved; inspect original pages where extraction is insufficient.'],
		'proposed_next_action' => $ambiguous ? 'Resolve contact mapping and then review the independently sourced document versions.' : ($class === 'no_candidate_found' ? 'Search authorized external paper/customer/dispatch archives; do not generate an original.' : 'Review the candidate evidence and content differences; obtain independent origin confirmation before any separate recovery workflow.'),
	];
}

function assessmentRun(array $input, string $base, string $output, int $clientId, string $extractor, string $recentSince, int $limit, ?callable $load = null): array
{
	DEEC_DocumentReview::privateDirectory(dirname($output)); reviewSafePath($output);
	if (file_exists($output) || !mkdir($output, 0700)) throw new RuntimeException('A new private output directory is required');
	reviewWrite($output . '/started.json', reviewJson(['schema_version' => 1, 'mode' => 'read_only_assessment', 'clientid' => $clientId, 'inventory' => ['path' => $input['path'], 'sha256' => $input['sha256']], 'started_at' => gmdate('c'), 'complete' => false, 'original_confirmation_allowed' => false]));
	$handle = fopen($output . '/documents.jsonl', 'x+b');
	if (!$handle || !chmod($output . '/documents.jsonl', 0600)) throw new RuntimeException('Private assessment output could not be opened');
	$summary = ['schema_version' => 1, 'mode' => 'read_only_assessment', 'clientid' => $clientId, 'inventory' => $input, 'complete' => true, 'documents' => 0, 'classes' => [], 'sha_patterns' => [], 'priorities' => [], 'extraction_statuses' => [], 'originals_confirmed' => 0, 'pdf_files_copied' => 0, 'database_writes' => 0];
	$index = [];
	try {
		foreach (assessmentRows($input['path']) as $row) {
			if (($row['kind'] ?? '') !== 'document' || ($row['plan']['status'] ?? '') !== 'blocked') continue;
			$result = assessmentDocument($row['plan'], $base, $load, $extractor, $recentSince, $limit);
			$line = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
			if (fwrite($handle, $line) !== strlen($line)) throw new RuntimeException('Assessment output write failed');
			$summary['documents']++; $summary['complete'] = $summary['complete'] && $result['analysis_complete'];
			foreach (['classes' => $result['classification'], 'sha_patterns' => $result['sha_pattern'], 'priorities' => $result['priority']['rank']] as $key => $value) $summary[$key][$value] = ($summary[$key][$value] ?? 0) + 1;
			foreach ($result['versions'] as $version) { $status = $version['extraction']['status']; $summary['extraction_statuses'][$status] = ($summary['extraction_statuses'][$status] ?? 0) + 1; }
			$index[] = [$result['priority']['rank'], $result['priority']['date'] ?? '', $result['priority']['date_basis'], $result['document']['module'], $result['document']['controller'], $result['document']['id'], $result['document']['document_number'], $result['classification'], count($result['versions']), $summary['documents'], $result['proposed_next_action']];
		}
		DEEC_DocumentReview::privateFile($input['path']);
		if (!hash_equals($input['sha256'], hash_file('sha256', $input['path'])) || $summary['documents'] !== $input['counts']['blocked']) throw new RuntimeException('Inventory changed during assessment');
		if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) throw new RuntimeException('Assessment output flush failed');
	} finally { fclose($handle); }
	usort($index, static fn(array $a, array $b): int => ($a[0] <=> $b[0]) ?: strcmp($b[1], $a[1]) ?: strcmp(implode('/', array_slice($a, 3, 3)), implode('/', array_slice($b, 3, 3))));
	array_unshift($index, ['priority', 'date', 'date_basis', 'module', 'controller', 'internal_id', 'visible_number', 'classification', 'byte_versions', 'detail_jsonl_line', 'proposed_next_action']);
	reviewWrite($output . '/review.csv', reviewCsv($index));
	$summary['finished_at'] = gmdate('c'); $summary['details_sha256'] = hash_file('sha256', $output . '/documents.jsonl');
	reviewWrite($output . '/summary.json', reviewJson($summary));
	return $summary;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;

$options = getopt('', ['inventory:', 'base:', 'client:', 'output:', 'config:', 'environment:', 'operator-user:', 'pdftotext:', 'recent-since:', 'max-candidates:', 'help']);
if (isset($options['help'])) {
	echo "Usage: php tools/document-recovery-assessment.php --inventory=/private/inventory.jsonl --base=/canonical/dewawi --client=100 --output=/private/new-assessment [--config=/private/select-only.ini --operator-user=42] [--pdftotext=/usr/bin/pdftotext] [--recent-since=YYYY-MM-DD] [--max-candidates=200]\nCreates private reports only; never copies PDFs, registers or approves originals. See tests/documents/document-recovery-assessment.md.\n"; exit(0);
}
$db = null; $auth = null;
try {
	$base = $options['base'] ?? ''; reviewSafePath($base);
	if (realpath($base) !== $base || !is_dir($base)) throw new RuntimeException('A canonical installation base is required');
	define('BASE_PATH', $base);
	$clientId = reviewId($options['client'] ?? null);
	$input = assessmentInventory($options['inventory'] ?? '', $clientId);
	$limit = filter_var($options['max-candidates'] ?? 200, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
	if (!$limit) throw new RuntimeException('Invalid candidate limit');
	$load = null;
	if (isset($options['config']) || isset($options['operator-user'])) {
		DEEC_DocumentReview::privateFile($options['config'] ?? '');
		$settings = migrationConfig($options['config'] ?? '', $options['environment'] ?? 'production');
		$auth = recoveryBoot($settings, $base, $clientId, reviewId($options['operator-user'] ?? null));
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		foreach ($input['blocked_types'] as [$module, $controller]) if (DEEC_DocumentArchive::validDocumentType($module, $controller) && !$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Operator lacks view permission for an inventory document type');
		$client = Zend_Registry::get('Client'); $contactsClient = (int)$client['modules']['contacts'];
		if (reviewId($input['summary']['contacts_clientid'] ?? $clientId) !== $contactsClient) throw new RuntimeException('Inventory contact tenant is not authorized by the current tenant relationship');
		$auth->query('SET TRANSACTION READ ONLY'); $auth->beginTransaction();
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$db = new mysqli($settings['host'], $settings['username'], $settings['password'], $settings['dbname'], $settings['port']);
		$db->set_charset('utf8mb4'); $db->query('SET TRANSACTION READ ONLY'); $db->begin_transaction();
		$load = static function(array $identity) use ($db, $contactsClient, $permission, $client, $base): ?array {
			$object = $permission->getObjectContext($identity['id'], $identity['module'], $identity['controller']);
			if (!$object || (int)$object['clientid'] !== $identity['clientid']) return null;
			$context = reviewLoadDocument($db, new DEEC_DocumentArchive($base), $identity, $contactsClient);
			if (!$context) return null;
			$stored = $object['parent'];
			$context['comparison_basis'] = array_intersect_key($stored, array_flip(['subtotal', 'taxes', 'total']));
			$context['comparison_basis'] += ['document_date' => $stored[$identity['controller'] . 'date'] ?? null, 'recipient' => $stored['billingname1'] ?? null, 'recipient_basis' => 'stored_document_snapshot', 'issuer' => $client['company'] ?? null, 'issuer_basis' => 'current_client_master_data_not_historical_identity_proof'];
			return $context;
		};
	}
	$recent = $options['recent-since'] ?? date('Y-m-d', strtotime('-1 year'));
	if (assessmentDate($recent) !== $recent) throw new RuntimeException('Invalid recent invoice threshold');
	$result = assessmentRun($input, $base, $options['output'] ?? '', $clientId, $options['pdftotext'] ?? '/usr/bin/pdftotext', $recent, $limit, $load);
	echo reviewJson(array_intersect_key($result, array_flip(['complete', 'documents', 'classes', 'sha_patterns', 'priorities', 'extraction_statuses', 'originals_confirmed'])) + ['output' => $options['output']]);
	$exit = $result['complete'] ? 0 : 3;
} catch (Throwable $error) {
	$detail = in_array(get_class($error), [RuntimeException::class, InvalidArgumentException::class], true) ? ': ' . $error->getMessage() : '';
	fwrite(STDERR, 'Recovery assessment failed (' . get_class($error) . ')' . $detail . ". No original was approved or registered.\n"); $exit = 2;
} finally {
	if ($db) { try { $db->rollback(); } catch (Throwable $ignored) {} $db->close(); }
	if ($auth) { try { $auth->rollBack(); } catch (Throwable $ignored) {} $auth->closeConnection(); }
}
exit($exit);
