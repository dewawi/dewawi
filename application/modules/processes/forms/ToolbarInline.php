<?php

class Processes_Form_ToolbarInline extends DEEC_Form
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

		$request = $this->getCurrentRequest();
		if ($request && get_class($this) === self::class && $request->getModuleName() === 'processes' && $request->getControllerName() === 'process'
			&& in_array($request->getActionName(), ['index', 'search'], true) && $request->getParam('context_action', 'index') === 'index') {
			foreach (['view', 'edit', 'copy', 'delete'] as $name) $this->setElementData($name, ['icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
		}
	}
}
