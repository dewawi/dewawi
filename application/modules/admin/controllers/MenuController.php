<?php

class Admin_MenuController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'menus',
			'list' => 'Admin_Model_List_Menus',
			'entity' => Admin_Model_Entity_Menu::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_MENU'),
			'shopid' => (int)$this->_getParam('shopid', 0),
			'position' => (string)$this->_getParam('position', ''),
		];
	}

	protected function beforeCreate(array $data): array
	{
		$db = new Admin_Model_DbTable_Menu();

		$data['ordering'] = $db->getNextOrdering([
			'shopid' => (int)$data['shopid'],
			'position' => (string)$data['position'],
		]);

		return $data;
	}

	protected function afterEditLoad(array $row): void
	{
		$menuitemList = new Admin_Model_List_Menuitems();

		$menuitemList->setData([
			'menuid' => (int)$row['id'],
		]);

		$this->view->menuitems = $menuitemList;
	}

	protected function canDeleteRow(array $row): bool
	{
		$menuitemDb = new Admin_Model_DbTable_Menuitem();

		return !$menuitemDb->hasItemsForMenu((int)$row['id']);
	}
}
