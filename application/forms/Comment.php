<?php

class Application_Form_Comment extends DEEC_Form
{
	public function __construct()
	{
		$this->addElement([
			'name' => 'comment',
			'type' => 'text',
			'format' => ['type' => 'string'],
			'attribs'=> [
				'cols' => 100,
				'rows' => 2,
			],
		]);
	}
}
