<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php echo $this->view->toolbar->catid; ?>
		<?php echo $this->view->toolbar->country; ?>
	<?php }
}
