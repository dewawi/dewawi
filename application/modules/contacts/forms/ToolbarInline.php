<?php

class Contacts_Form_ToolbarInline extends DEEC_Form
{

	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'attribs'=> ['class' => 'edit nolabel'],
			'wrap'=> false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'attribs'=> ['class' => 'copy nolabel'],
			'wrap'=> false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'attribs'=> ['class' => 'delete nolabel'],
			'wrap'=> false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'pdf',
			'attribs'=> ['class' => 'pdf nolabel'],
			'wrap'=> false,
		]);
	}
}
