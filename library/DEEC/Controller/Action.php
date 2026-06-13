<?php

abstract class DEEC_Controller_Action extends Zend_Controller_Action
{
	protected $_date = null;
	protected $_user = null;
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		$this->view->id = isset($params['id']) ? (int)$params['id'] : 0;
		$this->view->action = $params['action'] ?? '';
		$this->view->contextAction = $params['context_action'] ?? ($params['action'] ?? '');
		$this->view->controller = $params['controller'] ?? '';
		$this->view->module = $params['module'] ?? '';
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user;
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		if ($this->view->id) {
			$this->view->dirwritable = $this->_helper->Directory->isWritable(
				$this->view->id,
				'attachment',
				$this->_flashMessenger
			);
		}
	}

	public function indexAction()
	{
		if ($this->getRequest()->isPost()) {
			$this->disableLayout();
		}

		$this->buildIndexView();
	}

	public function searchAction()
	{
		$this->_helper->viewRenderer->setRender('index');
		$this->disableLayout();

		if ($this->_getParam('parent', '') !== '') {
			$this->getRequest()->setParam('context_action', 'select');
		}

		$this->buildIndexView();
	}

	public function selectAction()
	{
		$this->_helper->getHelper('layout')->setLayout('plain');

		$this->buildIndexView();
	}

	public function addAction()
	{
		$db = $this->getDb();

		$data = $this->getCreateData();
		$data = $this->beforeCreate($data);

		$id = $db->create($data);

		$this->afterCreate($id, $data);

		return $this->_helper->redirector->gotoSimple(
			'edit',
			$this->getRequest()->getControllerName(),
			null,
			['id' => $id]
		);
	}

	protected function getCreateData(): array
	{
		return [];
	}

	protected function beforeCreate(array $data): array
	{
		return $data;
	}

	protected function afterCreate(int $id, array $data): void
	{
	}

	public function editAction()
	{
		$request = $this->getRequest();
		$id = (int)$this->_getParam('id', 0);
		$isAjax = $request->isXmlHttpRequest();

		$db = $this->getDb();
		$row = $db->getById($id);

		if (!$row) {
			if ($isAjax) {
				$this->disableView();

				return $this->_helper->json([
					'ok' => false,
					'message' => 'not_found',
				]);
			}

			$this->_flashMessenger->addMessage($this->getNotFoundMessage());
			return $this->_helper->redirector->gotoSimple('index', $this->getRequest()->getControllerName());
		}

		$beforeEditResult = $this->beforeEdit((array)$row);

		if ($beforeEditResult !== null) {
			return $beforeEditResult;
		}

		$this->_helper->Access->lock($id, $this->_user['id'], $row['locked'] ?? 0, $row['lockedtime'] ?? null);

		$formData = $this->getEditForm();
		$form = $formData['form'];
		$options = $formData['options'];
		$toolbar = $this->getEditToolbar();

		if ($request->isPost()) {
			if ($isAjax) {
				return $this->handleEditAjaxSave($form, $db, $id, $row);
			}

			$post = (array)$request->getPost();

			if (!$form->isValid($post)) {
				$form->setValues($post);
			} else {
				$values = $form->getFilteredValues();
				$values = $this->beforeEditSave($values, $row);

				$db->updateById($id, $values);
				$this->afterEditSave($id, $values, $row);

				$this->_flashMessenger->addMessage('MESSAGES_SAVED');

				return $this->_helper->redirector->gotoSimple(
					'edit',
					$this->getRequest()->getControllerName(),
					null,
					['id' => $id]
				);
			}
		} else {
			$locale = Zend_Registry::get('Zend_Locale');
			$form->setValues(DEEC_Display::rowToFormValues($form, $row, $locale));

			$this->_helper->MultiEntityLoader->populate(
				$form,
				$id,
				$this->getRequest()->getModuleName(),
				$this->getRequest()->getControllerName()
			);
		}

		$vm = $this->buildEditViewModel($id, (array)$row);

		$this->view->assign(array_merge($vm, [
			'id' => $id,
			'form' => $form,
			'toolbar' => $toolbar,
			'options' => $options,
			'activeTab' => $request->getCookie('tab', null),
		]));

		$this->assignMessages();
	}

	protected function getDb(): DEEC_Model_DbTable_Entity
	{
		$class = $this->getDbTableClass();

		if (!class_exists($class)) {
			throw new RuntimeException('DB class not found: ' . $class);
		}

		$db = new $class();

		if (!$db instanceof DEEC_Model_DbTable_Entity) {
			throw new RuntimeException($class . ' must extend DEEC_Model_DbTable_Entity');
		}

		return $db;
	}

	protected function disableView(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();
	}

	protected function disableLayout(): void
	{
		$this->_helper->layout->disableLayout();
	}

	protected function getFormClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Form_'
			. $this->getControllerClassName();
	}

	protected function getEditForm(): array
	{
		$formClass = $this->getFormClass();

		$form = new $formClass();
		$options = $this->_helper->Options->applyFormOptions($form);

		return [
			'form' => $form,
			'options' => $options,
		];
	}

	protected function buildEditViewModel(int $id, array $row): array
	{
		$service = $this->getEditViewModelService();

		if (!$service) {
			return [];
		}

		return $service->build(
			$id,
			(array)$this->_user,
			$row,
			$this->getEntityContext($row)
		);
	}

	protected function getEntityContext(array $row): array
	{
		return [
			'module' => $this->getRequest()->getModuleName(),
			'controller' => $this->getRequest()->getControllerName(),
		];
	}

	protected function beforeEdit(array $row)
	{
		return null;
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
	}

	protected function handleEditAjaxSave(DEEC_Form $form, DEEC_Model_DbTable_Entity $db, int $id, array $row)
	{
		$this->disableView();

		$post = (array)$this->getRequest()->getPost();

		if (!$form->isValidPartial($post)) {
			return $this->_helper->json([
				'ok' => false,
				'errors' => $this->toErrorMessages($form->getErrors(), $form),
			]);
		}

		$values = $form->getFilteredValuesPartial($post);
		$values = $this->beforeEditSave($values, $row);

		try {
			$db->updateById($id, $values);
			$this->afterEditSave($id, $values, $row);
		} catch (Exception $e) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'save_failed',
			]);
		}

		$newRow = $db->getById($id);
		$changedFields = array_keys($values);

		return $this->_helper->json([
			'ok' => true,
			'id' => $id,
			'values' => array_intersect_key($newRow, array_flip($changedFields)),
			'display' => DEEC_Display::fromRow($form, $newRow, $changedFields),
			'meta' => [
				'recalc' => [],
			],
		]);
	}

	protected function getModuleClassPrefix(): string
	{
		return ucfirst($this->getRequest()->getModuleName());
	}

	protected function getControllerClassName(): string
	{
		return ucfirst($this->getRequest()->getControllerName());
	}

	protected function getToolbarClass(): string
	{
		return $this->getModuleClassPrefix() . '_Form_Toolbar';
	}

	protected function getToolbarInlineClass(): string
	{
		return $this->getModuleClassPrefix() . '_Form_ToolbarInline';
	}

	protected function getDbTableClass(): string
	{
		return $this->getModuleClassPrefix()
			. '_Model_DbTable_'
			. $this->getControllerClassName();
	}

	protected function getEditToolbar()
	{
		$class = $this->getToolbarClass();

		if (!class_exists($class)) {
			return null;
		}

		return new $class();
	}

	protected function getEditViewModelService()
	{
		$class = $this->getEditViewModelServiceClass();

		if ($class && class_exists($class)) {
			return new $class();
		}

		$class = $this->getModuleClassPrefix() . '_Service_' . $this->getControllerClassName() . 'EditViewModel';

		if (class_exists($class)) {
			return new $class();
		}

		$class = $this->getModuleClassPrefix() . '_Service_EditViewModel';

		if (class_exists($class)) {
			return new $class();
		}

		if (class_exists('DEEC_Service_EditViewModel')) {
			return new DEEC_Service_EditViewModel();
		}

		return null;
	}

	protected function getEditViewModelServiceClass(): ?string
	{
		return null;
	}

	protected function getNotFoundMessage(): string
	{
		return 'MESSAGES_' . strtoupper($this->getRequest()->getControllerName()) . '_NOT_FOUND';
	}

	protected function loadRow(int $id): ?array
	{
		$row = $this->getDb()->getById($id);

		return $row ? (array)$row : null;
	}

	protected function requireRow(int $id): array
	{
		$row = $this->loadRow($id);

		if ($row) {
			return (array)$row;
		}

		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->disableView();

			$this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		$this->_flashMessenger->addMessage($this->getNotFoundMessage());

		$this->_helper->redirector->gotoSimple(
			'index',
			$this->getRequest()->getControllerName()
		);

		exit;
	}

	public function getAction()
	{
		$this->disableView();

		$elementName = (string)$this->_getParam('element', '');

		if ($elementName !== '') {
			return $this->getElementOptions($elementName);
		}

		$parentId = (int)$this->_getParam('parentid', 0);

		if ($parentId > 0) {
			return $this->getRowsByParentId($parentId);
		}

		$id = (int)$this->_getParam('id', 0);

		if ($id > 0) {
			return $this->getRowData($id);
		}

		return $this->_helper->json([
			'ok' => false,
			'message' => 'missing_parameter',
		]);
	}

	protected function getElementOptions(string $elementName)
	{
		$formClass = $this->getToolbarClass();

		if (!class_exists($formClass)) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'toolbar_not_found',
			]);
		}

		$form = new $formClass();
		$el = $form->getElement($elementName);

		if (!$el) {
			return $this->_helper->json([
				'ok' => false,
				'message' => $this->view->translate('MESSAGES_ELEMENT_DOES_NOT_EXISTS'),
			]);
		}

		return $this->_helper->json($el['options'] ?? []);
	}

	protected function getRowData(int $id)
	{
		$row = $this->loadRow($id);

		if (!$row) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_found',
			]);
		}

		return $this->_helper->json([
			'ok' => true,
			'item' => $row,
		]);
	}

	protected function getRowsByParentId(int $parentId)
	{
		$db = $this->getDb();

		if (!method_exists($db, 'getByParentId')) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'method_not_found',
			]);
		}

		$parentModule = (string)$this->_getParam('parent_module', $this->getRequest()->getModuleName());
		$parentController = (string)$this->_getParam('parent_controller', $this->getRequest()->getControllerName());

		$items = $db->getByParentId($parentId, $parentModule, $parentController);

		return $this->_helper->json([
			'ok' => true,
			'items' => $items,
		]);
	}

	protected function saveFormAjax(DEEC_Form $form, DEEC_Model_DbTable_Entity $db, int $id): array
	{
		$service = new DEEC_Service_FormSaveService();

		return $service->save(
			$form,
			$db,
			$id,
			(array)$this->getRequest()->getPost(),
			true
		);
	}

	public function copyAction()
	{
		$this->disableView();

		$id = (int)$this->_getParam('id', 0);

		if ($id <= 0) {
			$this->_flashMessenger->addMessage($this->getNotFoundMessage());
			return $this->_helper->redirector->gotoSimple('index');
		}

		$db = $this->getDb();
		$newId = $db->copyById($id);

		$this->_flashMessenger->addMessage('MESSAGES_SUCCESFULLY_COPIED');

		return $this->_helper->redirector->gotoSimple(
			'edit',
			$this->getRequest()->getControllerName(),
			null,
			['id' => $newId]
		);
	}

	public function sortAction()
	{
		$this->disableView();

		if (!$this->getRequest()->isXmlHttpRequest() || !$this->getRequest()->isPost()) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'invalid_request',
			]);
		}

		$id = (int)$this->_getParam('id', 0);
		$direction = (string)$this->_getParam('ordering', '');

		$db = $this->getDb();

		if (!$db->moveOrdering($id, $direction)) {
			return $this->_helper->json([
				'ok' => false,
				'message' => 'not_sorted',
			]);
		}

		return $this->_helper->json([
			'ok' => true,
		]);
	}

	public function pinAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$this->disableView();
		$this->_helper->Pin->toggle($id);
	}

	public function lockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->lock($id, $this->_user['id']);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
	}

	public function unlockAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$result = $this->_helper->Access->unlock($id);

		if (is_array($result)) {
			return $this->_helper->json($result);
		}
	}

	public function keepaliveAction()
	{
		$id = (int)$this->_getParam('id', 0);
		$this->disableView();
		$this->_helper->Access->keepalive($id);
	}

	public function validateAction()
	{
		$this->disableView();
		$this->_helper->Validate();
	}

	protected function buildListView(array $config): DEEC_List
	{
		$toolbarClass = $config['toolbar'] ?? $this->getToolbarClass();
		$toolbarInlineClass = $config['toolbarInline'] ?? $this->getToolbarInlineClass();

		$toolbar = new $toolbarClass();
		$toolbarInline = new $toolbarInlineClass();

		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		if (isset($config['entity'])) {
			$listQuery = new DEEC_List_Query();
			$result = $listQuery->fetch($params, $options, $config['entity']);
		} else {
			$result = call_user_func($config['items'], $params, $options);
		}

		$records = null;

		if (is_array($result) && array_key_exists(0, $result) && array_key_exists(1, $result)) {
			$items = $result[0];
			$records = (int)$result[1];
		} else {
			$items = $result;
		}

		$this->assignPagination($params, $records, $items, $toolbar);

		$contextAction = $this->_getParam('context_action', $this->getRequest()->getActionName());

		$list = new $config['list']();
		$list->configure([
			'items' => $items,
			'options' => $options,
			'view' => $this->view,
			'module' => $this->getRequest()->getModuleName(),
			'controller' => $this->getRequest()->getControllerName(),
			'toolbarInline' => $toolbarInline,
			'context' => [
				'user' => $this->_user,
				'action' => $contextAction,
				'parent' => $this->_getParam('parent', null),
				'setid' => (int)$this->_getParam('setid', 0),
			],
		]);

		$this->view->{$config['viewKey']} = $list;
		$this->view->{$config['viewKey'] . 'Items'} = $items;
		$this->view->contextAction = $contextAction;
		$this->view->options = $options;
		$this->view->toolbar = $toolbar;
		$this->view->toolbarInline = $toolbarInline;

		$this->assignMessages();

		return $list;
	}

	protected function assignPagination(array $params, $records, $items, $toolbar): void
	{
		if ($records === null) {
			return;
		}

		$limit = (int)($params['limit'] ?? 25);
		$page = (int)($params['page'] ?? 1);

		if ($limit <= 0) {
			$limit = $records > 0 ? $records : 1;
		}

		if ($page <= 0) {
			$page = 1;
		}

		$count = is_countable($items) ? count($items) : 0;
		$start = $records > 0 ? (($page - 1) * $limit) + 1 : 0;
		$end = $records > 0 ? min($start + $count - 1, $records) : 0;
		$pages = $limit > 0 ? (int)ceil($records / $limit) : 1;

		$this->view->pagination = [
			'count' => $count,
			'start' => $start,
			'end' => $end,
			'records' => $records,
			'page' => $page,
			'limit' => $limit,
			'pages' => $pages,
		];

		if ($toolbar->getElement('page')) {
			$pageOptions = [];

			for ($i = 1; $i <= $pages; $i++) {
				$pageOptions[(string)$i] = (string)$i;
			}

			$toolbar->addOptions('page', $pageOptions, 'replace');
			$toolbar->setValue('page', (string)$page);
		}
	}

	protected function getLatestOrdering(string $dbClass, string $fetchMethod, string $sortMethod, array $args): int
	{
		$db = new $dbClass();
		$rows = call_user_func_array([$db, $fetchMethod], $args);

		$latest = 0;

		foreach ($rows as $row) {
			$ordering = (int)($row['ordering'] ?? $row->ordering ?? 0);

			if ($ordering > $latest) {
				$latest = $ordering;
			}
		}

		return $latest;
	}

	protected function resetOrdering(string $dbClass, string $fetchMethod, string $sortMethod, array $args): void
	{
		$db = new $dbClass();
		$rows = call_user_func_array([$db, $fetchMethod], $args);

		$i = 1;

		foreach ($rows as $row) {
			$id = (int)($row['id'] ?? $row->id ?? 0);
			$ordering = (int)($row['ordering'] ?? $row->ordering ?? 0);

			if ($id > 0 && $ordering !== $i) {
				$db->$sortMethod($id, $i);
			}

			$i++;
		}
	}

	protected function assignMessages(): void
	{
		$this->view->messages = array_merge(
			$this->_flashMessenger->getMessages(),
			$this->_flashMessenger->getCurrentMessages()
		);

		$this->_flashMessenger->clearCurrentMessages();
	}

	protected function toErrorMessages(array $errors, DEEC_Form $form): array
	{
		$out = [];

		foreach ($errors as $field => $codes) {
			if (empty($codes) || !is_array($codes)) {
				continue;
			}

			$out[$field] = [];

			foreach ($codes as $code) {
				$out[$field][] = $this->translateFormErrorCode((string)$code, $form, (string)$field);
			}

			$out[$field] = array_values(array_unique(array_filter($out[$field])));
		}

		return $out;
	}

	protected function translateFormErrorCode(string $code, DEEC_Form $form, string $field = ''): string
	{
		switch ($code) {
			case 'required':
				return 'Dieses Feld ist erforderlich.';
			case 'email':
				return 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
			case 'number':
				return 'Bitte geben Sie eine Zahl ein.';
			case 'min':
				return 'Der eingegebene Wert ist zu klein.';
			case 'max':
				return 'Der eingegebene Wert ist zu groß.';
			case 'pattern':
				return 'Das Format ist ungültig.';
			default:
				return 'Ungültige Eingabe.';
		}
	}
}
