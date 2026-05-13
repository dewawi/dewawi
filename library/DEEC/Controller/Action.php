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

	protected function disableView(): void
	{
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout->disableLayout();
	}

	protected function disableLayout(): void
	{
		$this->_helper->layout->disableLayout();
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

	protected function getNotFoundMessage(): string
	{
		return 'MESSAGES_' . strtoupper($this->getRequest()->getControllerName()) . '_NOT_FOUND';
	}

	protected function loadRow(int $id): ?array
	{
		$dbClass = $this->getDbTableClass();

		$db = new $dbClass();
		$row = $db->getById($id);

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
		$dbClass = $this->getDbTableClass();
		$db = new $dbClass();

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

		$result = call_user_func($config['items'], $params, $options);

		$records = null;

		if (is_array($result) && array_key_exists(0, $result) && array_key_exists(1, $result)) {
			$items = $result[0];
			$records = (int)$result[1];
		} else {
			$items = $result;
		}

		$this->assignPagination($params, $records, $items);

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

	protected function assignPagination(array $params, $records, $items): void
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
