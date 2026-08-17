<?php

class Zend_View_Helper_Variants extends Zend_View_Helper_Abstract
{
	public function Variants(): string
	{
		return $this->view->partial(
			'item/variants.phtml',
			[
				'item' => $this->view->item,
				'variants' => $this->view->itemVariants,
				'variantOptions' => $this->view->itemVariantOptions,
				'availableOptions' => $this->view->itemVariantAvailableOptions,
			]
		);
	}
}
