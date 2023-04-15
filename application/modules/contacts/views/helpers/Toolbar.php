<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php if($this->view->controller == 'contact') : ?>
			<?php if($this->view->action == 'edit') : ?>
				<input class="id" type="hidden" value="<?php echo $this->view->id ?>" name="id"/>
				<?php echo $this->view->toolbar->copy; ?>
				<?php echo $this->view->toolbar->delete; ?>
			<?php elseif($this->view->action == 'select') : ?>
				<span><?php echo $this->view->translate('TOOLBAR_SEARCH') ?></span>
				<?php echo $this->view->toolbar->keyword; ?>
				<?php echo $this->view->toolbar->clear; ?>
				<?php echo $this->view->toolbar->limit; ?>
				<?php echo $this->view->toolbar->catid; ?>
				<input id="type" type="hidden" name="type" value="select"/>
			<?php elseif($this->view->action == 'index') : ?>
				<?php echo $this->view->toolbar->add; ?>
				<?php echo $this->view->toolbar->edit; ?>
				<?php echo $this->view->toolbar->copy; ?>
				<?php echo $this->view->toolbar->delete; ?>
				<?php echo $this->view->toolbar->keyword; ?>
				<?php echo $this->view->toolbar->clear; ?>
				<?php echo $this->view->toolbar->reset; ?>
				<?php echo $this->view->toolbar->order; ?>
				<?php echo $this->view->toolbar->sort; ?>
				<?php echo $this->view->toolbar->country; ?>
				<?php echo $this->view->toolbar->limit; ?>
				<?php echo $this->view->toolbar->catid; ?>
				<?php echo $this->view->toolbar->tagid; ?>
			<?php endif; ?>
		<?php elseif($this->view->controller == 'email') : ?>
			<?php echo $this->view->toolbar->keyword; ?>
			<?php echo $this->view->toolbar->clear; ?>
			<?php echo $this->view->toolbar->reset; ?>
			<?php echo $this->view->toolbar->controller; ?>
			<?php echo $this->view->toolbar->limit; ?>
		<?php elseif(($this->view->controller == 'download') || ($this->view->controller == 'downloadset')) : ?>
			<?php if($this->view->action == 'add') : ?>
				<?php echo $this->view->toolbar->save; ?>
			<?php elseif($this->view->action == 'index') : ?>
				<?php echo $this->view->toolbar->add; ?>
				<?php echo $this->view->toolbar->addset; ?>
			<?php endif; ?>
		<?php endif;
	}
}
