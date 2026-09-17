<?php

class Zend_View_Helper_RenderPageBlocks extends Zend_View_Helper_Abstract
{
	public function RenderPageBlocks(array $blocks): string
	{
		$html = '';

		foreach ($blocks as $block) {
			$type = (string)($block['type'] ?? '');

			DEEC_Site_Block::getDefinition($type);

			$data = json_decode((string)($block['data'] ?? ''), true);

			if (!is_array($data)) {
				throw new RuntimeException('Invalid page block data: ' . (int)$block['id']);
			}

			$html .= $this->view->partial('page/blocks/' . $type . '.phtml', [
				'block' => $block,
				'data' => $data,
			]);
		}

		return $html;
	}
}
