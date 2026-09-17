<?php

class Admin_Form_Pageblock extends DEEC_Form
{
	public function __construct(string $type)
	{
		DEEC_Site_Block::getDefinition($type);

		$this->addElement([
			'name' => 'id',
			'type' => 'hidden',
			'format' => ['type' => 'int'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'type',
			'type' => 'hidden',
			'default' => $type,
			'format' => ['type' => 'string'],
			'wrap' => false,
		]);

		$this->addElement([
			'name' => 'activated',
			'type' => 'checkbox',
			'label' => 'ADMIN_ACTIVATED',
			'default' => 0,
			'format' => ['type' => 'int'],
			'col' => 12,
		]);

		foreach (DEEC_Site_Block::getFields($type) as $field) {
			$this->addElement($field);
		}
	}
}
