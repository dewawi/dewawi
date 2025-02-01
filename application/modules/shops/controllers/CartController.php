<?php
class Shops_CartController extends Zend_Controller_Action
{
	private $cart;

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
		$contact = new Shops_Form_Contact();
		$this->view->contact = $contact;

		$categoryDb = new Shops_Model_DbTable_Category();
		$categories = $categoryDb->getCategories();

		$slideDb = new Shops_Model_DbTable_Slide();
		$slides = $slideDb->getSlides($shop['id']);

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

	public function addAction()
	{
		$id = $this->_getParam('id');
		$title = $this->_getParam('title');
		$sku = $this->_getParam('sku');
		$price = $this->_getParam('price');
		$quantity = $this->_getParam('quantity', 1);

		$this->cart->addItem($id, $title, $sku, $price, $quantity);

		$response = [
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		];
		$this->_helper->json($response);
	}

	public function updateAction()
	{
		$id = $this->_getParam('id');
		$quantity = $this->_getParam('quantity');

		$this->cart->updateItem($id, $quantity);

		$response = [
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		];
		$this->_helper->json($response);
	}

	public function countAction()
	{
		$count = $this->cart->getItemCount();
		$response = [
			'success' => true,
			'count' => $count,
		];
		$this->_helper->json($response);
	}

	public function removeAction()
	{
		$id = $this->_getParam('id');

		$this->cart->removeItem($id);

		$response = [
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		];
		$this->_helper->json($response);
	}

	public function clearAction()
	{
		$this->cart->clearCart();

		$response = [
			'success' => true,
			'cart' => $this->cart->getItems(),
			'total' => $this->cart->getTotalPrice(),
			'cartItemCount' => $this->cart->getItemCount(),
		];
		$this->_helper->json($response);
	}
}
