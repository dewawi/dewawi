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
		return $this->getByClientId((int)$this->_client['id']);
	}

	public function getByClientId(int $clientId): ?array
	{
		$row = $this->fetchRow($this->getAdapter()->quoteInto('clientid = ?', $clientId));
		return $row ? $row->toArray() : null;
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
