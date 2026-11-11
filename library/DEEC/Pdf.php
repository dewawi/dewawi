<?php

class DEEC_Pdf
{
	public $document = [];
	public $template = [];
	public $shop = [];
	protected $translator = null;

	/*public function Header()
	{
		if ($this->page <= 1 || empty($this->document)) return;

		// Top title
		$this->SetFont('freesansb', 'B', 11);
		$this->SetXY(15, 12);
		$this->Cell(120, 6, mb_strtoupper($this->document['title'] ?? 'ANGEBOT', 'UTF-8'), 0, 1, 'L', false, '', 1);

		// Info line
		$nr = $this->document['documentid'] ?? '';
		$date = isset($this->document['documentdate']) ? (new DateTime($this->document['documentdate']))->format('d.m.Y') : '';
		$cid = $this->document['contactid'] ?? '';

		$this->SetFont('freesans', '', 9);
		$this->SetXY(15, 18);
		$line = "Angebotsnummer: {$nr} Angebotsdatum: {$date} Kundennummer: {$cid}";
		$this->Cell(170, 6, $line, 0, 1, 'L', false, '', 1);

		// Logo
		$website = !empty($template['website']) ? $template['website'] : ($shop['website'] ?? '');
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

		// small separator line
		$this->SetLineWidth(0.1);
		$this->Line(15, 26, 195, 26);
		$this->SetY(30);
	}

	public function Footer()
	{
		// page numbers centered
		$this->SetY(-15);
		$this->SetFont('freesans', '', 8);
		$this->Cell(0, 10, sprintf('Seite %d / %d', $this->getAliasNumPage(), $this->getAliasNbPages()), 0, 0, 'C');
	}*/

	public function getTranslator()
	{
		if ($this->translator) return $this->translator;
		if (class_exists('Zend_Registry') && Zend_Registry::isRegistered('DEEC_Translate')) {
			$this->translator = Zend_Registry::get('DEEC_Translate');
		}

		return $this->translator;
	}

	public function setTranslator(DEEC_Translate $translator): self
	{
		$this->translator = $translator;
		return $this;
	}

	public function translate(string $key, array $args = []): string
	{
		$t = $this->getTranslator();
		if ($t && method_exists($t, 't')) {
			return $t->t($key, $args);
		}
		return $key;
	}

	protected function translateError($code): string
	{
		switch ((string)$code) {
			case 'required':
				return $this->translate('VALIDATION_REQUIRED');
			case 'email':
				return $this->translate('VALIDATION_EMAIL');
			case 'number':
				return $this->translate('VALIDATION_NUMBER');
			case 'min':
				return $this->translate('VALIDATION_MIN');
			case 'max':
				return $this->translate('VALIDATION_MAX');
			case 'pattern':
				return $this->translate('VALIDATION_PATTERN');
			default:
				return $this->translate('VALIDATION_INVALID');
		}
	}

	public function generate(array $request): array
	{
		$payload = $this->buildPayload($request);
		$file = $this->getOutputFile($payload);

		if (empty($payload['meta']['overwrite']) && $file['exists']) {
			self::assertCompletePdfFile($file['path']);
			if (!empty($payload['meta']['createOriginal'])) throw new RuntimeException('Original PDF path is already occupied');
			return $file;
		}

		if (DEEC_Permission::isDocumentReadonly($payload['meta']['module'], $payload['meta']['controller'], $payload['document'])
			&& empty($request['createOriginal'])) {
			throw new RuntimeException('Original PDF is unavailable');
		}

		$this->bootstrapTcpdf();
		$payload = $this->buildDocumentPayload($payload);

		if (empty($payload['document']['id'])) {
			throw new RuntimeException('Document payload is invalid');
		}

		$pdf = $this->initPdf(
			$this->buildPdfTitle($payload),
			$payload['document'],
			$payload['template']
		);

		$this->renderPayload($pdf, $payload);

		return $this->outputPdf($pdf, $payload);
	}

