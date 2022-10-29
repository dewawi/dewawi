<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Popup extends Zend_View_Helper_Abstract
{
	public function Popup() { ?>
		<div id="popup">
			<div id="addCustomer" class="popup_block">
				<?php if($this->view->form->getValue('contactid')) : ?>
					<iframe src="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'select', 'contactid'=>$this->view->form->getValue('contactid'), 'parent'=>$this->view->module.'|'.$this->view->controller));?>" width="100%" height="100%"></iframe>
				<?php else : ?>
					<iframe src="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'select', 'parent'=>$this->view->module.'|'.$this->view->controller));?>" width="100%" height="100%"></iframe>
				<?php endif; ?>
			</div>

			<div id="selectPosition" class="popup_block">
				<iframe src="<?php echo $this->view->url(array('module'=>'items', 'controller'=>'item', 'action'=>'select', 'parent'=>$this->view->module.'|'.$this->view->controller.'|'.$this->view->form->getValue('id')));?>" width="100%" height="100%"></iframe>
			</div>
		</div>
		<?php
	}
}
