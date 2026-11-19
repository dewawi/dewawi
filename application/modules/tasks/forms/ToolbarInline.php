<?php

class Tasks_Form_ToolbarInline extends DEEC_Form
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

		$request = $this->getCurrentRequest();
		if ($request && get_class($this) === self::class && $request->getModuleName() === 'tasks' && $request->getControllerName() === 'task'
			&& in_array($request->getActionName(), ['index', 'search'], true) && $request->getParam('context_action', 'index') === 'index') {
			foreach (['view' => 'TOOLBAR_VIEW', 'edit' => 'TOOLBAR_EDIT', 'copy' => 'TOOLBAR_COPY'] as $name => $label) {
				$attribs = $this->getElement($name)['attribs'];
				$attribs['title'] = $this->translate($label);
				$this->setElementData($name, ['label' => $label, 'icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm', 'attribs' => $attribs]);
			}
			$this->setElementData('delete', ['label' => 'TOOLBAR_DELETE', 'icon' => 'delete', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
		}
	}
}
