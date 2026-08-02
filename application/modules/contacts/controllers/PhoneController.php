<?php

class Contacts_PhoneController extends DEEC_Controller_MultiEntityAction
{
	protected function getParentFormClass(): string
	{
		return Contacts_Form_Contact::class;
	}

	protected function getCreateDefaults(
		array $post
	): array {
		return [
			'type' => !empty($post['type'])
				? (string)$post['type']
				: 'phone',
		];
	}
}
