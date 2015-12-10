<?php
/**
* Class inserts neccery code for Menu
*/
class Zend_View_Helper_MainMenu extends Zend_View_Helper_Abstract{

	public function MainMenu() { ?>
		<ul id="mainmenu">
			<li<?php if($this->view->module == 'default' && $this->view->controller == 'index') echo ' class="active"'; ?>>
				<a href="<?php echo $this->view->url(array('module'=>'default', 'controller'=>'index', 'action'=>'index'), null, TRUE); ?>"><?php echo $this->view->translate('CONTROL_PANEL') ?></a>
			</li>
			<?php if(isset($this->view->mainmenu)) : ?>
				<?php foreach($this->view->mainmenu as $mainmenu) : ?>
					<li<?php if($this->view->module == $mainmenu['module']) echo ' class="active"'; ?>>
						<?php if(isset($mainmenu['controller']) && isset($mainmenu['action'])) : ?>
							<a href="<?php echo $this->view->url(array('module'=>$mainmenu['module'], 'controller'=>$mainmenu['controller'], 'action'=>$mainmenu['action']), null, TRUE); ?>"><?php echo $this->view->translate($mainmenu['title']) ?></a>
						<?php else : ?>
							<a href="#"><?php echo $this->view->translate($mainmenu['title']) ?></a>
						<?php endif; ?>
						<?php //Sub menu ?>
						<?php if(isset($mainmenu['childs'])) : ?>
							<ul class="sub_menu">
							<?php foreach($mainmenu['childs'] as $child) : ?>
								<li<?php if($this->view->controller == $child['controller']) echo ' class="active"'; ?>>
								<?php if(isset($child['controller']) && isset($child['action'])) : ?>
									<a href="<?php echo $this->view->url(array('module'=>$child['module'], 'controller'=>$child['controller'], 'action'=>$child['action']), null, TRUE); ?>"><?php echo $this->view->translate($child['title']) ?></a>
								<?php else : ?>
									<a href="#"><?php echo $this->view->translate($child['title']) ?></a>
								<?php endif; ?>
								</li>
							<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
	<?php }
}
