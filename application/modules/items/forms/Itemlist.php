<?php

class Items_Form_Itemlist extends Zend_Form
{
	public function init()
	{
		$this->setName('item');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('ITEMS_TITLE')
			->setRequired(true)
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->addValidator('NotEmpty')
			->setAttrib('size', '40')
			->setAttrib('class', 'required');

		$form['subtitle'] = new Zend_Form_Element_Text('subtitle');
		$form['subtitle']->setLabel('ITEMS_SUBTITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '40');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->setLabel('ITEMS_TYPE')
			->addMultiOption("stockItem", 'ITEMS_STOCK_ITEM')
			->addMultiOption("deliveryItem", 'ITEMS_DELIVERY_ITEM')
			->addMultiOption("service", 'ITEMS_SERVICE')
			->setRequired(true)
			->addValidator('NotEmpty')
			->setAttrib('class', 'required');

		$form['image'] = new Zend_Form_Element_Text('image');
		$form['image']->setLabel('ITEMS_IMAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ITEMS_DESCRIPTION')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '40')
			->setAttrib('rows', '20');

		$form['info'] = new Zend_Form_Element_Textarea('info');
		$form['info']->setLabel('ITEMS_INFO_INTERNAL')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '10');

		$form['params'] = new Zend_Form_Element_Textarea('params');
		$form['params']->setLabel('ITEMS_PARAMETERS')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '10');

		$form['modified'] = new Zend_Form_Element_Text('modified');
		$form['created'] = new Zend_Form_Element_Text('created');

		$form['submit'] = new Zend_Form_Element_Submit('submit');
		$form['submit']->setAttrib('id', 'submitbutton')
				->setLabel('ITEMS_SAVE');

		$this->addElements($form);
	}
}
