<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php if($this->view->controller != 'index') : ?>
			<?php echo $this->view->toolbar->copy; ?>
			<?php echo $this->view->toolbar->delete; ?>
			<?php if($this->view->controller != 'client') : ?>
				<?php echo $this->view->toolbar->clientid; ?>
				<?php //echo $this->view->toolbar->language; ?>
			<?php endif; ?>
			<?php if($this->view->controller == 'category') : ?>
				<?php echo $this->view->toolbar->type; ?>
			<?php endif; ?>
		<?php endif;
	}
}
