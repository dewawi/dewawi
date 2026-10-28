<?php

class Shops_InquiryController extends Shops_Controller_Action
{
	protected $formDataSession;
	protected $formConfig = [];
	protected $formRow = null;
	protected int $formId = 0;
	protected string $inquiryToken = '';
	protected string $csrfToken = '';

	public function init()
	{
		parent::init();

		$this->formDataSession = new Zend_Session_Namespace('MultiStepForm');

		if ($this->getRequest()->getActionName() === 'index') {
			$this->formId = (int)$this->_getParam('id', 0);
			$inquiryformDb = new Shops_Model_DbTable_Inquiryform();
			$this->formRow = $this->formId > 0 ? $inquiryformDb->getInquiryform($this->formId) : null;
			$this->formConfig = $this->formRow
				? (new Shops_Model_FormConfig($inquiryformDb))->getConfig($this->formId)
				: [];

			$context = $this->_siteContext->getSiteId() . ':' . $this->formId;

			if (($this->formDataSession->context ?? '') !== $context) {
				unset(
					$this->formDataSession->formData,
					$this->formDataSession->suggestions,
					$this->formDataSession->requestValues,
					$this->formDataSession->downloadUrl,
					$this->formDataSession->submittedData,
					$this->formDataSession->inquiryToken,
					$this->formDataSession->csrfToken
				);
				$this->formDataSession->context = $context;
			}
		}

		if (empty($this->formDataSession->inquiryToken)) {
			$this->formDataSession->inquiryToken = bin2hex(random_bytes(16));
		}

		if (empty($this->formDataSession->csrfToken)) {
			$this->formDataSession->csrfToken = bin2hex(random_bytes(32));
		}

		$this->inquiryToken = (string)$this->formDataSession->inquiryToken;
		$this->csrfToken = (string)$this->formDataSession->csrfToken;
	}

