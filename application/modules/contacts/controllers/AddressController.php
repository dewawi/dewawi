<?php

class Contacts_AddressController extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}

	protected function getCreateDefaults(
		array $post
	): array {
		$client = Zend_Registry::get('Client');

		return [
			'type' => !empty($post['type'])
				? (string)$post['type']
				: 'billing',
			'country' => (string)(
				$client['country'] ?? '0'
			),
		];
	}
}
