<?php

class Application_Model_DbTable_Config extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'config';

	public function init()
	{
		parent::init();
	}

	public function getConfig()
	{
		$where = array();
		$where[] = $this->getAdapter()->quoteInto('clientid = ?', $this->_client['id']);
		$row = $this->fetchRow($where);
		return $row->toArray();
	}

	public function getBySesFeedbackTopicArn(string $topicArn): ?array
	{
		$topicArn = trim($topicArn);
		if($topicArn === '') return null;

		$row = $this->fetchRow(
			$this->getAdapter()->quoteInto('sesfeedbacktopicarn = ?', $topicArn)
		);

		return $row ? $row->toArray() : null;
	}
}
