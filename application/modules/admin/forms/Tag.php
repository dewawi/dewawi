<?php

class Admin_Form_Tag extends DEEC_Form
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
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'image',
			'type' => 'text',
			'label' => 'ADMIN_TAG_IMAGE',
			'format' => ['type' => 'string'],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'description',
			'type' => 'textarea',
			'label' => 'ADMIN_TAG_DESCRIPTION',
			'format' => ['type' => 'html'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'shortdescription',
			'type' => 'textarea',
			'label' => 'ADMIN_TAG_SHORT_DESCRIPTION',
			'format' => ['type' => 'html'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'minidescription',
			'type' => 'textarea',
			'label' => 'ADMIN_TAG_MINI_DESCRIPTION',
			'format' => ['type' => 'html'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'header',
			'type' => 'textarea',
			'label' => 'CREDIT_NOTES_HEADER',
			'format' => ['type' => 'html'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'footer',
			'type' => 'textarea',
			'label' => 'CREDIT_NOTES_FOOTER',
			'format' => ['type' => 'html'],
			'attribs' => [
				'cols' => 75,
				'rows' => 18,
				'class' => 'editor',
			],
			'col' => 12,
		]);

		$this->addElement([
			'name' => 'parentid',
			'type' => 'select',
			'label' => 'ADMIN_MAIN_TAG',
			'format' => ['type' => 'int'],
			'options' => [
				0 => 'ADMIN_MAIN_TAG',
			],
			'default' => 0,
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'format' => ['type' => 'string'],
			'options' => [
				'contact' => 'CONTACT',
				'item' => 'ITEM',
			],
			'default' => 'contact',
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'options' => [],
			'col' => 6,
		]);

		$this->addElement([
			'name' => 'clientid',
			'type' => 'select',
			'format' => ['type' => 'int'],
			'options' => [],
			'default' => 0,
			'col' => 6,
		]);
	}
}
