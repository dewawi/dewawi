<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_ContactForm extends Zend_View_Helper_Abstract
{
	public function ContactForm($subject = null) {
		$this->view->contact->subject->setValue($subject);
		$html = $this->view->contact;
		$html .= '<p class="mt-3">(*) Diese Felder müssen ausgefüllt werden, um Ihre Anfrage schnellstmöglich zu bearbeiten.</p>';
		$html .= '<p class="text-muted">Ihre Daten werden nicht an Dritte weitergegeben.</p>';
		return $html;
	}
}
