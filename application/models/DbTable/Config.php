<?php

class Application_Model_DbTable_Config extends Zend_Db_Table_Abstract
{

	protected $_name = 'config';

	public function getConfig()
	{
		$row = $this->fetchRow();
		if (!$row) {
			throw new Exception("Could not find config row");
		}
		return $row->toArray();
	}

	public function updateConfig($id, $company, $address, $postcode, $city, $country, $email, $website, $language)
	{
		$data = array(
			'company' => $company,
			'address' => $address,
			'postcode' => $postcode,
			'city' => $city,
			'country' => $country,
			'email' => $email,
			'website' => $website,
			'language' => $language,
		);
		$this->update($data, 'id = '. (int)$id);
	}
}
