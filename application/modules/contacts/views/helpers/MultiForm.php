<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_MultiForm extends Zend_View_Helper_Abstract{

	public function MultiForm($type, $element) {
		$className = 'Contacts_Form_'.ucfirst($type);
		$form = new $className;
		$form->$type->setAttrib('id', $type.$element['id']);
		$form->$type->setAttrib('data-id', $element['id']);
		$form->$type->setAttrib('data-ordering', $element['ordering']);
		$form->$type->setAttrib('data-controller', $type);
		$form->$type->setValue($element[$type]);
		?>
		<div id="<?php echo $type.$element['id']; ?>">
			<?php echo $form->getElement($type); ?>
			<?php if($type == 'email') : ?>
                <?php if($element[$type]) : ?>
		            <?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$element[$type]."'", '', '', ''); ?>
			    <?php else : ?>
		            <?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$element[$type]."'", '', '', 'display:none'); ?>
			    <?php endif; ?>
			<?php endif; ?>
			<?php if(isset($element['type'])) :
				$form->type->setAttrib('id', $type.'type'.$element['id']);
				$form->type->setAttrib('data-id', $element['id']);
				$form->type->setAttrib('data-ordering', $element['ordering']);
				$form->type->setAttrib('data-controller', $type);
				$form->type->setValue($element['type']); ?>
				<?php echo $form->getElement('type'); ?>
			<?php endif; ?>
		<?php echo $this->view->Button('delete', 'del('.$element['id'].', deleteConfirm, \''.$type.'\');', '', '', ''); ?>
		</div>
	<?php }
}
