<?php

class Shops_Form_Category extends Zend_Form
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

		$form['header'] = new Zend_Form_Element_Textarea('header');
		$form['header']->setLabel('CREDIT_NOTES_HEADER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','div','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['footer'] = new Zend_Form_Element_Textarea('footer');
		$form['footer']->setLabel('CREDIT_NOTES_FOOTER')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','div','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['description'] = new Zend_Form_Element_Textarea('description');
		$form['description']->setLabel('ADMIN_CATEGORY_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','div','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['shortdescription'] = new Zend_Form_Element_Textarea('shortdescription');
		$form['shortdescription']->setLabel('ADMIN_CATEGORY_SHORT_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','div','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$form['minidescription'] = new Zend_Form_Element_Textarea('minidescription');
		$form['minidescription']->setLabel('ADMIN_CATEGORY_MINI_DESCRIPTION')
			->addFilter('StripTags', array(array(
				'allowTags' => array('a','p','span','div','img','br','strong','em','ul','ol','li','h1','h2','h3','h4','h5','h6'),
				'allowAttribs' => array('src','style','class','title','href')
			)))
			->addFilter('StringTrim')
			->setAttrib('cols', '75')
			->setAttrib('rows', '18')
			->setAttrib('class', 'editor');

		$this->addElements($form);
	}
}
