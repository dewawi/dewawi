<?php

class Items_Form_ToolbarItemlist extends Items_Form_Toolbar
{
	public function __construct()
	{
		parent::__construct();

		foreach(['catid', 'country', 'tagid'] as $name) {
			unset($this->elements[$name]);
		}
	}
}
