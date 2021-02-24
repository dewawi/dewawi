<?php

class Ebiztrader_Form_Listing extends Zend_Form
{
	public function init()
	{
		$this->setName('listing');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['ebiztraderuserid'] = new Zend_Form_Element_Text('ebiztraderuserid');
		$form['ebiztraderuserid']->setLabel('EBIZ_TRADER_USER_ID')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '20')
			->setAttrib('class', 'required');

		$form['itemid'] = new Zend_Form_Element_Text('itemid');
		$form['itemid']->setLabel('EBIZ_TRADER_ITEM_ID')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['category1'] = new Zend_Form_Element_Text('category1');
		$form['category1']->setLabel('EBIZ_TRADER_CATEGORY1')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['category2'] = new Zend_Form_Element_Text('category2');
		$form['category2']->setLabel('EBIZ_TRADER_CATEGORY2')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '20');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
