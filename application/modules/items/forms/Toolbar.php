<?php

class Items_Form_Toolbar extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'add',
			'type' => 'button',
			'label' => 'TOOLBAR_NEW',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'add'],
		]);

		$this->addElement([
			'name' => 'edit',
			'type' => 'button',
			'label' => 'TOOLBAR_EDIT',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'edit'],
		]);

		$this->addElement([
			'name' => 'copy',
			'type' => 'button',
			'label' => 'TOOLBAR_COPY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'copy'],
		]);

		$this->addElement([
			'name' => 'delete',
			'type' => 'button',
			'label' => 'TOOLBAR_DELETE',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'delete'],
		]);

		$this->addElement([
			'name' => 'apply',
			'type' => 'button',
			'label' => 'TOOLBAR_APPLY',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'apply'],
		]);

		$this->addElement([
			'name' => 'filter',
			'type' => 'button',
			'label' => 'TOOLBAR_FILTER',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'filter'],
		]);

		$this->addElement([
			'name' => 'reset',
			'type' => 'button',
			'label' => 'TOOLBAR_RESET',
			'toolbar' => 'actions',
			'wrap' => false,
			'attribs' => ['class' => 'reset'],
		]);

		$this->addElement([
			'name' => 'keyword',
			'type' => 'text',
			'default' => '',
			'toolbar' => 'search',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'keyword'],
		]);

		$this->addElement([
			'name' => 'clear',
			'type' => 'button',
			'toolbar' => 'search',
			'wrap' => false,
			'attribs' => [
				'class' => 'clear nolabel',
				'rel' => 'keyword',
			],
		]);

		$this->addElement([
			'name' => 'catid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'all' => 'CATEGORIES_ALL',
			],
			'source' => 'category:item',
			'filter' => true,
			'toolbar' => 'search',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'order',
			'type' => 'radio',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'modified',
			'options' => [
				'modified' => 'TOOLBAR_MODIFIED',
				'created' => 'TOOLBAR_CREATED',
				'title' => 'TOOLBAR_TITLE',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'sort',
			'type' => 'radio',
			'label' => 'TOOLBAR_SORT',
			'default' => 'DESC',
			'options' => [
				'ASC' => 'TOOLBAR_ASC',
				'DESC' => 'TOOLBAR_DESC',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'country',
			'type' => 'select',
			'label' => 'TOOLBAR_COUNTRY',
			'default' => '0',
			'options' => [
				'0' => 'TOOLBAR_ALL_COUNTRIES',
			],
			'source' => 'country',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'tagid',
			'type' => 'select',
			'label' => 'TOOLBAR_TAGS',
			'default' => '0',
			'options' => [
				'0' => 'TOOLBAR_ALL',
			],
			'source' => 'tag',
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'int'],
		]);

		$request = class_exists('Zend_Controller_Front') ? Zend_Controller_Front::getInstance()->getRequest() : null;
		if ($request && $request->getModuleName() === 'items' && $request->getControllerName() === 'item' && get_class($this) === self::class && in_array($request->getActionName(), ['index', 'search', 'select', 'edit', 'view'], true)) {
			foreach (['add' => 'add', 'edit' => 'edit', 'copy' => 'copy', 'filter' => 'filter', 'reset' => 'reset'] as $name => $icon) {
				$this->setElementData($name, ['icon' => $icon, 'iconFormat' => 'svg']);
			}
			$this->setElementData('clear', ['label' => 'CLEAR', 'icon' => 'clear', 'iconFormat' => 'svg', 'iconOnly' => true]);
			if ($request->getActionName() === 'select' || ($request->getActionName() === 'search' && $request->getParam('context_action') === 'select')) {
				$this->setElementData('apply', ['icon' => 'apply', 'iconFormat' => 'svg']);
			}
		}
	}
}
