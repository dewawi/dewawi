<?php

class Application_Model_DbTable_Template extends Zend_Db_Table_Abstract
{

	protected $_name = 'template';

	protected $_date = null;

	protected $_user = null;

	public function init()
	{
		$this->_date = date('Y-m-d H:i:s');
		$this->_user = Zend_Registry::get('User');
	}

	public function getTemplate($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id . ' AND activated = 1');
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function getDefaultTemplate()
	{
		$row = $this->fetchRow('`default` = 1 AND `activated` = 1');
		if($row) {
		    return $row->toArray();
		}
	}

	public function getTemplates()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_user['clientid']);
		$where[] = $this->getAdapter()->quoteInto('activated = ?', 1);
		$data = $this->fetchAll($where);

		$templates = array();
		foreach($data as $template) {
			$templates[$template->id] = $template->description;
		}
		return $templates;
	}
}
