<?php

class Admin_UomController extends DEEC_Controller_AdminAction
{
	protected function buildIndexView(): void
	{
		$this->buildListView([
			'viewKey' => 'uoms',
			'list' => 'Admin_Model_List_Uoms',
			'entity' => Admin_Model_Entity_Uom::listConfig(),
		]);
	}

	protected function getCreateData(): array
	{
		return [
			'title' => $this->view->translate('ADMIN_NEW_UOM'),
		];
	}

	protected function afterCreate(int $id, array $data): void
	{
		$db = $this->getDb();
		if (!$db->getDefault()) $db->setDefault($id);
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		$db = $this->getDb();
		// Keep the target and tenant fixed; UOMs have no language column.
		unset($values['id'], $values['clientid'], $values['language']);

		if (array_key_exists('default', $values)) {
			$current = $db->getByIdForUpdate((int)$row['id']);
			if (!$current) throw new RuntimeException('Record not found');
			$values['default'] = (int)(!empty($values['default']) || !empty($current['default']));
		}

		return $values;
	}

	protected function afterEditSave(int $id, array $values, array $oldRow): void
	{
		if (!empty($values['default'])) $this->getDb()->setDefault($id);
	}
}
