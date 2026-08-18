<?php

class Items_Model_DbTable_Item extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'item';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getItem($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);

		return $row ? $row->toArray() : null;
	}

	public function getItemForEdit($id)
	{
		$id = (int)$id;

		$where = [];
		$where[] = $this->getAdapter()->quoteInto('id = ?', $id);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);

		$row = $this->fetchRow($where);

		return $row ? $row->toArray() : null;
	}

	public function getItemBySKU($sku)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('sku = ?', $sku);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchRow($where);
		return $data ? $data->toArray() : $data;
	}

	public function getItems($ids)
	{
		$where = $this->getAdapter()->quoteInto('sku IN (?)', $ids);
		$data = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $ids");
		}
		return $row->toArray();
	}

	public function getItemsByCategory($catid)
	{
		$catid = (int)$catid;
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('catid = ?', $catid);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$row = $this->fetchAll($where);
		if (!$row) {
			throw new Exception("Could not find row $catid");
		}
		return $row->toArray();
	}

	public function getLatestItems()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where, 'id DESC', 5);
		return $data;
	}

	public function getVariants(int $parentId)
	{
		$select = $this->select()
			->where('parentid = ?', $parentId)
			->where('clientid = ?', $this->getClientId())
			->where('deleted = ?', 0)
			->order('sku ASC');

		return $this->fetchAll($select);
	}

	public function addItem($data)
	{
		$data['clientid'] = $this->_client['id'];
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function updateItem($id, $data)
	{
		$id = (int)$id;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function quantityItem($id, $quantity)
	{
		$id = (int)$id;
		$data = array();
		$data['quantity'] = $quantity;
		$data['modified'] = $this->_date;
		$data['modifiedby'] = $this->_user['id'];
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	protected function prepareCopyData(array $data): array
	{
		$data = parent::prepareCopyData($data);

		$data['quantity'] = 0;
		$data['inventory'] = 1;
		$data['pinned'] = 0;

		return $data;
	}

	public function changeQuantity(int $id, float $delta): void
	{
		$this->updateById($id, [
			'quantity' => new Zend_Db_Expr(
				$this->getAdapter()->quoteInto('IFNULL(quantity, 0) + ?', $delta)
			),
		]);
	}

	public function deleteItem($id)
	{
		$id = (int)$id;
		$data = array('deleted' => 1);
		$where = $this->getAdapter()->quoteInto('id = ?', $id);
		$this->update($data, $where);
	}

	public function suggestItems(string $keyword, int $clientId, int $limit = 10): array {
		$db = $this->getAdapter();

		$select = $this->select()
			->from(
				['i' => $this->_name],
				[
					'id',
					'sku',
					'title',
					'manufacturersku',
					'price',
					'quantity',
				]
			)
			->where('i.clientid = ?', $clientId)
			->where('i.deleted = ?', 0)
			->order('i.title ASC')
			->limit($limit);

		$words = preg_split('/\s+/', trim($keyword));

		foreach ($words as $word) {
			if ($word === '') {
				continue;
			}

			$like = '%' . $word . '%';

			$select->where(
				'(
					i.id LIKE ' . $db->quote($like) . '
					OR i.sku LIKE ' . $db->quote($like) . '
					OR i.manufacturersku LIKE ' . $db->quote($like) . '
					OR i.title LIKE ' . $db->quote($like) . '
					OR i.description LIKE ' . $db->quote($like) . '
				)'
			);
		}

		$rows = $this->fetchAll($select);
		$items = [];

		foreach ($rows as $row) {
			$label = trim(
				(string)$row->sku
				. ' · '
				. (string)$row->title
			);

			$subtitle = [];

			if ((string)$row->manufacturersku !== '') {
				$subtitle[] = (string)$row->manufacturersku;
			}

			$subtitle[] = (string)$row->price;
			$subtitle[] = 'Bestand: ' . (string)$row->quantity;

			$items[] = [
				'id' => (int)$row->id,
				'label' => $label,
				'subtitle' => implode(' · ', $subtitle),
			];
		}

		return $items;
	}
}
