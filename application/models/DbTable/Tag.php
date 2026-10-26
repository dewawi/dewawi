<?php

class Application_Model_DbTable_Tag extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'tag';

	protected $_date = null;

	protected $_user = null;

	protected $_client = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
		$this->_client = Zend_Registry::get('Client');
	}

	public function getTags($module, $controller, ?int $shopId = null)
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('module = ?', $module);
		$where[] = $this->getAdapter()->quoteInto('controller = ?', $controller);
		if ($shopId !== null) $where[] = $this->getAdapter()->quoteInto('shopid = ?', $shopId);
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->getClientId());
		$where[] = $this->getAdapter()->quoteInto('deleted = ?', 0);
		$data = $this->fetchAll($where);

		$tags = array();
		foreach($data as $tag) {
			$tags[$tag->id] = $tag->title;
		}
		return $tags;
	}

	public function addTag($data)
	{
		$data['clientid'] = $this->getClientId();
		$data['created'] = $this->_date;
		$data['createdby'] = $this->_user['id'];
		$this->insert($data);
		return $this->getAdapter()->lastInsertId();
	}

	public function findOrCreate(string $title, string $module, string $controller): int
	{
		$title = trim($title);

		if ($title === '') {
			throw new InvalidArgumentException('Tag title must not be empty');
		}

		$select = $this->select()
			->where('title = ?', $title)
			->where('module = ?', $module)
			->where('controller = ?', $controller)
			->where('shopid = ?', 0)
			->where('clientid = ?', $this->getClientId())
			->order('deleted ASC')
			->order('id ASC')
			->limit(1);

		$row = $this->fetchRow($select);

		if ($row) {
			if((int)$row->deleted === 1) {
				$where = [
					$this->getAdapter()->quoteInto('id = ?', (int)$row->id),
					$this->getAdapter()->quoteInto('clientid = ?', $this->getClientId()),
					$this->getAdapter()->quoteInto('module = ?', $module),
					$this->getAdapter()->quoteInto('controller = ?', $controller),
					$this->getAdapter()->quoteInto('shopid = ?', 0),
				];

				$this->update($this->prepareUpdateData(['deleted' => 0]), $where);
			}

			return (int)$row->id;
		}

		return $this->create([
			'shopid' => 0,
			'title' => $title,
			'module' => $module,
			'controller' => $controller,
		]);
	}

	public function getSelectOptions(string $module = '', string $controller = ''): array
	{
		if ($module === '' || $controller === '') {
			$request = Zend_Controller_Front::getInstance()->getRequest();

			$module = $module ?: $request->getModuleName();
			$controller = $controller ?: $request->getControllerName();
		}

		return $this->getTags($module, $controller);
	}
}
