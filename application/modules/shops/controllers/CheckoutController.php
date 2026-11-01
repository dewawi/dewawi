<?php
class Shops_CheckoutController extends Shops_Controller_Action
{
	protected $checkoutDataSession;

	public function init()
	{
		parent::init();

		$this->initSiteCart();
		$this->checkoutDataSession = new Zend_Session_Namespace('ShopsCheckout');
		$context = $this->_siteContext->getClientId() . ':' . $this->_siteContext->getSiteId();
		if (($this->checkoutDataSession->context ?? '') !== $context) {
			unset(
				$this->checkoutDataSession->formData,
				$this->checkoutDataSession->notificationSent,
				$this->checkoutDataSession->duplicate,
				$this->checkoutDataSession->checkoutToken,
				$this->checkoutDataSession->lastCheckoutToken
			);
			$this->checkoutDataSession->context = $context;
		}
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
		$post = (array)$request->getPost();
		if (!$this->isReasonableSubmission($post, 100, 32768, 2)) {
			$this->getResponse()->setHttpResponseCode(413);
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_SUBMISSION_TOO_LARGE')]);
		}

		$checkout = new Shops_Form_Checkout();

		$countryDb = new Shops_Model_DbTable_Country();
		$countries = $countryDb->getCountries();
		$checkout->billingcountry->addMultiOptions($countries);
		$checkout->shippingcountry->addMultiOptions($countries);

		if(!empty($post['differentshippingaddress'])) {
			foreach(['shippingname', 'shippingstreet', 'shippingpostcode', 'shippingcity', 'shippingcountry'] as $field) {
				$checkout->getElement($field)->setRequired(true)->addValidator('NotEmpty');
			}
		}

		if(!$checkout->isValid($post)) {
			return $this->_helper->json([
				'success' => false,
				'message' => $this->view->translate('SHOPS_FORM_INVALID'),
				'errors' => $checkout->getMessages(),
			]);
		}

		$data = $checkout->getValues();
		$shop = $this->_site;
		$token = strtolower(trim((string)($data['checkout_token'] ?? '')));
		$currentToken = (string)($this->checkoutDataSession->checkoutToken ?? '');
		$lastToken = (string)($this->checkoutDataSession->lastCheckoutToken ?? '');
		if ($token === '' || (!hash_equals($currentToken, $token) && ($lastToken === '' || !hash_equals($lastToken, $token)))) {
			$this->getResponse()->setHttpResponseCode(400);
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_FORM_EXPIRED')]);
		}

		$orderDb = (new Shops_Model_DbTable_Order())->setClientId((int)$shop['clientid']);
		$existing = $orderDb->getByCheckoutToken((int)$shop['id'], $token);
		if ($existing) {
			$this->checkoutDataSession->duplicate = true;
			$this->checkoutDataSession->lastCheckoutToken = $token;
			$this->checkoutDataSession->checkoutToken = bin2hex(random_bytes(16));
			$this->cart->clearCart();
			return $this->_helper->redirector->gotoRoute([], 'checkout_success', true);
		}

		$cart = $this->cart->getItems();
		if(empty($cart)) {
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_CART_EMPTY')]);
		}

		$itemDb = new Shops_Model_DbTable_Item();
		$positions = [];
		$total = 0.0;
		foreach($cart as $cartId => $row) {
			if (!is_array($row) || !isset($row['sku'], $row['quantity'], $row['price'])
				|| !is_numeric($row['quantity']) || !is_numeric($row['price'])) {
				return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_CART_CHANGED')]);
			}

			$quantity = (float)$row['quantity'];
			$price = (float)$row['price'];
			$item = $itemDb->getItemBySku((string)$row['sku']);
			$currentPrice = $item && !empty($item['specialprice']) ? (float)$item['specialprice'] : (float)($item['price'] ?? 0);
			if (!$item || (int)$item['id'] !== (int)$cartId || !is_finite($quantity) || !is_finite($price)
				|| $quantity <= 0 || $quantity > 100000 || $price < 0 || abs($currentPrice - $price) > 0.0001) {
				return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_CART_CHANGED')]);
			}

			$lineTotal = $quantity * $price;
			$total += $lineTotal;
			$positions[] = [
				'itemid' => (int)$item['id'],
				'total' => $lineTotal,
				'quantity' => $quantity,
				'price' => $price,
			];
		}

		try {
			$rate = $this->consumeSubmissionLimit('checkout', 10, 3600);
		} catch (Throwable $e) {
			error_log('Shop checkout rate limiter unavailable: ' . $e->getMessage());
			$this->getResponse()->setHttpResponseCode(503);
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_SUBMISSION_UNAVAILABLE')]);
		}
		if (!$rate['allowed']) {
			$this->setRateLimitResponse((int)$rate['retryAfter']);
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_RATE_LIMIT')]);
		}

		if(!$this->processPayment($total)) {
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_PAYMENT_FAILED')]);
		}

		$contactId = 0;
		if(!empty($data['email'])) {
			$emailDb = new Shops_Model_DbTable_Email();
			$contactId = (int)$emailDb->findContactIdByEmail($data['email']);
		}

		$orderDate = date('Y-m-d');

		try {
			$result = $orderDb->saveCheckoutOnce((int)$shop['id'], $token, [
				'contactid' => $contactId,
				'invoiceid' => 0,
				'orderdate' => $orderDate,
				'total' => $total,
			], $positions);
		} catch (Throwable $e) {
			error_log('Shop checkout could not be stored: ' . $e->getMessage());
			$this->getResponse()->setHttpResponseCode(500);
			return $this->_helper->json(['success' => false, 'message' => $this->view->translate('SHOPS_CHECKOUT_FAILED')]);
		}

		$orderNumber = (string)$result['order']['orderid'];
		$this->checkoutDataSession->duplicate = !$result['inserted'];
		$this->checkoutDataSession->lastCheckoutToken = $token;
		$this->checkoutDataSession->checkoutToken = bin2hex(random_bytes(16));

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

		if ($result['inserted']) {
			try {
				$emailResult = $this->_helper->Email->sendEmail('shops', 'checkout', 'checkout', $cart);
				$this->checkoutDataSession->notificationSent = !empty($emailResult['customerAccepted']);
				if(($emailResult['internalAccepted'] ?? null) === false) error_log('Stored shop order internal notification was not accepted by SMTP');
			} catch (Throwable $e) {
				error_log('Stored shop order could not be emailed: ' . $e->getMessage());
				$this->checkoutDataSession->notificationSent = false;
			}
		}

		$this->cart->clearCart();

		return $this->_helper->redirector->gotoRoute([], 'checkout_success', true);
	}

	public function successAction()
	{
		$this->initSiteLayout();

		$this->view->formData = $this->checkoutDataSession->formData ?? [];
		$this->view->notificationSent = $this->checkoutDataSession->notificationSent ?? null;

		$this->assignMessages();
	}

	private function processPayment($amount)
	{
		// Dummy payment logic
		return true;
	}
}
