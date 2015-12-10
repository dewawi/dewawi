<?php

class Application_Model_DbTable_Address extends Zend_Db_Table_Abstract
{

    protected $_name = 'address';

    public function getAddress($id)
    {
        $id = (int)$id;
        $row = $this->fetchRow('id = ' . $id);
        if (!$row) {
            throw new Exception("Could not find row $id");
        }
        return $row->toArray();
    }

    public function addAddress($contactid, $type, $name1, $name2, $department, $street, $postcode, $city, $country, $phone, $clientid, $created)
    {
        $data = array(
            'contactid' => $contactid,
            'type' => $type,
            'name1' => $name1,
            'name2' => $name2,
            'department' => $department,
            'street' => $street,
            'postcode' => $postcode,
            'city' => $city,
            'country' => $country,
            'phone' => $phone,
            'clientid' => $clientid,
            'created' => $created,
        );
        $this->insert($data);
    }

    public function updateAddress($id, $data)
    {
        $this->update($data, 'id = '. (int)$id);
    }

    public function deleteAddress($id)
    {
        $this->delete('id =' . (int)$id);
    }
}
