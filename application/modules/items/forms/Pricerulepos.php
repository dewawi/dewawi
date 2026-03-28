<?php

class Items_Form_Pricerulepos extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'type' => 'hidden',
			'name' => 'id',
			'format' => ['type' => 'int'],
			'tab' => 'overview',
		]);

		$this->addElement([
			'name' => 'action',
			'type' => 'select',
			'label' => 'PRICE_RULES_ACTION',
			'options' => [
				'0' => 'PRICE_RULES_NONE',
				'bypercent' => 'ITEMS_PRICE_RULE_BY_PERCENT',
				'byfixed' => 'ITEMS_PRICE_RULE_BY_FIXED',
				'topercent' => 'ITEMS_PRICE_RULE_TO_PERCENT',
				'tofixed' => 'ITEMS_PRICE_RULE_TO_FIXED',
			],
			'format' => ['type' => 'string'],
		]);

		$this->addElement([
			'type' => 'text',
			'name' => 'amount',
			'label' => 'PRICE_RULES_AMOUNT',
			'format' => ['type' => 'string'],
		]);
	}
}
