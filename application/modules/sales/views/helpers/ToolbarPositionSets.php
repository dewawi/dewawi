<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_ToolbarPositionSets extends Zend_View_Helper_Abstract{

	public function ToolbarPositionSets($count = 0, $i = 0, $class = null) { ?>
		<div class="toolbar positionsets<?php if($class) echo ' '.$class; ?>" style="float:right;">
			<?php echo $this->view->toolbarPositions->addset; ?>
			<?php echo $this->view->toolbarPositions->copyset; ?>
			<?php echo $this->view->toolbarPositions->deleteset; ?>
			<?php if($i>1) : ?>
				<?php echo $this->view->toolbarPositions->sortup; ?>
			<?php endif; ?>
			<?php if($i<$count) : ?>
				<?php echo $this->view->toolbarPositions->sortdown; ?>
			<?php endif; ?>
		</div>
	<?php }
}
