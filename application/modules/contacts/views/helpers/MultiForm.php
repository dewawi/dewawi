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
				<a class="mailto" href="mailto:<?php echo $element[$type]; ?>" <?php if(!$element[$type]) echo 'style="display:none"'; ?>>
					<img src="<?php echo $this->view->baseUrl(); ?>/images/email.png">
				</a>
			<?php endif; ?>
			<?php if(isset($element['type'])) :
				$form->type->setAttrib('id', $type.'type'.$element['id']);
				$form->type->setAttrib('data-id', $element['id']);
				$form->type->setAttrib('data-ordering', $element['ordering']);
				$form->type->setAttrib('data-controller', $type);
				$form->type->setValue($element['type']); ?>
				<?php echo $form->getElement('type'); ?>
			<?php endif; ?>
		<?php echo $this->view->Button('delete', 'del('.$element['id'].', deleteConfirm, \''.$type.'\');', '', '', 'float:left;'); ?>
		</div>
	<?php }
}
