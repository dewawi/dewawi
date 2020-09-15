<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_MultiForm extends Zend_View_Helper_Abstract{

	public function MultiForm($controller, $data, $elements = null, $label = null) {
		$className = 'Contacts_Form_'.ucfirst($controller);
		$form = new $className; ?>
		<?php if($label) : ?>
			<dt id="<?php echo $controller; ?>-label">
				<label for="<?php echo $controller; ?>"><?php echo $this->view->translate($label) ?></label>
			</dt>
		<?php endif; ?>
		<?php if(!isset($data['id'])) : ?>
			<div id="<?php echo $controller; ?>" class="multiform">
			<?php $dataset = $data; ?>
		<?php else : ?>
			<?php $dataset = array($data); ?>
		<?php endif; ?>
		<?php foreach($dataset as $child) : ?>
			<div id="<?php echo $controller.$child['id']; ?>">
				<?php if(!$elements || !is_array($elements)) : ?>
					<?php $element = $controller; ?>
					<?php $form->$element->setAttrib('id', $controller.$child['id']); ?>
					<?php $form->$element->setAttrib('data-id', $child['id']); ?>
					<?php $form->$element->setAttrib('data-ordering', $child['ordering']); ?>
					<?php $form->$element->setAttrib('data-controller', $controller); ?>
					<?php $form->$element->setValue($child[$element]); ?>
					<?php echo $form->getElement($element); ?>
					<?php if($element == 'email') : ?>
						<?php if($child['email']) : ?>
							<?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$child['email']."'", '', '', ''); ?>
						<?php else : ?>
							<?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$child['email']."'", '', '', 'display:none'); ?>
						<?php endif; ?>
					<?php endif; ?>
					<?php echo $this->view->Button('delete', 'del('.$child['id'].', deleteConfirm, \''.$controller.'\');', '', '', ''); ?>
				<?php elseif(is_array($elements[0])) : ?>
					<div class="field-group">
						<?php foreach($elements as $element) : ?>
							<?php if(isset($element['label'])) : ?>
								<dt id="<?php echo $controller; ?>-label">
									<label for="<?php echo $controller; ?>"><?php echo $this->view->translate($element['label']) ?></label>
								</dt>
							<?php endif; ?>
							<?php if(isset($element['fields']) && is_array($element['fields'])) : ?>
								<div class="sub-group">
									<?php foreach($element['fields'] as $field) : ?>
										<?php $form->$field->setAttrib('id', $controller.$child['id']); ?>
										<?php $form->$field->setAttrib('data-id', $child['id']); ?>
										<?php $form->$field->setAttrib('data-ordering', $child['ordering']); ?>
										<?php $form->$field->setAttrib('data-controller', $controller); ?>
										<?php $form->$field->setValue($child[$field]); ?>
										<?php if($field == 'country') $form->country->addMultiOptions($this->view->options['countries']); ?>
										<?php echo $form->getElement($field); ?>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<?php $element = $element['field']; ?>
								<?php $form->$element->setAttrib('id', $controller.$child['id']); ?>
								<?php $form->$element->setAttrib('data-id', $child['id']); ?>
								<?php $form->$element->setAttrib('data-ordering', $child['ordering']); ?>
								<?php $form->$element->setAttrib('data-controller', $controller); ?>
								<?php $form->$element->setValue($child[$element]); ?>
								<?php echo $form->getElement($element); ?>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if(($controller == 'address') && isset($element['label'])) : ?>
							<dt id="<?php echo $controller; ?>-label"></dt>
						<?php endif; ?>
						<?php echo $this->view->Button('delete', 'del('.$child['id'].', deleteConfirm, \''.$controller.'\');', '', '', ''); ?>
					</div>
				<?php elseif(is_array($elements)) : ?>
					<div class="sub-group">
						<?php foreach($elements as $element) : ?>
							<?php $form->$element->setAttrib('id', $controller.$child['id']); ?>
							<?php $form->$element->setAttrib('data-id', $child['id']); ?>
							<?php $form->$element->setAttrib('data-ordering', $child['ordering']); ?>
							<?php $form->$element->setAttrib('data-controller', $controller); ?>
							<?php $form->$element->setValue($child[$element]); ?>
							<?php echo $form->getElement($element); ?>
						<?php endforeach; ?>
						<?php echo $this->view->Button('delete', 'del('.$child['id'].', deleteConfirm, \''.$controller.'\');', '', '', ''); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if(($controller == 'address')) : ?>
				<hr>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if(($controller == 'address') && isset($element['label'])) : ?>
			<dt id="<?php echo $controller; ?>-label"></dt>
		<?php endif; ?>
		<?php if((isset($label) || is_array($elements[0])) && ($this->view->action != 'add')) : ?>
			<?php echo $this->view->Button('add', 'add({\'controller\':\''.$controller.'\',\'action\':\'add\',\'type\':\''.$controller.'\'});', '', '', ''); ?>
		<?php endif; ?>
		<?php if(!isset($data['id'])) : ?>
			</div>
		<?php endif; ?>
<?php }
}
