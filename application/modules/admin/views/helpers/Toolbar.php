<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php if($this->view->action == 'edit') : ?>
			<input class="id" type="hidden" value="<?php echo $this->view->id ?>" name="id"/>
			<?php echo $this->view->toolbar->copy; ?>
			<?php echo $this->view->toolbar->delete; ?>
		<?php elseif($this->view->controller != 'index') : ?>
			<?php if($this->view->user['admin']) : ?>
				<?php echo $this->view->toolbar->copy; ?>
				<?php echo $this->view->toolbar->delete; ?>
			<?php endif; ?>
			<?php /*if($this->view->controller != 'client') : ?>
				<?php echo $this->view->toolbar->clientid; ?>
				<?php //echo $this->view->toolbar->language; ?>
			<?php endif;*/ ?>
			<?php if($this->view->controller == 'category') : ?>
				<?php echo $this->view->toolbar->type; ?>
			<?php endif; ?>
		<?php endif;
	}
}
