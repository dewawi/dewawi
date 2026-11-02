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

			$context = $this->_siteContext->getClientId() . ':' . $this->_siteContext->getSiteId() . ':' . $this->formId;

			if (($this->formDataSession->context ?? '') !== $context) {
				unset(
					$this->formDataSession->formData,
					$this->formDataSession->suggestions,
					$this->formDataSession->suggestionStep,
					$this->formDataSession->requestValues,
					$this->formDataSession->downloadUrl,
					$this->formDataSession->downloadFile,
					$this->formDataSession->offerStatus,
					$this->formDataSession->submittedData,
					$this->formDataSession->inquiryToken,
					$this->formDataSession->lastInquiryToken,
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
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_FORM_EXPIRED')]);
			}

			$this->renderSiteError(404);
			return;
		}

		$stepOrder = $this->getStepOrder();
		$step = (string)$request->getPost('step', (string)$stepOrder[0]);

		if (!isset($this->formConfig[$step])) {
			if ($request->isPost()) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_STEP_ORDER_INVALID')]);
			}

			$this->renderSiteError(404);
			return;
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
			if (!$this->isReasonableSubmission($post, 200, 65536, 3)) {
				$this->getResponse()->setHttpResponseCode(413);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_SUBMISSION_TOO_LARGE')]);
			}

			if (!$this->isValidCsrf($post)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_FORM_EXPIRED')]);
			}

			$submittedToken = strtolower(trim((string)($post['inquiry_token'] ?? '')));

			if (!preg_match('/^[a-f0-9]{32,64}$/D', $submittedToken)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_INVALID_TOKEN')]);
			}

			if (!$this->isValidInquiryToken($submittedToken)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_INVALID_TOKEN')]);
			}

			if (!$request->getPost('back')) {
				$existing = (new Shops_Model_DbTable_Inquirydata())->getByToken($submittedToken);

				if ($existing) {
					$data = json_decode((string)$existing['data'], true);
					$result = $this->restoreInquiryResult($existing);
					$this->completeInquiry(is_array($data) ? $data : [], $result['url'], null, true, $submittedToken, $result['status']);

					return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
				}
			}

			if (!$this->hasCompletedPriorSteps($step, $stepOrder, (array)($this->formDataSession->formData ?? []))) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_STEP_ORDER_INVALID')]);
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
					'suggestedItems' => $this->buildSuggestedItems($this->formDataSession->suggestions ?? []),
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
					'suggestions' => $this->formDataSession->suggestions ?? [],
					'suggestedItems' => $this->buildSuggestedItems($this->formDataSession->suggestions ?? []),
					'requestValues' => $this->formDataSession->requestValues ?? [],
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
			$currentIndex = array_search($step, $stepOrder, true);
			$this->discardFollowingSteps($stepOrder, (int)$currentIndex);
			$this->formDataSession->formData[$step] = $formValues;

			if ($this->hasCalculation($this->formConfig[$step])) {
				$suggestions = $this->calculateSuggestions($this->formConfig[$step], $formValues);
				$this->formDataSession->suggestions = $suggestions;
				$this->formDataSession->suggestionStep = $step;
				$this->formDataSession->requestValues = $suggestions ? $this->normalizeRequestValues($formValues) : [];
			}

			// Get next step key
			$nextStep = $stepOrder[$currentIndex + 1] ?? null;

			if ($nextStep && isset($this->formConfig[$nextStep])) {
				$nextForm = $this->createStepForm($nextStep);
				if (!empty($this->formDataSession->formData[$nextStep])) {
					$nextForm->setValues($this->formDataSession->formData[$nextStep]);
				}

				$itemsWithImages = $this->buildSuggestedItems($this->formDataSession->suggestions ?? []);

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

			if (!$this->validateCompletedSteps($stepOrder, (array)$this->formDataSession->formData)) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_FORM_INVALID')]);
			}

			if (!$this->isValidSuggestedSku((string)($allFormValues['sku'] ?? ''))) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_FORM_INVALID')]);
			}

			// Datenschutz-Checkbox MUSS bestätigt sein
			$privacyOk = !empty($allFormValues['privacy']) && (int)$allFormValues['privacy'] === 1;
			if (!$privacyOk) {
				$this->_helper->json([
					'status' => 'error',
					'message' => $this->view->translate('SHOPS_ERROR_PRIVACY_REQUIRED')
				]);
				return;
			}

			// (Optional) finale Serverside-Validierung wichtiger Felder:
			if (empty($allFormValues['email']) || !filter_var($allFormValues['email'], FILTER_VALIDATE_EMAIL)) {
				$this->_helper->json([
					'status' => 'error',
					'message' => $this->view->translate('SHOPS_ERROR_EMAIL_INVALID')
				]);
				return;
			}

			try {
				$rate = $this->consumeSubmissionLimit('inquiry', 5, 3600);
			} catch (Throwable $e) {
				error_log('Shop inquiry rate limiter unavailable: ' . $e->getMessage());
				$this->getResponse()->setHttpResponseCode(503);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_SUBMISSION_UNAVAILABLE')]);
			}
			if (!$rate['allowed']) {
				$this->setRateLimitResponse((int)$rate['retryAfter']);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_RATE_LIMIT')]);
			}

			$storedData = array_merge($allFormValues, [
				'origin' => 'technical_inquiry',
				'source_path' => $this->getSourcePath(),
			]);

			$inquiryDb = new Shops_Model_DbTable_Inquirydata();
			try {
				$stored = $inquiryDb->saveOnce($this->formId, $submittedToken, $storedData);
			} catch (Throwable $e) {
				error_log('Shop inquiry could not be stored: ' . $e->getMessage());
				$this->getResponse()->setHttpResponseCode(500);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_SUBMISSION_FAILED')]);
			}

			if (!$stored['inserted']) {
				$existing = $inquiryDb->getByToken($submittedToken);
				$result = $existing ? $this->restoreInquiryResult($existing) : ['url' => null, 'status' => null];
				$this->completeInquiry($allFormValues, $result['url'], null, true, $submittedToken, $result['status']);

				return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
			}

			$this->markSiteConversion('technical_inquiry_submitted');

			// Auto-create quote if a SKU was provided
			$quoteId = null;
			$pdfInfo = [];
			$offerStatus = trim((string)($allFormValues['sku'] ?? '')) === '' ? null : 'quote_failed';

			try {
				$quoteId = $this->createQuoteFromInquiry($allFormValues, (int)$stored['id']);
			} catch (Throwable $e) {
				error_log('Stored shop inquiry could not create a quote: ' . $e->getMessage());
			}

			if ($quoteId) {
				$offerStatus = 'pdf_failed';
				try {
					$pdfInfo = $this->generateQuotePdf((int)$quoteId);
					$offerStatus = 'ready';
				} catch (Throwable $e) {
					error_log('Stored shop inquiry quote PDF could not be generated: ' . $e->getMessage());
				}
			}

			$notificationSent = $this->sendInquiryNotification($allFormValues);
			if ($quoteId && !empty($pdfInfo['path'])) {
				if (!$this->sendOfferNotification($allFormValues, (string)$pdfInfo['path'])) {
					$notificationSent = false;
				}
			}

			$this->completeInquiry($allFormValues, $pdfInfo['url'] ?? null, $notificationSent, false, $submittedToken, $offerStatus);

			return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);
		}

		$this->view->form = $form;
		$this->view->step = $step;
	}

	public function successAction()
	{
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		$this->view->formData = $this->formDataSession->submittedData ?? [];
		$this->view->downloadUrl = $this->formDataSession->downloadUrl ?? null;
		$this->view->notificationSent = $this->formDataSession->notificationSent ?? null;
		$this->view->duplicate = $this->formDataSession->duplicate ?? false;
		$this->view->offerStatus = $this->formDataSession->offerStatus ?? null;
		if (!$this->view->formData) {
			if ($this->getRequest()->isXmlHttpRequest()) {
				$this->getResponse()->setHttpResponseCode(400);
				return $this->_helper->json(['status' => 'error', 'message' => $this->view->translate('SHOPS_FORM_EXPIRED')]);
			}

			return $this->_helper->redirector->gotoRoute([], 'shop_home', true);
		}

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->assignMessages();
			$conversionEvents = $this->consumeSiteConversions();

			$formHtml = $this->view->partial('inquiry/success.phtml', [
				'formData' => $this->view->formData,
				'downloadUrl' => $this->view->downloadUrl,
				'notificationSent' => $this->view->notificationSent,
				'duplicate' => $this->view->duplicate,
				'offerStatus' => $this->view->offerStatus,
			]);

			$this->_helper->json([
				'status' => 'ok',
				'formHtml' => $formHtml,
				'conversionEvents' => $conversionEvents,
			]);

			return;
		}

		$this->initSiteLayout();
		$this->assignMessages();
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

	protected function getStepOrder(): array
	{
		return array_map('strval', array_keys($this->formConfig));
	}

	protected function discardFollowingSteps(array $stepOrder, int $currentIndex): void
	{
		foreach (array_slice($stepOrder, $currentIndex + 1) as $laterStep) {
			unset($this->formDataSession->formData[$laterStep]);
		}

		$suggestionStep = (string)($this->formDataSession->suggestionStep ?? '');
		$suggestionIndex = array_search($suggestionStep, $stepOrder, true);
		if ($suggestionIndex !== false && $currentIndex <= $suggestionIndex) {
			unset(
				$this->formDataSession->suggestions,
				$this->formDataSession->requestValues,
				$this->formDataSession->suggestionStep
			);
		}
	}

	protected function hasCalculation(array $fields): bool
	{
		return count(array_filter($fields, static fn($field): bool => is_array($field['calculate'] ?? null))) === 1;
	}

	protected function calculateSuggestions(array $fields, array $values): array
	{
		$calculationFields = array_values(array_filter($fields, static fn($field): bool => is_array($field['calculate'] ?? null)));
		if (count($calculationFields) !== 1) return [];

		$field = $calculationFields[0];
		$calculation = $field['calculate'];
		$fieldName = trim((string)($field['name'] ?? ''));
		$targetExpression = trim((string)($calculation['target'] ?? ''));
		$referenceTitle = trim((string)($calculation['reference'] ?? ''));
		$tolerance = self::parseDecimal($calculation['tolerance'] ?? null);
		$requiredCooling = self::parseDecimal($values['kuehlleistung'] ?? null);
		if ($fieldName === '' || $targetExpression === '' || $referenceTitle === '' || $tolerance === null || $tolerance < 0
			|| $requiredCooling === null || $requiredCooling <= 0) return [];

		$invalidExpression = false;
		$resolvedExpression = preg_replace_callback('/[a-zA-Z_][a-zA-Z0-9_]*/', static function(array $match) use ($values, &$invalidExpression): string {
			$value = self::parseDecimal($values[$match[0]] ?? null);
			if ($value === null) {
				$invalidExpression = true;
				return $match[0];
			}

			return (string)$value;
		}, $targetExpression);
		if ($invalidExpression || !is_string($resolvedExpression)) return [];

		$itemAttrDb = new Shops_Model_DbTable_Itematr();
		$targetRows = $itemAttrDb->getPositionsBySkuAndTitle('FORM_CALC', $fieldName . '_' . $resolvedExpression)->toArray();
		if (count($targetRows) !== 1) return [];
		$targetValue = self::parseDecimal($targetRows[0]['value'] ?? null);
		if ($targetValue === null) return [];

		$referenceRows = $itemAttrDb->getPositionsBySkuAndTitle('FORM_CALC', $referenceTitle)->toArray();
		$matches = $this->findMatchingReferences($referenceRows, $targetValue, $requiredCooling, $tolerance);
		if (count($matches) < 2) {
			$matches = $this->findMatchingReferences($referenceRows, $targetValue, $requiredCooling, $tolerance * 2);
		}

		return $this->filterAvailableSuggestions($matches);
	}

	protected static function parseDecimal($value): ?float
	{
		if (is_int($value) || is_float($value)) {
			$value = (float)$value;
			return is_finite($value) ? $value : null;
		}
		if (!is_string($value)) return null;

		$value = trim($value);
		if ($value === '' || !preg_match('/^[+-]?(?:\d+(?:[.,]\d+)?|[.,]\d+)$/D', $value)) return null;
		$number = (float)str_replace(',', '.', $value);

		return is_finite($number) ? $number : null;
	}

	protected function normalizeRequestValues(array $values): array
	{
		if (array_key_exists('kuehlleistung', $values)) {
			$value = self::parseDecimal($values['kuehlleistung']);
			if ($value !== null) $values['kuehlleistung'] = $value;
		}

		return $values;
	}

	protected function filterAvailableSuggestions(array $suggestions): array
	{
		$itemDb = new Shops_Model_DbTable_Item();
		$categoryDb = new Shops_Model_DbTable_Category();
		$available = [];
		foreach ($suggestions as $suggestion) {
			$sku = trim((string)($suggestion['description'] ?? ''));
			$item = $sku !== '' ? $itemDb->getItemBySku($sku) : null;
			if (!$item || !$categoryDb->getCategory((int)($item['shopcatid'] ?? 0))) continue;
			$suggestion['description'] = (string)$item['sku'];
			$available[] = $suggestion;
		}

		return $available;
	}

	protected function buildSuggestedItems(array $suggestions): array
	{
		$itemDb = new Shops_Model_DbTable_Item();
		$categoryDb = new Shops_Model_DbTable_Category();
		$mediaDb = new Shops_Model_DbTable_Media();
		$items = [];
		foreach ($suggestions as $suggestion) {
			$item = $itemDb->getItemBySku((string)($suggestion['description'] ?? ''));
			if (!$item || !$categoryDb->getCategory((int)($item['shopcatid'] ?? 0))) continue;
			$items[] = [
				'item' => $item,
				'images' => $mediaDb->getMedia((int)$item['id'], 'items', 'item'),
				'description' => (string)$item['sku'],
				'value' => $suggestion['value'],
				'percent' => $suggestion['percent'],
			];
		}

		return $items;
	}

	protected function isValidSuggestedSku(string $sku): bool
	{
		$sku = trim($sku);
		if ($sku === '') return true;

		foreach ((array)($this->formDataSession->suggestions ?? []) as $suggestion) {
			if (hash_equals((string)($suggestion['description'] ?? ''), $sku)) {
				return (bool)$this->filterAvailableSuggestions([$suggestion]);
			}
		}

		return false;
	}

	protected function isValidCsrf(array $data): bool
	{
		$token = (string)($data['csrf_token'] ?? '');

		return $token !== '' && hash_equals($this->csrfToken, $token);
	}

	protected function isValidInquiryToken(string $token): bool
	{
		$currentToken = (string)($this->formDataSession->inquiryToken ?? '');
		$lastToken = (string)($this->formDataSession->lastInquiryToken ?? '');
		if ($currentToken !== '' && hash_equals($currentToken, $token)) return true;

		return $lastToken !== '' && hash_equals($lastToken, $token);
	}

	protected function hasCompletedPriorSteps(string $step, array $stepOrder, array $formData): bool
	{
		$position = array_search($step, $stepOrder, true);
		if ($position === false) return false;

		foreach (array_slice($stepOrder, 0, $position) as $priorStep) {
			if (!isset($formData[$priorStep]) || !is_array($formData[$priorStep])) return false;
		}

		return true;
	}

	protected function validateCompletedSteps(array $stepOrder, array $formData): bool
	{
		foreach ($stepOrder as $step) {
			if (!isset($formData[$step]) || !is_array($formData[$step])) return false;
			$form = $this->createStepForm((string)$step);
			$values = array_merge($formData[$step], [
				'csrf_token' => $this->csrfToken,
				'inquiry_token' => $this->inquiryToken,
			]);
			if (!$form->isValid($values)) return false;
		}

		return true;
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
			if(($result['internalAccepted'] ?? null) === false) error_log('Stored shop inquiry internal notification was not accepted by SMTP');

			return !empty($result['customerAccepted']);
		} catch (Throwable $e) {
			error_log('Stored shop inquiry could not be emailed: ' . $e->getMessage());

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
			if(($result['internalAccepted'] ?? null) === false) error_log('Stored shop inquiry offer internal notification was not accepted by SMTP');

			return !empty($result['customerAccepted']);
		} catch (Throwable $e) {
			error_log('Stored shop inquiry offer could not be emailed: ' . $e->getMessage());

			return false;
		}
	}

	protected function completeInquiry(array $data, ?string $downloadUrl, ?bool $notificationSent, bool $duplicate, ?string $completedToken = null, ?string $offerStatus = null): void
	{
		$this->formDataSession->submittedData = $data;
		$this->formDataSession->downloadUrl = $downloadUrl;
		$this->formDataSession->notificationSent = $notificationSent;
		$this->formDataSession->duplicate = $duplicate;
		$this->formDataSession->offerStatus = $offerStatus;
		$this->formDataSession->lastInquiryToken = $completedToken ?? $this->inquiryToken;
		$this->formDataSession->inquiryToken = bin2hex(random_bytes(16));
		$this->inquiryToken = (string)$this->formDataSession->inquiryToken;

		unset(
			$this->formDataSession->formData,
			$this->formDataSession->suggestions,
			$this->formDataSession->requestValues,
			$this->formDataSession->suggestionStep
		);
	}

	protected function getSourcePath(): string
	{
		$path = parse_url((string)$this->getRequest()->getRequestUri(), PHP_URL_PATH);

		return is_string($path) ? $path : '';
	}

	protected function findMatchingReferences($attrsReference, $targetAttrValue, $requiredCooling, $tolerance)
	{
		if (!is_finite((float)$targetAttrValue) || !is_finite((float)$requiredCooling) || $requiredCooling <= 0
			|| !is_finite((float)$tolerance) || $tolerance < 0) return [];
		$min = $requiredCooling * (1 - $tolerance);
		$max = $requiredCooling * (1 + $tolerance);

		$smaller = null;
		$bigger = null;
		$smallestDiffBelow = PHP_FLOAT_MAX;
		$smallestDiffAbove = PHP_FLOAT_MAX;

		$uniqueReferences = [];
		$ambiguous = [];
		foreach ($attrsReference as $attrReference) {
			$description = trim((string)($attrReference['description'] ?? ''));
			$factor = self::parseDecimal($attrReference['value'] ?? null);
			if ($description === '' || $factor === null) continue;
			if (isset($uniqueReferences[$description])) {
				$ambiguous[$description] = true;
				continue;
			}
			$uniqueReferences[$description] = $attrReference;
		}

		foreach ($uniqueReferences as $description => $attrReference) {
			if (isset($ambiguous[$description])) continue;
			$factor = self::parseDecimal($attrReference['value']);
			$result = $factor * $targetAttrValue;
			$percent = round(($result / $requiredCooling) * 100, 1);

			if ($result >= $min && $result <= $max) {
				$diff = abs($result - $requiredCooling);

				if ($result < $requiredCooling && $diff < $smallestDiffBelow) {
					$smallestDiffBelow = $diff;
					$smaller = [
						'title' => $attrReference['title'],
						'value' => round($result, 2),
						'description' => $description,
						'percent' => $percent
					];
				}

				if ($result > $requiredCooling && $diff < $smallestDiffAbove) {
					$smallestDiffAbove = $diff;
					$bigger = [
						'title' => $attrReference['title'],
						'value' => round($result, 2),
						'description' => $description,
						'percent' => $percent
					];
				}

				if ($result == $requiredCooling) {
					return [[
						'title' => $attrReference['title'],
						'value' => round($result, 2),
						'description' => $description,
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

	protected function createQuoteFromInquiry(array $data, int $inquiryId): ?int
	{
		$sku = trim((string)($data['sku'] ?? ''));
		if ($sku === '') return null;
		if (!$this->isValidSuggestedSku($sku)) throw new RuntimeException('Selected item is no longer available');

		$quantity = self::parseDecimal($data['quantity'] ?? 1);
		if ($quantity === null || $quantity <= 0) throw new RuntimeException('Invalid quote quantity');

		$item = (new Shops_Model_DbTable_Item())->getItemBySku($sku);
		if (!$item || !(new Shops_Model_DbTable_Category())->getCategory((int)($item['shopcatid'] ?? 0))) {
			throw new RuntimeException('Selected item is no longer public');
		}

		$currency = (new Shops_Model_DbTable_Currency())->getPrimaryCurrency();
		$language = trim((string)($this->formRow['language'] ?? ''));
		if ($language === '') $language = (string)(new Shops_Model_DbTable_Language())->getPrimaryLanguage()['code'];
		$templateDb = new Shops_Model_DbTable_Template();
		$templateId = (int)($this->formRow['quotetemplateid'] ?? 0);
		$template = $templateId > 0 ? $templateDb->getTemplate($templateId) : $templateDb->getPrimaryTemplate();
		if (!$template) throw new RuntimeException('Configured quote template is unavailable');

		$price = !empty($item['specialprice']) && (float)$item['specialprice'] > 0
			? (float)$item['specialprice']
			: (float)($item['price'] ?? 0);
		if (!is_finite($price) || $price < 0) throw new RuntimeException('Invalid item price');
		$tax = (new Shops_Model_DbTable_Taxrate())->getTaxRate((int)($item['taxid'] ?? 0));
		$taxRate = self::parseDecimal($tax['rate'] ?? null);
		if ($taxRate === null || $taxRate < 0) throw new RuntimeException('Invalid item tax rate');
		$uoms = (new Application_Model_DbTable_Uom())->setClientId((int)$this->_siteContext->getClientId())->getUoms();
		$uom = trim((string)($uoms[(int)($item['uomid'] ?? 0)] ?? ''));
		if ($uom === '') throw new RuntimeException('Invalid item unit');

		$quoteDb = new Shops_Model_DbTable_Quote();
		$adapter = $quoteDb->getAdapter();
		$adapter->beginTransaction();

		try {
			[$contactRowId, $contact, $existingContact] = $this->resolveInquiryContact($data);
			$contactId = (int)($contact['contactid'] ?? 0);
			if ($contactRowId <= 0 || $contactId <= 0) throw new RuntimeException('Inquiry contact is unavailable');
			if ($existingContact && !empty($contact['taxfree'])) {
				throw new RuntimeException('Unverified inquiry contact requires manual tax handling');
			}
			$snapshot = $this->buildInquiryAddressSnapshot($data);
			$quoteId = $quoteDb->addQuote(array_merge($snapshot, [
				'title' => trim((string)($data['titel'] ?? '')) ?: (string)$item['title'],
				'reference' => trim((string)($data['projektname'] ?? '')),
				'currency' => (string)$currency['code'],
				'state' => 100,
				'header' => $this->formRow['quoteheader'] ?? null,
				'footer' => $this->formRow['quotefooter'] ?? null,
				'contactid' => $contactId,
				'subtotal' => 0,
				'taxes' => 0,
				'total' => 0,
				'language' => $language,
				'contactperson' => '',
				'templateid' => (int)$template['id'],
				'pdfshowprices' => 1,
			]));

			(new Shops_Model_DbTable_Quotepos())->addPosition([
				'parentid' => $quoteId,
				'itemid' => (int)$item['id'],
				'possetid' => 0,
				'sku' => (string)$item['sku'],
				'title' => (string)$item['title'],
				'description' => (string)($item['description'] ?? ''),
				'price' => $price,
				'taxrate' => $taxRate,
				'total' => 0,
				'quantity' => $quantity,
				'uom' => $uom,
				'currency' => (string)$currency['code'],
			]);

			$clientId = (int)$this->_siteContext->getClientId();
			Zend_Controller_Action_HelperBroker::getStaticHelper('Calculate')->direct($quoteId, 'sales', 'quote', $clientId);

			$incrementDb = new Shops_Model_DbTable_Increment();
			$quoteNumber = $incrementDb->reserveIncrement('quoteid');
			$filename = (new Shops_Model_DbTable_Filename())->getFilename('quote', $language);
			$quoteDb->saveQuote($quoteId, $quoteNumber, str_replace('%NUMBER%', (string)$quoteNumber, $filename));
			if (!(new Shops_Model_DbTable_Inquirydata())->linkQuoteIfEmpty($inquiryId, $contactRowId, $quoteId)) {
				throw new RuntimeException('Inquiry quote was already linked');
			}

			$adapter->commit();
			return $quoteId;
		} catch (Throwable $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	protected function resolveInquiryContact(array $data): array
	{
		$email = trim((string)($data['email'] ?? ''));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid inquiry email');

		$emailDb = new Shops_Model_DbTable_Email();
		$contactDb = new Shops_Model_DbTable_Contact();
		$contactIds = $emailDb->findContactIdsByEmail($email);
		if (count($contactIds) > 1) throw new RuntimeException('Inquiry email matches multiple contacts');
		$contactRowId = $contactIds[0] ?? 0;
		if ($contactRowId > 0) {
			$contact = $contactDb->getContact($contactRowId);
			if (!$contact) throw new RuntimeException('Matched inquiry contact is unavailable');
			return [$contactRowId, $contact, true];
		}

		$contactId = (new Shops_Model_DbTable_Increment())->reserveIncrement('contactid');
		$contactRowId = $contactDb->addContact([
			'catid' => 0,
			'name1' => trim((string)($data['firma'] ?? '')),
			'name2' => trim((string)($data['ansprechpartner'] ?? '')),
			'contactid' => $contactId,
		]);
		$country = trim((string)($data['country'] ?? $data['land'] ?? ''));
		(new Shops_Model_DbTable_Address())->addAddress($contactRowId, [
			'type' => 'billing',
			'street' => trim((string)($data['adresse'] ?? '')),
			'postcode' => trim((string)($data['plz'] ?? '')),
			'city' => trim((string)($data['stadt'] ?? '')),
			'country' => $country,
			'ordering' => 1,
		]);
		$emailDb->addEmail([
			'module' => 'contacts',
			'controller' => 'contact',
			'parentid' => $contactRowId,
			'ordering' => 1,
			'email' => $email,
			'password' => password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT),
		]);

		return [$contactRowId, $contactDb->getContact($contactRowId), false];
	}

	protected function buildInquiryAddressSnapshot(array $data): array
	{
		$name1 = trim((string)($data['firma'] ?? ''));
		$name2 = trim((string)($data['ansprechpartner'] ?? ''));
		$street = trim((string)($data['adresse'] ?? ''));
		$postcode = trim((string)($data['plz'] ?? ''));
		$city = trim((string)($data['stadt'] ?? ''));
		$country = trim((string)($data['country'] ?? $data['land'] ?? ''));

		return [
			'billingname1' => $name1,
			'billingname2' => $name2,
			'billingdepartment' => '',
			'billingstreet' => $street,
			'billingpostcode' => $postcode,
			'billingcity' => $city,
			'billingcountry' => $country,
			'shippingname1' => $name1,
			'shippingname2' => $name2,
			'shippingdepartment' => '',
			'shippingstreet' => $street,
			'shippingpostcode' => $postcode,
			'shippingcity' => $city,
			'shippingcountry' => $country,
			'shippingphone' => trim((string)($data['telefon'] ?? '')),
			'taxfree' => 0,
		];
	}

	protected function generateQuotePdf(int $quoteId): array
	{
		$result = (new DEEC_Pdf())->generate([
			'module' => 'shops',
			'controller' => 'quote',
			'documentId' => $quoteId,
			'output' => 'file',
		]);
		if (!empty($result['path'])) $this->setQuoteDownloadGrant($quoteId, (string)$result['path']);

		return $result;
	}

	protected function setQuoteDownloadGrant(int $quoteId, string $path): void
	{
		DEEC_Emailattachment::checkPath($path);
		$basePath = realpath(BASE_PATH);
		$realPath = realpath($path);
		if ($basePath === false || $realPath === false || !str_starts_with($realPath, $basePath . DIRECTORY_SEPARATOR)) {
			throw new RuntimeException('Quote PDF path is invalid');
		}

		$this->formDataSession->downloadFile = [
			'siteid' => (int)$this->_siteContext->getSiteId(),
			'clientid' => (int)$this->_siteContext->getClientId(),
			'quoteid' => $quoteId,
			'relative' => substr($realPath, strlen($basePath) + 1),
		];
	}

	protected function restoreInquiryResult(array $inquiry): array
	{
		$storedData = json_decode((string)($inquiry['data'] ?? ''), true);
		$status = is_array($storedData) && trim((string)($storedData['sku'] ?? '')) !== '' ? 'quote_failed' : null;
		$quoteId = (int)($inquiry['quoteid'] ?? 0);
		if ($quoteId <= 0 || !(new Shops_Model_DbTable_Quote())->getById($quoteId)) return ['url' => null, 'status' => $status];

		try {
			$pdf = $this->generateQuotePdf($quoteId);
			return ['url' => $pdf['url'] ?? null, 'status' => 'ready'];
		} catch (Throwable $e) {
			error_log('Stored shop inquiry result could not be restored: ' . $e->getMessage());
			return ['url' => null, 'status' => 'pdf_failed'];
		}
	}
}
