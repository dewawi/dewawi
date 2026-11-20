<?php
class Zend_View_Helper_CheckoutForm extends Zend_View_Helper_Abstract
{
	public function CheckoutForm($subject = null) {
		$this->view->checkout->subject->setValue($subject);
		return $this->view->partial('checkout/_form.phtml', [
			'checkout' => $this->view->checkout, 'review' => $this->view->checkoutReview,
		]);
	}
}
