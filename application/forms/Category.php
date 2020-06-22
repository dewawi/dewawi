<?php

class Application_Form_Category extends Zend_Form
{
    public function init()
    {
        $this->setName('category');

        $id = new Zend_Form_Element_Hidden('id');
        $id->addFilter('Int');

        $parentid = new Zend_Form_Element_Select('parentid');
        $parentid->setLabel('CATEGORIES_PARENT')
		        ->addMultiOption(0, 'CATEGORIES_MAIN_CATEGORY')
		        ->addFilter('Int');

        $title = new Zend_Form_Element_Text('title');
        $title->setLabel('CATEGORIES_TITLE')
               ->setRequired(true)
               ->addFilter('StripTags')
               ->addFilter('StringTrim')
               ->addValidator('NotEmpty');

        $type = new Zend_Form_Element_Hidden('type');
        $type->setRequired(true)
		        ->addValidator('NotEmpty');

        $ordering = new Zend_Form_Element_Hidden('ordering');
        $ordering->setLabel('CATEGORIES_ORDERING')
		        ->addFilter('Int');

        $this->addElements(array($id, $parentid, $title, $type, $ordering));
    }
}
