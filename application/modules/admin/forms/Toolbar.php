<?php

class Admin_Form_Toolbar extends DEEC_Form
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
			'name' => 'order',
			'type' => 'select',
			'label' => 'TOOLBAR_ORDER',
			'default' => 'modified',
			'options' => [
				'modified' => 'TOOLBAR_MODIFIED',
				'created' => 'TOOLBAR_CREATED',
				'processid' => 'PROCESSES_PROCESS_ID',
				'name1' => 'CONTACTS_NAME',
			],
			'filter' => true,
			'toolbar' => 'filters',
			'wrap' => false,
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'name' => 'sort',
			'type' => 'select',
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
			'name' => 'parentid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_MAIN_CATEGORY',
			],
			'source' => 'category',
			'filter' => true,
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
				'contact' => 'CONTACTS',
				'item' => 'ITEMS',
			],
			'filter' => true,
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'shopid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'shop',
			'filter' => true,
			'toolbar' => 'slide',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'menuid',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'menu',
			'filter' => true,
			'toolbar' => 'menuitem',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);

		$this->addElement([
			'name' => 'language',
			'type' => 'select',
			'default' => 'all',
			'options' => [
				'0' => 'ADMIN_SELECT',
			],
			'source' => 'language',
			'filter' => true,
			'toolbar' => 'language',
			'wrap' => false,
			'format' => ['type' => 'string'],
			'attribs' => ['class' => 'hidden-sm'],
		]);
	}
}