	public function reconstruct(int $id, string $module, string $controller): array
	{
		if (PHP_SAPI !== 'cli' || !DEEC_DocumentArchive::validDocumentType($module, $controller)) throw new RuntimeException('PDF reconstruction is CLI-only');
		$permission = new DEEC_Permission((array)Zend_Registry::get('User'));
		$context = $permission->getObjectContext($id, $module, $controller);
		if (!$context || !$permission->hasPermission($module, $controller, 'view') || !$permission->hasPermission($module, $controller, 'edit')) throw new RuntimeException('PDF reconstruction access denied');
		$document = $context['parent'];
		if (empty($document[$controller . 'id']) || empty($document[$controller . 'date']) || empty($document['currency']) || !DEEC_Permission::isDocumentReadonly($module, $controller, $document)) throw new RuntimeException('Issued document number, date or currency is missing');
		$date = (string)$document[$controller . 'date'];
		if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) throw new RuntimeException('Stored document date is invalid');
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller) . 'pos';
		$positions = (new $class())->setClientId($context['clientid'])->getPositions($id);
		if (!count($positions)) throw new RuntimeException('Stored document positions are missing');
		[$calculation, $validation] = $this->validateReconstructionAmounts($document, $positions, $module, $controller, $context['clientid']);
		$payload = $this->buildDocumentPayload(['meta' => ['module' => $module, 'controller' => $controller, 'templateid' => null, 'historical_snapshot' => true], 'document' => $document]);
		$validation['displayed'] = $this->assertReconstructionPayload($payload, $document, $positions, $calculation);
		if (!empty($payload['historical_recipient']['original_comparison_required'])) {
			$validation['warnings'][] = ['code' => 'stored_recipient_snapshot', 'evidence' => $payload['historical_recipient']];
			$validation['original_comparison_required'] = true;
		}
		$this->bootstrapTcpdf();
		$pdf = $this->initPdf($this->buildPdfTitle($payload), $payload['document'], $payload['template']);
		$this->renderPayload($pdf, $payload);
		return ['bytes' => $pdf->Output('', 'S'), 'validation' => $validation, 'dependencies' => [
			'template' => $payload['template'], 'template_sha256' => hash('sha256', json_encode($payload['template'], JSON_THROW_ON_ERROR)),
			'payload_sha256' => hash('sha256', serialize($payload)), 'renderer_sha256' => hash_file('sha256', __FILE__),
			'locale' => (string)Zend_Registry::get('Zend_Locale'), 'historical_layout_guaranteed' => false,
			'recipient' => $payload['historical_recipient'] ?? null,
			'notice' => 'Saved document and position amounts; current stored template, master data, images, fonts and translations. Manual comparison required.',
		]];
	}

	protected function validateReconstructionAmounts(array $document, $positions, string $module, string $controller, int $clientId): array
	{
		$lines = [];
		$rules = [];
		$priceRule = Zend_Controller_Action_HelperBroker::getStaticHelper('PriceRule');
		foreach ($positions as $position) {
			foreach (['price', 'quantity', 'taxrate'] as $field) if (!isset($position[$field]) || !is_numeric($position[$field]) || !is_finite((float)$position[$field])) throw new RuntimeException('Stored position amounts are incomplete');
			if ($position['total'] !== null && $position['total'] !== '' && (!is_numeric($position['total']) || !is_finite((float)$position['total']))) throw new RuntimeException('Stored position total is invalid');
			if ((float)$position['taxrate'] < 0 || (!empty($position['currency']) && $position['currency'] !== $document['currency'])) throw new RuntimeException('Stored position tax rate or currency is inconsistent');
			$id = (int)$position['id'];
			$lines[$id] = $position;
			$rules[$id] = $priceRule->getPriceRulePositions($module, $controller . 'pos', $id, $clientId);
			foreach ($rules[$id] as $rule) if (!is_numeric($rule['amount']) || !is_finite((float)$rule['amount']) || (!in_array($rule['action'], ['bypercent', 'byfixed', 'topercent', 'tofixed'], true) && !(in_array($rule['action'], ['', null], true) && (float)$rule['amount'] === 0.0))) throw new RuntimeException('Stored position price rule is invalid');
		}
		foreach ($lines as $id => $position) {
			$masterId = (int)$position['masterid'];
			if ($masterId && (!isset($lines[$masterId]) || $masterId === $id || !empty($lines[$masterId]['masterid']) || $rules[$id])) throw new RuntimeException('Stored position master or inherited price rules are inconsistent');
		}
		$calculation = Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->calculate($document, $positions, $module, $controller, $clientId);
		$validation = ['schema_version' => 1, 'currency' => $document['currency'], 'rounding_limit' => 0.10, 'header' => [], 'positions' => [], 'warnings' => [], 'original_comparison_required' => false];
		$current = ['subtotal' => $calculation['row']['subtotal'], 'taxes' => $calculation['row']['taxes']['total'], 'total' => $calculation['row']['total']];
		$rawNet = $rawTax = $lineTax = 0.0;
		foreach ($lines as $id => $position) {
			$amounts = $calculation['row'][$id];
			$rate = empty($document['taxfree']) ? (float)$position['taxrate'] : 0.0;
			$net = $amounts['price'] * (float)$position['quantity'];
			$gross = $net * (1 + $rate / 100);
			$rawNet += $net;
			$rawTax += $net * $rate / 100;
			$lineTax += round($amounts['total'] * $rate / 100, 2);
			$stored = $position['total'] !== null && $position['total'] !== '' ? (float)$position['total'] : null;
			$deltaNet = $stored === null ? null : round($stored - $amounts['total'], 8);
			$deltaGross = $stored === null ? null : round($stored - $amounts['gross'], 8);
			$classification = 'historical_position_total_conflict';
			$roundingBasis = null;
			if ($stored === null) $classification = 'stored_total_missing';
			elseif (abs($deltaGross) < 0.000001) $classification = 'matches_gross';
			elseif (abs($deltaNet) < 0.000001) $classification = 'matches_net';
			else {
				// A small difference alone is not evidence of historical rounding.
				$variants = ['unrounded_net' => [$net, 'net', $deltaNet], 'unrounded_gross' => [$gross, 'gross', $deltaGross],
					'rounded_gross' => [round($gross, 2), 'gross', $deltaGross], 'rounded_unit_price_net' => [round($amounts['price'], 2) * (float)$position['quantity'], 'net', $deltaNet]];
				foreach ($variants as $name => [$value, $reference, $difference]) if (abs($stored - $value) < 0.000001 && abs($difference) <= 0.100000001) {
					$classification = 'historical_rounding';
					$roundingBasis = ['variant' => $name, 'value' => $value, 'reference' => $reference, 'difference' => $difference];
					break;
				}
			}
			$masterId = (int)$position['masterid'];
			$ruleSource = $masterId && !empty($lines[$masterId]['pricerulemaster']) ? $masterId : ($masterId ? null : $id);
			$validation['positions'][$id] = ['stored_price' => $position['price'], 'stored_quantity' => $position['quantity'], 'stored_taxrate' => $position['taxrate'], 'stored_total' => $position['total'] ?? null,
				'calculated_price' => $amounts['price'], 'calculated_net' => $amounts['total'], 'calculated_gross' => $amounts['gross'], 'effective_taxrate' => $rate,
				'difference_to_net' => $deltaNet, 'difference_to_gross' => $deltaGross, 'classification' => $classification, 'rounding_basis' => $roundingBasis, 'price_rule_source' => $ruleSource, 'applicable_price_rules' => $ruleSource === null ? [] : $rules[$ruleSource]];
			if ($classification !== 'matches_gross') $validation['warnings'][] = ['code' => $classification, 'positionid' => $id];
			foreach ($rules[$id] as $rule) if (in_array($rule['action'], ['', null], true)) $validation['warnings'][] = ['code' => 'zero_amount_price_rule_without_action', 'positionid' => $id, 'rule' => $rule];
		}
		$variants = ['current' => $current, 'legacy_unrounded' => ['subtotal' => $rawNet, 'taxes' => $rawTax, 'total' => $rawNet + $rawTax],
			'line_rounded_tax' => ['subtotal' => $current['subtotal'], 'taxes' => round($lineTax, 2), 'total' => round($current['subtotal'] + $lineTax, 2)]];
		foreach ($current as $field => $value) {
			if (!isset($document[$field]) || !is_numeric($document[$field]) || !is_finite((float)$document[$field]) || abs((float)$document[$field] - $value) > 0.100000001) throw new RuntimeException('Stored document amounts cannot be reproduced within the historical rounding limit');
			$validation['header'][$field] = ['stored' => $document[$field], 'calculated' => $value, 'difference' => round((float)$document[$field] - $value, 8)];
		}
		if (abs((float)$document['subtotal'] + (float)$document['taxes'] - (float)$document['total']) >= 0.005) throw new RuntimeException('Stored document header is internally inconsistent');
		$basis = null;
		foreach ($variants as $name => $values) {
			$matches = true;
			foreach ($values as $field => $value) if (abs((float)$document[$field] - $value) >= 0.005) $matches = false;
			if ($matches) { $basis = $name; break; }
		}
		if ($basis === null) throw new RuntimeException('Stored document amounts have no reproducible historical rounding basis');
		$validation['header_basis'] = $basis;
		$validation['header_reproduced'] = $variants[$basis];
		if ($basis !== 'current') $validation['warnings'][] = ['code' => 'historical_header_rounding', 'basis' => $basis];
		$validation['original_comparison_required'] = (bool)$validation['warnings'];
		return [$calculation, $validation];
	}

	protected function assertReconstructionPayload(array $payload, array $document, $positions, array $calculation): array
	{
		$currency = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency')->getCurrency($document['currency'], 'USE_SYMBOL');
		$displayed = ['header' => [], 'positions' => [], 'taxgroups' => $payload['document']['taxgroups'] ?? []];
		foreach (['subtotal', 'taxes', 'total'] as $field) {
			$expected = $currency->toCurrency($document[$field]);
			if (($payload['document'][$field] ?? null) !== $expected) throw new RuntimeException('PDF header differs from the stored reconstruction basis');
			$displayed['header'][$field] = $expected;
		}
		$expected = [];
		foreach ($positions as $position) {
			$precision = floor($position['quantity']) == $position['quantity'] ? 0 : (round($position['quantity'], 2) == $position['quantity'] ? 2 : 4);
			$expected[(int)$position['id']] = $calculation['locale'][$position['id']] + ['quantity' => Zend_Locale_Format::toNumber($position['quantity'], ['precision' => $precision, 'locale' => Zend_Registry::get('Zend_Locale')]), 'masterid' => (int)$position['masterid']];
		}
		foreach ($payload['positions'] as $position) {
			$id = (int)$position['id'];
			if (!isset($expected[$id])) throw new RuntimeException('PDF position differs from the stored reconstruction basis');
			foreach (['price', 'total', 'quantity'] as $field) if ((string)$position[$field] !== (string)$expected[$id][$field]) throw new RuntimeException('PDF position amount differs from the calculated reconstruction basis');
			if ((int)$position['masterid'] !== $expected[$id]['masterid']) throw new RuntimeException('PDF position hierarchy differs from the stored reconstruction basis');
			$displayed['positions'][$id] = $expected[$id];
			unset($expected[$id]);
		}
		if ($expected) throw new RuntimeException('PDF omits stored positions');
		return $displayed;
	}

	protected function bootstrapTcpdf(): void
	{
		require_once BASE_PATH . '/library/Tcpdf/config/tcpdf_config.php';
		require_once BASE_PATH . '/library/Tcpdf/tcpdf.php';
	}

	protected function buildPayload(array $request): array
	{
		$module = (string)($request['module'] ?? '');
		$controller = (string)($request['controller'] ?? '');
		$documentId = (int)($request['documentId'] ?? 0);
		$output = (string)($request['output'] ?? 'file');
		$templateId = isset($request['templateid']) ? (int)$request['templateid'] : 0;

		if ($module === '' || $controller === '' || $documentId <= 0) {
			throw new InvalidArgumentException('Invalid PDF request');
		}

		$documentDb = $this->getDocumentDb($module, $controller);
		$document = $documentDb->getById($documentId);

		if (!$document) {
			throw new RuntimeException('Document not found');
		}

		$issued = !empty($document[$this->getDocumentIdField($controller)])
			|| ($module === 'sales' && in_array($controller, ['invoice', 'creditnote'], true)
				&& DEEC_Permission::isDocumentReadonly($module, $controller, $document));

		if ($issued && (empty($document['clientid']) || empty($document['contactid']) || empty($document['filename']))) {
			throw new RuntimeException('Issued document storage is incomplete');
		}

		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;
		$archiveModule = $module === 'shops' && $controller === 'quote' ? 'sales' : $module;
		$archiveController = $module === 'shops' && $controller === 'quote' ? 'quote' : $controller;

		return [
			'meta' => [
				'module' => $module,
				'controller' => $controller,
				'documentId' => $documentId,
				'output' => $output,
				'templateid' => $issued ? null : ($templateId ?: null),
				'storage' => $issued ? 'archive' : (string)($request['storage'] ?? 'cache'),
				'overwrite' => !$issued && !empty($request['overwrite']),
				'createOriginal' => !empty($request['createOriginal']),
				'baseUrl' => $view->baseUrl(),
				'archiveModule' => $archiveModule,
				'archiveController' => $archiveController,
			],
			'document' => $document,
			'clientId' => (int)($document['clientid'] ?? 0),
		];
	}

	protected function buildPdfTitle(array $payload): string
	{
		$document = $payload['document'];
		$controller = $payload['meta']['controller'] ?? 'document';

		$title = $this->translate(mb_strtoupper($controller));
		$docIdField = $this->getDocumentIdField($controller);

		return $title . ' ' . ($document[$docIdField] ?? $document['id'] ?? '');
	}

	protected function buildDocumentPayload(array $payload): array
	{
		$module = $payload['meta']['module'];
		$controller = $payload['meta']['controller'];
		$document = $payload['document'];

		$options = ['templateid' => $payload['meta']['templateid']];
		if (!empty($payload['meta']['historical_snapshot'])) $options['historical_snapshot'] = true;
		$data = $this->resolveDocumentPayloadData($module, $controller, $document, $options);

		$template = $data['template'] ?? [];
		$resolvedDocument = $data['document'] ?? $document;

		$responsibleUser = [];

		if (!empty($resolvedDocument['responsibleid'])) {
			$users = new Users_Model_DbTable_User();
			$user = $users->getUser((int)$resolvedDocument['responsibleid']);

			if (
				$user &&
				(int)$user['clientid'] === (int)$resolvedDocument['clientid']
			) {
				$responsibleUser = $user;
			}
		}

		return array_merge($payload, [
			'document' => $resolvedDocument,
			'contact' => (array)($data['contact'] ?? []),
			'historical_recipient' => $data['historical_recipient'] ?? null,
			'responsibleUser' => $responsibleUser,
			'clientId' => (int)($resolvedDocument['clientid'] ?? 0),
			'template' => (array)$template,
			'positions' => $data['positions'] ?? [],
			'options' => $data['options'] ?? [],
			'optionSets' => $data['optionSets'] ?? [],
			'attributesByGroup' => $data['attributesByGroup'] ?? [],
			'settings' => $data['settings'],
			'extra' => [
				'items' => $data['items'] ?? [],
				'categories' => $data['categories'] ?? [],
				'media' => $data['media'] ?? [],
				'footers' => $data['footers'] ?? [],
				'calculations' => $data['calculations'] ?? [],
			],
		]);
	}

	protected function resolveDocumentPayloadData(string $module, string $controller, array $document, array $options = []): array
	{
		$serviceClass = ucfirst($module) . '_Service_DocumentPayloadService';

		if (!class_exists($serviceClass)) {
			throw new RuntimeException('Missing payload service: ' . $serviceClass);
		}

		$service = new $serviceClass();

		return $service->build($document, $controller, $options);
	}

	protected function renderPayload(TCPDF $pdf, array $payload): void
	{
		$document = $payload['document'];
		$template = $payload['template'];
		$positions = $payload['positions'];
		$options = $payload['options'];
		$optionSets = $payload['optionSets'];
		$attributesByGroup = $payload['attributesByGroup'];
		$media = $payload['extra']['media'] ?? [];
		$settings = $payload['settings'];
		$responsibleUser = $payload['responsibleUser'] ?? [];
		$clientId = (int)($payload['clientId'] ?? 0);
		$controller = (string)($payload['meta']['controller'] ?? '');
		$website = !empty($template['website']) ? $template['website'] : '';
		$mediaPath = $this->buildClientMediaPath($clientId);

		$pages = [];

		if (!empty($settings['showCover'])) {
			$coverY = $this->renderCoverAndMeta($pdf, $document, $template, $controller, $payload['contact'] ?? []);

			$pages['product_start'] = $pdf->getPage();
			$this->renderProductDescription($pdf, $positions);

			if (true) {
				$pages['features_start'] = $pdf->getPage();
				$this->renderIncludedOptions($pdf, $positions, $options, $optionSets);
			}
		} else {
			$this->renderCompactHeader($pdf, $document, $template, $controller, $website, $payload['contact'] ?? []);
		}

		$pages['offer_start'] = $pdf->getPage();
		$this->renderPositions($pdf, $document, $positions, $options, $optionSets, $attributesByGroup, $media, $mediaPath, $settings, !empty($settings['showCover']));
		$pages['offer_end'] = $pdf->getPage();

		if (!empty($settings['showTotals']) || !empty($settings['showFooter'])) {
			$pages['terms_start'] = $pdf->getPage();
			$this->renderTotalsAndFooter($pdf, $document, $positions, $template, $settings);
			$pages['terms_end'] = $pdf->getPage();
		}

		if (!empty($settings['showOptions'])) {
			$pages['options_start'] = $pdf->getPage();
			$this->renderOptions($pdf, $positions, $options, $optionSets);
			$pages['options_end'] = $pdf->getPage();
		}

		if (!empty($settings['showImages'])) {
			$pages['images_start'] = $pdf->getPage();
			$this->renderImages($pdf, $positions, $media, $mediaPath);
			$pages['images_end'] = $pdf->getPage();
		}

		if (!empty($settings['showCover'])) {
			$this->renderTableOfContentsOnCover($pdf, $document, $template, $coverY, $pages, $settings, $responsibleUser);
			$this->renderCoverImage($pdf, $positions, $media, $mediaPath);
		}

		if (!empty($settings['showHeader'])) {
			$this->renderHeaderFooter($pdf, $document, $template, $website, $controller, $payload['extra']['footers'] ?? []);
		}
	}

	private function initPdf($title, array $document = [], array $template = [])
	{
		$pdf = new Tcpdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->document = $document;
		$pdf->template = $template;

		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetTitle($title);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(15, 30, 15);
		$pdf->SetAutoPageBreak(true, 28);
		$pdf->SetDisplayMode('real', 'OneColumn');
		$pdf->AddPage();
		return $pdf;
	}

	protected function outputPdf(TCPDF $pdf, array $payload): array
	{
		$file = $this->getOutputFile($payload);
		$this->mkdirp(dirname($file['path']));
		if (!empty($payload['meta']['createOriginal']) && $file['exists']) throw new RuntimeException('Original PDF path is already occupied');

		if (!empty($payload['meta']['overwrite'])) {
			$pdf->Output($file['path'], 'F');
		} elseif (!$file['exists']) {
			$tempFile = tempnam(dirname($file['path']), '.pdf-');
			if ($tempFile === false) throw new RuntimeException('Could not create temporary PDF file');

			try {
				$pdf->Output($tempFile, 'F');
				self::assertCompletePdfFile($tempFile);
				$this->publishPdfFile($tempFile, $file, $payload, 'render');
			} finally {
				if (is_file($tempFile)) unlink($tempFile);
			}
		}

		clearstatcache(true, $file['path']);
		$file['exists'] = is_file($file['path']);
		self::assertCompletePdfFile($file['path']);

		return $file;
	}

	protected function publishPdfFile(string $tempFile, array $file, array $payload, string $source): void
	{
		chmod($tempFile, 0666 & ~umask());

		// Publish the complete PDF without replacing a concurrent writer's file.
		if (@link($tempFile, $file['path'])) {
			error_log('Stored document PDF: ' . $payload['meta']['module'] . '/' . $payload['meta']['controller']
				. ' id=' . $payload['document']['id'] . ' clientid=' . $payload['clientId'] . ' source=' . $source);
			return;
		}

		clearstatcache(true, $file['path']);
		if (!empty($payload['meta']['createOriginal'])) throw new RuntimeException('Original PDF publication conflict');
		self::assertCompletePdfFile($file['path']);
	}

	public static function assertCompletePdfFile(string $path): void
	{
		clearstatcache(true, $path);
		if (!is_file($path) || !is_readable($path) || filesize($path) < 12) {
			throw new RuntimeException('PDF file is incomplete');
		}
		$handle = @fopen($path, 'rb');
		if (!$handle) throw new RuntimeException('PDF file is incomplete');
		try {
			$header = fread($handle, 5);
			$length = filesize($path);
			fseek($handle, max(0, $length - 2048));
			$tail = stream_get_contents($handle);
		} finally {
			fclose($handle);
		}
		if ($header !== '%PDF-' || !str_ends_with(rtrim((string)$tail), '%%EOF')) {
			throw new RuntimeException('PDF file is incomplete');
		}
	}

	protected function getOutputFile(array $payload): array
	{
		$output = $payload['meta']['output'] ?? 'file';
		$storage = $payload['meta']['storage'] ?? 'cache';
		$document = $payload['document'];
		$baseUrl = $payload['meta']['baseUrl'] ?? '';
		$module = $payload['meta']['module'] ?? 'document';
		$controller = $payload['meta']['controller'] ?? 'document';
		$archiveModule = $payload['meta']['archiveModule'] ?? $module;
		$archiveController = $payload['meta']['archiveController'] ?? $controller;
		$clientId = (int)($payload['clientId'] ?? 0);
		$contactId = (int)($document['contactid'] ?? 0);

		$filename = !empty($document['filename'])
			? $document['filename']
			: ($controller . '_' . $document['id'] . '.pdf');

		if (!DEEC_Emailattachment::validFilename($filename)) {
			throw new RuntimeException('Invalid PDF filename');
		}

		$relativeDir = '';
		$dirAbs = '';
		$dirUrl = '';
		$fileAbsOverride = null;

		if ($storage === 'archive' && $clientId > 0 && $contactId > 0) {
			$archive = new DEEC_DocumentArchive(BASE_PATH);
			$archivePath = $archive->path($clientId, $archiveModule, $archiveController, (int)$document['id'], 'pdf');
			DEEC_Emailattachment::checkPath($archivePath);
			$relativeDir = dirname($archive->relativePath($clientId, $archiveModule, $archiveController, (int)$document['id'], 'pdf')) . '/';
			$dirAbs = dirname($archivePath) . '/';
			$dirUrl = $baseUrl . '/' . $archiveModule . '/' . $archiveController . '/download/id/' . (int)$document['id'] . '/';
			$fileAbsOverride = $archivePath;
		} else {
			$safeModule = preg_replace('/[^a-z0-9_-]+/i', '_', $module);
			$safeController = preg_replace('/[^a-z0-9_-]+/i', '_', $controller);
			$relativeDir = $safeModule . '/' . $safeController . '/' . $clientId . '/' . (int)$document['id'] . '/';
			$dirAbs = BASE_PATH . '/cache/document/' . $relativeDir;
			$dirUrl = $baseUrl . '/cache/document/' . $relativeDir;
		}

		$fileAbs = $fileAbsOverride ?? $dirAbs . $filename;
		DEEC_Emailattachment::checkPath($fileAbs);
		$fileUrl = $storage === 'archive' ? rtrim($dirUrl, '/') : $dirUrl . rawurlencode($filename);
		clearstatcache(true, $fileAbs);

		return [
			'path' => $fileAbs,
			'url' => $fileUrl,
			'filename' => $filename,
			'output' => $output,
			'storage' => $storage,
			'exists' => is_file($fileAbs),
		];
	}

	private function mkdirp($dir)
	{
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
	}

	private function renderCompactHeader(TCPDF $pdf, array $document, array $template, string $controller, string $website = '', array $contact = [])
	{
		$docIdField = $this->getDocumentIdField($controller);
		$docDateField = $this->getDocumentDateField($controller);

		$documentId = $document[$docIdField] ?? '';
		$documentDate = $this->formatDate($document[$docDateField] ?? null);
		$deliveryDate = $this->formatDate($document['deliverydate'] ?? null);
		$vatin = $document['vatin'] ?? '';
		$customerId = $document['contactid'] ?? '';
		$salesPerson = $document['responsible'] ?? '';

		$x = 20;

		// Logo
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

		// Info box top right
		$pdf->SetY(40);
		$pdf->setCellPaddings(0, 0, 0, 0);
		$pdf->SetFont('freesans', '', 9);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(55, 5, ($documentId ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_DATE'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(55, 5, ($documentDate ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_CUSTOMER_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(55, 5, ($customerId ?: ' - - - - - '), 0, 'L', false, 1, 160);

		if (!empty($vatin)) {
			$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_VATIN'), 0, 'L', false, 0, 130);
			$pdf->MultiCell(84, 5, $vatin, 0, 'L', false, 1, 160);
		}
		if (!empty($deliveryDate)) {
			$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_DELIVERY_DATE'), 0, 'L', false, 0, 130);
			$pdf->MultiCell(55, 5, $deliveryDate, 0, 'L', false, 1, 160);
		}

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_SALES_PERSON'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(55, 5, ($salesPerson ?: ' - - - - - '), 0, 'L', false, 1, 160);

		// Optional shipping address block
		if (!empty($document['shippingname1']) || !empty($document['shippingstreet']) || !empty($document['shippingcity'])) {
			$pdf->ln(2);
			$pdf->SetFont('freesansb', 'B', 9);
			$pdf->MultiCell(55, 5, $this->translate('DOCUMENTS_SHIPPING_ADDRESS') . ':', 0, 'L', false, 1, 130);

			$pdf->SetFont('freesans', '', 9);

			$shippingLines = [];
			if (!empty($document['shippingname1'])) {
				$shippingLines[] = $document['shippingname1'];
			}
			if (!empty($document['shippingname2'])) {
				$shippingLines[] = $document['shippingname2'];
			}
			if (!empty($document['shippingdepartment'])) {
				$shippingLines[] = $document['shippingdepartment'];
			}
			if (!empty($document['shippingstreet'])) {
				$shippingLines[] = $document['shippingstreet'];
			}

			$cityLine = trim(($document['shippingpostcode'] ?? '') . ' ' . ($document['shippingcity'] ?? ''));
			if ($cityLine !== '') {
				$shippingLines[] = $cityLine;
			}

			if (!empty($document['shippingcountry'])) {
				$shippingLines[] = $this->translate($document['shippingcountry']);
			}

			if (!empty($document['shippingphone'])) {
				$shippingLines[] = $this->translate('DOCUMENTS_PHONE') . ': ' . $document['shippingphone'];
			}

			$pdf->MultiCell(55, 4.5, implode("\n", $shippingLines), 0, 'L', false, 1, 130);
		}

		// Title
		$pdf->SetXY($x, 25);
		$pdf->SetFont('freesansb', 'B', 15);
		$pdf->MultiCell(100, 0, mb_strtoupper($this->translate(mb_strtoupper($controller)), 'UTF-8'), 0, 'L', false, 1);

		// Letterhead
		$pdf->SetXY($x, 50);
		$pdf->SetFont('freesans', '', 7);
		$address = $template['company'] ?? '';
		$pdf->MultiCell(70, 4, $address, 'B', 'L', false, 1, $x);
		$pdf->ln(4);

		// Billing address
		if (!empty($contact)) {
			$pdf->SetFont('freesans', '', 10);
			if (!empty($document['billingname1'])) {
				$pdf->MultiCell(70, 0, $document['billingname1'], 0, 'L', false, 1, $x);
			}
			if (!empty($document['billingname2'])) {
				$pdf->MultiCell(70, 0, $document['billingname2'], 0, 'L', false, 1, $x);
			}
			if (!empty($document['billingdepartment'])) {
				$pdf->MultiCell(70, 0, $document['billingdepartment'], 0, 'L', false, 1, $x);
			}
			if (!empty($document['billingstreet'])) {
				$pdf->MultiCell(70, 0, $document['billingstreet'], 0, 'L', false, 1, $x);
			}

			$billingCityLine = trim(($document['billingpostcode'] ?? '') . ' ' . ($document['billingcity'] ?? ''));
			if ($billingCityLine !== '') {
				$pdf->MultiCell(70, 0, $billingCityLine, 0, 'L', false, 1, $x);
			}

			if (!empty($document['billingcountry'])) {
				$pdf->MultiCell(70, 0, $this->translate($document['billingcountry']), 0, 'L', false, 1, $x);
			}
		}

		$pdf->ln(12);

		// Offer ID
		if (!empty($document['quoteid'])) {
			$info = $this->translate('DOCUMENTS_QUOTE_ID_%s_FROM_%s');
			$info = sprintf($info, $document['quoteid'], $document['quotedate']);

			$pdf->SetFont('freesansb', 'B', 9);
			$pdf->MultiCell(175, 0, $info, 0, 'L', false, 1, $x);
			$pdf->ln(2);
		}

		// Sales Order ID
		if (!empty($document['salesorderid'])) {
			$info = $this->translate('DOCUMENTS_SALES_ORDER_ID_%s_FROM_%s');
			$info = sprintf($info, $document['salesorderid'], $document['salesorderdate']);

			$pdf->SetFont('freesansb', 'B', 9);
			$pdf->MultiCell(175, 0, $info, 0, 'L', false, 1, $x);
			$pdf->ln(2);
		}

		// Header
		if (!empty($document['header'])) {
			$pdf->SetFont('freesansb', '', 8);
			$pdf->MultiCell(175, 0, $document['header'], 0, 'L', false, 1, $x, '', true, 0, true);
			$pdf->ln(2);
		}
	}

	/**
	 * Renders the first page: headline, meta box (id/date/customer/etc),
	 * letterhead, billing/shipping address, subject/reference, intro text.
	 */
	private function renderCoverAndMeta(TCPDF $pdf, array $document, array $template, $controller, array $contact = [])
	{
		$docIdField = $this->getDocumentIdField($controller);
		$documentId = $document[$docIdField] ?? '';
		$docDateField = $this->getDocumentDateField($controller);
		$documentDate = $this->formatDate($document[$docDateField]);
		$delivery = $this->formatDate($document['deliverydate']);
		$vatin = $document['vatin'] ?? '';
		$salesPerson = $document['responsible'] ?? '';

		// “IMPORTANT INFORMATION” box on top-right
		$pdf->SetY(40);
		$pdf->setCellPaddings(0, 0, 0, 0);
		$pdf->SetFont('freesans', '', 9);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(40, 5, ($documentId ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_DATE'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(28, 5, ($documentDate ?: ' - - - - - '), 0, 'L', false, 1, 160);

		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_CUSTOMER_ID'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(28, 5, ($document['contactid'] ?? ''), 0, 'L', false, 1, 160);

		if (!empty($vatin)) {
			$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_VATIN'), 0, 'L', false, 0, 130);
			$pdf->MultiCell(84, 5, $vatin, 0, 'L', false, 1, 160);
		}
		if (!empty($delivery)) {
			$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_DELIVERY_DATE'), 0, 'L', false, 0, 130);
			$pdf->MultiCell(84, 5, $delivery, 0, 'L', false, 1, 160);
		}
		$pdf->MultiCell(30, 5, $this->translate('DOCUMENTS_SALES_PERSON'), 0, 'L', false, 0, 130);
		$pdf->MultiCell(84, 5, $salesPerson, 0, 'L', false, 1, 160);
		$pdf->ln(8);
		$yTop = $pdf->GetY();

		// Headline
		$x = $pdf->GetX()+5;
		$pdf->SetFont('freesansb', 'B', 15);
		$pdf->MultiCell(0, 0, mb_strtoupper($this->translate(mb_strtoupper($controller)), 'UTF-8'), 0, 'L', false, 1, $x, 20);
		$pdf->ln(22);

		// Letterhead (company address line)
		$pdf->SetFont('freesans', '', 7);
		$address = $template['company'] ?? '';
		$pdf->MultiCell(70, 4, $address, 'B', 'L', false, 1, $x);
		$pdf->ln(4);
		$pdf->SetFont('freesans', '', 10);

		// Billing address block
		if (!empty($contact)) {
			$pdf->MultiCell(70, 0, ($document['billingname1'] ?? ''), 0, 'L', false, 1, $x);
			if (!empty($document['billingname2'])) $pdf->MultiCell(70, 0, $document['billingname2'], 0, 'L', false, 1, $x);
			if (!empty($document['billingdepartment'])) $pdf->MultiCell(70, 0, $document['billingdepartment'], 0, 'L', false, 1, $x);
			if (!empty($document['billingstreet'])) $pdf->MultiCell(70, 0, $document['billingstreet'], 0, 'L', false, 1, $x);

			$line = trim(($document['billingpostcode'] ?? '').' '.($document['billingcity'] ?? ''));
			if ($line !== '') $pdf->MultiCell(70, 0, $line, 0, 'L', false, 1, $x);

			$country = $document['billingcountry'] ?? '';
			if ($country) $country = $this->translate($country);
			if ($country) $pdf->MultiCell(70, 0, $country, 0, 'L', false, 1, $x);

			$pdf->ln(20);
			if ($yTop > $pdf->GetY()) $pdf->SetY($yTop);
		}

		// Subject / reference / intro
		$pdf->SetFont('freesans', 'B', 12);
		if (!empty($document['subject'])) $pdf->MultiCell(165, 0, $this->translate('QUOTES_SUBJECT') . ': ' . $document['subject'], 0, 'L', false, 1, 20, '', true, 0);
		if (!empty($document['reference'])) $pdf->MultiCell(165, 0, $this->translate('QUOTES_REFERENCE') . ': ' . $document['reference'], 0, 'L', false, 1, 20, '', true, 0);
		$pdf->SetFont('freesans', '', 10);
		$pdf->MultiCell(165, 0, '', 0, 'L', false, 1, 20, '', true, 0);
		$pdf->MultiCell(165, 0, '', 0, 'L', false, 1, 20, '', true, 0);

		// Short intro (optional – keep it brief)
		$intro = !empty($template['intro'])
				? $template['intro']
				: 'Vielen Dank für Ihre Anfrage. Im Anhang finden Sie unser Angebot.';
		$pdf->MultiCell(100, 0, $intro, 0, 'L', false, 1, 20, '', true, 0);
		$pdf->ln(2);

		// Optional cover logo
		$website = $template['website'] ?? '';
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

		// Return a Y coordinate to start the TOC (a bit lower than current Y)
		return max($pdf->GetY() + 8, 90);
	}

	private function renderCoverImage(TCPDF $pdf, $positions, array $media, string $mediaPath): void
	{
		$pdf->setPage(1);

		foreach ($positions as $position) {
			if ((int)$position->masterid !== 0 || empty($position->itemid)) {
				continue;
			}

			$itemId = (int)$position->itemid;

			if (empty($media[$itemId])) {
				continue;
			}

			foreach ($media[$itemId] as $image) {
				$url = is_array($image)
					? ($image['url'] ?? '')
					: ($image->url ?? '');

				if ($url === '') {
					continue;
				}

				$file = $mediaPath . $url;

				if (!is_file($file)) {
					continue;
				}

				$pdf->Image($file, 120, 110, 85, 0, '', '', 'N');

				return;
			}
		}
	}

	private function renderProductDescription(TCPDF $pdf, $positions)
	{
		if (!count($positions)) return;

		$first = null;
		foreach ($positions as $p) {
			if ((int)$p->masterid === 0) {
				$first = $p;
				break;
			}
		}

		if (!$first || empty($first->itemid)) return;

		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getById((int)$first->itemid);

		if (!$item || empty($item['shopdescription'])) {
			return;
		}

		$pdf->AddPage();
		$pdf->SetFont('freesansb', 'B', 15);

		$title = trim((string)($first->title ?? ''));
		if (!empty($first->sku)) {
			$title = $first->sku . ' ' . $title;
		}

		$pdf->MultiCell(0, 0, ($title ?: 'Produkt'), 0, 'L', false, 1, '', '');
		$pdf->ln(4);

		$pdf->SetFont('freesans', '', 9);

		$desc = trim((string)$item['shopdescription']);
		if ($desc !== '') {
			$pdf->MultiCell(180, 0, $desc, 0, 'L', false, 1, '', '', true, 0, true);
		}
	}

	/**
	 * Page "Eigenschaften und bereits inkludierte Ausstattung …"
	 * Lists ONLY options whose price == 0 for each top-level position.
	 */
	private function renderIncludedOptions(TCPDF $pdf, $positions, $options, $optionSets)
	{
		if (!count($positions)) return;

		// First: check if there is any included option at all
		$hasIncluded = false;
		foreach ($positions as $p) {
			if (empty($options[$p->id]) || !empty($p->masterid)) continue;
			foreach ($optionSets[$p->id] as $set) {
				foreach ($options[$p->id][$set->id] as $opt) {
					if ($this->isIncludedOption($opt)) { $hasIncluded = true; break 3; }
				}
			}
		}
		if (!$hasIncluded) return;

		// Start a new page
		$pdf->AddPage();

		foreach ($positions as $p) {
			if (!empty($p->masterid)) continue; // only main positions
			if (empty($options[$p->id])) continue;

			// Collect included options for this position
			$hasForPos = false;
			foreach ($optionSets[$p->id] as $set) {
				foreach ($options[$p->id][$set->id] as $opt) {
					if ($this->isIncludedOption($opt)) { $hasForPos = true; break 2; }
				}
			}
			if (!$hasForPos) continue;

			// Section headline per position
			$pdf->SetFont('freesans', 'B', 13);
			$pdf->MultiCell(
				175, 0,
				'Eigenschaften und bereits inkludierte Ausstattung für ' . ($p->sku ?: '') . ' ' . ($p->title ?: ''),
				0, 'L', false, 1, '', '', true, 0
			);

			// Option sets and included options
			foreach ($optionSets[$p->id] as $set) {
				if ($pdf->getY() > 250) $pdf->AddPage();

				// (optional) set title – original template had this commented out
				// $pdf->SetFont('freesans', 'B', 10);
				// $pdf->MultiCell(145, 0, $set->title, 0, 'L', false, 1, '', '', true, 0);

				$pdf->SetFont('freesans', '', 9);
				foreach ($options[$p->id][$set->id] as $opt) {
					if (!$this->isIncludedOption($opt)) continue;

					// SKU
					$pdf->MultiCell(15, 0, ($opt->sku ?? ''), 0, 'L', false, 0, '', '', true, 0);
					// Title (bold)
					$pdf->SetFont('freesans', 'B', 9);
					$pdf->MultiCell(130, 0, ($opt->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
					// Right label
					$pdf->SetFont('freesans', '', 9);
					$pdf->MultiCell(30, 0, $this->translate('DOCUMENTS_OPTION_INCLUDED'), 0, 'R', false, 1, 160, '', true, 0);

					// Description (optional)
					if (!empty($opt->description)) {
						$pdf->MultiCell(120, 0, $opt->description, 0, 'L', false, 1, 30, '', true, 0);
					}
				}
			}

			// Separator
			$pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY()-2, true, 0);
			$pdf->setY($pdf->getY() + 2);
		}
	}

	/**
	 * Draws the main positions table (header + rows).
	 * Expects a Traversable/array of positions with fields: id, sku, title, description, price, quantity, uom, image (optional).
	 */
	private function renderPositions(TCPDF $pdf, array $document, $positions, $options, $optionSets, $attributesByGroup = [], $media, $mediaPath, array $settings = [], bool $startOnNewPage = true)
	{
		$settings = array_merge([
			'showPrices' => true,
			'showDiscounts' => false,
			'showOptions' => true,
			'showAttributes' => true,
			'showPositionImages' => true,
		], $settings);

		if (!count($positions)) {
			return;
		}

		if ($startOnNewPage) {
			$pdf->AddPage();
		}

		$pdf->setCellPaddings(1, 0, 0, 0);
		$pdf->SetFont('freesansb', 'B', 8);

		$this->positionsHeader($pdf, $settings);

		$pdf->SetFont('freesans', '', 9);

		$positionsArray = [];
		$children = [];

		foreach ($positions as $p) {
			$positionsArray[$p->id] = $p;
			$masterId = (int)($p->masterid ?? 0);
			if (!isset($children[$masterId])) {
				$children[$masterId] = [];
			}
			$children[$masterId][] = $p->id;
		}

		$mainIndex = 1;

		foreach ($positions as $p) {
			if ((int)$p->masterid !== 0) {
				continue;
			}

			$imageFile = null;

			if (!empty($settings['showPositionImages'])) {
				$imageFile = $this->getPositionImage($p, $media, $mediaPath);
			}

			list($descX, $descWidth) = $this->drawPositionRow(
				$pdf,
				$p,
				(string)$mainIndex,
				$settings,
				false,
				$imageFile
			);

			if (!empty($settings['showAttributes']) && isset($attributesByGroup[$p->id])) {
				$this->renderAttributesForPosition($pdf, $attributesByGroup[$p->id], $descX, $descWidth);
			}

			if (!empty($settings['showOptions'])) {
				$this->renderIncludedOptionsForPosition(
					$pdf,
					$p,
					$options,
					$optionSets,
					$descX,
					$descWidth
				);
			}

			$childIndex = 1;
			if (!empty($children[$p->id])) {
				foreach ($children[$p->id] as $childId) {
					if (empty($positionsArray[$childId])) {
						continue;
					}

					$this->drawPositionRow(
						$pdf,
						$positionsArray[$childId],
						$mainIndex . '.' . $childIndex,
						$settings,
						true
					);

					$childIndex++;
				}
			}

			$pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY() - 2, true, 0);
			$pdf->ln(1);

			$mainIndex++;
		}
	}

	private function renderAttributesForPosition(TCPDF $pdf, array $sets, $xLeft, $width)
	{
		if (!$sets) return;

		$lineGap = 2;

		$pdf->setCellPaddings(0, 0, 0, 0); // remove extra padding

		foreach ($sets as $set) {
			// Top margin before a set
			$pdf->SetY($pdf->GetY() + 2);

			$title = $set['title'] ?? '';
			$desc = $set['description'] ?? '';
			$attrs = $set['attributes'] ?? [];

			if ($title !== '') {
				$pdf->SetFont('freesans', 'B', 9);
				$pdf->MultiCell($width, 0, $title, 0, 'L', false, 1, $xLeft, '', true, 0);
			}

			if ($desc !== '') {
				$pdf->SetFont('freesans', '', 9);
				$pdf->MultiCell($width, 0, $desc, 0, 'L', false, 1, $xLeft, '', true, 0);
			}

			if ($attrs) {
				$pdf->SetFont('freesans', '', 9);
				foreach ($attrs as $a) {
					$title = trim((string)($a['title'] ?? ''));
					$value = trim((string)($a['description'] ?? ''));

					if ($value === '') {
						continue;
					}

					if (strpos($value, '|') !== false) {
						$listItems = array_filter(array_map('trim', explode('|', $value)));

						$pdf->SetFont('freesansb', 'B', 9);
						$pdf->MultiCell($width, 4, $title.':', 0, 'L', false, 1, $xLeft, '', true, 0);

						$pdf->SetFont('freesans', '', 9);

						foreach ($listItems as $listItem) {
							$pdf->MultiCell($width - 5, 4, '• '.$listItem, 0, 'L', false, 1, $xLeft + 5, '', true, 0);
						}
					} else {
						$line = $title.': '.$value;

						$pdf->MultiCell($width, 4, '– '.$line, 0, 'L', false, 1, $xLeft, '', true, 0);
					}
				}
			}

			// small gap to next set
			$pdf->SetY($pdf->GetY() + $lineGap);
		}
	}

	private function renderIncludedOptionsForPosition(TCPDF $pdf, $position, $options, $optionSets, $xLeft, $width): void
	{
		$positionId = $position->id ?? null;

		if (!$positionId || empty($options[$positionId]) || empty($optionSets[$positionId])) {
			return;
		}

		$hasIncludedOptions = false;

		foreach ($optionSets[$positionId] as $set) {
			if (empty($options[$positionId][$set->id])) {
				continue;
			}

			foreach ($options[$positionId][$set->id] as $opt) {
				if ($this->isIncludedOption($opt)) {
					$hasIncludedOptions = true;
					break 2;
				}
			}
		}

		if (!$hasIncludedOptions) {
			return;
		}

		$pdf->SetY($pdf->GetY() + 2);
		$pdf->SetFont('freesansb', 'B', 9);
		$pdf->MultiCell($width, 4, $this->translate('DOCUMENTS_OPTION_INCLUDED') . ':', 0, 'L', false, 1, $xLeft, '', true, 0);

		foreach ($optionSets[$positionId] as $set) {
			if (empty($options[$positionId][$set->id])) {
				continue;
			}

			foreach ($options[$positionId][$set->id] as $opt) {
				if (!$this->isIncludedOption($opt)) {
					continue;
				}

				$sku = trim((string)($opt->sku ?? ''));
				$title = trim((string)($opt->title ?? ''));
				$description = trim((string)($opt->description ?? ''));

				$line = trim($sku . ' ' . $title);

				if ($line === '') {
					continue;
				}

				$pdf->SetFont('freesans', '', 9);
				$pdf->MultiCell($width, 4, '– ' . $line, 0, 'L', false, 1, $xLeft, '', true, 0);

				if ($description !== '') {
					$pdf->SetFont('freesans', '', 8);
					$pdf->MultiCell($width - 5, 4, $description, 0, 'L', false, 1, $xLeft + 5, '', true, 0);
				}
			}
		}
	}

	/**
	 * “Optionen” section like your old `options()` function.
	 * Lists ONLY non-included options (price != 0) per position and per option set.
	 */
	private function renderOptions(TCPDF $pdf, $positions, $options, $optionSets)
	{
		if (!count($positions)) return;

		// Make a list of SKUs already present as base positions (to avoid duplicates)
		$baseSkus = [];
		foreach ($positions as $p) $baseSkus[] = $p->sku;

		// Decide if we need a page at all and add the header box
		$needsPage = false;
		foreach ($positions as $p) {
			if (empty($options[$p->id]) || !empty($p->masterid)) continue;
			foreach ($optionSets[$p->id] as $set) {
				foreach ($options[$p->id][$set->id] as $opt) {
					if (!$this->isIncludedOption($opt) && !$this->isSkuInList($opt->sku ?? '', $baseSkus)) {
						$needsPage = true; break 3;
					}
				}
			}
		}
		if (!$needsPage) return;

		$pdf->AddPage();
		$pdf->SetFont('freesans', 'B', 9);
		$pdf->setCellPaddings(1, 2, 1, 2);
		$pdf->MultiCell(175, 0, str_repeat('-', 163), 0, 'L', false, 1, '', '', true, 0);
		$pdf->MultiCell(
			175, 0,
			"Folgende Ausstattungen und Funktionen die nicht im Angebot enthalten sind können je nach Bedarf und Einsatzzweck der Maschine zusätzlich bestellt werden. Die Basismaschine in der Grundausstattung ist einsatzfähig und betriebsbereit.",
			0, 'L', false, 1, '', '', true, 0
		);
		$pdf->MultiCell(175, 0, str_repeat('-', 163), 0, 'L', false, 1, '', '', true, 0);
		$pdf->setCellPaddings(1, 1, 1, 1);

		// Now list per position and per set
		foreach ($positions as $p) {
			if (!empty($p->masterid) || empty($options[$p->id])) continue;

			$pdf->SetFont('freesans', 'B', 10);
			$pdf->MultiCell(
				175, 0,
				'Optionale Ausstattung für ' . ($p->sku ?: '') . ' ' . ($p->title ?: ''),
				0, 'L', false, 1, '', '', true, 0
			);

			foreach ($optionSets[$p->id] as $set) {
				if ($pdf->getY() > 250) $pdf->AddPage();

				// Set heading row: title + “- optional -”
				$pdf->SetFont('freesans', 'B', 10);
				$pdf->MultiCell(145, 0, ($set->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
				$pdf->MultiCell(30, 0, "- optional -", 0, 'R', false, 1, '', '', true, 0);

				// Set contents
				$pdf->SetFont('freesans', '', 9);
				foreach ($options[$p->id][$set->id] as $opt) {
					// Skip included (0) and duplicates that are actually base positions
					if ($this->isIncludedOption($opt)) continue;
					if ($this->isSkuInList($opt->sku ?? '', $baseSkus)) continue;

					$displayPrice = $this->renderableOptionPrice($opt->price);

					// SKU
					$pdf->MultiCell(15, 0, ($opt->sku ?? ''), 0, 'L', false, 0, '', '', true, 0);
					// Title
					$pdf->MultiCell(130, 0, ($opt->title ?? ''), 0, 'L', false, 0, '', '', true, 0);
					// Price (right aligned)
					$pdf->MultiCell(30, 0, $displayPrice, 0, 'R', false, 1, 160, '', true, 0);

					// Description
					if (!empty($opt->description)) {
						$pdf->MultiCell(120, 0, $opt->description, 0, 'L', false, 1, 30, '', true, 0);
					}
				}
			}

			// Separator
			$pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY()-2, true, 0);
			$pdf->setY($pdf->getY() + 2);
		}
	}

	/**
	 * Totals box + footer page with terms/conditions.
	 * $template may carry a 'footer' HTML block.
	 */
	private function renderTotalsAndFooter(TCPDF $pdf, array $document, $positions, array $template, array $settings = [])
	{
		if (!empty($settings['showPrices'])) {
			$this->ensureSpaceForTotalBox($pdf, $document);
			$yAfterBox = $this->totalBox($pdf, $document);
		}

		//$pdf->SetY($yAfterBox + 5);

		$footerHtml = $document['footer'] ?? ($template['footer'] ?? '');
		if (trim((string)$footerHtml) === '') {
			return;
		}

		$neededHeight = 60;
		$currentY = $pdf->GetY();
		$pageHeightLimit = 265;
		$footerHtmlWidth = 105;

		if ($currentY + $neededHeight > $pageHeightLimit) {
			//$pdf->AddPage();
			//$footerHtmlWidth = 165;
		} else {
			$pdf->ln(2);
		}

		$pdf->SetFont('freesansb', '', 9);
		$pdf->MultiCell($footerHtmlWidth, 0, $footerHtml, 0, 'L', false, 1, '', '', true, 0, true);

		$pdf->ln(4);
	}

	private function ensureSpaceForTotalBox(TCPDF $pdf, array $document): void
	{
		$neededHeight = $this->getTotalBoxHeight($document);
		$currentY = $pdf->GetY();

		$pageHeight = $pdf->getPageHeight();
		$bottomMargin = 28; // align with auto page break / footer reserve
		$usableBottom = $pageHeight - $bottomMargin;

		if (($currentY + $neededHeight) > $usableBottom) {
			$pdf->AddPage();
		}
	}

	private function getTotalBoxHeight(array $document): float
	{
		$height = 18; // subtotal + taxes + total
		$height += max(0, count($document['taxgroups'] ?? []) - 1) * 6;

		if (!empty($document['prepayment_raw']) && !empty($document['balance'])) {
			$height += 12; // prepayment + balance
		}

		return $height;
	}

	private function renderImages(TCPDF $pdf, $positions, array $media, string $mediaPath): void
	{
		if (!count($positions)) {
			return;
		}

		foreach ($positions as $position) {
			if ((int)$position->masterid !== 0 || empty($position->itemid)) {
				continue;
			}

			$itemId = (int)$position->itemid;

			if (empty($media[$itemId])) {
				continue;
			}

			foreach ($media[$itemId] as $image) {
				$url = is_array($image)
					? ($image['url'] ?? '')
					: ($image->url ?? '');

				$title = is_array($image)
					? ($image['title'] ?? '')
					: ($image->title ?? '');

				if ($url === '') {
					continue;
				}

				$file = $mediaPath . $url;

				if (!is_file($file)) {
					continue;
				}

				$pdf->AddPage();
				$pdf->ln(4);

				$pdf->SetFont('freesansb', 'B', 11);
				$pdf->MultiCell(108, 0, $title, 0, 'L', false, 1, '', '', true, 0);

				$pdf->SetFont('freesansb', '', 9);
				$pdf->MultiCell(
					108,
					0,
					$this->translate('DOCUMENTS_ILLUSTRATION_SIMILAR'),
					0,
					'L',
					false,
					1,
					'',
					'',
					true,
					0
				);

				$pdf->ln(4);
				$pdf->Image($file, 20, '', 165, 0, '', '', 'N');
			}
		}
	}

	private function renderTableOfContentsOnCover(TCPDF $pdf, array $document, array $template, $yStart, array $pages, $settings, $responsibleUser)
	{
		// Compute page numbers (defaults if a section wasn’t added)
		$pProduct = $pages['product_start'] ?? 2;
		$pOfferS = $pages['features_start'] ?? 3;
		$pOfferS = $pages['offer_start'] ?? 4;
		$pOfferE = $pages['offer_end'] ?? $pOfferS;
		$pTerms = $pages['terms_start'] ?? ($pOfferE + 1);

		// Options could span included+optional
		$pOptS = $pages['options_start'] ?? ($pTerms + 1);
		$pOptE = $pages['options_end'] ?? $pOptS;

		$pImgS = $pages['images_start'] ?? ($pOptE + 1);
		$pImgE = $pages['images_end'] ?? $pImgS;

		// Draw on page 1
		$pdf->setPage(1);
		$pdf->SetY($yStart);
		$pdf->SetFont('freesansb', 'B', 12);
		$pdf->MultiCell(100, 0, $this->translate('DOCUMENTS_TOC_TITLE'), 0, 'L', false, 1, 20, '', true, 0);
		$pdf->SetFont('freesans', '', 10);
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_PRODUCT_DESCRIPTION'), $pProduct);
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_SALES_OFFER'), ($pOfferS == $pOfferE) ? $pOfferS : "{$pOfferS}-{$pOfferE}");
		if (!empty($settings['showImages'])) {
			$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_TERMS'), $pTerms);
		}
		if (!empty($settings['showOptions'])) {
			$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_OPTIONS'), ($pOptS == $pOptE) ? $pOptS : "{$pOptS}-{$pOptE}");
		}
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_IMAGES'), ($pImgS == $pImgE) ? $pImgS : "{$pImgS}-{$pImgE}");

		$this->renderResponsibleContact($pdf, $document, $responsibleUser);
	}

	private function renderHeaderFooter(TCPDF $pdf, $document, $template, $website, $controller, $footers)
	{
		$i = 1;
		$lastPage = $pdf->getNumPages();
		for ($i = 1; $i <= $lastPage; $i++) {
			$pdf->setPage($i);

			// extra info only from page 2 on
			if ($i >= 2) {
				$pdf->SetY(20);
				$this->addPageInfoLine($pdf, $document, $controller);

				// Logo
				$website = !empty($template['website']) ? $template['website'] : ($website ?? '');
				$this->addLogo(150, 10, 45, $website, $template, $pdf);
			}

			$pdf->SetAutoPageBreak(false);
			$this->renderPageFooterColumns($pdf, $footers);

			// page numbers (manual footer)
			$pdf->SetY(284);
			$pdf->SetFont('freesans', 'I', 8);
			$pdf->Cell(0, 10, $this->translate('DOCUMENTS_PAGE').' '.$i.' / '.$lastPage, 0, 0, 'C');
		}
	}

	private function renderPageFooterColumns(TCPDF $pdf, $footers = []): void
	{
		if (empty($footers)) {
			return;
		}

		$grouped = [];
		foreach ($footers as $footer) {
			$column = (int)($footer['column'] ?? 0);
			if ($column <= 0) {
				continue;
			}

			if (!isset($grouped[$column])) {
				$grouped[$column] = [
					'width' => (float)($footer['width'] ?? 0),
					'lines' => [],
				];
			}

			$text = trim((string)($footer['text'] ?? ''));
			if ($text !== '') {
				$grouped[$column]['lines'][] = $text;
			}
		}

		if (empty($grouped)) {
			return;
		}

		ksort($grouped);

		$startX = 10;
		$startY = 273;
		$gap = 1;

		$pdf->SetFont('freesans', '', 7);
		$pdf->setCellPaddings(0, 0, 0, 0);

		foreach ($grouped as $column => $data) {
			$width = !empty($data['width']) ? (float)$data['width'] : 40;
			$text = implode("\n\n", $data['lines']);

			if ($text === '') {
				$startX += $width + $gap;
				continue;
			}

			$pdf->MultiCell(
				$width,
				3.5,
				$text,
				0,
				'L',
				false,
				1,
				$startX,
				$startY,
				true,
				0
			);

			$startX += $width + $gap;
		}
	}

	private function tocLine(TCPDF $pdf, string $label, $pageText)
	{
		$pdf->MultiCell(60, 0, $label, 0, 'L', false, 0, 20, '', true, 0);
		$pdf->MultiCell(30, 0, "(Seite {$pageText})", 0, 'R', false, 1, 80, '', true, 0);
	}

	private function renderResponsibleContact(TCPDF $pdf, array $document, array $user): void
	{
		$name = trim((string)($document['responsible'] ?? ''));

		if ($name === '') {
			return;
		}

		$position = trim((string)($user['position'] ?? ''));
		$phone = trim((string)($user['phone'] ?? ''));
		$mobile = trim((string)($user['mobile'] ?? ''));
		$email = trim((string)($user['email'] ?? ''));

		$pdf->ln(12);

		$pdf->SetFont('freesansb', 'B', 11);
		$pdf->MultiCell(90, 0, $this->translate('DOCUMENTS_RESPONSIBLE_TITLE'), 0, 'L', false, 1, 20);

		$pdf->ln(2);

		$pdf->SetFont('freesansb', 'B', 10);
		$pdf->MultiCell(90, 0, $name, 0, 'L', false, 1, 20);

		if ($position !== '') {
			$pdf->SetFont('freesans', '', 9);
			$pdf->MultiCell(90, 0, $position, 0, 'L', false, 1, 20);
		}

		$pdf->ln(2);
		$pdf->SetFont('freesans', '', 9);

		if ($phone !== '') {
			$pdf->MultiCell(90, 0, $this->translate('DOCUMENTS_PHONE') . ': ' . $phone, 0, 'L', false, 1, 20);
		}

		if ($mobile !== '') {
			$pdf->MultiCell(90, 0, $this->translate('DOCUMENTS_MOBILE') . ': ' . $mobile, 0, 'L', false, 1, 20);
		}

		if ($email !== '') {
			$pdf->MultiCell(90, 0, $this->translate('DOCUMENTS_EMAIL') . ': ' . $email, 0, 'L', false, 1, 20);
		}

		$pdf->ln(2);

		$pdf->SetFont('freesans', '', 9);
		$pdf->MultiCell(90, 0, $this->translate('DOCUMENTS_RESPONSIBLE_TEXT'), 0, 'L', false, 1, 20);
	}

	/* ===========================================================
	 * Small helpers used by the methods above
	 * ===========================================================
	 */

	private function addLogo($x, $y, $width, $website, array $template, TCPDF $pdf)
	{
		if (!empty($template['logo'])) {
			$logoFile = BASE_PATH.'/files/images/'.$template['logo'];
			if (is_file($logoFile)) {
				$pdf->Image($logoFile, $x, $y, $width, 0, '', $website ?: '');
			}
		}
	}

	private function positionsHeader(TCPDF $pdf, array $settings)
	{
		if (!empty($settings['showPrices'])) {
			$pdf->Cell(10, 5, $this->translate('DOCUMENTS_POSITION'), 'LTB');
			$pdf->setCellPaddings(0, 0, 0, 0);
			$pdf->Cell(98, 5, $this->translate('DOCUMENTS_DESCRIPTION'), 'TB');
			$pdf->Cell(10, 5, $this->translate('DOCUMENTS_QUANTITY'), 'TB');
			$pdf->Cell(12, 5, $this->translate('DOCUMENTS_UOM'), 'TB', 0, 'C');
			$pdf->Cell(25, 5, $this->translate('DOCUMENTS_PRICE_SINGLE'), 'TB', 0, 'C');
			$pdf->Cell(25, 5, $this->translate('DOCUMENTS_PRICE_TOTAL'), 'TBR', 0, 'C');
		} else {
			$pdf->Cell(10, 5, $this->translate('DOCUMENTS_POSITION'), 'LTB');
			$pdf->setCellPaddings(0, 0, 0, 0);
			$pdf->Cell(25, 5, $this->translate('DOCUMENTS_SKU'), 'TB', 0, 'C');
			$pdf->Cell(95, 5, $this->translate('DOCUMENTS_DESCRIPTION'), 'TB', 0, 'L');
			$pdf->Cell(25, 5, $this->translate('DOCUMENTS_QUANTITY'), 'TB', 0, 'C');
			$pdf->Cell(25, 5, $this->translate('DOCUMENTS_UOM'), 'TBR', 0, 'C');
		}
		$pdf->ln(5);
	}

	private function getPositionImage($position, array $media, string $mediaPath): ?string
	{
		$itemId = (int)($position->itemid ?? 0);

		if ($itemId <= 0 || empty($media[$itemId])) {
			return null;
		}

		foreach ($media[$itemId] as $image) {
			$url = is_array($image)
				? ($image['url'] ?? '')
				: ($image->url ?? '');

			if ($url === '') {
				continue;
			}

			$file = $mediaPath . $url;

			if (is_file($file)) {
				return $file;
			}
		}

		return null;
	}

	private function drawPositionRow(TCPDF $pdf, $p, string $positionNumber, array $settings, bool $isChild = false, ?string $imageFile = null)
	{
		$x = $pdf->GetX();
		$y = $pdf->GetY();

		$pdf->setCellPaddings(0, 2, 0, 0);

		$indent = $isChild ? 8 : 0;

		$imageWidth = 25;
		$imageHeight = 25;
		$imageGap = 3;
		$hasImage = !$isChild && $imageFile !== null;

		$title = trim((string)($p->title ?? ''));
		$desc = trim((string)($p->description ?? ''));
		$sku = trim((string)($p->sku ?? ''));
		$qty = $p->quantity ?? 1;
		$uom = $p->uom ?? '';
		$price = $p->price ?? 0;
		$total = $p->total ?? 0;

		if (!empty($settings['showPrices'])) {
			$descX = $x + 10 + $indent;
			$descWidth = 98 - $indent;
		} else {
			$descX = $x + 35 + $indent;
			$descWidth = 95 - $indent;
		}

		$textX = $descX;
		$textWidth = $descWidth;

		if ($hasImage) {
			$textX += $imageWidth + $imageGap;
			$textWidth -= $imageWidth + $imageGap;
		}

		$fontSize = $isChild ? 8.5 : 9;
		$titleFontSize = $isChild ? 8.5 : 9;

		$pdf->SetFont('freesans', '', $fontSize);

		if ($hasImage) {
			$pdf->Image($imageFile, $descX, $y + 2, $imageWidth, $imageHeight, '', '', 'N');
		}

		if (!empty($settings['showPrices'])) {
			// Right side fixed columns
			$pdf->MultiCell(10, 0, $positionNumber, 0, 'C', false, 0, $x, $y, true, 0);
			$pdf->MultiCell(10, 0, $qty, 0, 'C', false, 0, $x + 108, $y, true, 0);

			if ($uom) {
				$pdf->MultiCell(12, 0, $uom, 0, 'C', false, 0, $x + 118, $y, true, 0);
			}

			$pdf->MultiCell(25, 0, $price, 0, 'C', false, 0, $x + 130, $y, true, 0);
			$pdf->MultiCell(25, 0, $total, 0, 'C', false, 0, $x + 155, $y, true, 0);

			// Left content column
			$currentY = $y;

			if ($sku !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell($textWidth, 0, $sku, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($title !== '') {
				$pdf->SetFont('freesansb', 'B', $titleFontSize);
				$pdf->MultiCell($textWidth, 0, $title, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($desc !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell($textWidth, 0, $desc, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}
		} else {
			// No-price layout
			$pdf->MultiCell(10, 0, $positionNumber, 0, 'C', false, 0, $x, $y, true, 0);
			$pdf->MultiCell(25, 0, $qty, 0, 'C', false, 0, $x + 130, $y, true, 0);

			if ($uom) {
				$pdf->MultiCell(25, 0, $uom, 0, 'C', false, 0, $x + 155, $y, true, 0);
			}

			$currentY = $y;

			if ($sku !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell(25 - $indent, 0, $sku, 0, 'C', false, 1, $x + 10 + $indent, $currentY, true, 0);
			}

			if ($title !== '') {
				$pdf->SetFont('freesansb', 'B', $titleFontSize);
				$pdf->MultiCell($textWidth, 0, $title, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($desc !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell($textWidth, 0, $desc, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}


		}

		$rowBottom = $pdf->GetY();

		if ($hasImage) {
			$imageBottom = $y + $imageHeight + 2;

			if ($imageBottom > $rowBottom) {
				$rowBottom = $imageBottom;
			}
		}

		$pdf->SetY($rowBottom);

		return [$descX, $descWidth];
	}

	private function totalBox(TCPDF $pdf, array $document)
	{
		$yStart = $pdf->GetY();

		// expects formatted strings already in $document['subtotal'], ['taxes'], ['total'], ['taxrate']
		$x = 120;
		$y = $pdf->GetY();

		$subtotal = $document['subtotal'] ?? '';
		$taxrate = $document['taxrate'] ?? '';
		$taxes = $document['taxes'] ?? '';
		$total = $document['total'] ?? '';

		$pdf->ln(4);
		$pdf->SetFont('freesansb', 'B', 9);
		$pdf->setCellPaddings(3, 1, 3, 1);
		$pdf->MultiCell(45, 0, $this->translate('DOCUMENTS_SUBTOTAL'), 'LT', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $subtotal, 'TR', 'R', false, 1);

		$pdf->setCellPaddings(3, 0, 3, 1);
		$taxgroups = $document['taxgroups'] ?? [];
		$formattedRate = !$taxgroups;
		if (!$taxgroups) $taxgroups = [($taxrate ?? '') => $taxes];
		$locale = Zend_Registry::get('Zend_Locale');
		foreach ($taxgroups as $rate => $value) {
			$label = $this->translate('DOCUMENTS_TAXES');
			if ($rate !== '') {
				$label .= ' (' . ($formattedRate ? $rate : Zend_Locale_Format::toNumber($rate, ['precision' => 2, 'locale' => $locale])) . '%)';
			}
			$pdf->MultiCell(45, 0, $label, 'L', 'L', false, 0 ,$x);
			$pdf->MultiCell(30, 0, $value, 'R', 'R', false, 1);
		}

		$pdf->setCellPaddings(3, 1, 3, 1);
		$pdf->MultiCell(45, 0, $this->translate('DOCUMENTS_TOTAL'), 'LBT', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $total, 'RBT', 'R', false, 1);

		// optional prepayment/balance if present
		if (!empty($document['prepayment_raw']) && !empty($document['balance'])) {
			$pdf->MultiCell(45, 0, $this->translate('DOCUMENTS_PREPAYMENT'), 'L', 'L', false, 0 ,$x);
			$pdf->MultiCell(30, 0, '- '.$document['prepayment'], 'R', 'R', false, 1);
			$pdf->MultiCell(45, 0, $this->translate('DOCUMENTS_BALANCE'), 'LB', 'L', false, 0 ,$x);
			$pdf->MultiCell(30, 0, $document['balance'], 'RB', 'R', false, 1);
		}

		$yEnd = $pdf->GetY();

		$pdf->setY($yStart);

		return $yEnd;
	}

	private function addPageInfoLine(TCPDF $pdf, array $document, $controller)
	{
		// Line with documentId/date/customerId at top of page
		$docIdField = $this->getDocumentIdField($controller);
		$documentId = $document[$docIdField] ?? '';
		$docDateField = $this->getDocumentDateField($controller);
		$documentDate = $this->formatDate($document[$docDateField]);

		$pdf->setCellPaddings(1, 1, 0, 0);
		$pdf->SetFont('freesans', '', 8);

		$pdf->Write(0, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_ID').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($documentId ?: ' - - - - - ').'   ');

		$pdf->SetFont('freesans', '', 8);
		$pdf->Write(0, $this->translate('DOCUMENTS_' . mb_strtoupper($controller) . '_DATE').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($documentDate ?: ' - - - - - ').'   ');

		$pdf->SetFont('freesans', '', 8);
		$pdf->Write(0, $this->translate('DOCUMENTS_CUSTOMER_ID').' ');
		$pdf->SetFont('freesansb', 'B', 8);
		$pdf->Write(0, ($document['contactid'] ?? ''));

		$pdf->MultiCell(0, 5, ' ', 'B', 'L', false, 1, '', $pdf->getY(), true, 0);
		$pdf->ln();
	}

	private function isIncludedOption($opt): bool
	{
		$price = null;

		if (is_array($opt)) {
			$price = $opt['price'] ?? null;
		} else {
			$price = $opt->price ?? null;
		}

		if ($price === null) {
			return true;
		}

		if ($price === '') {
			return false;
		}

		return is_numeric($price) && (float)$price === 0.0;
	}

	private function isSkuInList($sku, array $list): bool
	{
		return $sku && array_search($sku, $list, true) !== false;
	}

	private function renderableOptionPrice($val): string
	{
		if ($val === null) {
			return $this->translate('DOCUMENTS_OPTION_INCLUDED');
		}

		if (is_numeric($val)) {
			$f = (float)$val;

			if ($f === 0.0) {
				return $this->translate('DOCUMENTS_OPTION_INCLUDED');
			}

			if ($f === -1.0) {
				return $this->translate('DOCUMENTS_OPTION_ON_REQUEST');
			}

			if ($f === -2.0) {
				return $this->translate('DOCUMENTS_OPTION_UNAVAILABLE');
			}
		}

		return (string)$val;
	}

	private function formatDate($date, $format = 'd.m.Y')
	{
		if (empty($date) || $date === '0000-00-00') {
			return null;
		}
		try {
			return (new DateTime($date))->format($format);
		} catch (Exception $e) {
			return null; // fallback for invalid dates
		}
	}

	protected function getDocumentIdField(string $controller): string
	{
		return $controller . 'id';
	}

	protected function getDocumentDateField(string $controller): string
	{
		return $controller . 'date';
	}

	protected function getDocumentDb($module, string $controller)
	{
		$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
		return new $class();
	}

	private function buildClientMediaPath(int $clientId): string
	{
		$dir1 = substr((string)$clientId, 0, 1);
		$dir2 = (strlen((string)$clientId) > 1) ? substr((string)$clientId, 1, 1) : '0';

		return BASE_PATH . '/media/' . $dir1 . '/' . $dir2 . '/' . $clientId . '/images/';
	}
}
