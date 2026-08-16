<?php

class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract
{
	public function Attributes(): string
	{
		return $this->view->partial(
			'item/attributes.phtml',
			[
				'attributes' => $this->view->attributes,
			]
		);
	}
}
