<?php

class Shops_InquiryController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;
	protected $formDataSession;
	protected $formConfig;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);

		$this->cart = new Shops_Model_ShoppingCart();

		// Make the cart accessible in all views
		$this->view->cart = $this->cart;

		$this->formDataSession = new Zend_Session_Namespace('MultiStepForm');

		// Load form config with safety checks
		$this->formConfig = (new Shops_Model_FormConfig())->getConfig();
		if (!is_array($this->formConfig)) {
			$this->formConfig = [];
		}

		if (!isset($this->formDataSession->inquiryToken)) {
			$this->formDataSession->inquiryToken = bin2hex(random_bytes(16));
		}
		$this->inquiryToken = $this->formDataSession->inquiryToken;
	}

	public function indexAction()
	{
		$shop = Zend_Registry::get('Shop');

		$this->_helper->getHelper('layout')->setLayout('shop');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);
		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->shop = $shop;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		$this->view->categories = $categories;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();

		$request = $this->getRequest();
		$isAjax = $request->isXmlHttpRequest();
		$step = $request->getPost('step', '1');

		// Fallback for initial page load (only for full render)
		if (!$isAjax && !$request->isPost()) {
			$step = '1';
		}

		$stepOrder = array_keys($this->formConfig);
		if (!in_array($step, $stepOrder)) {
			//throw new Zend_Controller_Action_Exception('Ungültiger Schritt');
		}

		$form = new Shops_Form_DynamicForm($this->formConfig, $step);

		// Falls Werte für den aktuellen Step vorhanden sind, ins Formular laden
		if (!empty($this->formDataSession->formData[$step])) {
			$form->setValues($this->formDataSession->formData[$step]);
		}

		$formId = (int)$this->_getParam('id', 0);
	 	$inqDb = new Shops_Model_DbTable_Inquiryform();
 	 	$this->view->formRow = $inqDb->getInquiryform($formId);

		// Handle form submission via AJAX
		if ($request->isPost()) {
			$this->_helper->layout()->disableLayout();
			$post = $request->getPost();

			if ($request->getPost('back')) {
				$currentIndex = array_search($step, $stepOrder);
				$prevStep = $stepOrder[max(0, $currentIndex - 1)];

				$prevForm = new Shops_Form_DynamicForm($this->formConfig, $prevStep);
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
 					'formRow' => $this->view->formRow
				]);

				$this->_helper->json(['status'=>'ok','formHtml'=>$formHtml]);
				return;
			}

 			// Populate -> validate
			$form->setValues($post);
			if (!$form->isValid($post)) {
				// re-render the same step with errors
				$this->view->form = $form;
				$this->view->step = $step;

				$formHtml = $this->view->partial('inquiry/index.phtml', [
					'form' => $form,
					'step' => $step,
					'ajax' => true,
 	 	 			'errors' => $form->getErrors()

				]);

				$this->_helper->json([
					'status' => 'error',
					'formHtml'=> $formHtml
				]);
				return;
			}

			// Save valid data to session under structured key
			$formValues = $form->getValues();
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
				$nextForm = new Shops_Form_DynamicForm($this->formConfig, $nextStep);
				if (!empty($this->formDataSession->formData[$nextStep])) {
					$nextForm->setValues($this->formDataSession->formData[$nextStep]);
				}

				$itemDb = new Shops_Model_DbTable_Item();
				$mediaDb = new Shops_Model_DbTable_Media();

				$itemsWithImages = [];
				foreach ($this->formDataSession->suggestions ?? [] as $suggestion) {
					$item = $itemDb->getItemBySku($suggestion['description'], $shop['id']);
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
					'requestValues' => $this->formDataSession->requestValues ?? []
				]);

				$this->_helper->json([
					'status' => 'ok',
					'formHtml' => $formHtml
				]);
	 	 		return;
			}

			// Last step: send email and redirect
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

			// Save data in DB
			$inquiryDb = new Shops_Model_DbTable_Inquirydata();
			$inquiryDb->save($formId, $shop['id'], $this->inquiryToken, $allFormValues, $shop['clientid']);

			// Auto-create quote if a SKU was provided
			$quoteId = $this->createQuoteFromInquiry($allFormValues, $shop);

			if($quoteId) {
				$generator = new DEEC_Pdf();
				$pdfInfo = $generator->generate([
					'module' => 'shops',
					'controller' => 'quote',
					'documentId' => $quoteId,
					'output' => 'file',
				]);

				// store for the success page
				$this->formDataSession->downloadUrl = $pdfInfo['url'] ?? null;

	 	 	 	// New per-inquiry token right after sending mail and before returning JSON
				$this->formDataSession->inquiryToken = bin2hex(random_bytes(16));

				// ------------------------------------------------------
				// 1) FIRST MAIL: Anfrageformular (no attachment)
				// ------------------------------------------------------
				$this->getRequest()->setPost(array_merge($allFormValues, [
					'subject' => 'Anfrageformular',
					'message' => $allFormValues['message'] ?? 'Vielen Dank für Ihre Anfrage.',
				]));
				Zend_Controller_Action_HelperBroker::getStaticHelper('Email')
					->sendEmail('shops', 'inquiry');

				// ------------------------------------------------------
				// 2) SECOND MAIL: Angebot (with attachment + quote info)
				// ------------------------------------------------------
				$quoteDb = new Shops_Model_DbTable_Quote();
				$quote = $quoteDb->getQuote($quoteId);

				// helpful values for the Angebot template
				$angebotPost = array_merge($allFormValues, [
					//'subject' => 'Angebot ' . ($quote['quoteid'] ?? ''),
					//'message' => 'Im Anhang erhalten Sie Ihr Angebot.',
					'__attach_paths'=> [$pdfInfo['path']], // let helper attach PDF
					//'download_url' => $pdfInfo['url'] ?? null, // in-template link if needed
					//'quote_id' => $quoteId, // for item table lookup
					//'quote_number' => $quote['quoteid'] ?? '', // placeholders
					//'quote_date' => date('d.m.Y', strtotime($quote['created'] ?? 'now')),
					//'quote_total' => number_format((float)($quote['total'] ?? 0), 2, ',', '.'),
					//'quote_filename'=> $pdfInfo['filename'] ?? '',
				]);

				$this->getRequest()->setPost($angebotPost);
				Zend_Controller_Action_HelperBroker::getStaticHelper('Email')
					->sendEmail('shops', 'offer');

				return $this->_helper->redirector->gotoRoute([], 'inquiry_success', true);

				//$successUrl = $this->view->url(['module'=>'shops','controller'=>'inquiry','action'=>'success'], null, true);

				//$this->_helper->json(['status' => 'done', 'redirectUrl' => $successUrl]);
				//return;
			}
		}

		$this->view->form = $form;
		$this->view->step = $step;
	}

	public function successAction()
	{
		$shop = Zend_Registry::get('Shop');

		// Holt die Formulardaten aus der Session
		$this->view->formData = $this->formDataSession->formData;

		$this->_helper->getHelper('layout')->setLayout('shop');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		$slideDb = new Shops_Model_DbTable_Slide();
		$slides = $slideDb->getSlides($shop['id']);

		$images = array();
		$imageDb = new Shops_Model_DbTable_Media();
		$images['categories'] = $imageDb->getCategoryMedia($categories);

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->shop = $shop;
		$this->view->menus = $menus;
		$this->view->images = $images;
		$this->view->slides = $slides;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		$this->view->categories = $categories;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();
		$this->view->downloadUrl = $this->formDataSession->downloadUrl ?? null;

		$isAjax = $this->getRequest()->isXmlHttpRequest();

		if ($isAjax) {
		 	$formHtml = $this->view->partial('inquiry/success.phtml', []);
		 	$this->_helper->json(['status'=>'ok','formHtml'=>$formHtml]);
		 	return;
		}
		return $this->_helper->redirector->gotoSimple('index', 'index', 'default');
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

	private function createQuoteFromInquiry(array $data, array $shop)
	{
		// need: sku (required), qty (optional), title (optional)
		$sku = trim((string)($data['sku'] ?? ''));
		if ($sku === '') return null;

		$qty = (float)($data['quantity'] ?? 1);
		if ($qty <= 0) $qty = 1;

		// 1) Find the item by SKU
		$itemDb = new Shops_Model_DbTable_Item();
		$item = $itemDb->getItemBySku($sku, $shop['id']);
		if (!$item) return null; // invalid sku, silently skip

		// 2) Pick defaults (currency/language/template) like Sales_QuoteController::addAction()
		$currencyDb = new Shops_Model_DbTable_Currency();
		$currency = $currencyDb->getPrimaryCurrency();

		$langDb = new Shops_Model_DbTable_Language();
		$lang = $langDb->getPrimaryLanguage();

		$tplDb = new Shops_Model_DbTable_Template();
		$tpl = $tplDb->getPrimaryTemplate();

		$formId = (int)$this->_getParam('id', 0);
	 	$inqDb = new Shops_Model_DbTable_Inquiryform();
 	 	$formRow = $inqDb->getInquiryform($formId);

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
		 	 	 	$addressDb->addAddress([
		 	 	 	 	'contactid' => $contactRowId,
		 	 	 	 	'type' => 'billing',
		 	 	 	 	'street' => trim($data['adresse']),
		 	 	 	 	'postcode' => trim($data['plz']),
		 	 	 	 	'city' => trim($data['stadt']),
		 	 	 	 	'country' => trim('DE'),
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
			 	$this->_helper->Calculate($quoteId, $this->_date, Zend_Registry::get('User')['id'] ?? 0, 0);
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
