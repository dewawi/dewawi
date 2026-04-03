<?php

class Application_Plugin_State extends Zend_Controller_Plugin_Abstract
{
	protected $_guarded = [
		'sales' => [
			'quote' => [
				'dbClass' => 'Sales_Model_DbTable_Quote',
				'getMethod' => 'getQuote',
				'blockedStates' => ['105', '106'],
				'redirectAction' => 'view',
			],
			'invoice' => [
				'dbClass' => 'Sales_Model_DbTable_Invoice',
				'getMethod' => 'getInvoice',
				'blockedStates' => ['105', '106'],
				'redirectAction' => 'view',
			],
		],
		'purchases' => [
			'quoterequest' => [
				'dbClass' => 'Purchases_Model_DbTable_Quoterequest',
				'getMethod' => 'getQuoterequest',
				'blockedStates' => ['105', '106'],
				'redirectAction' => 'view',
			],
			'purchaseorder' => [
				'dbClass' => 'Purchases_Model_DbTable_Purchaseorder',
				'getMethod' => 'getPurchaseorder',
				'blockedStates' => ['105', '106'],
				'redirectAction' => 'view',
			],
		],
	];

	public function preDispatch(Zend_Controller_Request_Abstract $request)
	{
		$module = $request->getModuleName();
		$controller = $request->getControllerName();
		$action = $request->getActionName();
		$id = (int) $request->getParam('id', 0);

		if ($action !== 'edit' || !$id) {
			return;
		}

		if (!isset($this->_guarded[$module][$controller])) {
			return;
		}

		$config = $this->_guarded[$module][$controller];

		$dbClass = $config['dbClass'];
		$getMethod = $config['getMethod'];

		if (!class_exists($dbClass)) {
			return;
		}

		$db = new $dbClass();

		if (!method_exists($db, $getMethod)) {
			return;
		}

		$row = $db->$getMethod($id);

		if (!$row || !isset($row['state'])) {
			return;
		}

		if (in_array((string)$row['state'], $config['blockedStates'], true)) {
			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

			$flashMessenger->addMessage('MESSAGES_DOCUMENT_READONLY');

			$redirector->gotoSimple(
				$config['redirectAction'],
				$controller,
				$module,
				['id' => $id]
			);
		}
	}
}
