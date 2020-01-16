<?php
/**
* Class inserts neccery code for Messages	
*/
class Zend_View_Helper_ActiveFilter extends Zend_View_Helper_Abstract{

	public function ActiveFilter() {
		$active = false;
		$filters = array();
		foreach($this->view->toolbar as $element) {
			$default = $element->getAttrib('default');
			if($element->getAttrib('default') != $element->getValue()) {
				$filters[$element->getName()] = $element->getValue();
			}
		}

		$options = $this->view->options;
		if(count($filters)) : ?>
			<div id="activefilters">
				<span><?php echo $this->view->translate('TOOLBAR_SELECTED_FILTER').':'; ?></span>
				<ul>
					<?php if(isset($filters['keyword'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_KEYWORD').':'; ?></b>
							<?php echo $filters['keyword']; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'keyword'); ?>
						</li>
					<?php endif; ?>
					<?php if(isset($filters['catid'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_CATEGORY').':'; ?></b>
							<?php echo $options['categories'][$filters['catid']]['title']; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'catid'); ?>
						</li>
					<?php endif; ?>
					<?php if(isset($filters['country'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_COUNTRY').':'; ?></b>
							<?php echo $options['countries'][$filters['country']]; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'country'); ?>
						</li>
					<?php endif; ?>
					<?php if(isset($filters['daterange'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_DATE_RANGE').':'; ?></b>
							<?php if($filters['daterange'] == 'custom') : ?>
								<?php echo $filters['from'].' - '.$filters['to']; ?>
							<?php else : ?>
								<?php echo $this->view->translate($options['daterange'][$filters['daterange']]); ?>
							<?php endif; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'daterange'); ?>
						</li>
					<?php endif; ?>
					<?php if(isset($filters['states']) && count($filters['states'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_STATE').':'; ?></b>
							<?php foreach($filters['states'] as $state) : ?>
								<?php echo $this->view->translate($options['states'][$state]); ?>
							<?php endforeach; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'states'); ?>
						</li>
					<?php endif; ?>
					<?php if(isset($filters['paymentstatus']) && count($filters['paymentstatus'])) : ?>
						<li>
							<b><?php echo $this->view->translate('TOOLBAR_STATE').':'; ?></b>
							<?php foreach($filters['paymentstatus'] as $paymentstatus) : ?>
								<?php echo $this->view->translate($options['paymentstatus'][$paymentstatus]); ?>
							<?php endforeach; ?>
							<?php echo $this->view->Button('clear', '', '', '', '', 'paymentstatus'); ?>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endif;
	}
}
