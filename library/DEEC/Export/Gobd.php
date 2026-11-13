<?php

class DEEC_Export_Gobd
{
	protected $db;
	protected $clientId;
	protected $userId;
	protected array $documentFiles = [];
	protected array $documentNames = [];
	protected bool $reviewExport = false;
	protected ?DEEC_Export_GobdIssuer $issuer = null;
	protected string $tenantIdentitySha256 = '';

	public function __construct($db, int $clientId, ?int $userId = null)
	{
		$this->db = $db;
		$this->clientId = $clientId;
		$this->userId = $userId;
	}

	public static function validatePeriod(?string $from, ?string $to, bool $required = false): void
	{
		if (!$required && $from === null && $to === null) return;
		foreach ([$from, $to] as $date) if (!is_string($date) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) throw new InvalidArgumentException('Invalid GoBD export period');
		if ($from > $to) throw new InvalidArgumentException('GoBD export period is reversed');
	}

	public static function assertDocumentAccess(int $clientId, ?int $userId = null): void
	{
		if (!Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new RuntimeException('Document export access denied');
		$user = Zend_Registry::get('User');
		$client = Zend_Registry::get('Client');
		if (empty($user['id']) || empty($user['admin']) || (int)$user['clientid'] !== $clientId || (int)$client['id'] !== $clientId || ($userId !== null && (int)$user['id'] !== $userId)) throw new RuntimeException('Document export access denied');
		$permission = new DEEC_Permission($user);
		foreach (DEEC_DocumentArchive::documents() as $module => $controllers) foreach ($controllers as $controller) if (!$permission->hasPermission($module, $controller, 'view')) throw new RuntimeException('Document export permission denied: ' . $module . '/' . $controller);
	}

	public static function assertPackageAccess(string $path, int $clientId): void
	{
		$required = str_starts_with(basename($path), 'gobd-documents-') || str_starts_with(basename($path), 'document-review-');
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::RDONLY) !== true) {
			if ($required) throw new RuntimeException('Document package is invalid');
			return;
		}
		try {
			$stat = $zip->statName('metadata.json');
			if ($stat) {
				if ($stat['size'] > 1048576) throw new RuntimeException('Export metadata is too large');
				$metadata = json_decode($zip->getFromName('metadata.json'), true, 512, JSON_THROW_ON_ERROR);
				if (!empty($metadata['documents']['enabled'])) {
					if ((int)($metadata['clientId'] ?? 0) !== $clientId) throw new RuntimeException('Foreign document package');
					self::assertDocumentAccess($clientId);
					return;
				}
			}
			if ($required) throw new RuntimeException('Document package metadata is missing');
		} finally { $zip->close(); }
	}

	public function export(string $filePath, ?string $from, ?string $to, bool $includeDocuments = false, bool $reviewExport = false): string
	{
		$this->reviewExport = $reviewExport;
		$includeDocuments = $includeDocuments || $reviewExport;
		self::validatePeriod($from, $to, $includeDocuments);
		if ($includeDocuments) self::assertDocumentAccess($this->clientId, $this->userId);
		$destination = rtrim($filePath, '/') . '/';
		DEEC_Emailattachment::checkPath(rtrim($destination, '/'));
		if (!is_dir($destination) || !is_writable($destination)) throw new RuntimeException('Export directory is unavailable');
		$private = getenv('DEWAWI_GOBD_REPORT_DIR') ?: null;
		if ($includeDocuments || $private !== null) {
			if ($private === null) throw new RuntimeException('A private GoBD report directory is required');
			DEEC_DocumentReview::privateDirectory($private);
		}
		$stage = ($private !== null ? $private . '/' : $destination) . '.gobd-' . bin2hex(random_bytes(16)) . '/';
		if (!@mkdir($stage, 0700)) throw new RuntimeException('Export staging is unavailable');
		$transaction = false;
		try {
			if ($includeDocuments && !$this->db->getConnection()->inTransaction()) {
				$this->db->query('SET TRANSACTION READ ONLY');
				$this->db->beginTransaction();
				$transaction = true;
			}
			$name = $this->buildPackage($stage, $from, $to, $includeDocuments);
			if ($transaction) { $this->db->rollBack(); $transaction = false; }
			if ($includeDocuments && !$reviewExport) $this->publishVerification($stage, $destination, $name);
			if (!@chmod($stage . $name, 0600) || !@link($stage . $name, $destination . $name)) throw new RuntimeException('Export could not be published without overwriting another file');
			return $name;
		} finally {
			try { if ($transaction) $this->db->rollBack(); }
			finally {
				foreach (new FilesystemIterator($stage) as $file) if (!$file->isDir() || $file->isLink()) unlink($file->getPathname());
				rmdir($stage);
				$this->documentFiles = [];
				$this->documentNames = [];
				$this->reviewExport = false;
				$this->issuer = null;
			}
		}
	}

	protected function buildPackage(string $filePath, ?string $from, ?string $to, bool $includeDocuments): string
	{
		$client = $this->db->fetchRow('SELECT id, company, address, postcode, city, country FROM client WHERE id = ?', [$this->clientId]);
		if (!$client) throw new RuntimeException('Export tenant is unavailable');
		$this->tenantIdentitySha256 = DEEC_Export_GobdIssuer::identityHash($client);
		if ($includeDocuments && !$this->reviewExport) $this->issuer = new DEEC_Export_GobdIssuer($client);
		$profile = $this->reviewExport ? [] : $this->getProfile();
		$resolvedProfile = [];
		$files = [];

		foreach($profile as $exportName => $config) {
			$columns = $this->filterExistingColumns($config['table'], $config['columns']);
			$config['unavailableColumns'] = array_values(array_diff($config['columns'], $columns));
			$config['columnSchema'] = array_intersect_key($this->db->describeTable($config['table']), array_flip($columns));
			foreach ($config['columnSchema'] as &$schema) $schema = array_intersect_key($schema, array_flip(['DATA_TYPE', 'LENGTH', 'PRECISION', 'SCALE', 'NULLABLE', 'PRIMARY', 'PRIMARY_POSITION', 'IDENTITY', 'UNSIGNED']));
			unset($schema);
			$config['columns'] = $columns;
			$resolvedProfile[$exportName] = $config;

			$data = $this->fetchData($config, $columns, $from, $to);

			$filename = $exportName . '.csv';
			$this->writeCsv($filePath . $filename, $data, $columns);
			$files[] = $filename;
		}

		$this->writeDataDictionary($filePath . 'data_dictionary.csv', $resolvedProfile);
		$files[] = 'data_dictionary.csv';

		$this->writeRelationships($filePath . 'relationships.csv', $resolvedProfile);
		$files[] = 'relationships.csv';

		$this->writeMetadata($filePath . 'metadata.json', $resolvedProfile, $from, $to);
		$files[] = 'metadata.json';

		$this->writeReadme($filePath . 'readme.txt', $from, $to);
		$files[] = 'readme.txt';
		if ($includeDocuments) {
			$summary = $this->writeDocuments($filePath, $resolvedProfile, $from, $to);
			$files = array_merge($files, ['document_index.csv', 'document_manifest.csv', 'missing_documents.csv', 'excluded_documents.csv']);
			$metadata = json_decode(file_get_contents($filePath . 'metadata.json'), true, 512, JSON_THROW_ON_ERROR);
			$metadata['documents'] = ['enabled' => true, 'types' => $this->documentTypes(), 'scope' => 'stored document date, inclusive; technically readonly documents and unresolved original references', 'summary' => $summary];
			if (!$this->reviewExport) {
				$metadata['export_kind'] = 'outgoing_documents';
				$metadata['documents']['outside_scope_types'] = ['purchases' => DEEC_DocumentArchive::documents()['purchases']];
				$metadata['documents']['scope'] .= '; outgoing sales documents only; purchases are outside this requested scope, not counted as delivered or missing';
			}
			if ($this->reviewExport) {
				$metadata['export_kind'] = 'internal_document_review';
				$metadata['exportType'] = 'Internal document review';
				$metadata['documents']['scope'] = 'stored document date, inclusive; all eight types; archive originals, published reconstructions and unresolved or excluded documents; undated diagnostic publications are additionally included explicitly unassigned to the period';
			}
			$bytes = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			if (file_put_contents($filePath . 'metadata.json', $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write export metadata');
			$bytes = "\n\nArchived PDF document images: " . ($summary['complete'] ? 'COMPLETE for the stated scope' : 'INCOMPLETE')
				. "\n" . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
				. "\nOnly registered version-1 archive PDFs with verified size and SHA-256 are included; pdf_origin identifies approved reconstructed duplicates separately. No rendering or recovery staging files are used."
				. "\nDocument dates determine the period; archival dates do not. Deleted issued documents remain identifiable in excluded_documents.csv. Drafts without original references do not require PDF originals."
				. "\ndocument_index.csv identifies the selected document types and links existing fiscal CSV rows by file and id; other types use record_key in this index."
				. "\ndocument_manifest.csv records included originals and stored archive/recovery provenance. missing_documents.csv records missing, invalid or excluded images."
				. "\nRecorded historical conflicts remain traceable. Approved restorations are not new fiscal corrections. Header/EOF and hash verification do not prove historical content equivalence."
				. "\nchecksums.sha256 covers every ZIP entry except itself. Verify with sha256sum -c checksums.sha256 after extraction. Completeness refers only to this stored dated-document scope, not overall legal compliance.\n";
			if ($this->reviewExport) $bytes = "INTERNAL DOCUMENT REVIEW ONLY\nNot a GoBD original-document export. No original equivalence or completed paper comparison is asserted.\n"
				. "Period (inclusive stored document dates): $from to $to\nTenant: " . $this->clientId . "\n"
				. "originals/ contains verified registered archive versions, including approved reconstructed duplicates explicitly identified by pdf_origin; candidates/ contains unreviewed historical PDFs; review/ contains only currently valid published reconstructions. The latter require an original comparison before final archive approval.\n"
				. "diagnostics/ contains visibly marked diagnostic documents with incomplete or contradictory data. They are not validated reconstructions and cannot be approved as originals. Undated diagnostic publications are included additionally with date_scope=undated_diagnostic_not_assigned_to_period; no historical date or period is inferred.\n"
				. "Every dated document is identified in document_index.csv. document_manifest.csv lists every included version and all missing, invalid or excluded cases. missing_documents.csv lists unresolved cases. Deleted issued originals retain their deleted flag and are also listed in excluded_documents.csv; no document is reactivated.\n"
				. "Visible numbers name PDFs. Unsafe or missing numbers and collisions receive --id-<internal ID> (and a counter if necessary); review versions also carry their SHA-256 prefix. Original filenames and internal IDs remain in the manifests.\n"
				. "Different versions are not automatically selected. No PDFs were rendered, no business data changed, and no files were registered as originals by this export.\n"
				. "Availability: " . ($summary['complete'] ? 'COMPLETE for the stored dated-document scope' : 'INCOMPLETE') . "\n" . json_encode($summary, JSON_THROW_ON_ERROR)
				. "\nVerify the extracted package with sha256sum -c checksums.sha256. Inspect all missing/invalid/excluded rows. Availability completeness does not mean original equivalence, fiscal completeness or legal compliance.\n";
			if (file_put_contents($filePath . 'readme.txt', $bytes, $this->reviewExport ? 0 : FILE_APPEND) !== strlen($bytes)) throw new RuntimeException('Could not write export README');
			$this->finalizeInventory($filePath, $files);
			$lines = [];
			foreach ($files as $file) $lines[] = hash_file('sha256', $filePath . $file) . '  ' . $file;
			foreach ($this->documentFiles as $name => $entry) $lines[] = $entry['sha256'] . '  ' . $name;
			$bytes = implode("\n", $lines) . "\n";
			if (file_put_contents($filePath . 'checksums.sha256', $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write export checksums');
			$files[] = 'checksums.sha256';
		} else $this->finalizeInventory($filePath, $files);

		$zipFileName = $this->buildZipFileName($from, $to);
		if ($includeDocuments) $zipFileName = str_replace('gobd-export-', 'gobd-documents-', substr($zipFileName, 0, -4)) . '-' . bin2hex(random_bytes(8)) . '.zip';
		if ($this->reviewExport) $zipFileName = str_replace('gobd-documents-', 'document-review-', $zipFileName);
		if ($includeDocuments && !$this->reviewExport) $files = $this->prepareExternalDelivery($filePath, $files);
		$this->createZip($filePath, $files, $zipFileName);

		return $zipFileName;
	}

	protected function prepareExternalDelivery(string $directory, array $files): array
	{
		// Validate the complete technical inventory privately before reducing the delivery view.
		$audit = new ZipArchive();
		if ($audit->open($directory . 'internal-verification.zip', ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Could not create private export verification');
		$expected = $this->documentFiles;
		foreach ($files as $file) {
			$expected[$file] = ['size' => filesize($directory . $file), 'sha256' => hash_file('sha256', $directory . $file)];
			if (!$audit->addFile($directory . $file, $file)) throw new RuntimeException('Could not retain private export inventory');
		}
		if (!$audit->close() || $audit->open($directory . 'internal-verification.zip', ZipArchive::CHECKCONS) !== true) throw new RuntimeException('Private export verification is invalid');
		try {
			foreach ($files as $file) {
				$stream = $audit->getStream($file); $hash = hash_init('sha256');
				if (!$stream) throw new RuntimeException('Private inventory entry is missing');
				try { $size = hash_update_stream($hash, $stream); } finally { fclose($stream); }
				if ($size !== $expected[$file]['size'] || hash_final($hash) !== $expected[$file]['sha256']) throw new RuntimeException('Private inventory integrity differs');
			}
			$this->verifyDocumentManifest($audit, $expected);
			$this->verifyCsvInventory($audit);
		} finally { $audit->close(); }
		$fields = ['record_key', 'module', 'controller', 'documentid', 'document_number', 'document_date', 'filename', 'deleted', 'issued', 'completed', 'state', 'cancelled', 'csv_file', 'csv_key_column', 'csv_key_value', 'pdf_path', 'pdf_supplied', 'pdf_origin', 'delivery_note', 'missing_reason', 'issuer_status'];
		$input = fopen($directory . 'document_index.csv', 'rb');
		$output = fopen($directory . 'document_files.csv', 'xb');
		if (!$input || !$output) throw new RuntimeException('Could not write document delivery overview');
		try {
			$columns = fgetcsv($input, 0, ';', '"', '');
			$reasons = ['Document archive versions are ambiguous' => 'archive_versions_ambiguous', 'Invalid document archive filename' => 'archive_filename_invalid', 'Document archive filename differs' => 'archive_filename_differs', 'Document archive path differs' => 'archive_path_differs', 'Document archive integrity check failed' => 'archive_integrity_check_failed', 'Unapproved reconstruction cannot be supplied as an archive document' => 'reconstruction_origin_unconfirmed'];
			if (fputcsv($output, $fields, ';', '"', '') === false) throw new RuntimeException('Could not write delivery columns');
			while (($values = fgetcsv($input, 0, ';', '"', '')) !== false) {
				$row = array_combine($columns, $values);
				if ($row['csv_file'] === 'document_index.csv') $row['csv_file'] = 'document_files.csv';
				$row['issuer_status'] = $row['issuer_status'] ?: 'not_checked';
				$row['pdf_origin'] = $row['status'] === 'included' ? $row['pdf_origin'] : 'not_supplied';
				$row['pdf_path'] = $row['zip_path']; $row['pdf_supplied'] = $row['status'] === 'included' ? '1' : '0';
				$row['delivery_note'] = ['included' => 'supplied', 'missing' => 'requested_pdf_not_supplied', 'excluded' => 'original_reference_not_supplied_issue_status_unconfirmed', 'not_issued' => 'draft_no_original_requested'][$row['status']];
				$row['missing_reason'] = in_array($row['status'], ['missing', 'excluded'], true) ? (preg_match('/^[a-z_]+$/D', $row['reason']) ? $row['reason'] : ($reasons[$row['reason']] ?? 'archive_verification_failed_see_private_report')) : '';
				if (fputcsv($output, array_map(static fn(string $field) => $row[$field], $fields), ';', '"', '') === false) throw new RuntimeException('Could not write delivery row');
			}
		} finally { fclose($input); fclose($output); }
		$metadata = json_decode(file_get_contents($directory . 'metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		$metadata['documents']['overview'] = ['file' => 'document_files.csv', 'columns' => $fields, 'key' => 'record_key', 'pdf_path' => 'Exact ZIP entry; empty when not supplied', 'pdf_supplied' => '1 for a verified registered archive PDF, including approved reconstructed duplicates', 'pdf_origin' => 'issued_original; verified_recovered_original; reviewed_reconstruction_duplicate; registered_archive_origin_unconfirmed; reconstruction_origin_unconfirmed; not_supplied. Reconstructed duplicates are not unchanged historical original files.', 'csv_reference' => 'csv_file/csv_key_column/csv_key_value identify the fiscal CSV row; types without a separate fiscal dataset refer to this overview', 'delivery_note' => 'supplied; requested_pdf_not_supplied; original_reference_not_supplied_issue_status_unconfirmed; draft_no_original_requested'];
		unset($metadata['userId']);
		$metadata['documents']['overview']['issuer_status'] = 'manually_verified; conflict; review_required; not_checked. Archive registration and tenant ID alone do not establish the actual PDF issuer; issued_original describes archive provenance, not dispatch.';
		$metadata['documents']['overview']['missing_reason'] = 'Reason code for an undelivered requested PDF or unresolved original reference; detailed technical evidence remains in the private report';
		$bytes = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		if (file_put_contents($directory . 'metadata.json', $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write delivery metadata');
		$this->writeReadme($directory . 'readme.txt', $metadata['from'], $metadata['to']);
		$bytes = "\n\nRequested PDF delivery: " . ($metadata['documents']['summary']['complete'] ? 'COMPLETE for the stated stored dated-document scope' : 'INCOMPLETE')
			. "\n" . json_encode($metadata['documents']['summary'], JSON_THROW_ON_ERROR)
			. "\nOnly verified registered archive PDFs with a separate SHA-bound manual issuer comparison are supplied. Approved reconstructed duplicates are identified by pdf_origin=reviewed_reconstruction_duplicate and are not unchanged historical original files. No unreviewed reconstructions or diagnostic PDFs are included."
			. "\ndocument_files.csv lists every document in the requested stored-date scope, its fiscal CSV reference, supplied PDF path and any delivery gap. Deleted issued documents retain their flags and are included when a verified original exists."
			. "\nEmpty pdf_path with pdf_supplied=0 explicitly means no PDF was delivered. Inspect delivery_note; missing requested originals make this delivery incomplete. Drafts without an original reference do not request a PDF."
			. "\nPDFs use visible document numbers under documents/<module>/<type>/. pdf_origin distinguishes issued originals, verified recovered originals, approved reconstructed duplicates and uncertain provenance. Delivery completeness describes available archive PDFs, not historical original-file equivalence. Archive integrity and the full technical inventory were verified and retained internally. Completeness of this delivery is not an assertion of overall legal compliance.\n";
		if (file_put_contents($directory . 'readme.txt', $bytes, FILE_APPEND) !== strlen($bytes)) throw new RuntimeException('Could not write delivery scope');
		$files = array_merge(array_values(array_diff($files, ['document_index.csv', 'document_manifest.csv', 'missing_documents.csv', 'excluded_documents.csv', 'checksums.sha256'])), ['document_files.csv']);
		$this->finalizeInventory($directory, $files);
		return $files;
	}

	protected function verifyDelivery(ZipArchive $zip): void
	{
		$metadata = json_decode($zip->getFromName('metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		$summary = $metadata['documents']['summary']; $keys = []; $paths = []; $references = [];
		$counts = ['included' => 0, 'missing' => 0, 'excluded' => 0, 'issued' => 0, 'deleted_issued' => 0];
		$stream = $zip->getStream('document_files.csv');
		try {
			$fields = fgetcsv($stream, 0, ';', '"', '');
			if ($fields !== $metadata['documents']['overview']['columns']) throw new RuntimeException('Delivery columns differ');
			while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
				if (count($values) !== count($fields)) throw new RuntimeException('Incomplete delivery row');
				$row = array_combine($fields, $values); $key = $this->clientId . '/' . $row['module'] . '/' . $row['controller'] . '/' . $row['documentid'];
				if ($key !== $row['record_key'] || isset($keys[$key]) || !in_array($row['controller'], $this->documentTypes()[$row['module']] ?? [], true) || $row['document_date'] < $metadata['from'] || $row['document_date'] > $metadata['to']) throw new RuntimeException('Delivery identity or date differs');
				$keys[$key] = true;
				if ($row['issued'] === '1') { $counts['issued']++; if ($row['deleted'] !== '0') $counts['deleted_issued']++; }
				$status = array_search($row['delivery_note'], ['included' => 'supplied', 'missing' => 'requested_pdf_not_supplied', 'excluded' => 'original_reference_not_supplied_issue_status_unconfirmed', 'not_issued' => 'draft_no_original_requested'], true);
				if ($status === false || $row['pdf_supplied'] !== ($status === 'included' ? '1' : '0')) throw new RuntimeException('Delivery statement differs');
				if (in_array($status, ['missing', 'excluded'], true) !== ($row['missing_reason'] !== '')) throw new RuntimeException('Delivery gap reason differs');
				if (isset($counts[$status])) $counts[$status]++;
				if ($status === 'included') {
					if ($row['issuer_status'] !== 'manually_verified') throw new RuntimeException('Delivered PDF issuer is unconfirmed');
					if (($this->documentFiles[$row['pdf_path']]['document_key'] ?? null) !== $row['record_key']) throw new RuntimeException('Delivered PDF document identity differs');
					if (!$row['issued'] || !isset($this->documentFiles[$row['pdf_path']]) || isset($paths[$row['pdf_path']]) || !str_starts_with($row['pdf_path'], 'documents/' . $row['module'] . '/' . $row['controller'] . '/')) throw new RuntimeException('Delivered PDF assignment differs');
					if ($row['pdf_origin'] !== ($this->documentFiles[$row['pdf_path']]['pdf_origin'] ?? '') || !in_array($row['pdf_origin'], ['issued_original', 'verified_recovered_original', 'reviewed_reconstruction_duplicate', 'registered_archive_origin_unconfirmed', 'reconstruction_origin_unconfirmed'], true)) throw new RuntimeException('Delivered PDF provenance differs');
					$paths[$row['pdf_path']] = true;
				} elseif ($row['pdf_path'] !== '' || $row['pdf_origin'] !== 'not_supplied') throw new RuntimeException('Undelivered document contains a PDF or provenance assertion');
				$dataset = null;
				foreach ($metadata['tables'] as $table) if ($table['table'] === $row['controller']) $dataset = $table['file'];
				if ($row['csv_file'] !== ($dataset ?? 'document_files.csv') || $row['csv_key_column'] !== ($dataset ? 'id' : 'record_key') || $row['csv_key_value'] !== ($dataset ? $row['documentid'] : $row['record_key'])) throw new RuntimeException('Delivery reference document type differs');
				$references[] = [$row['csv_file'], $row['csv_key_column'], $row['csv_key_value']];
			}
		} finally { fclose($stream); }
		$lookup = [];
		foreach ($references as [$file, $column, $value]) {
			if (!isset($lookup[$file][$column])) {
				$csv = $zip->getStream($file);
				if (!$csv) throw new RuntimeException('Delivery reference file is missing');
				try {
					$escape = '';
					$columns = fgetcsv($csv, 0, ';', '"', $escape); $offset = array_search($column, $columns, true);
					if ($offset === false) throw new RuntimeException('Delivery reference column is missing');
					$lookup[$file][$column] = [];
					while (($values = fgetcsv($csv, 0, ';', '"', $escape)) !== false) $lookup[$file][$column][(string)$values[$offset]] = true;
				} finally { fclose($csv); }
			}
			if (!isset($lookup[$file][$column][$value])) throw new RuntimeException('Delivery reference row is missing');
		}
		foreach ($counts as $key => $value) if ($summary[$key] !== $value) throw new RuntimeException('Delivery summary differs');
		if ($summary['dated_documents'] !== count($keys) || count($paths) !== count($this->documentFiles) || $summary['complete'] !== ($counts['missing'] === 0 && $counts['excluded'] === 0)) throw new RuntimeException('Delivery completeness differs');
	}

	protected function publishVerification(string $stage, string $destination, string $name): void
	{
		$root = getenv('DEWAWI_GOBD_REPORT_DIR');
		DEEC_DocumentReview::privateDirectory($root);
		$directory = $root . '/' . $this->clientId;
		if (!file_exists($directory) && !mkdir($directory, 0700)) throw new RuntimeException('Private verification directory is unavailable');
		DEEC_DocumentReview::privateDirectory($directory);
		$zip = new ZipArchive();
		if ($zip->open($stage . $name, ZipArchive::RDONLY) !== true) throw new RuntimeException('Verified delivery is unavailable');
		$entries = [];
		try {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$stat = $zip->statIndex($i); $stream = $zip->getStream($stat['name']); $hash = hash_init('sha256');
				if (!$stream) throw new RuntimeException('Verified delivery entry is unavailable');
				try { $size = hash_update_stream($hash, $stream); } finally { fclose($stream); }
				$entries[$stat['name']] = ['size' => $size, 'sha256' => hash_final($hash)];
			}
		} finally { $zip->close(); }
		$bytes = json_encode(['schema_version' => 1, 'clientid' => $this->clientId, 'verified_by' => $this->userId, 'verified_at' => gmdate('c'), 'delivery' => $name, 'size' => filesize($stage . $name), 'sha256' => hash_file('sha256', $stage . $name), 'issuer_review_sha256' => $this->issuer->reviewSha256, 'tenant_identity_sha256' => $this->tenantIdentitySha256, 'technical_inventory_sha256' => hash_file('sha256', $stage . 'internal-verification.zip'), 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		if (file_put_contents($stage . 'delivery-verification.json', $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write delivery verification');
		foreach (['internal-verification.zip' => '.audit.zip', 'delivery-verification.json' => '.verification.json'] as $source => $suffix) if (!chmod($stage . $source, 0600) || !@link($stage . $source, $directory . '/' . $name . $suffix)) throw new RuntimeException('Could not retain private delivery verification without overwrite');
	}

	protected function writeDocuments(string $directory, array $profile, string $from, string $to): array
	{
		$fields = ['record_key', 'clientid', 'module', 'controller', 'documentid', 'document_number', 'document_date', 'filename', 'deleted', 'issued', 'completed', 'state', 'cancelled', 'csv_file', 'csv_key_column', 'csv_key_value', 'status', 'reason', 'archive_version', 'zip_path', 'filesize', 'sha256', 'archived', 'archivedby', 'correctionarchiveid', 'archive_parameters', 'historical_conflicts', 'pdf_origin', 'issuer_status', 'issuer_evidence'];
		if ($this->reviewExport) $fields = array_merge($fields, ['evidence_status', 'proof_sha256', 'snapshot_sha256', 'generated_at', 'database_confirmation', 'diagnostic_issues', 'date_scope']);
		$handles = [];
		foreach (['document_index', 'document_manifest', 'missing_documents', 'excluded_documents'] as $name) {
			$handles[$name] = fopen($directory . $name . '.csv', 'xb');
			if (!$handles[$name] || fputcsv($handles[$name], $fields, ';', '"', '') === false) throw new RuntimeException('Could not create document manifest');
		}
		$summary = ['dated_documents' => 0, 'issued' => 0, 'included' => 0, 'missing' => 0, 'excluded' => 0, 'deleted_issued' => 0, 'complete' => true];
		$reviewCounts = ['archived_original' => 0, DEEC_DocumentReview::STATUS => 0, DEEC_DocumentReview::DIAGNOSTIC_STATUS => 0, DEEC_DocumentReview::UNREVIEWED_STATUS => 0, 'missing' => 0, 'invalid' => 0, 'excluded' => 0];
		$undatedCount = 0;
		$archive = new DEEC_DocumentArchive(BASE_PATH);
		$archiveDb = new Application_Model_DbTable_Documentarchive();
		try {
			foreach ($this->documentTypes() as $module => $controllers) foreach ($controllers as $controller) {
				$dataset = null;
				foreach ($profile as $name => $config) if ($config['table'] === $controller) $dataset = $name . '.csv';
				$columns = ['id', 'clientid', 'contactid', 'filename', $controller . 'id', $controller . 'date', 'state', 'completed', 'cancelled', 'deleted'];
				$dateColumn = $this->quoteIdentifier($controller . 'date');
				$period = $dateColumn . ' >= ? AND ' . $dateColumn . ' <= ?';
				if ($this->reviewExport) $period = '(' . $period . ' OR ' . $dateColumn . " IS NULL OR " . $dateColumn . " = '0000-00-00')";
				$query = $this->db->query('SELECT ' . implode(', ', array_map([$this, 'quoteIdentifier'], $columns)) . ' FROM ' . $this->quoteIdentifier($controller) . ' WHERE clientid = ? AND ' . $period . ' ORDER BY id', [$this->clientId, $from, $to]);
				while ($document = $query->fetch(Zend_Db::FETCH_ASSOC)) {
					$inspection = null;
					$undated = empty($document[$controller . 'date']) || $document[$controller . 'date'] === '0000-00-00';
					if ($this->reviewExport && $undated) {
						if (!DEEC_Permission::isDocumentReadonly($module, $controller, $document) || $document['deleted']) continue;
						$inspection = (new DEEC_DocumentReview())->inspection((int)$document['id'], $module, $controller);
						if (!$inspection['diagnostic_publications']) continue;
						$undatedCount++;
					}
					$summary['dated_documents']++;
					$issued = DEEC_Permission::isDocumentReadonly($module, $controller, $document);
					$key = $this->clientId . '/' . $module . '/' . $controller . '/' . $document['id'];
					$row = ['record_key' => $key, 'clientid' => $this->clientId, 'module' => $module, 'controller' => $controller, 'documentid' => $document['id'], 'document_number' => $document[$controller . 'id'], 'document_date' => $document[$controller . 'date'],
						'filename' => $document['filename'], 'deleted' => $document['deleted'], 'issued' => $issued ? 1 : 0, 'completed' => $document['completed'], 'state' => $document['state'], 'cancelled' => $document['cancelled'], 'csv_file' => $dataset ?? 'document_index.csv', 'csv_key_column' => $dataset ? 'id' : 'record_key', 'csv_key_value' => $dataset ? $document['id'] : $key, 'status' => 'not_issued', 'reason' => ''];
					if ($this->reviewExport) $row['date_scope'] = $undated ? 'undated_diagnostic_not_assigned_to_period' : 'selected_period';
					$excluded = $issued && !empty($document['deleted']);
					if ($issued) {
						$summary['issued']++;
						if ($excluded) $summary['deleted_issued']++;
						$row['status'] = 'missing';
						try {
							$original = $archiveDb->getForExport((int)$document['id'], $module, $controller);
							if (!$original) $row['reason'] = 'archive_registration_missing';
							else {
								$client = Zend_Registry::get('Client');
								$contacts = $this->db->fetchCol('SELECT id FROM contact WHERE clientid = ? AND contactid = ? AND deleted = 0 ORDER BY id', [(int)($client['modules']['contacts'] ?? $this->clientId), $document['contactid']]);
								$plan = $archive->plan($document + ['module' => $module, 'controller' => $controller, 'document_number' => $document[$controller . 'id'], 'contact_match_count' => count($contacts), 'contact_record_id' => count($contacts) === 1 ? (int)$contacts[0] : 0], [$original]);
								$conflicts = $plan['historical_conflicts'] ?? [];
								if (!empty($plan['reason'])) $conflicts[] = $plan['reason'];
								$row += ['archive_version' => $original['version'], 'archived' => $original['archived'], 'archivedby' => $original['archivedby'], 'correctionarchiveid' => $original['correctionarchiveid'], 'archive_parameters' => $original['parameters'], 'historical_conflicts' => json_encode(array_values(array_unique($conflicts)), JSON_THROW_ON_ERROR)];
								if (in_array($plan['reason'], ['historical_versions_differ', 'canonical_version_differs', 'invalid_historical_source'], true)) $row['reason'] = $plan['reason'];
								else {
									$pdfOrigin = DEEC_DocumentArchive::pdfProvenance($original);
									if ($pdfOrigin === 'reconstruction_origin_unconfirmed') throw new RuntimeException('Unapproved reconstruction cannot be supplied as an archive document');
									if (!$this->reviewExport) {
										$identity = ['clientid' => (int)$this->clientId, 'module' => $module, 'controller' => $controller, 'id' => (int)$document['id'], 'document_number' => (string)$document[$controller . 'id']];
										$issuer = $this->issuer->check($identity, $original['sha256']);
										$row['issuer_status'] = $issuer['status'];
										$row['issuer_evidence'] = json_encode($issuer, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
										if ($issuer['reason'] !== '') throw new RuntimeException($issuer['reason']);
									}
									$name = $this->documentZipPath($this->reviewExport ? 'originals' : 'documents', $module, $controller, $document);
									$this->documentFiles[$name] = ['path' => BASE_PATH . '/' . $original['path'], 'size' => (int)$original['filesize'], 'sha256' => $original['sha256'], 'pdf_origin' => $pdfOrigin, 'document_key' => $key];
									$row += ['zip_path' => $name, 'filesize' => $original['filesize'], 'sha256' => $original['sha256'], 'pdf_origin' => $pdfOrigin];
									$row['filename'] = $original['filename'];
									$row['status'] = 'included';
									$summary['included']++;
								}
							}
						} catch (RuntimeException | InvalidArgumentException $exception) { $row['reason'] = $exception->getMessage(); }
						if ($row['status'] === 'missing') $summary['missing']++;
					} elseif ($document[$controller . 'id'] !== null || !empty($document['filename']) || $this->db->fetchOne('SELECT COUNT(*) FROM documentarchive WHERE clientid = ? AND module = ? AND controller = ? AND documentid = ? AND format = ?', [$this->clientId, $module, $controller, $document['id'], 'pdf'])) {
						$row['status'] = 'excluded';
						$row['reason'] = 'no_issue_marker';
						$excluded = true;
						$summary['excluded']++;
					}
					if ($this->reviewExport) {
						$rows = $this->reviewRows($row, $document, $module, $controller, $inspection);
						$indexRow = $rows[0];
						foreach ($rows as $entry) {
							$reviewCounts[$entry['status']]++;
							$line = array_map(static fn(string $field) => $entry[$field] ?? '', $fields);
							if (fputcsv($handles['document_manifest'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write review manifest');
							if (in_array($entry['status'], ['missing', 'invalid', 'excluded'], true) && fputcsv($handles['missing_documents'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write unresolved review cases');
							if (($excluded || $entry['status'] === 'excluded') && fputcsv($handles['excluded_documents'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write review exclusions');
						}
						if (fputcsv($handles['document_index'], array_map(static fn(string $field) => $indexRow[$field] ?? '', $fields), ';', '"', '') === false) throw new RuntimeException('Could not write review index');
						continue;
					}
					$line = array_map(static fn(string $field) => $row[$field] ?? '', $fields);
					if (fputcsv($handles['document_index'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write document index');
					if ($row['status'] === 'included' && fputcsv($handles['document_manifest'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write document manifest');
					if (in_array($row['status'], ['missing', 'excluded'], true) && fputcsv($handles['missing_documents'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write missing document manifest');
					if ($excluded && fputcsv($handles['excluded_documents'], $line, ';', '"', '') === false) throw new RuntimeException('Could not write excluded document manifest');
				}
			}
		} finally { foreach ($handles as $handle) if ($handle) fclose($handle); }
		if ($this->reviewExport) return ['documents' => $summary['dated_documents'], 'dated_documents' => $summary['dated_documents'] - $undatedCount, 'undated_diagnostic_documents' => $undatedCount, 'issued' => $summary['issued'], 'deleted_issued' => $summary['deleted_issued']] + $reviewCounts + ['complete' => $reviewCounts['missing'] === 0 && $reviewCounts['invalid'] === 0 && $reviewCounts['excluded'] === 0];
		$summary['complete'] = $summary['missing'] === 0 && $summary['excluded'] === 0;
		return $summary;
	}

	protected function documentZipPath(string $prefix, string $module, string $controller, array $document, ?string $sha = null): string
	{
		$number = (string)($document[$controller . 'id'] ?? '');
		$stem = substr(trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '_', $number), '._-'), 0, 80);
		$safe = $stem !== '' && $stem === $number && !preg_match('/^(?:CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])(?:\.|$)/i', $stem);
		if (!$safe) $stem = ($stem ?: 'document') . '--id-' . $document['id'];
		if ($sha !== null) $stem .= '--' . substr($sha, 0, 12);
		$directory = $prefix . '/' . $module . '/' . $controller . '/';
		$name = $directory . $stem . '.pdf';
		if (isset($this->documentNames[strtolower($name)])) {
			$stem .= '--id-' . $document['id'];
			$name = $directory . $stem . '.pdf';
			$counter = 2;
			while (isset($this->documentNames[strtolower($name)])) $name = $directory . $stem . '-' . $counter++ . '.pdf';
		}
		$this->documentNames[strtolower($name)] = true;
		return $name;
	}

	protected function reviewRows(array $row, array $document, string $module, string $controller, ?array $inspection = null): array
	{
		if ($row['status'] === 'included') return [array_replace($row, ['status' => 'archived_original', 'evidence_status' => 'verified_registered_original'])];
		if (!$row['issued'] || $row['deleted']) return [array_replace($row, ['status' => 'excluded', 'reason' => $row['deleted'] ? 'deleted_issued_without_verified_original' : 'not_issued', 'evidence_status' => 'excluded'])];
		if ($row['reason'] !== 'archive_registration_missing') return [array_replace($row, ['status' => 'invalid', 'evidence_status' => 'archive_verification_failed'])];
		try {
			$inspection = $inspection ?? (new DEEC_DocumentReview())->inspection((int)$document['id'], $module, $controller);
			$rows = [];
			foreach ($inspection['candidates'] as $sha => $candidate) {
				$diagnostic = $candidate['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS;
				$unreviewed = $candidate['status'] === DEEC_DocumentReview::UNREVIEWED_STATUS;
				$name = $this->documentZipPath($diagnostic ? 'diagnostics' : ($unreviewed ? 'candidates' : 'review'), $module, $controller, $document, $sha);
				$this->documentFiles[$name] = ['path' => $candidate['file']['path'], 'size' => $candidate['file']['size'], 'sha256' => $sha];
				$rows[] = array_replace($row, ['status' => $candidate['status'], 'reason' => $diagnostic ? 'diagnostic_only_not_an_approved_basis' : ($unreviewed ? 'unreviewed_historical_candidate' : 'original_comparison_pending'), 'evidence_status' => $diagnostic ? 'diagnostic_only_not_validated' : ($unreviewed ? 'unreviewed_historical_candidate' : 'current_published_reconstruction'),
					'zip_path' => $name, 'filesize' => $candidate['file']['size'], 'sha256' => $sha, 'proof_sha256' => $candidate['proof_sha256'], 'snapshot_sha256' => $candidate['proof']['snapshot_sha256'],
					'generated_at' => $candidate['proof']['generated_at'], 'database_confirmation' => json_encode($candidate['receipt']['database_confirmation'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
					'diagnostic_issues' => $diagnostic ? json_encode($candidate['proof']['diagnosis']['issues'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : '']);
			}
			foreach ($inspection['invalid'] as $sha => $reason) $rows[] = array_replace($row, ['status' => 'invalid', 'reason' => $reason, 'sha256' => $sha, 'evidence_status' => 'published_reconstruction_invalid']);
			return $rows ?: [array_replace($row, ['status' => 'missing', 'reason' => 'no_current_published_review_pdf', 'evidence_status' => 'no_published_reconstruction'])];
		} catch (RuntimeException | InvalidArgumentException | JsonException $error) { return [array_replace($row, ['status' => 'invalid', 'reason' => $error->getMessage(), 'evidence_status' => 'review_verification_failed'])]; }
	}

	protected function fetchData(array $config, array $columns, ?string $from, ?string $to): array
	{
		if (empty($columns)) {
			return [];
		}

		if (!empty($config['parentTable']) && $from && $to) {
			return $this->fetchChildData($config, $columns, $from, $to);
		}

		return $this->fetchTableData($config, $columns, $from, $to);
	}

	protected function fetchTableData(array $config, array $columns, ?string $from, ?string $to): array
	{
		$table = $config['table'];
		$dateField = $config['dateField'] ?? null;

		$sql = 'SELECT ' . $this->buildColumnSql('t', $columns)
			. ' FROM ' . $this->quoteIdentifier($table) . ' t'
			. ' WHERE t.' . $this->quoteIdentifier('clientid') . ' = ?';

		$params = [$this->clientId];

		foreach($config['conditions'] ?? [] as $column => $value) {
			$sql .= ' AND t.' . $this->quoteIdentifier($column) . ' = ?';
			$params[] = $value;
		}

		if($dateField && $from && $to) {
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' >= ?';
			$sql .= ' AND t.' . $this->quoteIdentifier($dateField) . ' <= ?';
			$params[] = $from;
			$params[] = $to;
		}

		if(in_array('id', $columns, true)) {
			$sql .= ' ORDER BY t.' . $this->quoteIdentifier('id') . ' ASC';
		}

		return $this->db->fetchAll($sql, $params);
	}

	protected function fetchChildData(array $config, array $columns, string $from, string $to): array
	{
		$table = $config['table'];
		$parentTable = $config['parentTable'];
		$parentKey = $config['parentKey'];
		$parentDateField = $config['parentDateField'];

		$sql = 'SELECT ' . $this->buildColumnSql('t', $columns)
			. ' FROM ' . $this->quoteIdentifier($table) . ' t'
			. ' INNER JOIN ' . $this->quoteIdentifier($parentTable) . ' p'
			. ' ON p.' . $this->quoteIdentifier('id') . ' = t.' . $this->quoteIdentifier($parentKey)
			. ' WHERE t.' . $this->quoteIdentifier('clientid') . ' = ?'
			. ' AND p.' . $this->quoteIdentifier('clientid') . ' = ?'
			. ' AND p.' . $this->quoteIdentifier($parentDateField) . ' >= ?'
			. ' AND p.' . $this->quoteIdentifier($parentDateField) . ' <= ?';

		$params = [
			$this->clientId,
			$this->clientId,
			$from,
			$to,
		];

		if (in_array('id', $columns, true)) {
			$sql .= ' ORDER BY t.' . $this->quoteIdentifier('id') . ' ASC';
		}

		return $this->db->fetchAll($sql, $params);
	}

	protected function buildColumnSql(string $alias, array $columns): string
	{
		$parts = [];

		foreach ($columns as $column) {
			$parts[] = $alias . '.' . $this->quoteIdentifier($column)
				. ' AS ' . $this->quoteIdentifier($column);
		}

		return implode(', ', $parts);
	}

	protected function getProfile(): array
	{
		$documentColumns = [
			'id' => 'Internal Dewawi database identifier.',
			'contactid' => 'Visible customer number stored on the document.',
			'clientid' => 'Internal Dewawi client identifier.',
			'header' => 'Document header text.',
			'footer' => 'Document footer text.',
			'vatin' => 'VAT identification number stored on the document.',
			'orderdate' => 'Date of the customer order.',
			'deliverydate' => 'Date of delivery or service.',
			'paymentmethod' => 'Payment method agreed for the document.',
			'shippingmethod' => 'Shipping or delivery method.',
			'billingname1' => 'Primary billing address name stored on the document.',
			'billingname2' => 'Secondary billing address name stored on the document.',
			'billingdepartment' => 'Billing department stored on the document.',
			'billingstreet' => 'Billing street stored on the document.',
			'billingpostcode' => 'Billing postal code stored on the document.',
			'billingcity' => 'Billing city stored on the document.',
			'billingcountry' => 'Billing country stored on the document.',
			'shippingname1' => 'Primary shipping address name stored on the document.',
			'shippingname2' => 'Secondary shipping address name stored on the document.',
			'shippingdepartment' => 'Shipping department stored on the document.',
			'shippingstreet' => 'Shipping street stored on the document.',
			'shippingpostcode' => 'Shipping postal code stored on the document.',
			'shippingcity' => 'Shipping city stored on the document.',
			'shippingcountry' => 'Shipping country stored on the document.',
			'shippingphone' => 'Shipping phone number stored on the document.',
			'subtotal' => 'Document subtotal before taxes.',
			'taxes' => 'Total tax amount of the document.',
			'total' => 'Total document amount.',
			'currency' => 'Currency used for monetary amounts.',
			'taxfree' => 'Indicates whether the document is treated as tax exempt.',
			'state' => 'Stored workflow state; not evidence of dispatch to a customer.',
			'completed' => 'Stored technical completion flag; not evidence of dispatch to a customer.',
			'cancelled' => 'Indicates whether the document has been cancelled.',
			'filename' => 'Filename of the generated document file, if available.',
			'created' => 'Date and time the database record was created.',
			'deleted' => 'Logical deletion marker.',
		];

		$positionColumns = [
			'id' => 'Internal Dewawi database identifier.',
			'parentid' => 'Internal identifier of the parent document.',
			'itemid' => 'Reference to the item master record, if available.',
			'masterid' => 'Reference to the originating or master position, if available.',
			'possetid' => 'Internal identifier of the position set.',
			'clientid' => 'Internal Dewawi client identifier.',
			'sku' => 'Item number stored on the document position.',
			'title' => 'Position title stored on the document.',
			'description' => 'Position description stored on the document.',
			'price' => 'Unit price of the position.',
			'taxrate' => 'Tax rate applied to the position.',
			'quantity' => 'Quantity of the position.',
			'total' => 'Total amount of the position.',
			'currency' => 'Currency used for the position.',
			'uom' => 'Unit of measure.',
			'manufacturerid' => 'Reference to the manufacturer, if available.',
			'manufacturersku' => 'Manufacturer item number.',
			'ordering' => 'Position order within the document.',
			'created' => 'Date and time the position was created.',
			'deleted' => 'Logical deletion marker.',
		];

		return [
			'customers' => [
				'table' => 'contact',
				'title' => 'Customers',
				'description' => 'Customer master records used by sales and accounting documents.',
				'dateField' => null,
				'columns' => [
					'id', 'contactid', 'clientid', 'type',
					'name1', 'name2', 'department',
					'taxnumber', 'vatin', 'taxfree',
					'currency', 'debitornumber',
					'paymentmethod', 'paymentterm',
					'cashdiscountdays', 'cashdiscountpercent',
					'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal Dewawi customer identifier.',
					'contactid' => 'Visible customer number.',
					'clientid' => 'Internal Dewawi client identifier.',
					'type' => 'Customer or contact type.',
					'name1' => 'Primary customer name.',
					'name2' => 'Secondary customer name.',
					'department' => 'Customer department.',
					'taxnumber' => 'Customer tax number.',
					'vatin' => 'Customer VAT identification number.',
					'taxfree' => 'Indicates whether the customer is treated as tax exempt.',
					'currency' => 'Default customer currency.',
					'debitornumber' => 'Accounting debtor number.',
					'paymentmethod' => 'Default payment method.',
					'paymentterm' => 'Default payment term.',
					'cashdiscountdays' => 'Number of days allowed for cash discount.',
					'cashdiscountpercent' => 'Cash discount percentage.',
					'created' => 'Date and time the customer record was created.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'customer_addresses' => [
				'table' => 'address',
				'title' => 'Customer addresses',
				'description' => 'Addresses assigned to customer master records.',
				'dateField' => null,
				'conditions' => [
					'module' => 'contacts',
					'controller' => 'contact',
				],
				'columns' => [
					'id', 'parentid', 'type',
					'name1', 'name2', 'department',
					'street', 'postcode', 'city', 'country',
					'phone', 'ordering', 'clientid',
					'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal address identifier.',
					'parentid' => 'Internal identifier of the customer owning the address.',
					'type' => 'Address type, such as billing or shipping address.',
					'name1' => 'Primary address name.',
					'name2' => 'Secondary address name.',
					'department' => 'Department.',
					'street' => 'Street address.',
					'postcode' => 'Postal code.',
					'city' => 'City.',
					'country' => 'Country.',
					'phone' => 'Phone number stored with the address.',
					'ordering' => 'Display order of the address.',
					'clientid' => 'Internal Dewawi client identifier.',
					'created' => 'Date and time the address was created.',
					'deleted' => 'Logical deletion marker.',
				],
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'customers.csv',
						'targetColumn' => 'id',
						'description' => 'Customer owning this address.',
					],
				],
			],

			'quotes' => [
				'table' => 'quote',
				'title' => 'Quotes',
				'description' => 'Commercial quotations issued to customers.',
				'dateField' => 'quotedate',
				'columns' => [
					'id', 'quoteid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'quotedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'quoteid' => 'Visible quote number.',
					'quotedate' => 'Quote date.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the quote.',
					],
				],
			],

			'quote_positions' => [
				'table' => 'quotepos',
				'title' => 'Quote positions',
				'description' => 'Individual goods and service positions belonging to quotations.',
				'dateField' => null,
				'parentTable' => 'quote',
				'parentKey' => 'parentid',
				'parentDateField' => 'quotedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'quotes.csv',
						'targetColumn' => 'id',
						'description' => 'Quote containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'salesorders' => [
				'table' => 'salesorder',
				'title' => 'Sales orders',
				'description' => 'Customer orders confirmed by the company.',
				'dateField' => 'salesorderdate',
				'columns' => [
					'id', 'salesorderid', 'quoteid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'salesorderdate', 'quotedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'salesorderid' => 'Visible sales order number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderdate' => 'Sales order date.',
					'quotedate' => 'Date of the preceding quote, if available.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the sales order.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
				],
			],

			'salesorder_positions' => [
				'table' => 'salesorderpos',
				'title' => 'Sales order positions',
				'description' => 'Individual goods and service positions belonging to sales orders.',
				'dateField' => null,
				'parentTable' => 'salesorder',
				'parentKey' => 'parentid',
				'parentDateField' => 'salesorderdate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'id',
						'description' => 'Sales order containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'invoices' => [
				'table' => 'invoice',
				'title' => 'Invoices',
				'description' => 'Customer invoices issued by the company.',
				'dateField' => 'invoicedate',
				'columns' => [
					'id', 'invoiceid', 'quoteid', 'salesorderid',
					'deliveryorderid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'invoicedate', 'quotedate', 'salesorderdate',
					'deliveryorderdate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total', 'prepayment',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'invoiceid' => 'Visible invoice number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderid' => 'Visible number of the preceding sales order, if available.',
					'deliveryorderid' => 'Visible number of the preceding delivery order, if available.',
					'invoicedate' => 'Invoice date.',
					'quotedate' => 'Date of the preceding quote, if available.',
					'salesorderdate' => 'Date of the preceding sales order, if available.',
					'deliveryorderdate' => 'Date of the preceding delivery order, if available.',
					'prepayment' => 'Stored structured prepayment amount; an empty field does not prove that no advance payment occurred.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the invoice.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
					[
						'column' => 'salesorderid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'salesorderid',
						'description' => 'Preceding sales order, if available.',
					],
				],
			],

			'invoice_positions' => [
				'table' => 'invoicepos',
				'title' => 'Invoice positions',
				'description' => 'Individual goods and service positions belonging to customer invoices.',
				'dateField' => null,
				'parentTable' => 'invoice',
				'parentKey' => 'parentid',
				'parentDateField' => 'invoicedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'invoices.csv',
						'targetColumn' => 'id',
						'description' => 'Invoice containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'creditnotes' => [
				'table' => 'creditnote',
				'title' => 'Credit notes',
				'description' => 'Customer credit notes issued in relation to previous sales transactions.',
				'dateField' => 'creditnotedate',
				'columns' => [
					'id', 'creditnoteid', 'quoteid', 'salesorderid',
					'invoiceid', 'contactid', 'clientid',
					'vatin', 'header', 'footer',
					'creditnotedate', 'quotedate', 'salesorderdate',
					'invoicedate', 'orderdate', 'deliverydate',
					'paymentmethod', 'shippingmethod',
					'billingname1', 'billingname2', 'billingdepartment',
					'billingstreet', 'billingpostcode', 'billingcity', 'billingcountry',
					'shippingname1', 'shippingname2', 'shippingdepartment',
					'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry',
					'shippingphone',
					'subtotal', 'taxes', 'total',
					'currency', 'taxfree', 'state',
					'completed', 'cancelled', 'filename',
					'created', 'deleted',
				],
				'columnDescriptions' => array_merge($documentColumns, [
					'creditnoteid' => 'Visible credit note number.',
					'quoteid' => 'Visible number of the preceding quote, if available.',
					'salesorderid' => 'Visible number of the preceding sales order, if available.',
					'invoiceid' => 'Visible number of the referenced invoice, if available.',
					'creditnotedate' => 'Credit note date.',
					'quotedate' => 'Date of the preceding quote, if available.',
					'salesorderdate' => 'Date of the preceding sales order, if available.',
					'invoicedate' => 'Date of the referenced invoice, if available.',
				]),
				'relations' => [
					[
						'column' => 'contactid',
						'target' => 'customers.csv',
						'targetColumn' => 'contactid',
						'description' => 'Customer number referenced by the credit note.',
					],
					[
						'column' => 'quoteid',
						'target' => 'quotes.csv',
						'targetColumn' => 'quoteid',
						'description' => 'Preceding quote, if available.',
					],
					[
						'column' => 'salesorderid',
						'target' => 'salesorders.csv',
						'targetColumn' => 'salesorderid',
						'description' => 'Preceding sales order, if available.',
					],
					[
						'column' => 'invoiceid',
						'target' => 'invoices.csv',
						'targetColumn' => 'invoiceid',
						'description' => 'Invoice referenced by the credit note, if available.',
					],
				],
			],

			'creditnote_positions' => [
				'table' => 'creditnotepos',
				'title' => 'Credit note positions',
				'description' => 'Individual positions belonging to customer credit notes.',
				'dateField' => null,
				'parentTable' => 'creditnote',
				'parentKey' => 'parentid',
				'parentDateField' => 'creditnotedate',
				'columns' => [
					'id', 'parentid', 'itemid', 'masterid', 'possetid',
					'clientid', 'sku', 'title', 'description',
					'price', 'taxrate', 'quantity', 'total',
					'currency', 'uom', 'manufacturerid',
					'manufacturersku', 'ordering',
					'created', 'deleted',
				],
				'columnDescriptions' => $positionColumns,
				'relations' => [
					[
						'column' => 'parentid',
						'target' => 'creditnotes.csv',
						'targetColumn' => 'id',
						'description' => 'Credit note containing this position.',
					],
					[
						'column' => 'itemid',
						'target' => 'items.csv',
						'targetColumn' => 'id',
						'description' => 'Item master record referenced by the position, if available.',
					],
				],
			],

			'items' => [
				'table' => 'item',
				'title' => 'Items',
				'description' => 'Item master records referenced by document positions.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'catid', 'sku', 'gtin',
					'title', 'subtitle', 'type', 'description',
					'quantity', 'inventory', 'cost', 'price',
					'specialprice', 'margin', 'currency',
					'taxid', 'uomid', 'manufacturerid',
					'manufacturersku', 'manufacturergtin',
					'origincountry', 'created', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal item identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'catid' => 'Internal item category identifier.',
					'sku' => 'Item number.',
					'gtin' => 'Global Trade Item Number, if available.',
					'title' => 'Item name.',
					'subtitle' => 'Additional item name.',
					'type' => 'Item type.',
					'description' => 'Item description.',
					'quantity' => 'Current item quantity value.',
					'inventory' => 'Inventory management flag or state.',
					'cost' => 'Stored item cost.',
					'price' => 'Stored item sales price.',
					'specialprice' => 'Stored special sales price.',
					'margin' => 'Stored item margin.',
					'currency' => 'Currency used for item prices.',
					'taxid' => 'Reference to the tax rate master record.',
					'uomid' => 'Reference to the unit of measure master record.',
					'manufacturerid' => 'Reference to the manufacturer master record.',
					'manufacturersku' => 'Manufacturer item number.',
					'manufacturergtin' => 'Manufacturer GTIN.',
					'origincountry' => 'Country of origin.',
					'created' => 'Date and time the item record was created.',
					'deleted' => 'Logical deletion marker.',
				],
				'relations' => [
					[
						'column' => 'taxid',
						'target' => 'taxrates.csv',
						'targetColumn' => 'id',
						'description' => 'Tax rate assigned to the item, if available.',
					],
				],
			],

			'taxrates' => [
				'table' => 'taxrate',
				'title' => 'Tax rates',
				'description' => 'Tax rates configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'name', 'rate',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal tax rate identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'name' => 'Tax rate name.',
					'rate' => 'Tax rate percentage.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the tax rate record was created.',
					'modified' => 'Date and time the tax rate record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'payment_methods' => [
				'table' => 'paymentmethod',
				'title' => 'Payment methods',
				'description' => 'Payment methods configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal payment method identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'title' => 'Payment method name.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the payment method record was created.',
					'modified' => 'Date and time the payment method record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],

			'shipping_methods' => [
				'table' => 'shippingmethod',
				'title' => 'Shipping methods',
				'description' => 'Shipping and delivery methods configured in Dewawi.',
				'dateField' => null,
				'columns' => [
					'id', 'clientid', 'title',
					'ordering', 'created', 'modified', 'deleted',
				],
				'columnDescriptions' => [
					'id' => 'Internal shipping method identifier.',
					'clientid' => 'Internal Dewawi client identifier.',
					'title' => 'Shipping method name.',
					'ordering' => 'Display order.',
					'created' => 'Date and time the shipping method record was created.',
					'modified' => 'Date and time the shipping method record was last modified.',
					'deleted' => 'Logical deletion marker.',
				],
			],
		];
	}

	protected function documentTypes(): array
	{
		$types = DEEC_DocumentArchive::documents();
		return $this->reviewExport ? $types : ['sales' => $types['sales']];
	}

	protected function countDocumentTypes($stream): array
	{
		$counts = [];
		foreach ($this->documentTypes() as $module => $controllers) foreach ($controllers as $controller) $counts[$module . '/' . $controller] = ['total' => 0, 'issued' => 0, 'delivered_pdfs' => 0, 'missing_pdfs' => 0, 'deleted_issued_without_pdf' => 0, 'unresolved_original_references' => 0];
		$fields = fgetcsv($stream, 0, ';', '"', '');
		while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
			if (count($values) !== count($fields)) throw new RuntimeException('Incomplete document overview');
			$row = array_combine($fields, $values);
			$type = $row['module'] . '/' . $row['controller'];
			if (!isset($counts[$type])) throw new RuntimeException('Document overview type is outside export scope');
			$count = &$counts[$type];
			$count['total']++;
			$delivered = isset($row['pdf_supplied']) ? $row['pdf_supplied'] === '1' : $row['zip_path'] !== '';
			if ($delivered) $count['delivered_pdfs']++;
			if ($row['issued'] === '1') {
				$count['issued']++;
				if (!$delivered) { $count['missing_pdfs']++; if ($row['deleted'] !== '0') $count['deleted_issued_without_pdf']++; }
			} elseif (($row['status'] ?? '') === 'excluded' || ($row['delivery_note'] ?? '') === 'original_reference_not_supplied_issue_status_unconfirmed') $count['unresolved_original_references']++;
			unset($count);
		}
		return $counts;
	}

	protected function finalizeInventory(string $directory, array $files): void
	{
		$metadata = json_decode(file_get_contents($directory . 'metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		$metadata['csv_files'] = [];
		$text = "\n\nGelieferte CSV-Dateien (Datenzeilen ohne Kopfzeile):\n";
		foreach ($files as $file) if (str_ends_with($file, '.csv')) {
			$stream = fopen($directory . $file, 'rb');
			try { $entry = $this->csvInventory($stream, $file); } finally { fclose($stream); }
			$metadata['csv_files'][$file] = $entry;
			$text .= $file . ': ' . $entry['rows'] . "\n";
			$name = substr($file, 0, -4);
			if (isset($metadata['tables'][$name])) $metadata['tables'][$name]['rowCount'] = $entry['rows'];
		}
		foreach ($metadata['tables'] as $table) if ($table['unavailableColumns']) $text .= 'Nicht vorhandene Profilspalten in ' . $table['file'] . ': ' . implode(', ', $table['unavailableColumns']) . "\n";
		if (isset($metadata['documents'])) {
			$file = in_array('document_files.csv', $files, true) ? 'document_files.csv' : 'document_index.csv';
			$stream = fopen($directory . $file, 'rb');
			try { $counts = $this->countDocumentTypes($stream); } finally { fclose($stream); }
			$metadata['documents']['by_type'] = $counts;
			$text .= "\nBelegübersicht je Art (je Beleg einmal; Gesamt enthält auch Entwürfe):\nArt; Gesamt; technisch schreibgeschützt; PDF geliefert; schreibgeschützt ohne PDF; gelöscht und schreibgeschützt ohne PDF; ungeklärte Originalbezüge\n";
			foreach ($counts as $type => $count) $text .= $type . '; ' . implode('; ', $count) . "\n";
			$text .= "Fehlstellen: $file, mit Belegart, Nummer, Datum und " . ($this->reviewExport ? 'reason' : 'missing_reason') . ".\n";
			$text .= $this->reviewExport ? "Interner Umfang: alle acht Belegarten. PDF-Verfügbarkeit bestätigt keine Originalgleichheit.\n" : "Externer Umfang: ausschließlich sechs Verkaufsbelegarten (sales). purchases/quoterequest und purchases/purchaseorder sind ausdrücklich nicht angefordert; die interne vollständige Archivprüfung bleibt unverändert.\n";
		}
		$bytes = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		if (file_put_contents($directory . 'metadata.json', $bytes) !== strlen($bytes) || file_put_contents($directory . 'readme.txt', $text, FILE_APPEND) !== strlen($text)) throw new RuntimeException('Could not write export inventory');
	}

	protected function csvInventory($stream, string $name): array
	{
		if (!$stream) throw new RuntimeException('CSV inventory entry is unavailable');
		$escape = '';
		$fields = fgetcsv($stream, 0, ';', '"', $escape);
		if (!$fields || in_array(null, $fields, true) || count(array_unique($fields)) !== count($fields)) throw new RuntimeException('Invalid CSV inventory header');
		$count = 0;
		while (($values = fgetcsv($stream, 0, ';', '"', $escape)) !== false) {
			if (count($values) !== count($fields)) throw new RuntimeException('CSV row structure differs: ' . $name);
			$count++;
		}
		return ['rows' => $count, 'columns' => $fields];
	}

	protected function verifyCsvInventory(ZipArchive $zip): void
	{
		$metadata = json_decode($zip->getFromName('metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		$actual = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			if (!str_ends_with($name, '.csv')) continue;
			$stream = $zip->getStream($name);
			try { $actual[$name] = $this->csvInventory($stream, $name); } finally { if ($stream) fclose($stream); }
		}
		if ($actual !== $metadata['csv_files']) throw new RuntimeException('Delivered CSV inventory differs');
		if (isset($metadata['documents'])) {
			$stream = $zip->getStream(isset($metadata['documents']['overview']) ? 'document_files.csv' : 'document_index.csv');
			try { $counts = $this->countDocumentTypes($stream); } finally { fclose($stream); }
			if ($counts !== $metadata['documents']['by_type'] || $metadata['documents']['types'] !== $this->documentTypes()) throw new RuntimeException('Document type summary differs');
		}
	}

	protected function filterExistingColumns(string $table, array $columns): array
	{
		$description = $this->db->describeTable($table);
		$existing = [];

		foreach ($columns as $column) {
			if (array_key_exists($column, $description)) {
				$existing[] = $column;
			}
		}

		return $existing;
	}

	protected function writeCsv(string $filename, array $rows, array $columns): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException('Could not create CSV file: ' . $filename);
		}

		if (fputcsv($handle, $columns, ';', '"', '') === false) throw new RuntimeException('Could not write export CSV header');

		foreach ($rows as $row) {
			$line = [];

			foreach ($columns as $column) {
				$line[] = $this->normalizeValue($row[$column] ?? null);
			}

			if (fputcsv($handle, $line, ';', '"', '') === false) throw new RuntimeException('Could not write export CSV row');
		}

		if (!fflush($handle)) throw new RuntimeException('Could not flush export CSV');
		fclose($handle);
	}

	protected function normalizeValue($value)
	{
		if($value === null) {
			return '';
		}

		if($value instanceof DateTime) {
			return $value->format('Y-m-d H:i:s');
		}

		if(is_bool($value)) {
			return $value ? '1' : '0';
		}

		return (string)$value;
	}

	protected function writeDataDictionary(string $filename, array $profile): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException(
				'Could not create data dictionary: ' . $filename
			);
		}

		if (fputcsv($handle, [
			'file',
			'table',
			'title',
			'description',
			'column',
			'column_description',
			'data_type', 'length', 'precision', 'scale', 'nullable', 'primary_key',
		], ';', '"', '') === false) throw new RuntimeException('Could not write data dictionary header');

		foreach ($profile as $exportName => $config) {
			$descriptions = $config['columnDescriptions'] ?? [];

			foreach ($config['columns'] as $column) {
				$schema = $config['columnSchema'][$column];
				if (fputcsv($handle, [
					$exportName . '.csv',
					$config['table'],
					$config['title'] ?? $exportName,
					$config['description'] ?? '',
					$column,
					$descriptions[$column] ?? 'Stored field ' . $config['table'] . '.' . $column,
					$schema['DATA_TYPE'], $schema['LENGTH'], $schema['PRECISION'], $schema['SCALE'], (int)$schema['NULLABLE'], (int)$schema['PRIMARY'],
				], ';', '"', '') === false) throw new RuntimeException('Could not write data dictionary row');
			}
		}

		if (!fflush($handle)) throw new RuntimeException('Could not flush data dictionary');
		fclose($handle);
	}

	protected function writeRelationships(string $filename, array $profile): void
	{
		$handle = fopen($filename, 'w');

		if (!$handle) {
			throw new RuntimeException(
				'Could not create relationships file: ' . $filename
			);
		}

		if (fputcsv($handle, [
			'source_file',
			'source_column',
			'target_file',
			'target_column',
			'description',
		], ';', '"', '') === false) throw new RuntimeException('Could not write relationship header');

		foreach ($profile as $exportName => $config) {
			foreach ($config['relations'] ?? [] as $relation) {
				if (fputcsv($handle, [
					$exportName . '.csv',
					$relation['column'],
					$relation['target'],
					$relation['targetColumn'],
					$relation['description'] ?? '',
				], ';', '"', '') === false) throw new RuntimeException('Could not write relationship row');
			}
		}

		if (!fflush($handle)) throw new RuntimeException('Could not flush relationships');
		fclose($handle);
	}

	protected function writeMetadata(string $filename, array $profile, ?string $from, ?string $to): void
	{
		$metadata = [
			'system' => 'Dewawi',
			'exportType' => 'GoBD',
			'createdAt' => date('c'),
			'clientId' => $this->clientId,
			'tenant_identity_sha256' => $this->tenantIdentitySha256,
			'userId' => $this->userId,
			'document_status' => ['issued' => 'Legacy field: technical readonly classification from document number, completed, cancelled or state 105/106; not proof of dispatch', 'completed' => 'Stored technical completion flag; not proof of dispatch', 'state' => 'Stored workflow state; not dispatch evidence', 'cancelled' => 'Stored cancellation flag; not proof of dispatch', 'deleted' => 'Stored logical deletion flag; retained unchanged', 'dispatch_evidence' => 'not_assessed: no dispatch claim is derived from these flags'],
			'prepayment_note' => 'An empty structured prepayment field does not prove absence of advance payments. Positions, document texts and external accounting may contain evidence. No historic amounts are calculated or added.',
			'from' => $from,
			'to' => $to,
			'encoding' => 'UTF-8',
			'delimiter' => ';',
			'csv_quoting' => 'double enclosure; literal backslashes; no backslash escape',
			'decimalFormat' => '1234.56',
			'dateFormat' => 'YYYY-MM-DD',
			'dateTimeFormat' => 'YYYY-MM-DD HH:MM:SS',
			'nullValue' => 'empty field',
			'historical_evidence' => [
				'master_data' => 'current stored records; this export does not contain a complete old/new master-data change journal',
				'document_snapshots' => 'billing/shipping addresses and position descriptions stored on each document; not an independent change log or proof that the record was never modified',
				'last_modified' => 'last modification only; no previous values',
			],
			'tables' => [],
		];

		foreach($profile as $exportName => $config) {
			$metadata['tables'][$exportName] = [
				'file' => $exportName . '.csv',
				'table' => $config['table'],
				'title' => $config['title'] ?? $exportName,
				'description' => $config['description'] ?? '',
				'dateField' => $config['dateField'],
				'parentTable' => $config['parentTable'] ?? null,
				'parentKey' => $config['parentKey'] ?? null,
				'parentDateField' => $config['parentDateField'] ?? null,
				'conditions' => $config['conditions'] ?? [],
				'columns' => $config['columns'],
				'columnDescriptions' => $config['columnDescriptions'] ?? [],
				'columnSchema' => $config['columnSchema'],
				'unavailableColumns' => $config['unavailableColumns'],
				'relations' => $config['relations'] ?? [],
			];
		}

		$bytes = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		if (file_put_contents($filename, $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write export metadata');
	}

	protected function writeReadme(string $filename, ?string $from, ?string $to): void
	{
		$content = [];
		$content[] = 'Dewawi GoBD Export';
		$content[] = 'Created: ' . date('Y-m-d H:i:s');
		$content[] = 'Client ID: ' . $this->clientId;
		$content[] = 'From: ' . ($from ?: '-');
		$content[] = 'To: ' . ($to ?: '-');
		$content[] = '';
		$content[] = 'Format: CSV';
		$content[] = 'Encoding: UTF-8';
		$content[] = 'Delimiter: semicolon';
		$content[] = 'Enclosure: double quote; embedded enclosures are doubled, backslashes are literal.';
		$content[] = 'Decimal format: 1234.56';
		$content[] = 'Date format: YYYY-MM-DD';
		$content[] = 'Date/time format: YYYY-MM-DD HH:MM:SS';
		$content[] = 'NULL values are represented as empty CSV fields.';
		$content[] = '';
		$content[] = 'The selected date range is applied to dated sales documents and their positions.';
		$content[] = 'Master and reference data are exported independently of the selected date range so referenced records remain available for interpretation.';
		$content[] = 'Rows marked with deleted = 1 are included when present in the source data.';
		$content[] = 'The export represents records stored in Dewawi at the time of export and does not reconstruct previous versions of modified records.';
		$content[] = 'Document title, subject, reference, info and notes fields are intentionally excluded from the export.';
		$content[] = 'Document header and footer fields remain included.';
		$content[] = 'Billing and shipping address fields represent the address values stored directly on each document.';
		$content[] = 'Customer addresses are exported separately and are restricted to addresses assigned to contact master records.';
		$content[] = 'issued is a legacy technical readonly classification, not proof of dispatch. completed, state, cancelled and deleted retain their stored values. No sent status is inferred, including for historical correction variants.';
		$content[] = 'An empty structured prepayment value does not prove absence of advance payments. Document positions, stored texts and external accounting may provide evidence. No historical prepayment values are calculated or added.';
		$content[] = 'Generic modified fields are excluded from fiscal document exports because they may include non-fiscal internal updates.';
		$content[] = 'data_dictionary.csv describes the meaning of exported files and columns.';
		$content[] = 'relationships.csv describes known relationships between exported datasets.';
		$content[] = 'metadata.json describes export settings, datasets, filters and exported columns.';
		$content[] = '';
		$content[] = 'Software-/Exportbeschreibung: DEWAWI ist eine mandantenfähige Warenwirtschaft mit Verkaufsbelegen, Positionen und Stamm-/Referenzdaten. Der Export liest gespeicherte Werte; er berechnet keine historischen Beträge neu und verändert keine Geschäftsdaten oder Archivregistrierungen.';
		$content[] = 'Belegnummern sind sichtbare Geschäftsnummern; id ist der interne Datensatzschlüssel. Beziehungen gelten zusammen mit clientid. Beziehungen zu zeitlich früheren Belegen können außerhalb des gewählten Zeitraums liegen; daraus wird kein Datensatz erfunden.';
		$content[] = 'Stammdatenhistorie: Geliefert wird der aktuell gespeicherte Stand. Der Export enthält keine vollständigen Feldänderungsfolgen mit Alt-/Neuwerten. Letzte Änderungszeitpunkte ersetzen kein Änderungsjournal.';
		$content[] = 'Gespeicherte Belegadressen und Positionsbeschreibungen sind Beleg-Snapshots, keine Änderungslogs und kein Nachweis unveränderter Ursprungswerte. Frühere Stammdatenänderungen müssen gegebenenfalls durch gesonderte Sicherungen oder externe Nachweise belegt werden; sie werden nicht rekonstruiert oder erfunden.';
		$content[] = 'Datentypen, Längen, Präzision, Nachkommastellen, NULL-Zulässigkeit und Primärschlüssel stammen aus dem tatsächlichen Datenbankschema (data_dictionary.csv und metadata.json/columnSchema). Fehlende Profilspalten stehen unter unavailableColumns; CSV-Leerfelder unterscheiden NULL nicht von leeren Zeichenketten.';
		$content[] = 'Zeitraumgrenze: Maßgeblich ist ausschließlich das gespeicherte Belegdatum (einschließlich beider Grenzen), kein Archivierungsdatum. Undatierte oder außerhalb liegende Belege sind nicht diesem Zeitraum zugeordnet; Vollständigkeit gilt ausschließlich für den genannten Umfang. Fehlende Dateinamen und Löschkennzeichen entfernen keine datierten Belege aus der Übersicht. Der vollständige mandantenweite Archivaudit bleibt für undatierte Fälle erforderlich.';

		$bytes = implode("\n", $content);
		if (file_put_contents($filename, $bytes) !== strlen($bytes)) throw new RuntimeException('Could not write export README');
	}

	protected function createZip(string $filePath, array $files, string $zipFileName): void
	{
		$zip = new ZipArchive();
		$path = $filePath . $zipFileName;
		if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
			throw new RuntimeException('Could not create ZIP archive.');
		}
		$expected = $this->documentFiles;
		try {
			foreach ($files as $file) {
				$source = $filePath . $file;
				if (!is_file($source) || !is_readable($source) || is_link($source)) throw new RuntimeException('Export source is unavailable');
				$expected[$file] = ['path' => $source, 'size' => filesize($source), 'sha256' => hash_file('sha256', $source)];
			}
			foreach ($expected as $name => $entry) if (!$zip->addFile($entry['path'], $name)) throw new RuntimeException('Could not add export entry');
			if (!$zip->close()) throw new RuntimeException('Could not finish ZIP archive');
		} catch (Throwable $exception) {
			try { $zip->close(); } catch (Throwable $ignored) {}
			throw $exception;
		}
		$this->verifyZip($path, $expected);
		foreach ($this->documentFiles as $entry) if (filesize($entry['path']) !== $entry['size'] || hash_file('sha256', $entry['path']) !== $entry['sha256']) throw new RuntimeException('Archive original changed during export');
	}

	protected function verifyZip(string $path, array $expected): void
	{
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('ZIP integrity check failed');
		try {
			if ($zip->numFiles !== count($expected)) throw new RuntimeException('ZIP entries are incomplete');
			foreach ($expected as $name => $entry) {
				$stat = $zip->statName($name);
				$stream = $zip->getStream($name);
				if (!$stat || !$stream || $stat['size'] !== $entry['size']) throw new RuntimeException('ZIP entry size differs');
				$hash = hash_init('sha256');
				try { $size = hash_update_stream($hash, $stream); } finally { fclose($stream); }
				if ($size !== $entry['size'] || hash_final($hash) !== $entry['sha256']) throw new RuntimeException('ZIP entry hash differs');
			}
			if (isset($expected['document_manifest.csv'])) $this->verifyDocumentManifest($zip, $expected);
			if (isset($expected['document_files.csv'])) $this->verifyDelivery($zip);
			$this->verifyCsvInventory($zip);
		} finally { $zip->close(); }
	}

	protected function verifyDocumentManifest(ZipArchive $zip, array $expected): void
	{
		$lookup = [];
		$seen = [];
		$stream = $zip->getStream('document_manifest.csv');
		try {
			$fields = fgetcsv($stream, 0, ';', '"', '');
			while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
				if (count($fields) !== count($values)) throw new RuntimeException('Invalid document manifest row');
				$row = array_combine($fields, $values);
				if ($this->reviewExport && $row['zip_path'] === '') {
					if (!in_array($row['status'], ['missing', 'invalid', 'excluded'], true)) throw new RuntimeException('Review manifest omits a required PDF');
					continue;
				}
				$entry = $expected[$row['zip_path']] ?? null;
				if (!$entry || isset($seen[$row['zip_path']]) || (int)$row['filesize'] !== $entry['size'] || $row['sha256'] !== $entry['sha256'] || (int)$row['clientid'] !== $this->clientId) throw new RuntimeException('Document manifest differs from ZIP entries');
				$seen[$row['zip_path']] = true;
				$file = $row['csv_file'];
				if (!isset($lookup[$file])) {
					if (!isset($expected[$file])) throw new RuntimeException('Referenced CSV is missing');
					$csv = $zip->getStream($file);
					try {
						$escape = '';
						$columns = fgetcsv($csv, 0, ';', '"', $escape);
						$column = array_search($row['csv_key_column'], $columns, true);
						if ($column === false) throw new RuntimeException('Referenced CSV key is missing');
						$lookup[$file] = [];
						while (($record = fgetcsv($csv, 0, ';', '"', $escape)) !== false) $lookup[$file][(string)$record[$column]] = true;
					} finally { fclose($csv); }
				}
				if (!isset($lookup[$file][$row['csv_key_value']])) throw new RuntimeException('Referenced CSV row is missing');
			}
		} finally { fclose($stream); }
		if (count($seen) !== count($this->documentFiles)) throw new RuntimeException('Document manifest is incomplete');
		$this->verifyDocumentInventory($zip);
		$stream = $zip->getStream('checksums.sha256');
		$checksums = [];
		try {
			while (($line = fgets($stream)) !== false) {
				[$sha, $name] = explode('  ', rtrim($line, "\r\n"), 2);
				if (!isset($expected[$name]) || isset($checksums[$name]) || $name === 'checksums.sha256' || $expected[$name]['sha256'] !== $sha) throw new RuntimeException('Checksum manifest differs');
				$checksums[$name] = true;
			}
		} finally { fclose($stream); }
		if (count($checksums) !== count($expected) - 1) throw new RuntimeException('Checksum manifest is incomplete');
	}

	protected function verifyDocumentInventory(ZipArchive $zip): void
	{
		if ($this->reviewExport) { $this->verifyReviewInventory($zip); return; }
		$metadata = json_decode($zip->getFromName('metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		$summary = $metadata['documents']['summary'];
		$sets = [];
		foreach (['document_index', 'document_manifest', 'missing_documents', 'excluded_documents'] as $file) {
			$sets[$file] = [];
			$stream = $zip->getStream($file . '.csv');
			try {
				$fields = fgetcsv($stream, 0, ';', '"', '');
				while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
					if (count($values) !== count($fields)) throw new RuntimeException('Incomplete document inventory row');
					$row = array_combine($fields, $values);
					$key = $row['clientid'] . '/' . $row['module'] . '/' . $row['controller'] . '/' . $row['documentid'];
					if ($key !== $row['record_key'] || (int)$row['clientid'] !== $this->clientId || isset($sets[$file][$key]) || $row['document_date'] < $metadata['from'] || $row['document_date'] > $metadata['to']) throw new RuntimeException('Document inventory identity or period differs');
					$sets[$file][$key] = ['status' => $row['status'], 'issued' => $row['issued'], 'deleted' => $row['deleted']];
				}
			} finally { fclose($stream); }
		}
		$counts = ['included' => 0, 'missing' => 0, 'excluded' => 0, 'issued' => 0, 'deleted_issued' => 0];
		foreach ($sets['document_index'] as $key => $row) {
			$included = $row['status'] === 'included';
			$missing = in_array($row['status'], ['missing', 'excluded'], true);
			$excluded = $row['status'] === 'excluded' || ($row['issued'] === '1' && $row['deleted'] !== '0');
			foreach (['document_manifest' => $included, 'missing_documents' => $missing, 'excluded_documents' => $excluded] as $file => $required) if (isset($sets[$file][$key]) !== $required) throw new RuntimeException('Document inventory and manifests differ');
			if (isset($counts[$row['status']])) $counts[$row['status']]++;
			if ($row['issued'] === '1') { $counts['issued']++; if ($row['deleted'] !== '0') $counts['deleted_issued']++; }
		}
		foreach (['document_manifest', 'missing_documents', 'excluded_documents'] as $file) if (array_diff_key($sets[$file], $sets['document_index'])) throw new RuntimeException('Orphan document manifest row');
		foreach ($counts as $name => $count) if ($summary[$name] !== $count) throw new RuntimeException('Document summary differs from inventory');
		if ($summary['dated_documents'] !== count($sets['document_index']) || $summary['complete'] !== ($counts['missing'] === 0 && $counts['excluded'] === 0)) throw new RuntimeException('Document completeness statement differs');
	}

	protected function verifyReviewInventory(ZipArchive $zip): void
	{
		$metadata = json_decode($zip->getFromName('metadata.json'), true, 512, JSON_THROW_ON_ERROR);
		if (($metadata['export_kind'] ?? '') !== 'internal_document_review') throw new RuntimeException('Review export kind is missing');
		$sets = []; $index = []; $documents = []; $expectedReports = ['missing_documents' => [], 'excluded_documents' => []];
		$issued = 0; $deleted = 0; $undated = 0;
		$counts = ['archived_original' => 0, DEEC_DocumentReview::STATUS => 0, DEEC_DocumentReview::DIAGNOSTIC_STATUS => 0, DEEC_DocumentReview::UNREVIEWED_STATUS => 0, 'missing' => 0, 'invalid' => 0, 'excluded' => 0];
		foreach (['document_index', 'document_manifest', 'missing_documents', 'excluded_documents'] as $file) {
			$sets[$file] = [];
			$stream = $zip->getStream($file . '.csv');
			try {
				$fields = fgetcsv($stream, 0, ';', '"', '');
				while (($values = fgetcsv($stream, 0, ';', '"', '')) !== false) {
					if (count($values) !== count($fields)) throw new RuntimeException('Invalid review inventory row');
					$row = array_combine($fields, $values);
					$key = $row['clientid'] . '/' . $row['module'] . '/' . $row['controller'] . '/' . $row['documentid'];
					$unknownDate = $row['date_scope'] === 'undated_diagnostic_not_assigned_to_period';
					$dateValid = $unknownDate ? in_array($row['document_date'], ['', '0000-00-00'], true) && in_array($row['status'], [DEEC_DocumentReview::DIAGNOSTIC_STATUS, 'invalid'], true) : $row['date_scope'] === 'selected_period' && $row['document_date'] >= $metadata['from'] && $row['document_date'] <= $metadata['to'];
					if ($row['record_key'] !== $key || (int)$row['clientid'] !== $this->clientId || !DEEC_DocumentArchive::validDocumentType($row['module'], $row['controller']) || !$dateValid || !array_key_exists($row['status'], $counts)) throw new RuntimeException('Review inventory identity, period or status differs');
					$identity = hash('sha256', json_encode(array_intersect_key($row, array_flip(['document_number', 'document_date', 'date_scope', 'deleted', 'issued', 'filename'])), JSON_THROW_ON_ERROR));
					if ($file === 'document_index') {
						if (isset($index[$key])) throw new RuntimeException('Duplicate review document index');
						$index[$key] = $identity;
						if ($unknownDate) $undated++;
						if ($row['issued']) { $issued++; if ($row['deleted']) $deleted++; }
						continue;
					}
					if (($index[$key] ?? null) !== $identity) throw new RuntimeException('Review manifest and document index differ');
					if ($row['csv_file'] !== 'document_index.csv' || $row['csv_key_column'] !== 'record_key' || $row['csv_key_value'] !== $key) throw new RuntimeException('Review index reference differs');
					$pdf = in_array($row['status'], ['archived_original', DEEC_DocumentReview::STATUS, DEEC_DocumentReview::DIAGNOSTIC_STATUS, DEEC_DocumentReview::UNREVIEWED_STATUS], true);
					if ($pdf) {
						$prefix = ($row['status'] === 'archived_original' ? 'originals' : ($row['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS ? 'diagnostics' : ($row['status'] === DEEC_DocumentReview::UNREVIEWED_STATUS ? 'candidates' : 'review'))) . '/' . $row['module'] . '/' . $row['controller'] . '/';
						if (!str_starts_with($row['zip_path'], $prefix) || !$row['issued'] || ($row['status'] !== 'archived_original' && $row['deleted'])) throw new RuntimeException('Original and review PDF separation differs');
						if ($row['status'] === DEEC_DocumentReview::STATUS && ($row['evidence_status'] !== 'current_published_reconstruction' || !preg_match('/^[a-f0-9]{64}$/D', $row['proof_sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $row['snapshot_sha256']))) throw new RuntimeException('Review evidence is incomplete');
						if ($row['status'] === DEEC_DocumentReview::DIAGNOSTIC_STATUS && ($row['evidence_status'] !== 'diagnostic_only_not_validated' || json_decode($row['database_confirmation'], true, 512, JSON_THROW_ON_ERROR)['database_values_verified'] !== false || $row['diagnostic_issues'] === '')) throw new RuntimeException('Diagnostic status or evidence differs');
					} elseif ($row['zip_path'] !== '') throw new RuntimeException('Unresolved review case contains a PDF');
					$hash = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
					if (isset($sets[$file][$hash])) throw new RuntimeException('Duplicate review manifest row');
					$sets[$file][$hash] = true;
					if ($file === 'document_manifest') {
						$documents[$key] = true;
						$counts[$row['status']]++;
						if (in_array($row['status'], ['missing', 'invalid', 'excluded'], true)) $expectedReports['missing_documents'][$hash] = true;
						if ($row['status'] === 'excluded' || ($row['issued'] && $row['deleted'])) $expectedReports['excluded_documents'][$hash] = true;
					}
				}
			} finally { fclose($stream); }
		}
		foreach ($expectedReports as $file => $required) if (array_diff_key($sets[$file], $required) || array_diff_key($required, $sets[$file])) throw new RuntimeException('Review reports differ from manifest');
		$summary = $metadata['documents']['summary'];
		foreach ($counts as $status => $count) if ($summary[$status] !== $count) throw new RuntimeException('Review status counts differ');
		if (array_diff_key($index, $documents) || count($index) !== count($documents) || $summary['documents'] !== count($index) || $summary['dated_documents'] !== count($index) - $undated || $summary['undated_diagnostic_documents'] !== $undated || $summary['issued'] !== $issued || $summary['deleted_issued'] !== $deleted
			|| $summary['complete'] !== ($counts['missing'] === 0 && $counts['invalid'] === 0 && $counts['excluded'] === 0)) throw new RuntimeException('Review scope or availability statement differs');
	}

	protected function buildZipFileName(?string $from, ?string $to): string
	{
		return 'gobd-export-'
			. $this->formatDateForFilename($from)
			. '-'
			. $this->formatDateForFilename($to)
			. '-'
			. date('Ymd-His')
			. '.zip';
	}

	protected function formatDateForFilename(?string $date): string
	{
		if (!$date) {
			return 'unknown';
		}

		$timestamp = strtotime($date);

		if (!$timestamp) {
			return 'unknown';
		}

		return date('Ymd', $timestamp);
	}

	protected function quoteIdentifier(string $name): string
	{
		return '`' . str_replace('`', '``', $name) . '`';
	}
}
