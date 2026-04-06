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
		$this->bootstrapTcpdf();

		$payload = $this->buildPayload($request);

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

		return $this->buildDocumentPayload($documentId, $module, $controller, $output, $templateId ?: null);
	}

	protected function buildPdfTitle(array $payload): string
	{
		$document = $payload['document'];
		$controller = $payload['meta']['controller'] ?? 'document';

		$title = $this->translate(mb_strtoupper($controller));
		$docIdField = $this->getDocumentIdField($controller);

		return $title . ' ' . ($document[$docIdField] ?? $document['id'] ?? '');
	}

	protected function buildDocumentPayload(int $documentId, string $module, string $controller, string $output, ?int $templateId = null): array
	{
		$view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

		$documentDb = $this->getDocumentDb($module, $controller);
		$document = $documentDb->getById($documentId);

		if (!$document) {
			throw new RuntimeException('Document not found');
		}

		$data = $this->resolveDocumentPayloadData($module, $controller, $document, [
			'templateid' => $templateId,
		]);

		$template = $data['template'] ?? [];
		$resolvedDocument = $data['document'] ?? $document;

		return [
			'meta' => [
				'module' => $module,
				'controller' => $controller,
				'documentId' => $documentId,
				'output' => $output,
				'baseUrl' => $view->baseUrl(),
			],
			'document' => $resolvedDocument,
			'contact' => (array)($data['contact'] ?? []),
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
		];
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
		$settings = $payload['settings'];
		$clientId = (int)($payload['clientId'] ?? 0);
		$controller = (string)($payload['meta']['controller'] ?? '');
		$website = !empty($template['website']) ? $template['website'] : '';
		$mediaPath = $this->buildClientMediaPath($clientId);

		$pages = [];

		if (!empty($settings['showCover'])) {
			$coverY = $this->renderCoverAndMeta($pdf, $document, $template, $controller);

			$pages['product_start'] = $pdf->getPage();
			$this->renderProductDescription($pdf, $positions);

			if (!empty($settings['showIncludedOptions'])) {
				$pages['features_start'] = $pdf->getPage();
				$this->renderIncludedOptions($pdf, $positions, $options, $optionSets);
			}
		} else {
			$this->renderCompactHeader($pdf, $document, $template, $controller, $website);
		}

		$pages['offer_start'] = $pdf->getPage();
		$this->renderPositions($pdf, $document, $positions, $options, $optionSets, $attributesByGroup, $settings, !empty($settings['showCover']));
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

		if (!empty($settings['showCover'])) {
			$pages['images_start'] = $pdf->getPage();
			$this->renderImages($pdf, $positions, $mediaPath);
			$pages['images_end'] = $pdf->getPage();

			$this->renderTableOfContentsOnCover($pdf, $document, $template, $coverY, $pages);
			$this->renderCoverImage($pdf, $positions, $mediaPath);
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
		$output = $payload['meta']['output'] ?? 'file';
		$document = $payload['document'];
		$baseUrl = $payload['meta']['baseUrl'] ?? '';
		$module = $payload['meta']['module'] ?? 'document';
		$controller = $payload['meta']['controller'] ?? 'document';

		$filename = !empty($document['filename'])
			? $document['filename']
			: ($controller . '_' . $document['id'] . '.pdf');

		$safeModule = preg_replace('/[^a-z0-9_-]+/i', '_', $module);
		$safeController = preg_replace('/[^a-z0-9_-]+/i', '_', $controller);

		$dirAbs = BASE_PATH . '/cache/document/' . $safeModule . '/' . $safeController . '/';
		$this->mkdirp($dirAbs);

		$fileAbs = $dirAbs . $document['id'] . '_' . $filename;
		$fileUrl = $baseUrl . '/cache/document/' . $safeModule . '/' . $safeController . '/' . $document['id'] . '_' . $filename;

		$pdf->Output($fileAbs, 'F');

		return [
			'path' => $fileAbs,
			'url' => $fileUrl,
			'filename' => $filename,
			'output' => $output,
		];
	}

	private function mkdirp($dir)
	{
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
	}

	private function contactUrl($contactId, $clientId)
	{
		// reproduce your “files/contacts” sharding scheme
		$dir1 = substr($clientId, 0, 1);
		$dir2 = (strlen($clientId) > 1) ? substr($clientId, 1, 1) : '0';
		$c1 = substr($contactId, 0, 1);
		$c2 = (strlen((string)$contactId) > 1) ? substr((string)$contactId, 1, 1) : '0';
		return $dir1.'/'.$dir2.'/'.$clientId.'/'.$c1.'/'.$c2.'/'.$contactId.'/';
	}

	private function renderCompactHeader(TCPDF $pdf, array $document, array $template, string $controller, string $website = '')
	{
		$contact = null;
		if (!empty($document['contactid'])) {
			$contactDb = new Contacts_Model_DbTable_Contact();
			$contact = $contactDb->getContactWithID((int)$document['contactid']);
		}

		$docIdField = $this->getDocumentIdField($controller);
		$docDateField = $this->getDocumentDateField($controller);

		$documentId = $document[$docIdField] ?? '';
		$documentDate = $this->formatDate($document[$docDateField] ?? null);
		$deliveryDate = $this->formatDate($document['deliverydate'] ?? null);
		$vatin = $document['vatin'] ?? '';
		$customerId = $document['contactid'] ?? '';
		$salesPerson = $document['contactperson'] ?? '';

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
	private function renderCoverAndMeta(TCPDF $pdf, array $document, array $template, $controller)
	{
		$contact = null;
		if (!empty($document['contactid'])) {
			$cDb = new Contacts_Model_DbTable_Contact();
			$contact = $cDb->getContactWithID($document['contactid']);
		}

		$docIdField = $this->getDocumentIdField($controller);
		$documentId = $document[$docIdField] ?? '';
		$docDateField = $this->getDocumentDateField($controller);
		$documentDate = $this->formatDate($document[$docDateField]);
		$delivery = $this->formatDate($document['deliverydate']);
		$vatin = $document['vatin'] ?? '';
		$salesPerson = $document['contactperson'] ?? '';

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
		$pdf->MultiCell(165, 0, $intro, 0, 'L', false, 1, 20, '', true, 0);
		$pdf->ln(2);

		// Optional cover logo
		$website = $template['website'] ?? '';
		$this->addLogo(145, 10, 50, $website, $template, $pdf);

		// Return a Y coordinate to start the TOC (a bit lower than current Y)
		return max($pdf->GetY() + 8, 90);
	}

	private function renderCoverImage(TCPDF $pdf, $positions, $mediaPath)
	{
		// Draw on page 1
		$pdf->setPage(1);

		// --- Display first product image if available
		$mediaDb = new Application_Model_DbTable_Media();

		foreach ($positions as $p) {
			if ((int)$p->masterid !== 0) continue; // only main items

			if (!empty($p->itemid)) {
				$images = $mediaDb->getByParentId($p->itemid, 'items', 'item');
				if (!count($images)) continue;

				foreach ($images as $img) {
					$url = $img['url'] ?? null;
					$title = $img['title'] ?? '';

					if (!$url) continue;
					$file = $mediaPath . $url;
					if (!is_file($file)) continue;

					$pdf->Image($file, 120, 110, 85, 0, '', '', 'N');

					// done after the first valid image
					return;
				}
			}
		}
	}

	private function renderProductDescription(TCPDF $pdf, $positions)
	{
		if (!count($positions)) return;

		// find first top-level position
		$first = null;
		foreach ($positions as $p) {
			if ((int)$p->masterid === 0) {
				$first = $p;
				break;
			}
		}
		if (!$first->itemid) return;

		$itemDb = new Items_Model_DbTable_Item();
		$item = $itemDb->getById($first->itemid);

		if (!$item['shopdescription']) return;

		$pdf->AddPage();
		$pdf->SetFont('freesansb', 'B', 15);
		$title = trim(($first->title ?? ''));
		if ($first->sku) $title = $first->sku.' '.$title;
		$pdf->MultiCell(0, 0, ($title ?: 'Produkt'), 0, 'L', false, 1, '', '');
		$pdf->ln(4);

		$pdf->SetFont('freesans', '', 9);
		// Use the position description as “Produktbeschreibung” (adapt if you pull item->shopdescription elsewhere)
		$desc = trim((string)($item['shopdescription'] ?? ''));
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
	private function renderPositions(TCPDF $pdf, array $document, $positions, $options, $optionSets, $attributesByGroup = [], array $settings = [], bool $startOnNewPage = true)
	{
		$settings = array_merge([
			'showPrices' => true,
			'showDiscounts' => false,
			'showOptions' => true,
			'showAttributes' => true,
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

			list($descX, $descWidth) = $this->drawPositionRow(
				$pdf,
				$p,
				(string)$mainIndex,
				$settings,
				false
			);

			if (!empty($settings['showAttributes']) && isset($attributesByGroup[$p->id])) {
				$this->renderAttributesForPosition($pdf, $attributesByGroup[$p->id], $descX, $descWidth);
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
					$line = trim(($a['title'] ?? '').($a['description'] ? ': '.$a['description'] : ''));
					if ($line !== '') {
						// bullet-like flow inside description column
						$pdf->MultiCell($width, 4, '– '.$line, 0, 'L', false, 1, $xLeft, '', true, 0);
					}
				}
			}

			// small gap to next set
			$pdf->SetY($pdf->GetY() + $lineGap);
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
		$this->ensureSpaceForTotalBox($pdf, $document);
		$yAfterBox = $this->totalBox($pdf, $document);

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

		if (!empty($document['prepayment_raw']) && !empty($document['balance'])) {
			$height += 12; // prepayment + balance
		}

		return $height;
	}

	/**
	 * Renders an “images” section where each item image is put on its own page with a caption.
	 * Expects you can fetch media by itemid via Shops model.
	 */
	private function renderImages(TCPDF $pdf, $positions, $mediaPath)
	{
		if (!count($positions)) return;

		$mediaDb = new Application_Model_DbTable_Media();

		foreach ($positions as $p) {
			if ((int)$p->masterid !== 0) continue; // only main items

			if (!empty($p->itemid)) {
				$images = $mediaDb->getByParentId($p->itemid, 'items', 'item');
				if (!count($images)) continue;

				foreach ($images as $img) {
					$url = $img['url'] ?? null;
					$title = $img['title'] ?? '';

					if (!$url) continue;
					$file = $mediaPath . $url;
					if (!is_file($file)) continue;

					$pdf->AddPage();
					$pdf->ln(4);
					$pdf->SetFont('freesansb', 'B', 11);
					$pdf->MultiCell(108, 0, $title, 0, 'L', false, 1, '', '', true, 0);
					$pdf->SetFont('freesansb', '', 9);
					$pdf->MultiCell(108, 0, $this->translate('DOCUMENTS_ILLUSTRATION_SIMILAR'), 0, 'L', false, 1, '', '', true, 0);
					$pdf->ln(4);
					$pdf->Image($file, 20, '', 165, 0, '', '', 'N');
					$pdf->SetFont('freesansb', '', 10);
				}
			}
		}
	}

	private function renderTableOfContentsOnCover(TCPDF $pdf, array $document, array $template, $yStart, array $pages)
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
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_TERMS'), $pTerms);
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_OPTIONS'), ($pOptS == $pOptE) ? $pOptS : "{$pOptS}-{$pOptE}");
		$this->tocLine($pdf, $this->translate('DOCUMENTS_TOC_IMAGES'), ($pImgS == $pImgE) ? $pImgS : "{$pImgS}-{$pImgE}");
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

	private function drawPositionRow(TCPDF $pdf, $p, string $positionNumber, array $settings, bool $isChild = false)
	{
		$x = $pdf->GetX();
		$y = $pdf->GetY();

		$pdf->setCellPaddings(0, 2, 0, 0);

		$indent = $isChild ? 8 : 0;

		$title = trim((string)($p->title ?? ''));
		$desc = trim((string)($p->description ?? ''));
		$sku = trim((string)($p->sku ?? ''));
		$qty = $p->quantity ?? 1;
		$uom = $p->uom ?? '';
		$price = $p->price ?? 0;
		$total = $p->total ?? 0;

		$descX = $x + 10 + $indent;
		$descWidth = (!empty($settings['showPrices']) ? 98 : 95) - $indent;

		$fontSize = $isChild ? 8.5 : 9;
		$titleFontSize = $isChild ? 8.5 : 9;

		$pdf->SetFont('freesans', '', $fontSize);

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
				$pdf->MultiCell(108 - $indent, 0, $sku, 0, 'L', false, 1, $x + 10 + $indent, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($title !== '') {
				$pdf->SetFont('freesansb', 'B', $titleFontSize);
				$pdf->MultiCell(108 - $indent, 0, $title, 0, 'L', false, 1, $x + 10 + $indent, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($desc !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell(98 - $indent, 0, $desc, 0, 'L', false, 1, $x + 10 + $indent, $currentY, true, 0);
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

			$textX = $x + 35 + $indent;

			if ($title !== '') {
				$pdf->SetFont('freesansb', 'B', $titleFontSize);
				$pdf->MultiCell(95 - $indent, 0, $title, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}

			if ($desc !== '') {
				$pdf->SetFont('freesans', '', $fontSize);
				$pdf->MultiCell(95 - $indent, 0, $desc, 0, 'L', false, 1, $textX, $currentY, true, 0);
				$currentY = $pdf->GetY();
			}
		}

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
		$pdf->MultiCell(45, 0, $this->translate('DOCUMENTS_TAXES').' ('.$taxrate.'%)', 'L', 'L', false, 0 ,$x);
		$pdf->MultiCell(30, 0, $taxes, 'R', 'R', false, 1);

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
		// In your pipeline, option->price is either:
		// - float 0/-1/-2, or
		// - a formatted currency string for positive prices.
		if (!isset($opt->price)) return false;
		if (is_numeric($opt->price)) return ((float)$opt->price === 0.0);
		// just in case someone already mapped label “bereits enthalten”
		$val = trim((string)$opt->price);
		$includedLabel = $this->translate('DOCUMENTS_OPTION_INCLUDED');
		return ($val === '0' || mb_stripos($val, $includedLabel) !== false);
	}

	private function isSkuInList($sku, array $list): bool
	{
		return $sku && array_search($sku, $list, true) !== false;
	}

	private function renderableOptionPrice($val): string
	{
		// Map special codes
		if (is_numeric($val)) {
			$f = (float)$val;
			if ($f === 0.0) return $this->translate('DOCUMENTS_OPTION_INCLUDED');
			if ($f === -1.0) return $this->translate('DOCUMENTS_OPTION_ON_REQUEST');
			if ($f === -2.0) return $this->translate('DOCUMENTS_OPTION_UNAVAILABLE');
		}
		// Already formatted currency string → return as-is
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
