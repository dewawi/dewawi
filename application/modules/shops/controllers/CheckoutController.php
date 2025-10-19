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

		$this->_helper->getHelper('layout')->setLayout('shop');

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

		$slideDb = new Shops_Model_DbTable_Slide();
		$slides = $slideDb->getSlides($shop['id']);

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

		$pageDb = new Shops_Model_DbTable_Page();
		$page = $pageDb->getPageByTitle('Home');

		//$this->view->tags = $tags;
		//$this->view->tagEntites = $tagEntites;
		$this->view->page = $page;
		$this->view->shop = $shop;
		$this->view->images = $images;
		$this->view->slides = $slides;
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

		if($request->isPost()) {
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();

 	 	 	$shop = Zend_Registry::get('Shop');

			// Fetch cart items
			$cart = $this->cart->getItems();
			if (empty($cart)) {
				return $this->_helper->json(['success' => false, 'message' => 'Cart is empty']);
			}

			// Dummy payment logic
			$total = $this->cart->getTotalPrice();
			$paymentSuccess = $this->processPayment($total);

			if ($paymentSuccess) {
				$data = $request->getPost();

 	 	 	 	// Resolve contact by email (adjust to your fields)
 	 	 	 	$contactId = 0;
 	 	 	 	if (!empty($data['email'])) {
 	 	 	 	 	$emailDb = new Shops_Model_DbTable_Email();
 	 	 	 	 	$contactId = (int)$emailDb->findContactIdByEmail($data['email']);
 	 	 	 	}

 	 	 	 	// Generate next order number from increment table
 	 	 	 	$incrementDb = new Shops_Model_DbTable_Increment();
 	 	 	 	$orderNumber = $incrementDb->getIncrement('shoporderid');

 	 	 	 	// Generate order date
 	 	 	 	$orderDate = date('Y-m-d');

 	 	 	 	// Create order (shoporder)
 	 	 	 	$orderDb = new Shops_Model_DbTable_Order();
 	 	 	 	$orderRowId = $orderDb->addOrder([
 	 	 	 	 	'shopid' => $shop['id'],
 	 	 	 	 	'orderid' => $orderNumber,
 	 	 	 	 	'contactid' => $contactId,
 	 	 	 	 	'invoiceid' => 0,
 	 	 	 	 	'orderdate' => $orderDate,
 	 	 	 	 	'total' => $total,
 	 	 	 	 	'clientid' => $shop['clientid'],
 	 	 	 	 	// created/modified are set in the model
 	 	 	 	]);

 	 	 	 	// Create positions
 	 	 	 	$orderposDb = new Shops_Model_DbTable_Orderpos();
 	 	 	 	$itemDb 	 = new Shops_Model_DbTable_Item();

 	 	 	 	foreach ($cart as $row) {
 	 	 	 	 	// Your cart rows: ['title','sku','price','quantity', ...]
 	 	 	 	 	$sku = $row['sku'];
 	 	 	 	 	$qty = (float)$row['quantity'];
 	 	 	 	 	$price = (float)$row['price'];
 	 	 	 	 	$lineTotal = $qty * $price;

 	 	 	 	 	$item = $itemDb->getItemBySku($sku, $shop['id']);
 	 	 	 	 	if (!$item) { continue; } // or log/throw

 	 	 	 	 	$orderposDb->addOrderpos([
 	 	 	 	 	 	'shopid' => $shop['id'],
 	 	 	 	 	 	'orderid' => $orderNumber, 	 	 // IMPORTANT: same string as shoporder.orderid
 	 	 	 	 	 	'itemid' => (int)$item['id'],
 	 	 	 	 	 	'total' => $lineTotal,
 	 	 	 	 	 	'quantity' => $qty,
 	 	 	 	 	 	'price' => $price,
 	 	 	 	 	 	'clientid' => $shop['clientid'],
 	 	 	 	 	]);
 	 	 	 	}

 	 	 	 	// Mark increment used
 	 	 	 	$incrementDb->setIncrement($orderNumber, 'shoporderid');

				// Clear cart
				$this->cart->clearCart();

				// Save to session
				$this->checkoutDataSession->formData = [
					'billingname' => $data['billingname'],
					'billingcompany' => $data['billingcompany'],
					'billingstreet' => $data['billingstreet'],
					'billingpostcode' => $data['billingpostcode'],
					'billingcity' => $data['billingcity'],
					'billingcountry' => $data['billingcountry'],
					'billingphone' => $data['billingphone'],
					'email' => $data['email'],
					'subject' => 'Bestellbestätigung',
					'message' => $data['message'],
					'total' => $total
				];

 	 	 	 	// make them available to the Email helper (it reads $request->getPost())
 	 	 	 	$this->getRequest()->setPost(array_merge(
 	 	 	 	 	$this->getRequest()->getPost(),
 	 	 	 	 	[
 	 	 	 	 	 	'orderid' => $orderNumber,
 	 	 	 	 	 	'orderdate' => $orderDate,
 	 	 	 	 	]
 	 	 	 	));

				// Send order confirmation
				$this->_helper->Email->sendEmail('shops', 'checkout', 'checkout', $cart);

 	 	 	 	// Respond JSON for AJAX; client will redirect
 	 	 	 	$successUrl = $this->view->url(['module'=>'shops','controller'=>'checkout','action'=>'success'], null, true);
 	 	 	 	//return $this->_helper->json(['success' => true, 'redirectUrl' => $successUrl, 'orderid' => $orderNumber]);
 	 	 	 	$this->_helper->redirector->gotoRoute([], 'successcheckout', true);
			}

			return $this->_helper->json(['success' => false, 'message' => 'Payment failed']);
		} else {
			$this->_helper->redirector->gotoSimple('index', 'index', 'default');
		}
	}

	public function successAction()
	{
		$shop = Zend_Registry::get('Shop');

		// Holt die Formulardaten aus der Session
		$this->view->formData = $this->checkoutDataSession->formData;

		$this->_helper->getHelper('layout')->setLayout('shop');

		$toolbar = new Items_Form_Toolbar();
		//$options = $this->_helper->Options->getOptions($toolbar);
		$params = $this->_helper->Params->getParams($toolbar);

		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		$slideDb = new Shops_Model_DbTable_Slide();
		$slides = $slideDb->getSlides($shop['id']);

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
		$this->view->slides = $slides;
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
