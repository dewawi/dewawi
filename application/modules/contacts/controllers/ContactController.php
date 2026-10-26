<?php

class Contacts_ContactController extends DEEC_Controller_Action
{
	private const MAX_TAG_CONTACTS = 100;
	private const MAX_IMPORT_ROWS = 5000;
	private const IMPORT_SESSION = 'Contacts_Import';
	private const IMPORT_SESSION_TTL = 7200;

	public function init()
	{
		parent::init();

		$this->view->contactid = (int)$this->_getParam('contactid', 0);

		$id = $this->view->contactid ?: $this->view->id;

		if ($id) {
			$this->view->dirwritable = $this->_helper->Directory->isWritable(
				$id,
				'contact',
				$this->_flashMessenger
			);
			$this->view->dirwritable = $this->_helper->Directory->isWritable(
				$id,
				'attachment',
				$this->_flashMessenger
			);
		}
	}

	protected function buildIndexView(): void
	{
		$list = $this->buildListView([
			'viewKey' => 'contacts',
			'list' => 'Contacts_Model_List_Contacts',
			'entity' => Contacts_Model_Entity_Contact::listConfig(),
		]);

		$contactIds = [];
		$contactClientId = 0;

		foreach($list->getItems() as $contact) {
			$contactIds[] = (int)$contact->id;
			if ($contactClientId === 0) {
				$contactClientId = (int)$contact->clientid;
			}
		}

		$tagEntityDb = new Application_Model_DbTable_Tagentity();
		if ($contactClientId > 0) {
			$tagEntityDb->setClientId($contactClientId);
		}
		$options = $list->getOptions();
		$options['tagEntities'] = $tagEntityDb->getTagEntitiesForParents('contacts', 'contact', $contactIds);

		$contactRoleDb = new Contacts_Model_DbTable_Contactrole();
		if ($contactClientId > 0) {
			$contactRoleDb->setClientId($contactClientId);
		}
		$options['contactRoles'] = $contactRoleDb->getForContacts($contactIds);
		$list->setOptions($options);

		if ($this->getRequest()->getActionName() === 'export') {
			$service = new Contacts_Service_ContactExportService();
			$this->exportList = $service->createList($this->getExportProfile(), $list->getItems(), [
				'view' => $this->view,
				'tagEntities' => $options['tagEntities'],
				'contactRoles' => $options['contactRoles'],
			]);
		}
	}

	public function exportAction()
	{
		try {
			$profile = $this->getExportProfile();
		} catch (InvalidArgumentException $e) {
			$this->disableView();
			$this->getResponse()->setHttpResponseCode(400);
			return;
		}

		parent::exportAction();

		$filename = 'contacts-' . $profile . '-' . date('Ymd-His') . '.csv';
		$this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
	}

	private function getExportProfile(): string
	{
		$profile = (string)$this->_getParam('profile', Contacts_Service_ContactExportService::PROFILE_COMPANIES);

		if (!in_array($profile, [
			Contacts_Service_ContactExportService::PROFILE_COMPANIES,
			Contacts_Service_ContactExportService::PROFILE_CONTACTPERSONS,
		], true)) {
			throw new InvalidArgumentException('Invalid contact export profile.');
		}

		return $profile;
	}

