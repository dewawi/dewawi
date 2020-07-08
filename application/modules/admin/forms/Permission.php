<?php

class Admin_Form_Permission extends Zend_Form
{
	public function init()
	{
		$this->setName('permission');

		$form = array();

		$form['id'] = new Zend_Form_Element_Hidden('id');
		$form['id']->addFilter('Int')->removeDecorator('Label');

		$form['add'] = new Zend_Form_Element_Checkbox('add');
		$form['add']->addFilter('Int')->setLabel('ADMIN_ADD');

		$form['edit'] = new Zend_Form_Element_Checkbox('edit');
		$form['edit']->addFilter('Int')->setLabel('ADMIN_EDIT');

		$form['view'] = new Zend_Form_Element_Checkbox('view');
		$form['view']->addFilter('Int')->setLabel('ADMIN_VIEW');

		$form['delete'] = new Zend_Form_Element_Checkbox('delete');
		$form['delete']->addFilter('Int')->setLabel('ADMIN_DELETE');

		$this->addElements($form);
	}
}
