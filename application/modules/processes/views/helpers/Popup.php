<?php
/**
 * Class inserts necessary code for Popup
 */
class Zend_View_Helper_Popup extends Zend_View_Helper_Abstract
{
	public function Popup()
	{
		$contactId = $this->view->form->getValue('contactid');
		$parentBase = $this->view->module . '|' . $this->view->controller;

		// Customer iframe URL
		if ($contactId) {
			$customerUrl = $this->view->url([
				'module' => 'contacts',
				'controller' => 'contact',
				'action' => 'select',
				'contactid' => $contactId,
				'parent' => $parentBase,
			]);
		} else {
			$customerUrl = $this->view->url([
				'module' => 'contacts',
				'controller' => 'contact',
				'action' => 'select',
				'parent' => $parentBase,
			]);
		}

		// Position iframe URL
		$positionUrl = $this->view->url([
			'module' => 'items',
			'controller' => 'item',
			'action' => 'select',
			'parent' => $parentBase . '|' . $this->view->form->getValue('id'),
		]);

		$html  = '<div id="popup">';

		$html .= '<div id="addCustomer" class="popup_block">';
		$html .= '<iframe src="' . $customerUrl . '" width="100%" height="100%"></iframe>';
		$html .= '</div>';

		$html .= '<div id="selectPosition" class="popup_block">';
		$html .= '<iframe src="' . $positionUrl . '" width="100%" height="100%"></iframe>';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}
}
