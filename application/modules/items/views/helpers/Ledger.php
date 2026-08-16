<?php

class Zend_View_Helper_Ledger extends Zend_View_Helper_Abstract
{
	public function Ledger(): string
	{
		return $this->view->partial(
			'item/ledger.phtml',
			[
				'ledgers' => $this->view->ledgers,
			]
		);
	}
}
