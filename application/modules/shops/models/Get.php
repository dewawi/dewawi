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
		$shop = Zend_Registry::get('Shop');

		$itemsDb = new Shops_Model_DbTable_Item();

		$records = $itemsDb->fetchAll(
			$itemsDb->select()
				->where('shopid = ?', $shopid)
				->where('shopcatid = ?', $params['catid'])
				->where('clientid = ?', (int)$shop['clientid'])
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
				->where('deleted = ?', 0)
		);

		$items = $itemsDb->fetchAll(
			$itemsDb->select()
				->where('shopid = ?', $shopid)
				->where('shopcatid = ?', $params['catid'])
				->where('clientid = ?', (int)$shop['clientid'])
				->order($params['order'].' '.$params['sort'])
				->limit($params['limit'])
				->where('deleted = ?', 0)
		);

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
}
