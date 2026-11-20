<?php
class Shops_CheckoutController extends Shops_Controller_Action
{
	protected $checkoutDataSession;

	public function init()
	{
		parent::init();
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		if (!$this->_siteContext->hasFeature('checkout') || !$this->_siteContext->hasFeature('cart')) throw new Zend_Controller_Action_Exception('Not found', 404);

		$this->initSiteCart();
		$this->checkoutDataSession = new Zend_Session_Namespace('ShopsCheckout');
		$context = $this->_siteContext->getClientId() . ':' . $this->_siteContext->getSiteId();
		if (($this->checkoutDataSession->context ?? '') !== $context) {
			unset(
				$this->checkoutDataSession->formData,
				$this->checkoutDataSession->notificationSent,
				$this->checkoutDataSession->duplicate,
				$this->checkoutDataSession->checkoutToken,
				$this->checkoutDataSession->review,
				$this->checkoutDataSession->receipt,
				$this->checkoutDataSession->lastCheckoutToken
			);
			$this->checkoutDataSession->context = $context;
		}
	}

	public function indexAction()
	{
		$this->showCheckout($this->checkoutForm(), $this->checkoutDataSession->formData ?? []);
	}

	private function checkoutForm(): Shops_Form_Checkout
	{
		$checkout = new Shops_Form_Checkout();
		$checkout->setAction($this->view->url([], 'checkout_send'));
		$checkout->addElement('hidden', 'cart_revision', ['value' => $this->cart->getRevision(), 'decorators' => ['ViewHelper']]);
		$countries = (new Shops_Model_DbTable_Country())->getCountries();
		$checkout->billingcountry->addMultiOptions($countries)->setValue('DE');
		$checkout->shippingcountry->addMultiOptions($countries)->setValue('DE');
		foreach (['billingcountry', 'shippingcountry'] as $field) $checkout->getElement($field)->setRegisterInArrayValidator(false)
			->addValidator('InArray', true, ['haystack' => array_keys($countries), 'messages' => $this->view->translate('SHOPS_CHECKOUT_COUNTRY_INVALID')]);
		return $checkout;
	}

	private function showCheckout(Shops_Form_Checkout $checkout, array $data = [], ?array $review = null): void
	{
		$this->initSiteLayout();
		if ($data) $checkout->populate(array_intersect_key(array_filter($data, 'is_scalar'), $checkout->getElements()));
		$checkout->checkout_token->setValue($this->checkoutDataSession->checkoutToken);
		$checkout->cart_revision->setValue($this->cart->getRevision());
		$checkout->setShippingRequired((bool)$checkout->differentshippingaddress->getValue());
		$this->view->checkout = $checkout;
		$this->view->checkoutReview = $review;
		$current = $this->cartPositions($this->cart->getItems());
		$this->view->positions = $current['positions'] ?? [];
		$this->view->orderTotals = $current && $current['positions'] ? Shops_Service_OrderSnapshot::build($this->_site, [], $current['positions']) : null;
		$this->assignMessages();
	}

