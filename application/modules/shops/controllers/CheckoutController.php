<?php
class Shops_CheckoutController extends Zend_Controller_Action
{
	private $cart;

	protected $checkoutDataSession;

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

		$this->checkoutDataSession = new Zend_Session_Namespace('ShopsCheckout');
	}

	public function indexAction()
	{
		$shop = Zend_Registry::get('Shop');

		$this->_helper->getHelper('layout')->setLayout('site');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);
		//print_r($params);
		//print_r($this->getRequest()->getParams());
		$checkout = new Shops_Form_Checkout();
		$this->view->checkout = $checkout;

		// Falls Werte vorhanden sind, ins Formular laden
		if (!empty($this->checkoutDataSession->formData)) {
			$checkout->populate($this->checkoutDataSession->formData);
		}

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		//Get countries
		$countryDb = new Shops_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$checkout->billingcountry->addMultiOptions($countries);
		$checkout->billingcountry->setValue('DE');
		$checkout->shippingcountry->addMultiOptions($countries);
		$checkout->shippingcountry->setValue('DE');

		$menuDb = new Shops_Model_DbTable_Menu();
		$menus = $menuDb->getMenus($shop['id']);

		$menuitems = array();
		$menuitemDb = new Shops_Model_DbTable_Menuitem();
		foreach($menus as $menu) {
			$menuitems[$menu->id] = $menuitemDb->getMenuitems($menu->id);
		}

		$images = array();
		$imageDb = new Shops_Model_DbTable_Media();
		$images['categories'] = $imageDb->getCategoryMedia($categories);

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->shop = $shop;
		$this->view->images = $images;
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		$this->view->categories = $categories;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();

		//Get currency
		$currency = $this->_helper->Currency->getCurrency('EUR', 'USE_SYMBOL');

		// Retrieve cart items for display
		$items = array();
		foreach($this->cart->getItems() as $id => $item) {
			$items[$id]['title'] = $item['title'];
			$items[$id]['sku'] = $item['sku'];
			$items[$id]['quantity'] = $item['quantity'];
			$items[$id]['total'] = $currency->toCurrency($item['price']*$item['quantity']);
			$items[$id]['price'] = $currency->toCurrency($item['price']);
		}

		$this->view->items = $items;
		$this->view->total = $currency->toCurrency($this->cart->getTotalPrice());
	}

	public function sendAction()
	{
		$request = $this->getRequest();

		if(!$request->isPost()) {
			return $this->_helper->redirector->gotoRoute([], 'checkout', true);
		}

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		$cart = $this->cart->getItems();
		if(empty($cart)) {
			return $this->_helper->json(['success' => false, 'message' => 'Cart is empty']);
		}

		$checkout = new Shops_Form_Checkout();

		$countryDb = new Shops_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$checkout->billingcountry->addMultiOptions($countries);
		$checkout->shippingcountry->addMultiOptions($countries);

		$post = $request->getPost();

		if(!empty($post['differentshippingaddress'])) {
			foreach(['shippingname', 'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry'] as $field) {
				$checkout->getElement($field)->setRequired(true)->addValidator('NotEmpty');
			}
		}

		if(!$checkout->isValid($post)) {
			return $this->_helper->json([
				'success' => false,
				'message' => 'Please check your input',
				'errors' => $checkout->getMessages(),
			]);
		}

		$data = $checkout->getValues();
		$shop = Zend_Registry::get('Shop');
		$total = $this->cart->getTotalPrice();

		if(!$this->processPayment($total)) {
			return $this->_helper->json(['success' => false, 'message' => 'Payment failed']);
		}

		$contactId = 0;
		if(!empty($data['email'])) {
			$emailDb = new Shops_Model_DbTable_Email();
			$contactId = (int)$emailDb->findContactIdByEmail($data['email']);
		}

		$incrementDb = new Shops_Model_DbTable_Increment();
		$orderNumber = $incrementDb->getIncrement('shoporderid');
		$orderDate = date('Y-m-d');

		$orderDb = new Shops_Model_DbTable_Order();
		$orderDb->addOrder([
			'shopid' => $shop['id'],
			'orderid' => $orderNumber,
			'contactid' => $contactId,
			'invoiceid' => 0,
			'orderdate' => $orderDate,
			'total' => $total,
			'clientid' => $shop['clientid'],
		]);

		$orderposDb = new Shops_Model_DbTable_Orderpos();
		$itemDb = new Shops_Model_DbTable_Item();

		foreach($cart as $row) {
			$sku = $row['sku'];
			$quantity = (float)$row['quantity'];
			$price = (float)$row['price'];
			$lineTotal = $quantity * $price;

			$item = $itemDb->getItemBySku($sku, $shop['id']);
			if(!$item) {
				return $this->_helper->json(['success' => false, 'message' => 'Cart item not found']);
			}

			$orderposDb->addOrderpos([
				'shopid' => $shop['id'],
				'orderid' => $orderNumber,
				'itemid' => (int)$item['id'],
				'total' => $lineTotal,
				'quantity' => $quantity,
				'price' => $price,
				'clientid' => $shop['clientid'],
			]);
		}

		$incrementDb->setIncrement($orderNumber, 'shoporderid');

		$this->checkoutDataSession->formData = [
			'billingname' => $data['billingname'],
			'billingcompany' => $data['billingcompany'],
			'billingdepartment' => $data['billingdepartment'],
			'billingstreet' => $data['billingstreet'],
			'billingpostcode' => $data['billingpostcode'],
			'billingcity' => $data['billingcity'],
			'billingcountry' => $data['billingcountry'],
			'billingphone' => $data['billingphone'],
			'differentshippingaddress' => $data['differentshippingaddress'],
			'shippingname' => $data['shippingname'],
			'shippingcompany' => $data['shippingcompany'],
			'shippingdepartment' => $data['shippingdepartment'],
			'shippingstreet' => $data['shippingstreet'],
			'shippingpostcode' => $data['shippingpostcode'],
			'shippingcity' => $data['shippingcity'],
			'shippingcountry' => $data['shippingcountry'],
			'shippingphone' => $data['shippingphone'],
			'email' => $data['email'],
			'subject' => 'Bestellbestätigung',
			'message' => $data['message'],
			'total' => $total,
		];

		$this->getRequest()->setPost(array_merge(
			$data,
			[
				'subject' => 'Bestellbestätigung',
				'orderid' => $orderNumber,
				'orderdate' => $orderDate,
			]
		));

		$this->_helper->Email->sendEmail('shops', 'checkout', 'checkout', $cart);

		$this->cart->clearCart();

		return $this->_helper->redirector->gotoRoute([], 'successcheckout', true);
	}

	public function successAction()
	{
		$shop = Zend_Registry::get('Shop');

		// Holt die Formulardaten aus der Session
		$this->view->formData = $this->checkoutDataSession->formData;

		$this->_helper->getHelper('layout')->setLayout('site');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

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
		$this->view->menus = $menus;
		$this->view->menuitems = $menuitems;
		$this->view->categories = $categories;
		//$this->view->pagination = $this->_helper->Pagination->getPagination($toolbar, $params, $records, count($items));
		$this->view->messages = $this->_flashMessenger->getMessages();
	}

	private function processPayment($amount)
	{
		// Dummy payment logic
		return true;
	}
}
