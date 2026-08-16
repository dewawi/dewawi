<?php

class Zend_View_Helper_Options extends Zend_View_Helper_Abstract
{
	public function Options(): string
	{
		return $this->view->partial(
			'item/options.phtml',
			[
				'options' => $this->view->options,
			]
		);
	}
}
