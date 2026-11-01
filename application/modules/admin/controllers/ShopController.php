<?php

class Admin_ShopController extends DEEC_Controller_AdminAction
{
	private const REDIRECT_SESSION = 'AdminLegacyRedirect';
	private const REDIRECT_SESSION_TTL = 900;

	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'shops',
			'list' => 'Admin_Model_List_Shops',
			'entity' => Admin_Model_Entity_Shop::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_SHOP'),
		];
	}

	protected function buildEditViewModel(int $id, array $row): array
	{
		$viewModel = parent::buildEditViewModel($id, $row);
		$redirectDb = new Admin_Model_DbTable_Slugredirect();
		$session = $this->getRedirectSession();
		$preview = null;

		if ($this->isValidRedirectPreview($session, $id)) {
			$preview = $redirectDb->previewImport($id, (array)$session->rows);
		} elseif (isset($session->token)) {
			$session->unsetAll();
		}

		return $viewModel + [
			'legacyRedirects' => $redirectDb->getManagementRows($id),
			'legacyRedirectTargets' => $redirectDb->getTargetsByShop($id),
			'legacyRedirectCsrf' => $this->getRedirectCsrfToken($session),
			'legacyRedirectPreview' => $preview,
			'legacyRedirectPreviewToken' => $preview ? (string)$session->token : '',
		];
	}

	public function legacyredirectAction()
	{
		$this->disableView();
		$request = $this->getRequest();
		$shopId = (int)$this->_getParam('id', 0);
		$session = $this->getRedirectSession();

		if (!$request->isPost() || !$this->isValidRedirectCsrf($session, (string)$request->getPost('csrf_token', ''))) {
			$this->_flashMessenger->addMessage('MESSAGES_REDIRECT_CSRF_INVALID');
			return $this->redirectToShop($shopId);
		}

		$db = new Admin_Model_DbTable_Slugredirect();
		$adapter = $db->getAdapter();

		try {
			$adapter->beginTransaction();
			if ((string)$request->getPost('redirect_action', '') === 'disable') {
				$db->disablePath($shopId, (int)$request->getPost('redirect_id', 0));
				$message = 'MESSAGES_REDIRECT_DISABLED';
			} else {
				$db->saveManualPath($shopId, (string)$request->getPost('old_path', ''), (string)$request->getPost('target_path', ''));
				$message = 'MESSAGES_REDIRECT_CREATED';
			}
			$adapter->commit();
			$this->_flashMessenger->addMessage($message);
		} catch (DomainException $e) {
			$adapter->rollBack();
			$this->_flashMessenger->addMessage($e->getMessage());
		} catch (Throwable $e) {
			$adapter->rollBack();
			throw $e;
		}

		return $this->redirectToShop($shopId);
	}

	public function legacyredirectimportAction()
	{
		$this->disableView();
		$request = $this->getRequest();
		$shopId = (int)$this->_getParam('id', 0);
		$session = $this->getRedirectSession();

		if (!$request->isPost() || !$this->isValidRedirectCsrf($session, (string)$request->getPost('csrf_token', ''))) {
			$this->_flashMessenger->addMessage('MESSAGES_REDIRECT_CSRF_INVALID');
			return $this->redirectToShop($shopId);
		}

		$action = (string)$request->getPost('import_action', 'preview');
		$db = new Admin_Model_DbTable_Slugredirect();

		try {
			if ($action === 'cancel') {
				$session->unsetAll();
				$this->getRedirectCsrfToken($session);
			} elseif ($action === 'confirm') {
				if (!$this->isValidRedirectPreview($session, $shopId)
					|| !hash_equals((string)$session->token, (string)$request->getPost('preview_token', ''))) {
					throw new DomainException('MESSAGES_REDIRECT_IMPORT_SESSION_INVALID');
				}
				$db->importPaths($shopId, (array)$session->rows);
				$session->unsetAll();
				$this->getRedirectCsrfToken($session);
				$this->_flashMessenger->addMessage('MESSAGES_REDIRECT_IMPORT_COMPLETE');
			} else {
				$rows = $db->parseCsv($this->receiveRedirectCsv());
				$session->rows = $rows;
				$session->shopid = $shopId;
				$session->clientid = $this->getRedirectClientId();
				$session->token = bin2hex(random_bytes(24));
				$session->created = time();
			}
		} catch (DomainException $e) {
			if ($action !== 'confirm') $session->unsetAll();
			$this->getRedirectCsrfToken($session);
			$this->_flashMessenger->addMessage($e->getMessage());
		}

		return $this->redirectToShop($shopId);
	}

	protected function receiveRedirectCsv(): string
	{
		$file = $_FILES['redirect_csv'] ?? null;
		if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| (int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > Admin_Model_DbTable_Slugredirect::MAX_CSV_SIZE
			|| empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
			throw new DomainException('MESSAGES_REDIRECT_CSV_INVALID');
		}

		$contents = file_get_contents((string)$file['tmp_name']);
		if ($contents === false) throw new DomainException('MESSAGES_REDIRECT_CSV_INVALID');

		return $contents;
	}

	protected function isValidRedirectCsrf(Zend_Session_Namespace $session, string $token): bool
	{
		return $token !== '' && isset($session->csrf) && hash_equals((string)$session->csrf, $token);
	}

	private function getRedirectSession(): Zend_Session_Namespace
	{
		return new Zend_Session_Namespace(self::REDIRECT_SESSION);
	}

	private function getRedirectCsrfToken(Zend_Session_Namespace $session): string
	{
		if (empty($session->csrf)) $session->csrf = bin2hex(random_bytes(24));
		return (string)$session->csrf;
	}

	private function isValidRedirectPreview(Zend_Session_Namespace $session, int $shopId): bool
	{
		return isset($session->rows, $session->shopid, $session->clientid, $session->token, $session->created)
			&& (int)$session->shopid === $shopId
			&& (int)$session->clientid === $this->getRedirectClientId()
			&& time() - (int)$session->created <= self::REDIRECT_SESSION_TTL;
	}

	private function getRedirectClientId(): int
	{
		$client = Zend_Registry::get('Client');
		return (int)$client['id'];
	}

	private function redirectToShop(int $shopId)
	{
		return $this->_helper->redirector->gotoSimple('edit', 'shop', 'admin', ['id' => $shopId]);
	}

	protected function afterCreate(int $id, array $data): void
	{
		$pageDb = new Admin_Model_DbTable_Page();
		$pageDb->create([
			'shopid' => $id,
			'parentid' => 0,
			'type' => 'home',
			'title' => $this->view->translate('ADMIN_HOME_PAGE'),
			'ordering' => $pageDb->getNextOrdering([
				'shopid' => $id,
				'parentid' => 0,
				'type' => 'home',
			]),
			'activated' => 1,
		]);

		$menuDb = new Admin_Model_DbTable_Menu();

		foreach ([
			'header' => 'ADMIN_HEADER_MENU',
			'header-actions' => 'ADMIN_HEADER_ACTIONS_MENU',
			'footer' => 'ADMIN_FOOTER_MENU',
		] as $position => $title) {
			$menuDb->create([
				'shopid' => $id,
				'title' => $this->view->translate($title),
				'position' => $position,
				'ordering' => $menuDb->getNextOrdering([
					'shopid' => $id,
					'position' => $position,
				]),
				'activated' => 1,
			]);
		}
	}

	protected function afterCopy(int $oldId, int $newId, array $oldRow, array $newRow): void
	{
		$configDb = new Admin_Model_DbTable_Config();
		$config = $configDb->getConfigByShopID($oldId);

		if (!$config) {
			return;
		}

		unset($config['id']);

		$config['shopid'] = $newId;
		$config['modified'] = null;
		$config['modifiedby'] = 0;
		$config['locked'] = 0;
		$config['lockedtime'] = null;

		$configDb->addConfig($config);
	}
}
