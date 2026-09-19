<?php

class Shops_Model_DbTable_Inquirydata extends DEEC_Model_DbTable_SiteEntity
{
	protected $_name = 'shopinquirydata';

	public function save(int $formId, string $token, array $data): int
	{
		return $this->create([
			'formid' => $formId,
			'token' => $token,
			'data' => json_encode($data),
			'modified' => $this->_date,
		]);
	}
}