	public function indexAction()
	{
		$shop = $this->_site;
		$request = $this->getRequest();

		if (!$this->formRow || !$this->formConfig) {
			if ($request->isPost()) {
				$this->getResponse()->setHttpResponseCode(404);
				return $this->_helper->json(['status' => 'error', 'message' => 'Anfrageformular nicht gefunden.']);
			}

			throw new Zend_Controller_Action_Exception('Inquiry form not found', 404);
		}

		$stepOrder = array_keys($this->formConfig);
		$step = (string)$request->getPost('step', (string)$stepOrder[0]);

		if (!isset($this->formConfig[$step])) {
			if ($request->isPost()) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => 'Ungültiger Formularschritt.']);
			}

			throw new Zend_Controller_Action_Exception('Invalid inquiry form step', 404);
		}

		if (!$request->isPost()) {
			$this->initSiteLayout();
			$this->assignMessages();
		}

		$this->view->shop = $shop;
		$this->view->formRow = $this->formRow;

		$form = $this->createStepForm($step);

		// Falls Werte für den aktuellen Step vorhanden sind, ins Formular laden
		if (!empty($this->formDataSession->formData[$step])) {
			$form->setValues($this->formDataSession->formData[$step]);
		}

		// Handle form submission via AJAX
		if ($request->isPost()) {
			$this->_helper->layout()->disableLayout();
			$post = (array)$request->getPost();

			if (!$this->isValidCsrf($post)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => 'Das Formular ist abgelaufen. Bitte laden Sie die Seite neu.']);
			}

			$submittedToken = strtolower(trim((string)($post['inquiry_token'] ?? '')));

			if (!preg_match('/^[a-f0-9]{32,64}$/D', $submittedToken)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => 'Ungültige Anfragekennung.']);
			}

			if (!$request->getPost('back')) {
				$existing = (new Shops_Model_DbTable_Inquirydata())->getByToken($submittedToken);

				if ($existing) {
					$data = json_decode((string)$existing['data'], true);
					$this->completeInquiry(is_array($data) ? $data : [], null, null, true);

					return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
				}
			}

			if ($request->getPost('back')) {
				$currentIndex = array_search($step, $stepOrder);
				$prevStep = $stepOrder[max(0, $currentIndex - 1)];

				$prevForm = $this->createStepForm($prevStep);
				if (!empty($this->formDataSession->formData[$prevStep])) {
					$prevForm->setValues($this->formDataSession->formData[$prevStep]);
				}

				$formHtml = $this->view->partial('inquiry/index.phtml', [
					'form' => $prevForm,
					'step' => $prevStep,
					'ajax' => true,
					'suggestions' => $this->formDataSession->suggestions ?? [],
					'suggestedItems' => [],
					'requestValues' => $this->formDataSession->requestValues ?? [],
					'formRow' => $this->formRow
				]);

				$this->_helper->json(['status'=>'ok','formHtml'=>$formHtml]);
				return;
			}

			// Populate -> validate
			if (!$form->isValid($post)) {
				// re-render the same step with errors
				$this->view->form = $form;
				$this->view->step = $step;

				$formHtml = $this->view->partial('inquiry/index.phtml', [
					'form' => $form,
					'step' => $step,
					'ajax' => true,
					'errors' => $form->getErrors(),
					'formRow' => $this->formRow,
				]);

				$this->_helper->json([
					'status' => 'error',
					'formHtml'=> $formHtml
				]);
				return;
			}

			// Save valid data to session under structured key
			$formValues = $form->getFilteredValues();
			unset($formValues['csrf_token'], $formValues['inquiry_token'], $formValues['next']);
			$this->formDataSession->formData[$step] = $formValues;

			// Step 1 suggestions (use validated values!)
			if ($step === '1') {
				foreach ($this->formConfig[$step] as $field) {
	 	 	 		if (empty($field['calculate']) || !is_array($field['calculate'])) continue;

				 	$targetExpr = $field['calculate']['target'] ?? null;
				 	$referenceTitle = $field['calculate']['reference'] ?? null;
				 	$tolerance = $field['calculate']['tolerance'] ?? null;

		 	 	 	if (!$targetExpr || !$referenceTitle || !isset($formValues[$field['name']])) continue;

					// Zielausdruck vorbereiten (z. B. vorlauf_temp / umgebung_temp_max)
					preg_match_all('/[a-zA-Z0-9_]+/', $targetExpr, $matches);
					$expr = $targetExpr;
					foreach ($matches[0] as $token) {
						$value = isset($formValues[$token]) ? floatval($formValues[$token]) : 0;
						$expr = str_replace($token, $value, $expr);
					}

					// Titel
					$targetTitle = $field['name'] . "_{$expr}";

					// Datenbankzugriffe
					$itemAttrDb = new Shops_Model_DbTable_Itematr();
					$attrsTarget = $itemAttrDb->getPositionsBySku('FORM_CALC')->toArray();
					$attrsReference = $itemAttrDb->getPositionsByTitle($referenceTitle)->toArray();

					// Zielwert aus Target-Attributen extrahieren
					$targetAttrValue = null;
					foreach ($attrsTarget as $attrTarget) {
						if ($attrTarget['title'] === $targetTitle) {
							$targetAttrValue = (float)$attrTarget['value'];
							break;
						}
					}
					if ($targetAttrValue === null) continue;

					$requiredCooling = isset($formValues['kuehlleistung']) ? floatval($formValues['kuehlleistung']) : null;
					if ($requiredCooling === null) {
						continue; // oder logge den Fehler
					}

					// Passende Referenzwerte mit Toleranz prüfen
					$matches = $this->findMatchingReferences(
						$attrsReference,
						$targetAttrValue,
						$requiredCooling,
						$tolerance
					);

					// Falls zu wenige Treffer: Toleranz verdoppeln und erneut suchen
					if (count($matches) < 2) {
						$matches = $this->findMatchingReferences(
							$attrsReference,
							$targetAttrValue,
							$requiredCooling,
							$tolerance * 2
						);
					}

					if (!empty($matches)) {
						$this->formDataSession->suggestions = $matches;
						$this->formDataSession->requestValues = $formValues;
					} else {
						$this->formDataSession->suggestions = [];
						$this->formDataSession->requestValues = [];
					}
				}
			}

			// Get next step key
			$currentIndex = array_search($step, $stepOrder);
			$nextStep = $stepOrder[$currentIndex + 1] ?? null;

			if ($nextStep && isset($this->formConfig[$nextStep])) {
				$nextForm = $this->createStepForm($nextStep);
				if (!empty($this->formDataSession->formData[$nextStep])) {
					$nextForm->setValues($this->formDataSession->formData[$nextStep]);
				}

				$itemDb = new Shops_Model_DbTable_Item();
				$mediaDb = new Shops_Model_DbTable_Media();

				$itemsWithImages = [];
				foreach ($this->formDataSession->suggestions ?? [] as $suggestion) {
					$item = $itemDb->getItemBySku($suggestion['description']);
					if (!$item) continue;

					$images = $mediaDb->getMedia($item['id'], 'items', 'item');
					$itemsWithImages[] = [
						'item' => $item,
						'images' => $images,
						'description' => $suggestion['description'],
						'value' => $suggestion['value'],
						'percent' => $suggestion['percent']
					];
				}

				$this->view->suggestedItems = $itemsWithImages;

				$formHtml = $this->view->partial('inquiry/index.phtml', [
					'form' => $nextForm,
					'step' => $nextStep,
					'ajax' => true,
					'suggestions' => $this->formDataSession->suggestions ?? [],
					'suggestedItems' => $itemsWithImages ?? [],
					'requestValues' => $this->formDataSession->requestValues ?? [],
					'formRow' => $this->formRow,
				]);

				$this->_helper->json([
					'status' => 'ok',
					'formHtml' => $formHtml
				]);
	 	 		return;
			}

			// Last step: persist first, then run the existing optional quote and notification flow.
			$allFormValues = [];
			foreach ($this->formDataSession->formData as $stepValues) {
				$allFormValues = array_merge($allFormValues, $stepValues);
			}

			// Datenschutz-Checkbox MUSS bestätigt sein
			$privacyOk = !empty($allFormValues['privacy']) && (int)$allFormValues['privacy'] === 1;
			if (!$privacyOk) {
				$this->_helper->json([
					'status' => 'error',
					'message' => 'Bitte bestätigen Sie die Datenschutzbestimmungen.'
				]);
				return;
			}

			// (Optional) finale Serverside-Validierung wichtiger Felder:
			if (empty($allFormValues['email']) || !filter_var($allFormValues['email'], FILTER_VALIDATE_EMAIL)) {
				$this->_helper->json([
					'status' => 'error',
					'message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
				]);
				return;
			}

			$storedData = array_merge($allFormValues, [
				'origin' => 'technical_inquiry',
				'source_path' => $this->getSourcePath(),
			]);

			$inquiryDb = new Shops_Model_DbTable_Inquirydata();
			$stored = $inquiryDb->saveOnce($this->formId, $submittedToken, $storedData);

			if (!$stored['inserted']) {
				$this->completeInquiry($allFormValues, null, null, true);

				return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
			}

			// Auto-create quote if a SKU was provided
			$quoteId = null;
			$pdfInfo = [];

			try {
				$quoteId = $this->createQuoteFromInquiry($allFormValues, $shop);
			} catch (Exception $e) {
				error_log('Stored shop inquiry could not create a quote: ' . $e->getMessage());
			}

			if ($quoteId) {
				try {
					$pdfInfo = (new DEEC_Pdf())->generate([
						'module' => 'shops',
						'controller' => 'quote',
						'documentId' => $quoteId,
						'output' => 'file',
					]);
				} catch (Exception $e) {
					error_log('Stored shop inquiry quote PDF could not be generated: ' . $e->getMessage());
				}
			}

			$notificationSent = $this->sendInquiryNotification($allFormValues);

			if ($quoteId && !empty($pdfInfo['path'])) {
				$this->sendOfferNotification($allFormValues, (string)$pdfInfo['path']);
			}

			$this->completeInquiry($allFormValues, $pdfInfo['url'] ?? null, $notificationSent, false);

			return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
		}

		$this->view->form = $form;
		$this->view->step = $step;
	}

	public function successAction()
	{
		$this->view->formData = $this->formDataSession->submittedData ?? [];
		$this->view->downloadUrl = $this->formDataSession->downloadUrl ?? null;
		$this->view->notificationSent = $this->formDataSession->notificationSent ?? null;
		$this->view->duplicate = $this->formDataSession->duplicate ?? false;

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->assignMessages();

			$formHtml = $this->view->partial('inquiry/success.phtml', [
				'formData' => $this->view->formData,
				'downloadUrl' => $this->view->downloadUrl,
				'notificationSent' => $this->view->notificationSent,
				'duplicate' => $this->view->duplicate,
			]);

			$this->_helper->json([
				'status' => 'ok',
				'formHtml' => $formHtml,
			]);

			return;
		}

		return $this->_helper->redirector->gotoSimple('index', 'index', 'default');
	}

	protected function createStepForm(string $step): Shops_Form_DynamicForm
	{
		return new Shops_Form_DynamicForm(
			$this->formConfig,
			$step,
			$this->csrfToken,
			$this->inquiryToken
		);
	}

	protected function isValidCsrf(array $data): bool
	{
		$token = (string)($data['csrf_token'] ?? '');

		return $token !== '' && hash_equals($this->csrfToken, $token);
	}

	protected function sendInquiryNotification(array $data): bool
	{
		$this->getRequest()->setPost(array_merge($data, [
			'subject' => 'Anfrageformular',
			'message' => $data['message'] ?? 'Vielen Dank für Ihre Anfrage.',
		]));

		try {
			$result = Zend_Controller_Action_HelperBroker::getStaticHelper('Email')
				->sendEmail('shops', 'inquiry');

			return !empty($result['ok']);
		} catch (Exception $e) {
			error_log('Stored shop inquiry could not be emailed: ' . $e->getMessage());
			$this->_flashMessenger->addMessage('MESSAGES_EMAIL_SENT_ERROR');

			return false;
		}
	}

	protected function sendOfferNotification(array $data, string $pdfPath): bool
	{
		$this->getRequest()->setPost(array_merge($data, [
			'__attach_paths' => [$pdfPath],
		]));

		try {
			$result = Zend_Controller_Action_HelperBroker::getStaticHelper('Email')
				->sendEmail('shops', 'offer');

			return !empty($result['ok']);
		} catch (Exception $e) {
			error_log('Stored shop inquiry offer could not be emailed: ' . $e->getMessage());
			$this->_flashMessenger->addMessage('MESSAGES_EMAIL_SENT_ERROR');

			return false;
		}
	}

	protected function completeInquiry(array $data, ?string $downloadUrl, ?bool $notificationSent, bool $duplicate): void
	{
		$this->formDataSession->submittedData = $data;
		$this->formDataSession->downloadUrl = $downloadUrl;
		$this->formDataSession->notificationSent = $notificationSent;
		$this->formDataSession->duplicate = $duplicate;
		$this->formDataSession->inquiryToken = bin2hex(random_bytes(16));
		$this->inquiryToken = (string)$this->formDataSession->inquiryToken;

		unset(
			$this->formDataSession->formData,
			$this->formDataSession->suggestions,
			$this->formDataSession->requestValues
		);
	}

	protected function getSourcePath(): string
	{
		$path = parse_url((string)$this->getRequest()->getRequestUri(), PHP_URL_PATH);

		return is_string($path) ? $path : '';
	}

	protected function findMatchingReferences($attrsReference, $targetAttrValue, $requiredCooling, $tolerance)
	{
		$min = $requiredCooling * (1 - $tolerance);
		$max = $requiredCooling * (1 + $tolerance);

		$smaller = null;
		$bigger = null;
		$smallestDiffBelow = PHP_FLOAT_MAX;
		$smallestDiffAbove = PHP_FLOAT_MAX;

		foreach ($attrsReference as $attrReference) {
		 	$factor = floatval($attrReference['value']);
		 	$result = $factor * $targetAttrValue;
			$percent = round(($result / $requiredCooling) * 100, 1);

		 	if ($result >= $min && $result <= $max) {
		 	 	$diff = abs($result - $requiredCooling);

		 	 	if ($result < $requiredCooling && $diff < $smallestDiffBelow) {
		 	 	 	$smallestDiffBelow = $diff;
		 	 	 	$smaller = [
		 	 	 	 	'title' => $attrReference['title'],
		 	 	 	 	'value' => round($result, 2),
		 	 	 	 	'description' => $attrReference['description'],
						'percent' => $percent
		 	 	 	];
		 	 	}

		 	 	if ($result > $requiredCooling && $diff < $smallestDiffAbove) {
		 	 	 	$smallestDiffAbove = $diff;
		 	 	 	$bigger = [
		 	 	 	 	'title' => $attrReference['title'],
		 	 	 	 	'value' => round($result, 2),
		 	 	 	 	'description' => $attrReference['description'],
						'percent' => $percent
		 	 	 	];
		 	 	}

		 	 	// Optional: wenn exakt gleich, kannst du auch sofort zurückgeben
		 	 	if ($result == $requiredCooling) {
		 	 	 	return [[
		 	 	 	 	'title' => $attrReference['title'],
		 	 	 	 	'value' => round($result, 2),
		 	 	 	 	'description' => $attrReference['description'],
						'percent' => 100.0
		 	 	 	]];
		 	 	}
		 	}
		}

		$matches = [];
		if ($smaller !== null) $matches[] = $smaller;
		if ($bigger !== null) $matches[] = $bigger;

		return $matches;
	}

	/**
	 * Existing optional auto-quote flow, invoked only after the inquiry was inserted:
	 * resolve SKU and defaults, resolve/create the contact and address, create quote and
	 * position, calculate totals, then finalize number and filename. PDF generation and
	 * both notification emails remain in indexAction after this method returns a quote ID.
	 */
	private function createQuoteFromInquiry(array $data, array $shop)
	{
		// need: sku (required), qty (optional), title (optional)
		$sku = trim((string)($data['sku'] ?? ''));
		if ($sku === '') return null;

		$qty = (float)($data['quantity'] ?? 1);
		if ($qty <= 0) $qty = 1;

		// 1) Find the item by SKU
		$itemDb = new Shops_Model_DbTable_Item();
		$item = $itemDb->getItemBySku($sku);
		if (!$item) return null; // invalid sku, silently skip

		// 2) Pick defaults (currency/language/template) like Sales_QuoteController::addAction()
		$currencyDb = new Shops_Model_DbTable_Currency();
		$currency = $currencyDb->getPrimaryCurrency();

		$langDb = new Shops_Model_DbTable_Language();
		$lang = $langDb->getPrimaryLanguage();

		$tplDb = new Shops_Model_DbTable_Template();
		$tpl = $tplDb->getPrimaryTemplate();

		$formRow = $this->formRow ?? [];

		// 3) (Optional) resolve/create a contact from inquiry data
		// adjust to your real fields!
		$contactId = 0;
		$contactRowId = 0;
		if (!empty($data['email'])) {
 	 		$emailDb = new Shops_Model_DbTable_Email();
 	 		$contactRowId = (int)$emailDb->findContactIdByEmail($data['email']);

		 	// auto-create if not found
		 	if ($contactRowId === 0) {
		 	 	try {
					//Set new quote Id
					$incrementDb = new Shops_Model_DbTable_Increment();
					$contactId = $incrementDb->getIncrement('contactid');
					$incrementDb->setIncrement(($contactId), 'contactid');

					$contactData = [
		 	 	 	 	'catid' => 0,
		 	 	 	 	'name1' => trim($data['firma']),
		 	 	 	 	'name2' => trim($data['ansprechpartner']),
		 	 	 	 	'contactid' => $contactId
		 	 	 	];
		 	 	 	$contactDb = new Shops_Model_DbTable_Contact();
		 	 	 	$contactRowId = $contactDb->addContact($contactData);

					//Add address
					$addressDb = new Shops_Model_DbTable_Address();
					$addressDb->addAddress($contactRowId, [
						'type' => 'billing',
						'street' => trim($data['adresse']),
						'postcode' => trim($data['plz']),
						'city' => trim($data['stadt']),
						'country' => 'DE',
						'ordering' => 1,
					]);

		 	 	 	// attach email row
					$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
		 	 	 	$emailDb->addEmail([
		 	 	 	 	'module' => 'contacts',
		 	 	 	 	'controller' => 'contact',
		 	 	 	 	'parentid' => $contactRowId,
		 	 	 	 	'ordering' => 1,
		 	 	 	 	'email' => trim($data['email']),
		 	 	 	 	'password' => $password,
		 	 	 	]);
		 	 	} catch (\Exception $e) {
		 	 	 	return;
		 	 	}
		 	} else {
	 	 	 	$contactDb = new Shops_Model_DbTable_Contact();
	 	 	 	$contact = $contactDb->getContact($contactRowId);
				$contactId = $contact['contactid'];
			}
		}

		// choose special price if available
		$basePrice = isset($item['specialprice']) && $item['specialprice'] > 0
			? (float)$item['specialprice']
			: (float)($item['price'] ?? 0);

		if ($contactId) {
			// Create the quote
			$quoteDb = new Shops_Model_DbTable_Quote();
			$quoteId = (int)$quoteDb->addQuote([
			 	'title' => $data['titel'] ?? ($item['title'] ?? 'Angebot'),
			 	'reference' => $data['projektname'],
			 	'currency' => $currency['code'],
			 	'state' => 100,
			 	'header' => $formRow['quoteheader'] ?? null,
			 	'footer' => $formRow['quotefooter'] ?? null,
			 	'contactid' => $contactId,
			 	'subtotal' => $basePrice ?? 0,
			 	'taxes' => $basePrice * 0.19,
			 	'total' => $basePrice * 1.19,
			 	'language' => $formRow['language'] ?? $lang['code'],
			 	'contactperson' => 'System',
			 	'templateid' => (int)($formRow['quotetemplateid'] ?? 0) ?: $tpl['id'],
			]);

			$this->applyContactToQuote($quoteId, $contactId);

			// 5) Add one position with the item
			$posDb = new Shops_Model_DbTable_Quotepos();
			$posDb->addPosition([
			 	'parentid' => $quoteId,
			 	'itemid' => (int)$item['id'],
			 	'possetid' => 0,
			 	'sku' => $item['sku'],
			 	'title' => $item['title'],
			 	'description' => $item['description'] ?? '',
			 	'price' => $basePrice,
			 	'taxrate' => 19.0000,
			 	'total' => $basePrice,
			 	'quantity' => $qty,
			 	'uom' => 'Stück',
			 	'currency' => $currency['code'],
			 	'ordering' => 1,
			]);

			// trigger calculation once so totals are set
			try {
			 	$this->_helper->Calculate($quoteId, 'sales', 'quote');
			} catch (Exception $e) {}

			$quote = $quoteDb->getQuote($quoteId);
	 	 	$contactDb = new Shops_Model_DbTable_Contact();
			$contact = $contactDb->getContactWithID($quote['contactid']);

			//Set new quote Id
			$incrementDb = new Shops_Model_DbTable_Increment();
			$increment = $incrementDb->getIncrement('quoteid');
			$filenameDb = new Shops_Model_DbTable_Filename();
			$filename = $filenameDb->getFilename('quote', $quote['language']);
			$filename = str_replace('%NUMBER%', $increment, $filename);
			$quoteDb->saveQuote($quoteId, $increment, $filename);
			$incrementDb->setIncrement(($increment), 'quoteid');

			return $quoteId;
		}
	}

	private function applyContactToQuote(int $quoteId, int $contactId): void
	{
		if ($quoteId <= 0 || $contactId <= 0) return;

		$quoteDb = new Shops_Model_DbTable_Quote();
		$contactDb = new Shops_Model_DbTable_Contact();
		$addrDb 	= new Shops_Model_DbTable_Address();

		$contact = $contactDb->getContactWithID($contactId);
		if (!$contact) return;

		// Load all addresses for the contact (adapt method name if yours differs)
		// Expecting rows like: ['type' => 'billing'|'shipping', 'street'=>..., 'postcode'=>..., 'city'=>..., 'country'=>...]
		$addresses = $addrDb->getAddresses($contact['id']);
		$billing = null;
		$shipping = null;

		foreach ((array)$addresses as $a) {
		 	// Some table layers return objects; normalize access
		 	$type = is_array($a) ? ($a['type'] ?? '') : ($a->type ?? '');
		 	if (strcasecmp($type, 'billing') === 0) $billing = $a;
		 	if (strcasecmp($type, 'shipping') === 0) $shipping = $a;
		}

		// Fallbacks
		if (!$billing && !empty($addresses[0])) $billing = $addresses[0];
		if (!$shipping) $shipping = $billing;

		// Small access helpers
		$get = function($row, $key, $default = '') {
		 	if (is_array($row)) return $row[$key] ?? $default;
		 	if (is_object($row)) return $row->$key ?? $default;
		 	return $default;
		};

		$data = [
		 	// billing from contact + billing address
		 	'billingname1' => $contact['name1'] ?? '',
		 	'billingname2' => $contact['name2'] ?? '',
		 	'billingdepartment'=> $contact['department'] ?? '',
		 	'billingstreet' => $get($billing, 'street'),
		 	'billingpostcode' => $get($billing, 'postcode'),
		 	'billingcity' => $get($billing, 'city'),
		 	'billingcountry' => $get($billing, 'country'),
		 	'taxfree' => (int)($contact['taxfree'] ?? 0),

		 	// shipping from contact + shipping address (fallback to billing if missing)
		 	'shippingname1' => $contact['name1'] ?? '',
		 	'shippingname2' => $contact['name2'] ?? '',
		 	'shippingdepartment'=> $contact['department']?? '',
		 	'shippingstreet' => $get($shipping, 'street'),
		 	'shippingpostcode' => $get($shipping, 'postcode'),
		 	'shippingcity' => $get($shipping, 'city'),
		 	'shippingcountry' => $get($shipping, 'country'),
		 	'shippingphone' => $contact['phone'] ?? '', // if you store phone elsewhere, swap in your lookup
		];

		// Persist to quote
		$quoteDb->updateQuote($quoteId, $data);
	}
}
