<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php if($this->view->action == 'edit') : ?>
			<input class="id" type="hidden" value="<?php echo $this->view->id ?>" name="id"/>
			<?php //echo $this->view->toolbar->copy; ?>
			<?php //echo $this->view->toolbar->delete; ?>
		<?php endif;
	}
}
