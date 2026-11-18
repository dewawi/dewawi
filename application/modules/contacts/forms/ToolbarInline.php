<?php

class Contacts_Form_ToolbarInline extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'view',
			'attribs' => ['class' => 'view nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'edit',
			'attribs' => ['class' => 'edit nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'copy',
			'attribs' => ['class' => 'copy nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'delete',
			'attribs' => ['class' => 'delete nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'pdf',
			'attribs' => ['class' => 'pdf nolabel'],
			'wrap' => false,
		]);

		$this->addElement([
			'type' => 'button',
			'name' => 'apply',
			'attribs' => ['class' => 'apply nolabel'],
			'wrap' => false,
		]);

		$request = $this->getCurrentRequest();
		if ($request && get_class($this) === self::class && $request->getModuleName() === 'contacts' && $request->getControllerName() === 'contact'
			&& ($request->getActionName() === 'select' || ($request->getActionName() === 'search' && $request->getParam('context_action') === 'select'))) {
			$this->setElementData('apply', ['label' => 'TOOLBAR_APPLY', 'icon' => 'apply', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
		}
	}
}
