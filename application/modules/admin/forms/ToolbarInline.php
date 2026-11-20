<?php

class Admin_Form_ToolbarInline extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'button',
			'name' => 'save',
			'attribs' => ['class' => 'save nolabel'],
			'wrap' => false,
		]);

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
			'name' => 'delete',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => [
				'class' => 'delete nolabel',
				'data-action' => 'media-delete',
			],
		]);

		$this->addElement([
			'name' => 'sortup',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'up nolabel'],
		]);

		$this->addElement([
			'name' => 'sortdown',
			'type' => 'button',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'down nolabel'],
		]);

		$request = $this->getCurrentRequest();
		if ($request && get_class($this) === self::class && $request->getModuleName() === 'admin' && in_array($request->getControllerName(), ['country', 'uom', 'deliverytime', 'manufacturer', 'warehouse', 'shippingmethod'], true)
			&& in_array($request->getActionName(), ['index', 'search'], true) && $request->getParam('context_action', 'index') === 'index') {
			foreach (['copy' => 'TOOLBAR_COPY'] as $name => $label) {
				$attribs = $this->getElement($name)['attribs'];
				$attribs['title'] = $this->translate($label);
				$this->setElementData($name, ['label' => $label, 'icon' => $name, 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm', 'attribs' => $attribs]);
			}
		}

		if ($request && get_class($this) === self::class && $request->getModuleName() === 'admin' && $request->getControllerName() === 'shop'
			&& in_array($request->getActionName(), ['index', 'search'], true) && $request->getParam('context_action', 'index') === 'index') {
			$this->setElementData('copy', ['icon' => 'copy', 'iconFormat' => 'svg', 'iconOnly' => true, 'size' => 'sm']);
		}
	}
}
