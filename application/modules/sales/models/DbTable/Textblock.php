<?php

class Sales_Model_DbTable_Textblock extends Zend_Db_Table_Abstract
{

	protected $_name = 'textblock';

	public function updateTextblock($data, $controller, $section)
	{
        $where[] = "controller = '".$controller."'";
        $where[] = "section = '".$section."'";
		$this->update($data, $where);
	}
}
