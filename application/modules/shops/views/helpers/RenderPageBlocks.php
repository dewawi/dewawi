<?php

class Zend_View_Helper_RenderPageBlocks extends Zend_View_Helper_Abstract
{
	public function RenderPageBlocks(array $blocks): string
	{
		$html = '';

		$pageblockDb = new Application_Model_DbTable_Pageblock();

		if (($this->view->siteContext ?? null) instanceof DEEC_Site_Context) {
			$pageblockDb->setClientId($this->view->siteContext->getClientId());
		}

		foreach ($blocks as $block) {
			$type = (string)($block['type'] ?? '');

			$definition = DEEC_Site_Block::getDefinition($type);

			$data = json_decode((string)($block['data'] ?? ''), true);

			if (!is_array($data)) {
				throw new RuntimeException('Invalid page block data: ' . (int)$block['id']);
			}

			$data = DEEC_Filter::applyAll(
				$data,
				DEEC_Site_Block::getFormatSchema($type),
				Zend_Registry::get('Zend_Locale')
			);

			$children = [];

			if (DEEC_Site_Block::getTypeOptions($type)) {
				$children = $pageblockDb->getBlocksByPageId(
					(int)$block['pageid'],
					true,
					(int)$block['id']
				);
			}

			$html .= $this->view->partial('page/blocks/' . $type . '.phtml', [
				'block' => $block,
				'data' => $data,
				'children' => $children,
				'siteContext' => $this->view->siteContext ?? null,
				'shop' => is_array($this->view->shop ?? null) ? $this->view->shop : [],
				'categories' => is_array($this->view->categories ?? null) ? $this->view->categories : [],
				'images' => is_array($this->view->images ?? null) ? $this->view->images : [],
				'contact' => $this->view->contact ?? null,
			]);
		}

		return $html;
	}
}
