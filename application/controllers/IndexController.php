<?php

class IndexController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		if(Zend_Registry::isRegistered('User')) {
			$this->view->user = $this->_user = Zend_Registry::get('User');
			$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();
		}
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
	}

	public function indexAction()
	{
		$toolbar = new Statistics_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		$charts = new Statistics_Model_Turnover();
		$charts->createCharts(12, 750, 400, $this->view->translate('STATISTICS_UNCATEGORIZED'), $params, $options);

		$quotesDb = new Sales_Model_DbTable_Quote();
		$quotes = $quotesDb->getLatestQuotes();
		$this->view->quotes = $quotes;

		$salesordersDb = new Sales_Model_DbTable_Salesorder();
		$salesorders = $salesordersDb->getLatestSalesorders();
		$this->view->salesorders = $salesorders;

		$invoicesDb = new Sales_Model_DbTable_Invoice();
		$invoices = $invoicesDb->getLatestInvoices();
		$this->view->invoices = $invoices;

		$quoterequestsDb = new Purchases_Model_DbTable_Quoterequest();
		$quoterequests = $quoterequestsDb->getLatestQuoterequests();
		$this->view->quoterequests = $quoterequests;

		$purchaseordersDb = new Purchases_Model_DbTable_Purchaseorder();
		$purchaseorders = $purchaseordersDb->getLatestPurchaseorders();
		$this->view->purchaseorders = $purchaseorders;

		$contactsDb = new Contacts_Model_DbTable_Contact();
		$contacts = $contactsDb->getLatestContacts();
		$this->view->contacts = $contacts;

		$itemsDb = new Items_Model_DbTable_Item();
		$items = $itemsDb->getLatestItems();
		$this->view->items = $items;

		$inventoryDb = new Items_Model_DbTable_Inventory();
		$inventory = $inventoryDb->getLatestInventorys();
		$this->view->inventories = $inventory;

		$tasksDb = new Tasks_Model_DbTable_Task();
		$tasks = $tasksDb->getLatestTasks();
		foreach($tasks as $task) {
			if($task->startdate) {
				$startdate = new Zend_Date($task->startdate, Zend_Date::DATES, 'de');
				$task->startdate = $startdate->get('dd.MM.yyyy');
			}
			if($task->duedate) {
				$duedate = new Zend_Date($task->duedate, Zend_Date::DATES, 'de');
				$task->duedate = $duedate->get('dd.MM.yyyy');
			}
		}
		$this->view->tasks = $tasks;

		$this->view->options = $options;
		$this->view->toolbar = new Application_Form_Toolbar();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}







