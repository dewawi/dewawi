<?php
class Shops_CheckoutController extends Shops_Controller_Action
{
	protected $checkoutDataSession;

	public function init()
	{
		parent::init();

		$this->initSiteCart();
		$this->checkoutDataSession = new Zend_Session_Namespace('ShopsCheckout');
	}

	public function indexAction()
	{
		$this->initSiteLayout();

		$checkout = new Shops_Form_Checkout();
		$this->view->checkout = $checkout;

		if (!empty($this->checkoutDataSession->formData)) {
			$checkout->populate($this->checkoutDataSession->formData);
		}

		$countryDb = new Shops_Model_DbTable_Country();
		$countries = $countryDb->getCountries();

		$checkout->billingcountry->addMultiOptions($countries);
		$checkout->shippingcountry->addMultiOptions($countries);

		if (empty($this->checkoutDataSession->formData)) {
			$checkout->billingcountry->setValue('DE');
			$checkout->shippingcountry->setValue('DE');
		}

		$currency = $this->_helper->Currency->getCurrency('EUR', 'USE_SYMBOL');

		$items = [];
		foreach ($this->cart->getItems() as $id => $item) {
			$items[$id] = [
				'title' => $item['title'],
				'sku' => $item['sku'],
				'quantity' => $item['quantity'],
				'total' => $currency->toCurrency($item['price'] * $item['quantity']),
				'price' => $currency->toCurrency($item['price']),
			];
		}

		$this->view->items = $items;
		$this->view->total = $currency->toCurrency($this->cart->getTotalPrice());

		$this->assignMessages();
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
		$shop = $this->_site;
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

		return $this->_helper->redirector->gotoRoute([], 'checkout_success', true);
	}

	public function successAction()
	{
		$this->initSiteLayout();

		$this->view->formData = $this->checkoutDataSession->formData ?? [];

		$this->assignMessages();
	}

	private function processPayment($amount)
	{
		// Dummy payment logic
		return true;
	}
}
