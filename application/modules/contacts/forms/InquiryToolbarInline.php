<?php

class Contacts_Form_InquiryToolbarInline extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'view',
			'label' => 'TOOLBAR_VIEW',
			'icon' => 'view',
			'iconFormat' => 'svg',
			'iconOnly' => true,
			'size' => 'sm',
			'attribs' => ['class' => 'view nolabel'],
			'wrap' => false,
		]);
		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'label' => 'TOOLBAR_EDIT',
			'icon' => 'edit',
			'iconFormat' => 'svg',
			'iconOnly' => true,
			'size' => 'sm',
			'attribs' => ['class' => 'edit nolabel'],
			'wrap' => false,
		]);
	}
}
