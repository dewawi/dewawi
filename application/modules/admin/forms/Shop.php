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
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'format' => ['type' => 'int'],
			'col' => 12,
		]);
	}
}
