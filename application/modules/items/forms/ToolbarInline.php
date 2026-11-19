<?php

class Items_Form_ToolbarInline extends DEEC_Form
{
	public function __construct()
	{
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
		if ($request && get_class($this) === self::class && $request->getModuleName() === 'items' && $request->getControllerName() === 'item'
			&& ($request->getActionName() === 'select' || ($request->getActionName() === 'search' && $request->getParam('context_action') === 'select'))) {
			$this->setElementData('apply', ['label' => 'TOOLBAR_APPLY', 'icon' => 'apply', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
		}

		if ($request && get_class($this) === self::class && $request->getModuleName() === 'items' && $request->getControllerName() === 'item'
			&& in_array($request->getActionName(), ['index', 'search'], true) && $request->getParam('context_action', 'index') === 'index') {
			foreach (['edit' => 'TOOLBAR_EDIT', 'copy' => 'TOOLBAR_COPY'] as $name => $label) {
				$attribs = $this->getElement($name)['attribs'];
				$attribs['title'] = $this->translate($label);
				$this->setElementData($name, ['label' => $label, 'icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm', 'attribs' => $attribs]);
			}
		}
	}
}
