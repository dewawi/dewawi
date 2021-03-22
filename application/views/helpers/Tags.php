<?php
/**
* Class inserts neccery code for initialize file manager TreeMenu
*/
class Zend_View_Helper_Tags extends Zend_View_Helper_Abstract{

	public function Tags($tags, $module, $controller) { ?>
		<h3>Tags</h3>
		<?php foreach($tags as $id => $title) { ?>
			<a href="<?php echo $this->view->url(array('module'=>$module, 'controller'=>$controller, 'action'=>'index', 'tagid'=>$id), null, TRUE); ?>"><?php echo $this->view->translate($title) ?></a>
		<?php }
	}
}
