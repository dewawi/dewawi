<?php

class Admin_ShippingController extends DEEC_Controller_AdminAction
{
	private Shops_Service_ShippingConfiguration $shipping;
	public function init()
	{
		$this->shipping = new Shops_Service_ShippingConfiguration();
		$this->shipping->requireAdmin();
		if (!in_array($this->getRequest()->getActionName(), ['index', 'edit'], true)) throw new Zend_Controller_Action_Exception('Not found', 404);
		parent::init();
		$this->view->toolbar = null;
		$this->view->headScript()->appendFile($this->view->baseUrl() . '/js/shipping.js');
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
	}
	protected function shouldPrepareAttachmentDirectory(): bool { return false; }
	private function kind(): string
	{
		$kind = $this->_getParam('kind', 'classes');
		if (!is_string($kind) || (!isset(Shops_Service_ShippingConfiguration::TABLES[$kind]) && $kind !== 'settings')) throw new Zend_Controller_Action_Exception('Not found', 404);
		return $kind;
	}
	private function positive(string $field, bool $optional = false): int
	{
		$id = filter_var($this->_getParam($field, 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => $optional ? 0 : 1]]);
		if ($id === false) throw new Zend_Controller_Action_Exception('Not found', 404);
		return $id;
	}
	public function indexAction()
	{
		if (!$this->getRequest()->isGet()) throw new Zend_Controller_Action_Exception('Method not allowed', 405);
		$kind = $this->kind(); $shopId = $this->positive('shopid', true);
		if ($kind === 'settings' && $shopId) return $this->_helper->redirector->gotoSimple('edit', 'shipping', 'admin', ['shopid' => $shopId, 'kind' => $kind]);
		$rows = $shopId ? $this->shipping->rows($shopId, $kind) : [];
		$query = $this->getRequest()->getQuery('q', ''); $state = $this->getRequest()->getQuery('status', 'all');
		$query = is_string($query) ? mb_substr($query, 0, 100) : '';
		$rows = array_values(array_filter($rows, static fn(array $row): bool => ($query === '' || mb_stripos((string)($row['title'] ?? $row['itemid'] ?? $row['id']), $query) !== false)
			&& ($state === 'all' || (in_array($state, ['0', '1'], true) && (string)$row['active'] === $state))));
		$list = new Admin_Model_List_Shipping(['view' => $this->view, 'module' => 'admin', 'controller' => 'shipping', 'items' => $rows, 'selectable' => false]);
		$list->setContext(['shopid' => $shopId, 'kind' => $kind, 'choices' => $shopId ? $this->shipping->choices($shopId) : []]);
		$this->view->assign(['kind' => $kind, 'shopid' => $shopId, 'shops' => $this->shipping->shops(), 'shippingList' => $list, 'query' => $query, 'filterStatus' => $state]);
		$this->assignMessages();
	}
	public function editAction()
	{
		$kind = $this->kind(); $shopId = $this->positive('shopid'); $id = $this->positive('id', true);
		$values = $this->shipping->edit($shopId, $kind, $id); $errors = [];
		$request = $this->getRequest();
		if ($request->isPost()) {
			$post = (array)$request->getPost();
			if (!Shops_Service_ShippingConfiguration::validCsrf($post['csrf'] ?? null)) throw new Zend_Controller_Action_Exception('Forbidden', 403);
			unset($post['csrf']);
			$result = $this->shipping->save($shopId, $kind, $id, $post);
			$errors = $result['errors'];
			if (!$errors) {
				$this->_flashMessenger->addMessage('MESSAGES_SAVED');
				return $this->_helper->redirector->gotoSimple('edit', 'shipping', 'admin', ['kind' => $kind, 'shopid' => $shopId, 'id' => $result['id']]);
			}
			$values = $post;
		} elseif (!$request->isGet()) throw new Zend_Controller_Action_Exception('Method not allowed', 405);
		$choices = $this->shipping->choices($shopId, $values);
		$choices['countries'] = $this->shipping->countries();
		if (is_array($values['countries'] ?? null)) $values['countries'] = array_values(array_filter($values['countries'], 'is_string'));
		$form = new Admin_Form_Shipping($kind, $choices);
		$form->setValues(array_filter($values, static fn($value, $key): bool => is_scalar($value) || ($key === 'countries' && is_array($value)), ARRAY_FILTER_USE_BOTH));
		$this->view->assign(['kind' => $kind, 'shopid' => $shopId, 'form' => $form, 'fieldErrors' => $errors, 'tiers' => is_array($values['tiers'] ?? null) ? $values['tiers'] : [], 'csrf' => Shops_Service_ShippingConfiguration::csrfToken()]);
		$this->assignMessages();
	}
}
