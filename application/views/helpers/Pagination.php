<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Pagination extends Zend_View_Helper_Abstract{

	public function Pagination() { ?>
		<div id="pagination" class="toolbar">
			<span>Angezeigt: <?php echo $this->view->pagination['count']; ?></span>
			<span>(<?php echo $this->view->pagination['start']; ?>:<?php echo $this->view->pagination['end']; ?>)</span>
			<span>|</span>
			<span>Insgesamt: <?php echo $this->view->pagination['records']; ?></span>
			<span>|</span>
			<span>Seite:<?php echo $this->view->toolbar->page; ?></span>
		</div>
		<?php
	}
}
