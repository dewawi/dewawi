<?php

class Application_Model_DbTable_State extends DEEC_Model_DbTable_Entity
{

	protected $_name = 'state';

	public function getStates()
	{
		$states = array(
			'100' => 'STATES_CREATED',
			'101' => 'STATES_IN_PROCESS',
			'102' => 'STATES_PLEASE_CHECK',
			'103' => 'STATES_PLEASE_DELETE',
			'104' => 'STATES_RELEASED',
		);
		return $states;
	}

	public function getSelectOptions(): array
	{
		return $this->getStates();
	}
}
