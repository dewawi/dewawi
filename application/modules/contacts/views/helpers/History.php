<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_History extends Zend_View_Helper_Abstract{

	public function History() {
		$history = (array)($this->view->history ?? []);
		$options = (array)($this->view->options ?? []);
		$contactId = (int)($this->view->id ?? 0);
		$toolbar = $this->view->toolbar ?? null;

		return $this->view->partial('partials/history.phtml', [
			'history' => $history,
			'options' => $options,
			'contactId' => $contactId,
			'id' => $contactId,
			'toolbar' => $toolbar,
		]);
	}
}
