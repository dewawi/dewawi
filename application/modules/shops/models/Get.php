<?php

class Shops_Model_Get
{
	protected $db;

	public function __construct()
	{
		// Initialize your database adapter
		$this->db = Zend_Db_Table::getDefaultAdapter();
	}

	public function accounts($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$accountsDb = new Shops_Model_DbTable_Account();

		$columns = array('title');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$accounts = $accountsDb->fetchAll(
			$accountsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $accounts;
	}

	public function items($params, $shopid)
	{
		//$client = Zend_Registry::get('Client');
		//if($client['parentid']) {
		//	$client['id'] = $client['modules']['shops'];
		//}
		//print_r($params);

		$itemsDb = new Shops_Model_DbTable_Item();

		$records = $itemsDb->fetchAll(
			$itemsDb->select()
				->where('shopid = ?', $shopid)
				->where('shopcatid = ?', $params['catid'])
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
				->where('deleted = ?', 0)
		);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where('shopid = ?', $shopid)
				->where('shopcatid = ?', $params['catid'])
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
				->where('deleted = ?', 0)
		);
		//echo $params['catid'];
		return array($items, count($records));
	}

	public function getImages($parentid, $module, $controller)
	{
		// Fetch images from the database associated with the given item ID
		$select = $this->db->select()
						   ->from('images')
						   ->where('parentid = ?', $parentid)
						   ->where('module = ?', $module)
						   ->where('controller = ?', $controller)
						   ->where('deleted = ?', 0);

		$stmt = $this->db->query($select);
		$imagesData = $stmt->fetchAll();

		$images = [];
		foreach ($imagesData as $imageData) {
			$image = new stdClass();
			$image->url = $imageData['url'];
			$image->title = $imageData['title'];
			$images[] = $image;
		}

		return $images;
	}

	public function orders($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$ordersDb = new Shops_Model_DbTable_Order();

		$columns = array('userid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$orders = $ordersDb->fetchAll(
			$ordersDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		return $orders;
	}

	public function tags($module, $controller, $id = null) {
		if($id) {
			$shopid = 100; // TODO
			//$client = Zend_Registry::get('Client');
			$tagEntityDb = new Shops_Model_DbTable_Tagentity();
			$tags = $tagEntityDb->fetchAll(
				$tagEntityDb->select()
					->setIntegrityCheck(false)
					->from(array('t' => 'tagentity'))
					->join(array('tag' => 'tag'), 't.tagid = tag.id', array('title as tag', 'module', 'controller', 'slug'))
					->group('t.id')
					//->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.shopid = "'.$shopid.'") AND (t.deleted = 0)')
					->where('(t.entityid = "'.$id.'") AND (t.module = "'.$module.'") AND (t.controller = "'.$controller.'") AND (t.deleted = 0)')
					//->order($order.' '.$params['sort'])
					//->limit($params['limit'], $params['offset'])
			);
			$tags = $tags->toArray();
		} else {
			$tagsDb = new Shops_Model_DbTable_Tag();
			$tags = $tagsDb->getTags($module, $controller);
		}
		//print_r($tags);

		return $tags;
	}

	/*public function items($params, $options)
	{
		$client = Zend_Registry::get('Client');
		if($client['parentid']) {
			$client['id'] = $client['modules']['shops'];
		}

		$itemsDb = new Shops_Model_DbTable_Item();

		$columns = array('itemid');

		$query = '';
		$queryHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Query');
		if($params['keyword']) $query = $queryHelper->getQueryKeyword($query, $params['keyword'], $columns);
		$query = $queryHelper->getQueryClient($query, $client['id']);
		$query = $queryHelper->getQueryDeleted($query);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where($query ? $query : 0)
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
		);

		$currencyHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('Currency');
		$currency = $currencyHelper->getCurrency();
		foreach($items as $item) {
			//if(strlen($item->description) > 43) $item->description = substr($item->description, 0, 40).'...';
			//$currency = $currencyHelper->setCurrency($currency, $item->currency, 'USE_SYMBOL');
			//$item->cost = $currency->toCurrency($item->cost);
			//$item->price = $currency->toCurrency($item->price);
		}

		return $items;
	}*/
}
