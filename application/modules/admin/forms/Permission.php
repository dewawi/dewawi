<?php

class Admin_Form_Permission extends DEEC_Form
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
			'name' => 'add',
			'type' => 'checkbox',
			'label' => 'ADMIN_ADD',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'checkbox',
			'label' => 'ADMIN_EDIT',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'view',
			'type' => 'checkbox',
			'label' => 'ADMIN_VIEW',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'checkbox',
			'label' => 'ADMIN_DELETE',
			'format' => ['type' => 'int'],
			'col' => 3,
		]);
	}
}
