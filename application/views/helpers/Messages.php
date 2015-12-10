<?php
/**
* Class inserts neccery code for Messages	
*/
class Zend_View_Helper_Messages extends Zend_View_Helper_Abstract{

	public function Messages() {
		if($this->view->messages) : ?>
		<div id="messages">
			<ul>
				<?php foreach($this->view->messages as $message) : ?>
					<li><?php echo $this->view->translate($message); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif;
	}
}
