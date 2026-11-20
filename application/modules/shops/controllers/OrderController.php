<?php

class Shops_OrderController extends DEEC_Controller_Action
{
	private Shops_Service_OrderWorkflow $workflow;

	public function init()
	{
		if (!Zend_Auth::getInstance()->hasIdentity() || !Zend_Registry::isRegistered('User') || !Zend_Registry::isRegistered('Client')) throw new Zend_Controller_Action_Exception('Forbidden', 403);
		$this->workflow = new Shops_Service_OrderWorkflow();
		$this->workflow->requirePermission('view');
		if (!in_array($this->getRequest()->getActionName(), ['index', 'search', 'view', 'edit', 'state', 'createsalesorder'], true)) throw new Zend_Controller_Action_Exception('Not found', 404);
		parent::init();
		$this->view->toolbar = null;
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
	}

	protected function shouldPrepareAttachmentDirectory(): bool { return false; }
	protected function getDb(): DEEC_Model_DbTable_Entity { return $this->workflow->db(); }
	protected function getCurrentPermissions(): array
	{
		return ['view' => $this->workflow->allowed('view'), 'edit' => $this->workflow->allowed('edit'), 'add' => false, 'delete' => false];
	}

	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'orders', 'list' => 'Shops_Model_List_Orders', 'entity' => Shops_Model_Entity_Order::listConfig(),
			'toolbar' => 'Shops_Form_OrderToolbar', 'toolbarInline' => 'Shops_Form_OrderToolbarInline', 'selectable' => false, 'exportable' => false,
		]);
	}

	public function editAction()
	{
		if (!$this->getRequest()->isGet()) throw new Zend_Controller_Action_Exception('Method not allowed', 405);
		return $this->_helper->redirector->gotoSimple('view', 'order', 'shops', ['id' => $this->orderId()]);
	}

	public function viewAction()
	{
		$this->_helper->viewRenderer->setRender('view');
		$order = $this->workflow->detail($this->orderId());
		$query = $this->getRequest()->getQuery('contactquery', $order['email'] ?? '');
		$query = is_string($query) ? mb_substr($query, 0, 100) : '';
		$session = new Zend_Session_Namespace('ShopOrderWorkflow');
		if (empty($session->csrf)) $session->csrf = bin2hex(random_bytes(32));
		$permission = new DEEC_Permission($this->_user);
		$this->view->assign([
			'order' => $order, 'csrf' => $session->csrf, 'contactquery' => $query,
			'contacts' => $this->workflow->contacts($query), 'salesorder' => $this->workflow->salesorder($order),
			'problems' => Shops_Service_OrderSnapshot::problems($order, $order['positions']),
			'canConvert' => $this->workflow->allowed('edit') && $this->workflow->allowed('add') && $permission->hasPermission('contacts', 'contact', 'view'),
			'canCreateContact' => $permission->hasPermission('contacts', 'contact', 'add') && $permission->hasPermission('contacts', 'contact', 'edit'),
		]);
		$this->assignMessages();
	}

	public function stateAction() { return $this->mutate(false); }
	public function createsalesorderAction() { return $this->mutate(true); }

	private function orderId(): int
	{
		$id = filter_var($this->_getParam('id', 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if (!$id) throw new Zend_Controller_Action_Exception('Not found', 404);
		return $id;
	}

	private function mutate(bool $convert)
	{
		$id = $this->orderId();
		$request = $this->getRequest();
		$post = (array)$request->getPost();
		$session = new Zend_Session_Namespace('ShopOrderWorkflow');
		if (!$request->isPost()) throw new Zend_Controller_Action_Exception('Method not allowed', 405);
		if (!isset($session->csrf) || !is_string($post['csrf'] ?? null) || !hash_equals($session->csrf, $post['csrf'])) throw new Zend_Controller_Action_Exception('Forbidden', 403);
		if (array_diff(array_keys($post), ['csrf', $convert ? 'contactid' : 'status'])) throw new Zend_Controller_Action_Exception('Invalid input', 400);
		try {
			if ($convert) {
				$contactId = filter_var($post['contactid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
				if (!$contactId) throw new DomainException('SHOPS_ORDER_SELECT_CONTACT');
				$result = $this->workflow->createSalesorder($id, (int)$contactId);
				$this->_flashMessenger->addMessage($result['created'] ? 'SHOPS_ORDER_CREATED' : 'SHOPS_ORDER_ALREADY_CREATED');
			} else {
				if (!is_string($post['status'] ?? null)) throw new DomainException('SHOPS_ORDER_STATUS_INVALID');
				$this->workflow->changeStatus($id, $post['status']);
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');
			}
		} catch (DomainException $e) {
			if (in_array($e->getCode(), [403, 404], true)) throw new Zend_Controller_Action_Exception('Not available', $e->getCode());
			$this->_flashMessenger->addMessage($e->getMessage());
		} catch (Throwable $e) {
			error_log('Shop order workflow failed');
			$this->_flashMessenger->addMessage('SHOPS_ORDER_SAVE_FAILED');
		}
		return $this->_helper->redirector->gotoSimple('view', 'order', 'shops', ['id' => $id]);
	}
}
