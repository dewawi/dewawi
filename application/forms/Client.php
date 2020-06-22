<?php

class Application_Form_Client extends Zend_Form
{
    public function init()
    {
        $this->setName('client');

        $clientid = new Zend_Form_Element_Select('clientid');
        $clientid->removeDecorator('label')
            ->addFilter('Int');

        $this->addElements(array($clientid));
    }
}
