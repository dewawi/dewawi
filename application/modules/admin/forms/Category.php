<?php

class Admin_Form_Category extends Zend_Form
{
	public function init()
	{
		$this->setName('category');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['title'] = new Zend_Form_Element_Text('title');
		$form['title']->setLabel('ADMIN_TITLE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['slug'] = new Zend_Form_Element_Text('slug');
		$form['slug']->setLabel('ADMIN_SLUG')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['image'] = new Zend_Form_Element_Text('image');
		$form['image']->setLabel('ADMIN_CATEGORY_IMAGE')
			->addFilter('StripTags')
			->addFilter('StringTrim')
			->setAttrib('size', '12');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ADMIN_CATEGORY_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['shortdescription'] = new Zend_Form_Element_Textarea('shortdescription');
		$form['shortdescription']->setLabel('ADMIN_CATEGORY_SHORT_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['minidescription'] = new Zend_Form_Element_Textarea('minidescription');
		$form['minidescription']->setLabel('ADMIN_CATEGORY_MINI_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('CREDIT_NOTES_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('CREDIT_NOTES_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['parentid'] = new Zend_Form_Element_Select('parentid');
		$form['parentid']->setLabel('ADMIN_MAIN_CATEGORY')
			->addMultiOption('0', 'ADMIN_MAIN_CATEGORY')
			->setAttrib('default', '0');

		$form['type'] = new Zend_Form_Element_Select('type');
		$form['type']->setDecorators(array('ViewHelper'))
			->addMultiOption('contact', 'CONTACT')
			->addMultiOption('item', 'ITEM')
			->setAttrib('default', 'contact');

		$form['language'] = new Zend_Form_Element_Select('language');
		$form['language']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '');

		$form['clientid'] = new Zend_Form_Element_Select('clientid');
		$form['clientid']->setDecorators(array('ViewHelper'))
			->setAttrib('default', '0');

		$this->addElements($form);
	}
}