	public function sendAction()
	{
		$request = $this->getRequest();
		if (!$request->isPost()) return $this->_helper->redirector->gotoRoute([], 'checkout', true);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		$post = (array)$request->getPost();
		if (!$this->isReasonableSubmission($post, 100, 32768, 2)) return $this->checkoutFailure('SHOPS_SUBMISSION_TOO_LARGE', 413);

		$token = $post['checkout_token'] ?? '';
		$currentToken = (string)($this->checkoutDataSession->checkoutToken ?? '');
		$lastToken = (string)($this->checkoutDataSession->lastCheckoutToken ?? '');
		if (!is_string($token) || !preg_match('/^[a-f0-9]{32,64}$/D', $token)
			|| (!hash_equals($currentToken, $token) && ($lastToken === '' || !hash_equals($lastToken, $token)))) {
			return $this->checkoutFailure('SHOPS_FORM_EXPIRED', 403);
		}
		$orderDb = (new Shops_Model_DbTable_Order())->setClientId((int)$this->_site['clientid']);
		// Recovery only reads the order bound to this session; it never submits an order or sends mail.
		if (($post['status_only'] ?? '') === '1') {
			try {
				$existing = $orderDb->getByCheckoutToken((int)$this->_site['id'], $token);
			} catch (Throwable $e) {
				return $this->checkoutFailure('SHOPS_CHECKOUT_SAVE_UNCERTAIN', 503);
			}
			if ($existing) $this->rememberReceipt($token, $token === $currentToken && (string)($post['cart_revision'] ?? '') === (string)$this->cart->getRevision());
			return $this->_helper->json(['success' => true, 'stored' => (bool)$existing,
				'redirect' => $existing ? $this->view->url([], 'checkout_success') : null,
				'csrfToken' => $existing ? null : (new Shops_Form_Checkout())->renewCsrfToken()]);
		}

		$existing = $orderDb->getByCheckoutToken((int)$this->_site['id'], $token);
		if ($existing) {
			$this->checkoutDataSession->duplicate = true;
			$this->rememberReceipt($token, $token === $currentToken && (string)($post['cart_revision'] ?? '') === (string)$this->cart->getRevision());
			return $this->checkoutSuccess();
		}

		$checkout = $this->checkoutForm();
		$checkout->setShippingRequired((string)($post['differentshippingaddress'] ?? '') === '1');
		if ((string)($post['differentshippingaddress'] ?? '') !== '1') {
			foreach (Shops_Form_Checkout::SHIPPING_FIELDS as $field) unset($post[$field]);
		}
		if (!$checkout->isValid($post)) return $this->checkoutFailure($checkout->csrf_token->hasErrors() ? 'SHOPS_FORM_EXPIRED' : 'SHOPS_FORM_INVALID', $checkout->csrf_token->hasErrors() ? 403 : 422, $checkout);
		$data = array_map(static fn($value) => $value ?? '', $checkout->getValues());
		if (!empty($post['checkout_edit'])) {
			unset($this->checkoutDataSession->review);
			return $this->renderCheckout($checkout, $data);
		}
		$cartRevision = filter_var($post['cart_revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
		if ($cartRevision === false || $cartRevision !== $this->cart->getRevision()) return $this->checkoutFailure('SHOPS_CART_CHANGED', 409, $checkout);
		$cart = $this->cart->getItems();
		if (!$cart) return $this->checkoutFailure('SHOPS_CART_EMPTY', 409, $checkout);
		$current = $this->cartPositions($cart);
		if (!$current) return $this->checkoutFailure('SHOPS_CART_CHANGED', 409, $checkout);
		$positions = $current['positions'];
		$cart = $current['cart'];
		$shop = $this->_site;
		try {
			$orderSnapshot = Shops_Service_OrderSnapshot::build($shop, $data, $positions);
		} catch (Throwable $e) {
			error_log('Shop checkout summary unavailable: ' . $e->getMessage());
			return $this->checkoutFailure('SHOPS_SUBMISSION_UNAVAILABLE', 503, $checkout);
		}
		$fingerprint = hash('sha256', json_encode([$token, $cartRevision, $orderSnapshot, $positions], JSON_THROW_ON_ERROR));
		if (!in_array($post['checkout_stage'] ?? 'preview', ['preview', 'confirm'], true)) return $this->checkoutFailure('SHOPS_FORM_INVALID', 422, $checkout);
		if (($post['checkout_stage'] ?? 'preview') === 'preview') {
			$review = ['token' => bin2hex(random_bytes(32)), 'fingerprint' => $fingerprint, 'checkoutToken' => $token];
			$this->checkoutDataSession->review = $review;
			$reviewData = ['order' => $orderSnapshot, 'positions' => $positions, 'reviewToken' => $review['token']];
			if ($request->isXmlHttpRequest()) return $this->_helper->json(['success' => true, 'reviewHtml' => $this->view->partial('checkout/_review.phtml', $reviewData), 'csrfToken' => $checkout->renewCsrfToken()]);
			return $this->renderCheckout($checkout, $data, $reviewData);
		}
		$review = $this->checkoutDataSession->review ?? [];
		if (!is_string($post['review_token'] ?? null) || !isset($review['token'], $review['fingerprint'])
			|| !hash_equals($review['token'], $post['review_token']) || !hash_equals($review['fingerprint'], $fingerprint)) {
			return $this->checkoutFailure('SHOPS_CHECKOUT_REVIEW_CHANGED', 409, $checkout);
		}
		try {
			$rate = $this->consumeSubmissionLimit('checkout', 10, 3600);
		} catch (Throwable $e) {
			error_log('Shop checkout rate limiter unavailable: ' . $e->getMessage());
			return $this->checkoutFailure('SHOPS_SUBMISSION_UNAVAILABLE', 503, $checkout);
		}
		if (!$rate['allowed']) {
			$this->setRateLimitResponse((int)$rate['retryAfter']);
			return $this->checkoutFailure('SHOPS_RATE_LIMIT', 429, $checkout);
		}
		$contactId = !empty($data['email']) ? (int)(new Shops_Model_DbTable_Email())->findContactIdByEmail($data['email']) : 0;
		$orderDate = date('Y-m-d');
		$total = (float)$orderSnapshot['total'];
		try {
			$result = $orderDb->saveCheckoutOnce((int)$shop['id'], $token, array_merge([
				'contactid' => $contactId, 'invoiceid' => 0, 'orderdate' => $orderDate, 'total' => $total,
			], $orderSnapshot), $positions);
		} catch (Throwable $e) {
			error_log('Shop checkout could not be stored: ' . $e->getMessage());
			return $this->checkoutFailure('SHOPS_CHECKOUT_SAVE_UNCERTAIN', 503, $checkout);
		}
		$orderNumber = (string)$result['order']['orderid'];
		$this->checkoutDataSession->duplicate = !$result['inserted'];
		$this->rememberReceipt($token, true);
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
			$this->markSiteConversion('order_submitted', $total, 'EUR');
			try {
				$emailResult = $this->_helper->Email->sendEmail('shops', 'checkout', 'checkout', $cart, (int)$result['order']['id']);
				$this->checkoutDataSession->notificationSent = !empty($emailResult['customerAccepted']);
				if(($emailResult['internalAccepted'] ?? null) === false) error_log('Stored shop order internal notification was not accepted by SMTP');
			} catch (Throwable $e) {
				error_log('Stored shop order #' . (int)$result['order']['id'] . ' could not be emailed: ' . $e->getMessage());
				$this->checkoutDataSession->notificationSent = false;
			}
		}

		$this->checkoutDataSession->receipt = ['token' => $token, 'notificationSent' => $this->checkoutDataSession->notificationSent ?? null];
		return $this->checkoutSuccess();
	}

	private function cartPositions(array $cart): ?array
	{
		$shop = $this->_site;
		$itemDb = new Shops_Model_DbTable_Item();
		$taxDb = new Shops_Model_DbTable_Taxrate();
		$positions = [];
		$total = 0.0;
		foreach($cart as $cartId => $row) {
			if (!is_array($row) || !isset($row['sku'], $row['quantity'], $row['price'])
				|| !is_numeric($row['quantity']) || !is_numeric($row['price'])) {
				return null;
			}

			$quantity = (float)$row['quantity'];
			$price = (float)$row['price'];
			$item = $itemDb->getCartItem((int)$cartId);
			$currentPrice = $item ? Shops_Model_ShoppingCart::itemPrice($item) : null;
			if (!$item || $currentPrice === null || !Shops_Model_ShoppingCart::validQuantity($row['quantity'])
				|| (int)$item['id'] !== (int)$cartId || (string)$item['sku'] !== (string)$row['sku'] || !is_finite($quantity) || !is_finite($price)
				|| $quantity <= 0 || $quantity > 100000 || $price < 0 || $price > 99999999 || $currentPrice !== $price
				|| strtoupper((string)$item['currency']) !== 'EUR') {
				return null;
			}

			$lineTotal = round($quantity * $price, 2);
			if ($lineTotal > 99999999 || $total + $lineTotal > 99999999) return null;
			$total += $lineTotal;
			$tax = !empty($item['taxid']) ? $taxDb->getTaxRate((int)$item['taxid']) : null;
			$uom = !empty($item['uomid']) ? Zend_Db_Table::getDefaultAdapter()->fetchOne(Zend_Db_Table::getDefaultAdapter()->select()
				->from('uom', ['title'])->where('id = ?', (int)$item['uomid'])->where('clientid = ?', (int)$shop['clientid'])->where('deleted = ?', 0)) : null;
			$cart[$cartId]['title'] = (string)$item['title'];
			$positions[] = [
				'itemid' => (int)$item['id'],
				'total' => $lineTotal,
				'quantity' => $quantity,
				'price' => $price,
				'snapshot' => json_encode([
					'version' => 1, 'sku' => (string)$item['sku'], 'title' => (string)$item['title'],
					'description' => (string)($item['description'] ?? ''), 'currency' => 'EUR', 'uom' => $uom ?: null,
					'taxrate' => $tax && is_numeric($tax['rate']) && (float)$tax['rate'] >= 0 && (float)$tax['rate'] <= 100 ? (float)$tax['rate'] : null,
					'price_source' => (float)($item['specialprice'] ?? 0) > 0 ? 'specialprice' : 'price',
				], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
			];
		}

		return ['positions' => $positions, 'cart' => $cart];
	}

	private function rememberReceipt(string $token, bool $clearCart = false): void
	{
		if (($this->checkoutDataSession->receipt['token'] ?? '') !== $token) {
			$this->checkoutDataSession->receipt = ['token' => $token, 'notificationSent' => ($this->checkoutDataSession->lastCheckoutToken ?? '') === $token ? ($this->checkoutDataSession->notificationSent ?? null) : null];
			unset($this->checkoutDataSession->notificationSent);
		}
		$this->checkoutDataSession->lastCheckoutToken = $token;
		if (($this->checkoutDataSession->checkoutToken ?? '') === $token) $this->checkoutDataSession->checkoutToken = bin2hex(random_bytes(16));
		unset($this->checkoutDataSession->review);
		if ($clearCart) $this->cart->clearCart();
	}

	private function checkoutSuccess()
	{
		if ($this->getRequest()->isXmlHttpRequest()) return $this->_helper->json(['success' => true, 'redirect' => $this->view->url([], 'checkout_success')]);
		return $this->_helper->redirector->gotoRoute([], 'checkout_success', true);
	}

	private function checkoutFailure(string $key, int $status = 400, ?Shops_Form_Checkout $checkout = null)
	{
		$this->getResponse()->setHttpResponseCode($status);
		$this->view->checkoutError = $this->view->translate($key);
		if ($this->getRequest()->isXmlHttpRequest()) return $this->_helper->json([
			'success' => false, 'message' => $this->view->checkoutError, 'code' => $key,
			'errors' => $checkout ? $checkout->getMessages() : [],
			'csrfToken' => $checkout && !$checkout->csrf_token->hasErrors() ? $checkout->renewCsrfToken() : null, 'reviewUrl' => $this->view->url([], 'checkout'),
		]);
		return $this->renderCheckout($checkout ?: $this->checkoutForm(), $status === 413 ? [] : (array)$this->getRequest()->getPost());
	}

	private function renderCheckout(Shops_Form_Checkout $checkout, array $data, ?array $review = null): void
	{
		$this->_helper->viewRenderer->setNoRender(false);
		$this->_helper->viewRenderer->setRender('index');
		$this->_helper->getHelper('layout')->enableLayout();
		$this->showCheckout($checkout, $data, $review);
	}

	public function successAction()
	{
		$this->getResponse()->setHeader('Cache-Control', 'private, no-store', true);
		$this->initSiteLayout();
		$receipt = $this->checkoutDataSession->receipt ?? [
			'token' => $this->checkoutDataSession->lastCheckoutToken ?? '',
			'notificationSent' => $this->checkoutDataSession->notificationSent ?? null,
		];
		$order = !empty($receipt['token']) ? (new Shops_Model_DbTable_Order())->setClientId((int)$this->_site['clientid'])
			->getByCheckoutToken((int)$this->_site['id'], $receipt['token']) : null;
		$this->view->order = $order;
		$this->view->positions = $order ? (new Shops_Model_DbTable_Orderpos())->setClientId((int)$this->_site['clientid'])->getForOrder($order) : [];
		$this->view->notificationSent = $order ? ($receipt['notificationSent'] ?? null) : null;
		if (!$order) $this->getResponse()->setHttpResponseCode(404);
		$this->assignMessages();
	}
}
