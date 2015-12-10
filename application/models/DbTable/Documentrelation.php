<?php

class Application_Model_DbTable_Documentrelation extends Zend_Db_Table_Abstract
{

	protected $_name = 'documentrelation';

	public function getDocumentrelation($id)
	{
		$id = (int)$id;
		$row = $this->fetchRow('id = ' . $id);
		if (!$row) {
			throw new Exception("Could not find row $id");
		}
		return $row->toArray();
	}

	public function addDocumentrelation($contactid, $documentid, $module, $controller, $created, $createdby)
	{
		$data = array(
			'contactid' => $contactid,
			'documentid' => $documentid,
			'module' => $module,
			'controller' => $controller,
			'created' => $created,
			'createdby' => $createdby
		);
		$this->insert($data);
	}

	public function updateDocumentrelation($id, $contactid, $documentid, $module, $controller)
	{
		$data = array(
			'contactid' => $contactid,
			'documentid' => $documentid,
			'module' => $module,
			'controller' => $controller,
			'modified' => $modified,
			'createdby' => $createdby
		);
		$this->update($data, 'id = '. (int)$id);
	}

	public function deleteDocumentrelation($id)
	{
		$this->delete('id =' . (int)$id);
	}
}