	public function tagsAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isPost()) {
			$this->getResponse()->setHttpResponseCode(405);

			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_INVALID_REQUEST'),
			]);
		}

		try {
			$contactIds = $this->getTagContactIds($this->getRequest()->getPost('ids', []));
		} catch (InvalidArgumentException $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_TAG_SELECTION_INVALID'),
			]);
		}

		$tagId = filter_var(
			$this->getRequest()->getPost('tagid'),
			FILTER_VALIDATE_INT,
			['options' => ['min_range' => 1]]
		);
		$operation = (string)$this->getRequest()->getPost('operation', '');

		if ($tagId === false || !in_array($operation, ['add', 'remove'], true)) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_TAG_INVALID'),
			]);
		}

		$contactDb = new Contacts_Model_DbTable_Contact();
		$tagDb = new Application_Model_DbTable_Tag();
		$tagEntityDb = new Contacts_Model_DbTable_Tag();
		$adapter = $contactDb->getAdapter();

		try {
			$adapter->beginTransaction();

			$tag = $tagDb->getByIdForUpdate((int)$tagId);

			if (
				!$tag
				|| (string)$tag['module'] !== 'contacts'
				|| (string)$tag['controller'] !== 'contact'
				|| (int)$tag['shopid'] !== 0
			) {
				throw new DomainException('invalid_tag');
			}

			foreach($contactIds as $contactId) {
				if (!$contactDb->getByIdForUpdate($contactId)) {
					throw new DomainException('invalid_contact');
				}
			}

			$result = $operation === 'add'
				? $tagEntityDb->assignToContacts($contactIds, (int)$tagId)
				: $tagEntityDb->removeFromContacts($contactIds, (int)$tagId);

			$adapter->commit();
		} catch (DomainException $e) {
			$adapter->rollBack();

			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate(
					$e->getMessage() === 'invalid_tag'
						? 'MESSAGES_TAG_INVALID'
						: 'MESSAGES_CONTACT_NOT_FOUND'
				),
			]);
		} catch (Exception $e) {
			$adapter->rollBack();

			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_TAGS_UPDATE_FAILED'),
			]);
		}

		$message = sprintf(
			$this->view->translate(
				$operation === 'add'
					? 'MESSAGES_TAGS_ASSIGNED'
					: 'MESSAGES_TAGS_REMOVED'
			),
			$result['changed'],
			$result['unchanged']
		);

		return $this->_helper->json([
			'ok' => true,
			'operation' => $operation,
			'changed' => $result['changed'],
			'unchanged' => $result['unchanged'],
			'message' => $message,
		]);
	}

	private function getTagContactIds($ids): array
	{
		if (!is_array($ids) || !$ids || count($ids) > self::MAX_TAG_CONTACTS) {
			throw new InvalidArgumentException('Invalid contact selection');
		}

		$contactIds = [];

		foreach($ids as $id) {
			if (
				!(is_int($id) && $id > 0)
				&& !(is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id))
			) {
				throw new InvalidArgumentException('Invalid contact ID');
			}

			$contactIds[(int)$id] = true;
		}

		return array_keys($contactIds);
	}

	protected function getCreateData(): array
	{
		return [
			'catid' => (int)$this->_getParam('catid', 0),
		];
	}

	protected function afterCreate(int $id, array $data): void
	{
		$client = Zend_Registry::get('Client');

		$addressDb = new Contacts_Model_DbTable_Address();
		$addressDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'billing',
			'country' => $client['country'] ?? '0',
		]);

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phoneDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'phone',
		]);

		$emailDb = new Contacts_Model_DbTable_Email();
		$emailDb->createForParent($id, 'contacts', 'contact');

		$internetDb = new Contacts_Model_DbTable_Internet();
		$internetDb->createForParent($id, 'contacts', 'contact');
	}

	public function addAction()
	{
		$catid = $this->_getParam('catid', 0);

		$data = [
			'catid' => $catid,
		];

		$client = Zend_Registry::get('Client');

		$contactDb = new Contacts_Model_DbTable_Contact();
		$id = $contactDb->addContact($data);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$contactDb->updateContact($id, array('contactid' => $increment));
		$incrementDb->setIncrement($increment, 'contactid');

		$addressDb = new Contacts_Model_DbTable_Address();
		$addressDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'billing',
			'country' => $client['country'] ?? '0',
		]);

		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phoneDb->createForParent($id, 'contacts', 'contact', [
			'type' => 'phone',
		]);

		$emailDb = new Contacts_Model_DbTable_Email();

		$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
		$emailDb->createForParent($id, 'contacts', 'contact', [
			'password' => $password,
		]);

		$internetDb = new Contacts_Model_DbTable_Internet();
		$internetDb->createForParent($id, 'contacts', 'contact');

		$this->_helper->redirector->gotoSimple('edit', 'contact', null, array('id' => $id));
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$form = new Contacts_Form_Contact();
		$options = $this->_helper->Options->applyFormOptions($form);

		$toolbar = new Contacts_Form_Toolbar();
		$contactClientId = $this->getContactClientId();
		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($contactClientId);
		$contactRoleDb = (new Contacts_Model_DbTable_Contactrole())->setClientId($contactClientId);

		$contact = $contactDb->getById($id);

		if (!$contact) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found',
				]);
			}

			$this->_flashMessenger->addMessage('MESSAGES_CONTACT_NOT_FOUND');
			return $this->_helper->redirector->gotoSimple('index', 'contact');
		}

		$this->_helper->Access->lock($id, $this->_user['id'], $contact['locked'] ?? 0, $contact['lockedtime'] ?? null);

		if ($request->isPost()) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json(
					$this->saveContactFormAjax($form, $contactDb, $contactRoleDb, $id)
				);
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				try {
					$this->saveContactForm($form, $contactDb, $contactRoleDb, $id);
					$this->_flashMessenger->addMessage('MESSAGES_SAVED');

					return $this->_helper->redirector->gotoSimple('edit', 'contact', null, ['id' => $id]);
				} catch (InvalidArgumentException $e) {
					$this->_flashMessenger->addMessage('MESSAGES_INVALID_CONTACT_ROLES');
					$form->setValues($post);
				}
			}
		} else {
			$locale = Zend_Registry::get('Zend_Locale');
			$contactDisplay = DEEC_Display::rowToFormValues($form, $contact, $locale);

			$form->setValues($contactDisplay);
			$form->setValue('roles', $contactRoleDb->getForContact($id));

			$this->_helper->MultiEntityLoader->populate($form, $id, 'contacts', 'contact');
		}

		$vmService = new Contacts_Service_ContactEditViewModel();
		$vm = $vmService->build($id, (array)$this->_user, (array)$contact);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
			'activeTabHistory' => $request->getCookie('tab-history', null),
		]));

		$this->assignMessages();
	}

	private function saveContactFormAjax(
		DEEC_Form $form,
		Contacts_Model_DbTable_Contact $contactDb,
		Contacts_Model_DbTable_Contactrole $contactRoleDb,
		int $id
	): array {
		$post = (array)$this->getRequest()->getPost();
		$hasRoles = array_key_exists('roles', $post);

		try {
			$roles = $hasRoles ? Contacts_Model_DbTable_Contactrole::normalizeRoleCodes($post['roles']) : [];
		} catch (InvalidArgumentException $e) {
			return [
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_INVALID_CONTACT_ROLES'),
			];
		}

		unset($post['roles']);
		$adapter = $contactDb->getAdapter();

		try {
			$adapter->beginTransaction();

			if (!$contactDb->getByIdForUpdate($id)) {
				throw new RuntimeException('Contact not found.');
			}

			$service = new DEEC_Service_FormSaveService();
			$result = $service->save($form, $contactDb, $id, $post, true);

			if (empty($result['ok'])) {
				$adapter->rollBack();
				return $result;
			}

			if ($hasRoles) {
				$result['values']['roles'] = $contactRoleDb->syncForContact($id, $roles);
			}

			$adapter->commit();
			return $result;
		} catch (Exception $e) {
			$adapter->rollBack();

			return [
				'ok' => false,
				'message' => 'save_failed',
			];
		}
	}

	private function saveContactForm(
		DEEC_Form $form,
		Contacts_Model_DbTable_Contact $contactDb,
		Contacts_Model_DbTable_Contactrole $contactRoleDb,
		int $id
	): void {
		$values = $form->getFilteredValues();
		$roles = Contacts_Model_DbTable_Contactrole::normalizeRoleCodes($values['roles'] ?? []);
		unset($values['roles']);
		$adapter = $contactDb->getAdapter();

		try {
			$adapter->beginTransaction();

			if (!$contactDb->getByIdForUpdate($id)) {
				throw new RuntimeException('Contact not found.');
			}

			$contactDb->updateById($id, $values);
			$contactRoleDb->syncForContact($id, $roles);
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}
	}

	public function historyAction()
	{
		$this->disableView();

		$id = (int)$this->_getParam('id', 0);
		$contact = $this->requireRow($id);

		$get = new Contacts_Model_Get();

		echo $this->view->partial('partials/history.phtml', [
			'history' => $get->history((int)$contact['contactid']),
			'activeTabHistory' => $this->getRequest()->getCookie('tab-history', null),
			'options' => [],
			'contactId' => $id,
			'id' => $id,
			'toolbar' => new Contacts_Form_Toolbar(),
		]);
	}

	public function copyAction()
	{
		$id = $this->_getParam('id', 0);

		$data = $this->requireRow($id);

		$this->disableView();

		unset($data['id']);
		unset(
			$data['street'],
			$data['postcode'],
			$data['city'],
			$data['country'],
			$data['shippingname1'],
			$data['shippingname2'],
			$data['shippingdepartment'],
			$data['shippingstreet'],
			$data['shippingpostcode'],
			$data['shippingcity'],
			$data['shippingcountry'],
			$data['shippingphone']
		);

		//Set increment value
		$incrementDb = new Application_Model_DbTable_Increment();
		$increment = $incrementDb->getIncrement('contactid');
		$incrementDb->setIncrement(($increment), 'contactid');

		$data['name1'] = $data['name1'].' 2';
		$data['pinned'] = 0;
		$data['modified'] = NULL;
		$data['modifiedby'] = 0;
		$data['contactid'] = $increment;

		echo $contactid = $contact->addContact($data);

		//Copy addresses
		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getByParentId($id, 'contacts', 'contact');
		foreach($addresses as $address) {
			$data = array(
				'type' => $address['type'],
				'street' => $address['street'],
				'postcode' => $address['postcode'],
				'city' => $address['city'],
				'country' => $address['country'],
				'ordering' => $address['ordering']
			);
			$addressDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Phone
		$phoneDb = new Contacts_Model_DbTable_Phone();
		$phones = $phoneDb->getByParentId($id, 'contacts', 'contact');
		foreach($phones as $phone) {
			$data = array(
				'type' => $phone['type'],
				'phone' => $phone['phone'],
				'ordering' => $phone['ordering']
			);
			$phoneDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Email
		$emailDb = new Contacts_Model_DbTable_Email();
		$emails = $emailDb->getByParentId($id, 'contacts', 'contact');
		foreach($emails as $email) {
			$password = password_hash(bin2hex(openssl_random_pseudo_bytes(5)), PASSWORD_DEFAULT);
			$data = array(
				'email' => $email['email'],
				'ordering' => $email['ordering'],
				'password' => $password
			);
			$emailDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		//Internet
		$internetDb = new Contacts_Model_DbTable_Internet();
		$internets = $internetDb->getByParentId($id, 'contacts', 'contact');
		foreach($internets as $internet) {
			$data = array(
				'internet' => $internet['internet'],
				'ordering' => $internet['ordering']
			);
			$internetDb->createForParent($contactid, 'contacts', 'contact', $data);
		}

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');
	}

	public function importAction()
	{
		$request = $this->getRequest();
		$form = new Contacts_Form_Import();
		$clientId = $this->getContactClientId();
		$session = new Zend_Session_Namespace(self::IMPORT_SESSION);
		$tagDb = (new Contacts_Model_DbTable_Tag())->setClientId($clientId);
		$form->getElement('tagid')->addMultiOptions($tagDb->getSelectOptions());
		$action = (string)$request->getPost('import_action', '');

		if ($request->isPost() && $action === 'cancel') {
			$this->clearContactImportSession($session);
			return $this->_helper->redirector->gotoSimple('import', 'contact');
		}

		if ($request->isPost() && $action === 'confirm') {
			$token = (string)$request->getPost('token', '');

			if (!$this->isValidContactImportSession($session, $token, $clientId)) {
				$this->clearContactImportSession($session);
				$this->view->importError = 'CONTACTS_IMPORT_SESSION_EXPIRED';
			} else {
				$preview = $this->buildContactImportPreview((string)$session->file, (string)$session->separator, $clientId);

				try {
					$this->view->importResult = $this->executeContactImport($preview, (int)$session->tagid, $clientId);
					$this->clearContactImportSession($session);
				} catch (Exception $e) {
					$this->view->importError = 'CONTACTS_IMPORT_FAILED';
				}
			}
		} elseif ($request->isPost()) {
			$formData = (array)$request->getPost();
			$formData['separator'] = $formData['separator'] ?? 'auto';
			$formData['tagid'] = $formData['tagid'] ?? '0';

			if ($form->isValid($formData)) {
				$file = null;
				try {
					$this->clearContactImportSession($session);
					$file = $this->receiveContactImportFile($clientId);
					$preview = $this->buildContactImportPreview($file, (string)$formData['separator'], $clientId);
					$token = bin2hex(random_bytes(24));
					$session->file = $file;
					$session->separator = (string)$formData['separator'];
					$session->tagid = (int)$formData['tagid'];
					$session->clientid = $clientId;
					$session->token = $token;
					$session->created = time();
					$this->view->importPreview = $preview;
					$this->view->importToken = $token;
				} catch (Exception $e) {
					if ($file && is_file($file)) unlink($file);
					$this->view->importError = 'CONTACTS_IMPORT_FILE_ERROR';
				}
			} else {
				$form->populate($formData);
			}
		} elseif ($this->isValidContactImportSession($session, (string)($session->token ?? ''), $clientId)) {
			$this->view->importPreview = $this->buildContactImportPreview((string)$session->file, (string)$session->separator, $clientId);
			$this->view->importToken = (string)$session->token;
			$form->getElement('tagid')->setValue((string)$session->tagid);
		}

		$this->view->form = $form;
		$this->assignMessages();
	}

	protected function buildContactImportPreview(string $file, string $separator, int $clientId): array
	{
		$parsed = $this->readContactImportCsv($file, $separator);

		if ($parsed['errors']) {
			return $parsed + ['items' => [], 'counts' => $this->getEmptyImportCounts()];
		}

		$reference = $this->getContactImportReferenceData($clientId);
		$contacts = $reference['contacts'];
		$categories = $reference['categories'];
		$contactsByNumber = [];
		$contactsByName = [];

		foreach ($contacts as $contact) {
			$number = (int)$contact['contactid'];
			if ($number > 0) $contactsByNumber[$number][] = $contact;
			$name = $this->normalizeImportValue((string)$contact['name1']);
			if ($name !== '') $contactsByName[$name][] = $contact;
		}

		$categoryIndex = $this->buildContactImportCategoryIndex($categories);
		$numberCounts = [];
		$nameCounts = [];

		foreach ($parsed['rows'] as $row) {
			$number = trim((string)($row['values']['contactid'] ?? ''));
			if (preg_match('/^[1-9][0-9]*$/D', $number)) $numberCounts[(int)$number] = ($numberCounts[(int)$number] ?? 0) + 1;
			if ($number === '') {
				$name = $this->normalizeImportValue((string)($row['values']['name1'] ?? ''));
				if ($name !== '') $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
			}
		}

		$items = [];
		$counts = $this->getEmptyImportCounts();

		foreach ($parsed['rows'] as $row) {
			$item = $this->buildContactImportPreviewItem($row, $contactsByNumber, $contactsByName, $categoryIndex, $numberCounts, $nameCounts);
			$items[] = $item;
			$counts[$item['status']]++;
		}

		return $parsed + ['items' => $items, 'counts' => $counts];
	}

	protected function readContactImportCsv(string $file, string $separator): array
	{
		$handle = fopen($file, 'rb');
		if ($handle === false) throw new RuntimeException('Unable to read contact import file.');

		$delimiter = $this->resolveContactImportSeparator($handle, $separator);
		$headers = fgetcsv($handle, 0, $delimiter, '"', '');

		if ($headers === false) {
			fclose($handle);
			return ['headers' => [], 'mappedHeaders' => [], 'unknownHeaders' => [], 'rows' => [], 'errors' => ['CONTACTS_IMPORT_EMPTY_FILE']];
		}

		$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
		$aliases = $this->getContactImportHeaderAliases();
		$mappedHeaders = [];
		$unknownHeaders = [];
		$errors = [];

		foreach ($headers as $position => $header) {
			$normalized = $this->normalizeImportHeader((string)$header);
			$canonical = $aliases[$normalized] ?? null;

			if ($canonical === null) {
				if ($normalized !== '') $unknownHeaders[] = trim((string)$header);
				continue;
			}

			if (in_array($canonical, $mappedHeaders, true)) {
				$errors[] = 'CONTACTS_IMPORT_DUPLICATE_HEADER';
				continue;
			}

			$mappedHeaders[$position] = $canonical;
		}

		if (!in_array('contactid', $mappedHeaders, true) && !in_array('name1', $mappedHeaders, true)) {
			$errors[] = 'CONTACTS_IMPORT_REQUIRED_HEADER';
		}

		$rows = [];
		$line = 1;

		while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
			$line++;
			$values = [];

			foreach ($mappedHeaders as $position => $canonical) {
				if (array_key_exists($position, $cells)) $values[$canonical] = trim((string)$cells[$position]);
			}

			if (!array_filter($values, static fn($value): bool => $value !== '')) continue;
			$rows[] = ['line' => $line, 'values' => $values];

			if (count($rows) > self::MAX_IMPORT_ROWS) {
				$errors[] = 'CONTACTS_IMPORT_TOO_MANY_ROWS';
				break;
			}
		}

		fclose($handle);

		if (!$rows && !$errors) $errors[] = 'CONTACTS_IMPORT_EMPTY_FILE';

		return [
			'headers' => $headers,
			'mappedHeaders' => $mappedHeaders,
			'unknownHeaders' => array_values(array_unique($unknownHeaders)),
			'rows' => $rows,
			'errors' => array_values(array_unique($errors)),
			'separator' => $delimiter,
		];
	}

	protected function buildContactImportPreviewItem(array $row, array $contactsByNumber, array $contactsByName, array $categoryIndex, array $numberCounts, array $nameCounts): array
	{
		$values = $row['values'];
		$numberValue = trim((string)($values['contactid'] ?? ''));
		$name = trim((string)($values['name1'] ?? ''));
		$errors = [];
		$existing = null;
		$duplicates = [];
		$csvDuplicate = false;

		if ($numberValue !== '' && !preg_match('/^[1-9][0-9]*$/D', $numberValue)) {
			$errors[] = 'CONTACTS_IMPORT_INVALID_CONTACT_ID';
		} elseif ($numberValue !== '') {
			$number = (int)$numberValue;
			if (($numberCounts[$number] ?? 0) > 1) $errors[] = 'CONTACTS_IMPORT_DUPLICATE_CONTACT_ID_CSV';
			if (count($contactsByNumber[$number] ?? []) > 1) $errors[] = 'CONTACTS_IMPORT_AMBIGUOUS_CONTACT_ID';
			if (count($contactsByNumber[$number] ?? []) === 1) $existing = $contactsByNumber[$number][0];
		}

		if ($existing === null && $name === '') $errors[] = 'CONTACTS_IMPORT_NAME_REQUIRED';
		if ($existing !== null && array_key_exists('name1', $values) && $name === '') $errors[] = 'CONTACTS_IMPORT_NAME_REQUIRED';

		$contactData = [];
		foreach (['name1', 'name2', 'department'] as $field) {
			if (array_key_exists($field, $values)) $contactData[$field] = $values[$field];
		}

		if (array_key_exists('category', $values)) {
			$category = $this->resolveContactImportCategory($values['category'], $categoryIndex);
			if ($category === null) $errors[] = 'CONTACTS_IMPORT_INVALID_CATEGORY';
			else $contactData['catid'] = $category;
		}

		$addressData = [];
		foreach (['street', 'postcode', 'city', 'country'] as $field) {
			if (array_key_exists($field, $values)) $addressData[$field] = $values[$field];
		}

		$phones = $this->splitContactImportValues($values['phone'] ?? '');
		$emails = $this->splitContactImportValues($values['email'] ?? '');
		$internets = $this->splitContactImportValues($values['internet'] ?? '');

		foreach ($emails as $email) {
			if (!DEEC_Filter::isValidEmail($email)) $errors[] = 'CONTACTS_IMPORT_INVALID_EMAIL';
		}

		if ($existing === null && $numberValue === '') {
			$normalizedName = $this->normalizeImportValue($name);
			$duplicates = $contactsByName[$normalizedName] ?? [];
			$csvDuplicate = ($nameCounts[$normalizedName] ?? 0) > 1;
		}

		$status = $errors ? 'error' : ($existing !== null ? 'update' : (($duplicates || $csvDuplicate) ? 'duplicate' : 'new'));

		return [
			'line' => (int)$row['line'],
			'status' => $status,
			'contactid' => $numberValue,
			'name1' => $name !== '' ? $name : (string)($existing['name1'] ?? ''),
			'name2' => (string)($values['name2'] ?? $existing['name2'] ?? ''),
			'category' => (string)($values['category'] ?? ''),
			'existingId' => (int)($existing['id'] ?? 0),
			'duplicates' => array_map(static fn(array $contact): string => (string)$contact['contactid'] . ' · ' . (string)$contact['name1'], $duplicates),
			'csvDuplicate' => $csvDuplicate,
			'errors' => array_values(array_unique($errors)),
			'contactData' => $contactData,
			'addressData' => $addressData,
			'phones' => $phones,
			'emails' => $emails,
			'internets' => $internets,
		];
	}

	protected function executeContactImport(array $preview, int $tagId, int $clientId): array
	{
		if ($preview['errors']) throw new RuntimeException('Contact import preview is invalid.');

		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($clientId);
		$addressDb = (new Contacts_Model_DbTable_Address())->setClientId($clientId);
		$phoneDb = (new Contacts_Model_DbTable_Phone())->setClientId($clientId);
		$emailDb = (new Contacts_Model_DbTable_Email())->setClientId($clientId);
		$internetDb = (new Contacts_Model_DbTable_Internet())->setClientId($clientId);
		$incrementDb = (new Application_Model_DbTable_Increment())->setClientId($clientId);
		$adapter = $contactDb->getAdapter();
		$validItems = array_values(array_filter($preview['items'], static fn(array $item): bool => $item['status'] !== 'error'));
		usort($validItems, static function(array $a, array $b): int {
			$aPriority = (int)$a['existingId'] === 0 && $a['contactid'] !== '' ? 0 : 1;
			$bPriority = (int)$b['existingId'] === 0 && $b['contactid'] !== '' ? 0 : 1;
			return $aPriority <=> $bPriority;
		});
		$existingIds = array_values(array_unique(array_filter(array_map(static fn(array $item): int => (int)$item['existingId'], $validItems))));
		$addresses = $this->groupImportRows($addressDb->getByParentIds($existingIds, 'contacts', 'contact'));
		$phones = $this->groupImportRows($phoneDb->getByParentIds($existingIds, 'contacts', 'contact'));
		$emails = $this->groupImportRows($emailDb->getByParentIds($existingIds, 'contacts', 'contact'));
		$internets = $this->groupImportRows($internetDb->getByParentIds($existingIds, 'contacts', 'contact'));
		$created = 0;
		$updated = 0;
		$tagContactIds = [];

		$tagEntityDb = null;
		if ($tagId > 0) {
			$tagDefinitionDb = (new Application_Model_DbTable_Tag())->setClientId($clientId);
			$tag = $tagDefinitionDb->getById($tagId);
			if (!$tag || $tag['module'] !== 'contacts' || $tag['controller'] !== 'contact' || (int)$tag['shopid'] !== 0) {
				throw new InvalidArgumentException('Invalid contact import tag.');
			}
			$tagEntityDb = (new Contacts_Model_DbTable_Tag())->setClientId($clientId);
		}

		try {
			$adapter->beginTransaction();

			foreach ($validItems as $item) {
				$isNew = (int)$item['existingId'] === 0;
				$contactData = $item['contactData'];

				if ($isNew) {
					$contactData['catid'] = (int)($contactData['catid'] ?? 0);
					$contactData['contactid'] = $this->getImportedContactNumber($item['contactid'], $incrementDb, $contactDb);
					$contactId = $contactDb->create($contactData);
					$created++;
				} else {
					$contactId = (int)$item['existingId'];
					if ($contactData) $contactDb->updateById($contactId, $contactData);
					$updated++;
				}

				$this->importContactAddress($addressDb, $contactId, $item['addressData'], $addresses[$contactId] ?? [], $isNew);
				$this->importContactValues($phoneDb, $contactId, 'phone', $item['phones'], $phones[$contactId] ?? [], ['type' => 'phone']);
				$this->importContactValues($emailDb, $contactId, 'email', $item['emails'], $emails[$contactId] ?? []);
				$this->importContactValues($internetDb, $contactId, 'internet', $item['internets'], $internets[$contactId] ?? []);
				$tagContactIds[] = $contactId;
			}

			if ($tagEntityDb && $tagContactIds) $tagEntityDb->assignToContacts(array_values(array_unique($tagContactIds)), $tagId);
			$adapter->commit();
		} catch (Exception $e) {
			$adapter->rollBack();
			throw $e;
		}

		return [
			'created' => $created,
			'updated' => $updated,
			'skipped' => (int)($preview['counts']['error'] ?? 0),
			'processed' => count($preview['items']),
		];
	}

	private function receiveContactImportFile(int $clientId): string
	{
		$digits = str_pad((string)$clientId, 2, '0');
		$directory = BASE_PATH . '/files/import/' . $digits[0] . '/' . $digits[1] . '/' . $clientId . '/';
		if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
			throw new RuntimeException('Unable to create contact import directory.');
		}

		$target = $directory . 'contacts-' . bin2hex(random_bytes(16)) . '.csv';
		$upload = new Zend_File_Transfer_Adapter_Http();
		$upload->addValidator('Count', false, 1);
		$upload->addValidator('Size', false, 10240000);
		$upload->addValidator('Extension', false, 'csv');
		$upload->addFilter('Rename', ['target' => $target, 'overwrite' => false]);

		if (!$upload->receive() || !is_file($target)) throw new RuntimeException('Unable to receive contact import file.');
		return $target;
	}

	protected function getContactImportReferenceData(int $clientId): array
	{
		$contactDb = (new Contacts_Model_DbTable_Contact())->setClientId($clientId);
		$categoryDb = (new Application_Model_DbTable_Category())->setClientId($clientId);

		return [
			'contacts' => $contactDb->getActiveImportContacts(),
			'categories' => $categoryDb->getCategories('contact'),
		];
	}

	private function resolveContactImportSeparator($handle, string $separator): string
	{
		if ($separator === 'comma') return ',';
		if ($separator === 'semicolon') return ';';

		$position = ftell($handle);
		$line = fgets($handle);
		fseek($handle, $position);

		if ($line === false) return ';';
		return count(str_getcsv($line, ';', '"', '')) >= count(str_getcsv($line, ',', '"', '')) ? ';' : ',';
	}

	private function getContactImportHeaderAliases(): array
	{
		$aliases = [
			'contactid' => ['contactid', 'contact id', 'contact number', 'kontaktnr', 'kontaktnummer'],
			'name1' => ['name1', 'company', 'company name', 'organisation', 'organization', 'firmenname', 'unternehmen'],
			'name2' => ['name2', 'name affix', 'namenszusatz'],
			'department' => ['department', 'abteilung'],
			'category' => ['category', 'kategorie'],
			'street' => ['street', 'strasse', 'straße'],
			'postcode' => ['postcode', 'post code', 'postal code', 'zip', 'plz'],
			'city' => ['city', 'ort'],
			'country' => ['country', 'land'],
			'phone' => ['phone', 'telephone', 'telefon'],
			'email' => ['email', 'e mail'],
			'internet' => ['internet', 'website', 'url'],
		];
		$index = [];

		foreach ($aliases as $canonical => $names) {
			foreach ($names as $name) $index[$this->normalizeImportHeader($name)] = $canonical;
		}

		return $index;
	}

	private function buildContactImportCategoryIndex(array $categories): array
	{
		$index = ['ids' => [], 'names' => []];

		foreach ($categories as $id => $category) {
			$id = (int)$id;
			$index['ids'][$id] = true;
			foreach ([(string)($category['title'] ?? ''), (string)($category['breadcrumb'] ?? '')] as $name) {
				$name = $this->normalizeImportValue($name);
				if ($name !== '') $index['names'][$name][$id] = true;
			}
		}

		return $index;
	}

	private function resolveContactImportCategory(string $value, array $index): ?int
	{
		$value = trim($value);
		if ($value === '' || $value === '0') return 0;
		if (preg_match('/^[1-9][0-9]*$/D', $value)) return isset($index['ids'][(int)$value]) ? (int)$value : null;

		$matches = array_keys($index['names'][$this->normalizeImportValue($value)] ?? []);
		return count($matches) === 1 ? (int)$matches[0] : null;
	}

	private function splitContactImportValues(string $value): array
	{
		$values = preg_split('/[,|\r\n]+/', $value);
		$unique = [];

		foreach ($values as $item) {
			$item = trim($item);
			if ($item !== '') $unique[$this->normalizeImportValue($item)] = $item;
		}

		return array_values($unique);
	}

	private function normalizeImportValue(string $value): string
	{
		$value = trim(preg_replace('/\s+/u', ' ', $value));
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function normalizeImportHeader(string $value): string
	{
		$value = preg_replace('/[^\pL\pN]+/u', ' ', $value);
		return $this->normalizeImportValue($value);
	}

	private function getEmptyImportCounts(): array
	{
		return ['new' => 0, 'update' => 0, 'duplicate' => 0, 'error' => 0];
	}

	private function groupImportRows(array $rows): array
	{
		$grouped = [];
		foreach ($rows as $row) $grouped[(int)$row['parentid']][] = $row;
		return $grouped;
	}

	private function getImportedContactNumber(string $value, Application_Model_DbTable_Increment $incrementDb, Contacts_Model_DbTable_Contact $contactDb): int
	{
		$current = (int)$incrementDb->getIncrement('contactid', true);

		if ($value !== '') {
			$number = (int)$value;
			if ($contactDb->getContactWithID($number)) throw new RuntimeException('Contact number already exists.');
			if ($number >= $current) $incrementDb->setIncrement($number, 'contactid');
			return $number;
		}

		while ($contactDb->getContactWithID($current)) $current++;
		$incrementDb->setIncrement($current, 'contactid');
		return $current;
	}

	private function importContactAddress(Contacts_Model_DbTable_Address $addressDb, int $contactId, array $data, array $existing, bool $isNew): void
	{
		$billing = null;
		foreach ($existing as $address) {
			if ($address['type'] === 'billing') {
				$billing = $address;
				break;
			}
		}

		if ($billing && $data) {
			$addressDb->updateById((int)$billing['id'], $data);
			return;
		}

		if (!$billing && ($data || $isNew)) {
			if ($isNew && !array_key_exists('country', $data)) {
				$client = Zend_Registry::get('Client');
				$data['country'] = $client['country'] ?? '0';
			}
			$data['type'] = 'billing';
			$addressDb->createForParent($contactId, 'contacts', 'contact', $data);
		}
	}

	private function importContactValues(DEEC_Model_DbTable_Entity $db, int $contactId, string $field, array $values, array $existing, array $defaults = []): void
	{
		$known = [];
		foreach ($existing as $row) {
			$value = $this->normalizeImportValue((string)($row[$field] ?? ''));
			if ($value !== '') $known[$value] = true;
		}

		foreach ($values as $value) {
			$normalized = $this->normalizeImportValue($value);
			if (isset($known[$normalized])) continue;
			$data = $defaults + [$field => $value];
			if ($field === 'email') $data['password'] = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
			$db->createForParent($contactId, 'contacts', 'contact', $data);
			$known[$normalized] = true;
		}
	}

	private function getContactClientId(): int
	{
		$client = Zend_Registry::get('Client');
		$clientId = !empty($client['parentid']) ? (int)($client['modules']['contacts'] ?? 0) : (int)$client['id'];
		if ($clientId <= 0) throw new RuntimeException('Contact client context is missing.');
		return $clientId;
	}

	private function isValidContactImportSession(Zend_Session_Namespace $session, string $token, int $clientId): bool
	{
		return $token !== ''
			&& isset($session->token, $session->file, $session->clientid, $session->created)
			&& hash_equals((string)$session->token, $token)
			&& (int)$session->clientid === $clientId
			&& time() - (int)$session->created <= self::IMPORT_SESSION_TTL
			&& is_file((string)$session->file);
	}

	private function clearContactImportSession(Zend_Session_Namespace $session): void
	{
		if (isset($session->file) && is_file((string)$session->file)) unlink((string)$session->file);
		$session->unsetAll();
	}

	public function suggestAction()
	{
		$this->disableView();

		$keyword = trim((string)$this->_getParam('q', ''));

		if (mb_strlen($keyword) < 2) {
			return $this->_helper->json([
				'ok' => true,
				'items' => [],
			]);
		}

		$contactDb = new Contacts_Model_DbTable_Contact();

		return $this->_helper->json([
			'ok' => true,
			'items' => $contactDb->suggestContacts($keyword, (int)$this->_user['clientid']),
		]);
	}
}
