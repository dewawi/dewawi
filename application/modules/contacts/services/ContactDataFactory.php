<?php

class Contacts_Service_ContactDataFactory
{
	public function getContactData(int $contactId): array
	{
		$data = [];

		$contactDb = new Contacts_Model_DbTable_Contact();
		$contact = $contactDb->getContact($contactId);

		if(!$contact) {
			return $data;
		}

		$data['contactid'] = $contact['contactid'];
		$data['billingname1'] = $contact['name1'];
		$data['billingname2'] = $contact['name2'];
		$data['billingdepartment'] = $contact['department'];

		if(!empty($contact['vatin'])) {
			$data['vatin'] = $contact['vatin'];
		}

		if(!empty($contact['currency'])) {
			$data['currency'] = $contact['currency'];
		}

		if(!empty($contact['taxfree'])) {
			$data['taxfree'] = $contact['taxfree'];
		}

		$addressDb = new Contacts_Model_DbTable_Address();
		$addresses = $addressDb->getByParentId($contact['id'], 'contacts', 'contact');

		$billingAddress = null;
		$shippingAddress = null;

		foreach($addresses as $address) {
			if($address['type'] === 'billing' && !$billingAddress) {
				$billingAddress = $address;
			}

			if($address['type'] === 'shipping' && !$shippingAddress) {
				$shippingAddress = $address;
			}
		}

		if($billingAddress) {
			$data['billingname1'] = $billingAddress['name1'] ?: $data['billingname1'];
			$data['billingname2'] = $billingAddress['name2'] ?: $data['billingname2'];
			$data['billingdepartment'] = $billingAddress['department'] ?: $data['billingdepartment'];
			$data['billingstreet'] = $billingAddress['street'];
			$data['billingpostcode'] = $billingAddress['postcode'];
			$data['billingcity'] = $billingAddress['city'];
			$data['billingcountry'] = $billingAddress['country'];
		}

		if($shippingAddress) {
			$data['shippingname1'] = $shippingAddress['name1'] ?: $data['billingname1'];
			$data['shippingname2'] = $shippingAddress['name2'] ?: $data['billingname2'];
			$data['shippingdepartment'] = $shippingAddress['department'] ?: $data['billingdepartment'];
			$data['shippingstreet'] = $shippingAddress['street'];
			$data['shippingpostcode'] = $shippingAddress['postcode'];
			$data['shippingcity'] = $shippingAddress['city'];
			$data['shippingcountry'] = $shippingAddress['country'];
			$data['shippingphone'] = $shippingAddress['phone'];
		} elseif($billingAddress) {
			$data['shippingname1'] = $data['billingname1'];
			$data['shippingname2'] = $data['billingname2'];
			$data['shippingdepartment'] = $data['billingdepartment'];
			$data['shippingstreet'] = $data['billingstreet'];
			$data['shippingpostcode'] = $data['billingpostcode'];
			$data['shippingcity'] = $data['billingcity'];
			$data['shippingcountry'] = $data['billingcountry'];
		}

		return $data;
	}
}
