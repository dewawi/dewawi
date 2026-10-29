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
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);

		$toolbar = new Application_Form_Toolbar();
		$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar, $options);

		//Get categories
		$categoriesDb = new Application_Model_DbTable_Category();
		$categories = $categoriesDb->getCategories('contact');
		$options['categories'] = $categories;

		$permission = new DEEC_Permission((array)$this->_user);
		$this->view->canViewTurnover = $permission->hasPermission('statistics', 'turnover', 'view');

		if($this->view->canViewTurnover) {
			$params['daterange'] = 'custom';
			$params['from'] = date('Y-m-01', strtotime('-11 months'));
			$params['to'] = date('Y-m-d');

			$charts = new Statistics_Model_Turnover();
			$charts->createCharts(750, 400, $this->view->translate('STATISTICS_UNCATEGORIZED'), $this->view->translate('STATISTICS_NO_DATA'), $params, $options);
			$this->view->chartImages = $charts->getChartImages();
		}

		foreach (['sales' => ['quote', 'salesorder', 'invoice'], 'purchases' => ['quoterequest', 'purchaseorder']] as $module => $controllers) {
			foreach ($controllers as $controller) {
				$class = ucfirst($module) . '_Model_DbTable_' . ucfirst($controller);
				$key = $controller . 's';
				$this->view->$key = $permission->hasActionPermission($module, $controller, 'view') ? (new $class())->getLatestDrafts() : [];
			}
		}

		$contactsDb = new Contacts_Model_DbTable_Contact();
		$client = Zend_Registry::get('Client');
		$contactsDb->setClientId((int)($client['modules']['contacts'] ?? $client['id']));
		$contacts = $permission->hasActionPermission('contacts', 'contact', 'view') ? $contactsDb->getLatestContacts() : [];
		$this->view->contacts = $contacts;

		$itemsDb = new Items_Model_DbTable_Item();
		$itemsDb->setClientId((int)($client['modules']['items'] ?? $client['id']));
		$items = $permission->hasActionPermission('items', 'item', 'view') ? $itemsDb->getLatestItems() : [];
		$this->view->items = $items;

		$ledgerDb = new Items_Model_DbTable_Ledger();
		$itemDb = new Items_Model_DbTable_Item();

		$ledgerDb->setClientId((int)($client['modules']['items'] ?? $client['id']));
		$itemDb->setClientId($ledgerDb->getClientId());
		$ledgers = $permission->hasActionPermission('items', 'item', 'view') ? $ledgerDb->getLatest(5, 'ledgerdate') : [];

		foreach($ledgers as &$ledger) {
			$item = $itemDb->getById((int)$ledger['itemid']);

			$ledger['sku'] = $item['sku'] ?? '';
		}

		unset($ledger);

		$this->view->ledgers = $ledgers;

		$tasksDb = new Tasks_Model_DbTable_Task();
		$tasks = !$permission->hasModule('tasks') || $permission->hasActionPermission('tasks', 'task', 'view') ? $tasksDb->getLatestTasks() : [];
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

		//Get states
		$stateDb = new Application_Model_DbTable_State();
		$states = $stateDb->getStates();
		$this->view->states = $states;

		//Get users
		$userDb = new Users_Model_DbTable_User();
		$users = $userDb->getUsers();
		$this->view->users = $users;

		$this->view->options = $options;
		$this->view->toolbar = new Application_Form_Toolbar();
		$this->view->messages = $this->_flashMessenger->getMessages();
	}
}







