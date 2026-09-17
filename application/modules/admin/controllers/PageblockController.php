<?php

class Admin_PageblockController extends DEEC_Controller_AdminAction
{
	protected string $blockType = '';

	protected function buildIndexView(): void
	{
		$this->_helper->redirector->gotoSimple('index', 'page');
	}

	protected function getDbTableClass(): string
	{
		return Application_Model_DbTable_Pageblock::class;
	}

	protected function getCreateData(): array
	{
		$pageId = (int)$this->_getParam('pageid', 0);
		$parentId = (int)$this->_getParam('parentid', 0);
		$type = trim((string)$this->_getParam('type', ''));

		$pageDb = new Admin_Model_DbTable_Page();

		if (!$pageDb->getById($pageId)) {
			throw new RuntimeException('Page not found');
		}

		DEEC_Site_Block::getDefinition($type);

		$db = new Application_Model_DbTable_Pageblock();

		if ($parentId > 0) {
			$parent = $db->getById($parentId);

			if (!$parent || (int)$parent['pageid'] !== $pageId) {
				throw new RuntimeException('Invalid parent page block');
			}
		}

		return [
			'parentid' => $parentId,
			'pageid' => $pageId,
			'type' => $type,
			'data' => '{}',
			'ordering' => $db->getNextOrdering([
				'pageid' => $pageId,
				'parentid' => $parentId,
			]),
			'activated' => 0,
		];
	}

	protected function prepareEditRow(array $row): array
	{
		$this->blockType = (string)$row['type'];

		$fields = DEEC_Site_Block::getFields($this->blockType);
		$data = $this->decodeBlockData($row['data'] ?? null);

		foreach ($fields as $field) {
			$name = (string)$field['name'];

			if (array_key_exists($name, $data)) {
				$row[$name] = $data[$name];
			}
		}

		return $row;
	}

	protected function getEditForm(): array
	{
		$form = new Admin_Form_Pageblock($this->blockType);
		$options = $this->_helper->Options->applyFormOptions($form);

		return [
			'form' => $form,
			'options' => $options,
		];
	}

	protected function beforeEditSave(array $values, array $row): array
	{
		unset($values['type']);

		$data = $this->decodeBlockData($row['data'] ?? null);
		$changed = false;

		foreach (DEEC_Site_Block::getFields($this->blockType) as $field) {
			$name = (string)$field['name'];

			if (!array_key_exists($name, $values)) {
				continue;
			}

			$data[$name] = $values[$name];
			unset($values[$name]);
			$changed = true;
		}

		if ($changed) {
			$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if ($json === false) {
				throw new RuntimeException('Could not encode page block data');
			}

			$values['data'] = $json;
		}

		return $values;
	}

	private function decodeBlockData($data): array
	{
		if ($data === null || $data === '') {
			return [];
		}

		$decoded = json_decode((string)$data, true);

		if (!is_array($decoded)) {
			throw new RuntimeException('Invalid page block data');
		}

		return $decoded;
	}
}
