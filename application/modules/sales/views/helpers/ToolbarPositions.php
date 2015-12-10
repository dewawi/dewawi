<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_ToolbarPositions extends Zend_View_Helper_Abstract{

	public function ToolbarPositions($class = null) { ?>
		<div class="toolbar positions<?php if($class) echo ' '.$class; ?>">
			<?php echo $this->view->toolbarPositions->add; ?>
			<?php echo $this->view->toolbarPositions->select; ?>
			<?php echo $this->view->toolbarPositions->copy; ?>
			<?php echo $this->view->toolbarPositions->delete; ?>
		</div>
	<?php }
}
