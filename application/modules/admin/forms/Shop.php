<?php

class Admin_Form_Shop extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'title',
			'type' => 'text',
			'label' => 'ADMIN_TITLE',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'url',
			'type' => 'text',
			'label' => 'ADMIN_URL',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'logo',
			'type' => 'text',
			'label' => 'ADMIN_LOGO',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'text',
			'label' => 'ADMIN_FOOTER',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'emailsender',
			'type' => 'text',
			'label' => 'ADMIN_EMAIL',
			'format' => ['type' => 'string'],
			'attribs' => [
				'maxlength' => 255,
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'catalogenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CATALOG_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'checkoutenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CHECKOUT_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'contactenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_CONTACT_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'inquiryenabled',
			'type' => 'checkbox',
			'label' => 'ADMIN_INQUIRY_ENABLED',
			'format' => ['type' => 'int'],
			'default' => 0,
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
