<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_MultiForm extends Zend_View_Helper_Abstract{

	public function MultiForm($module, $controller, $data, $elements = null, $label = null, $childs = null) {
		if($module == 'default') {
			$className = 'Application_Form_'.ucfirst($controller);
		} else {
			$className = ucfirst($module).'_Form_'.ucfirst($controller);
		}
		$form = new $className; ?>
		<?php if($label) : ?>
			<dt id="<?php echo $controller; ?>-label">
				<label for="<?php echo $controller; ?>"><?php echo $this->view->translate($label) ?></label>
			</dt>
		<?php endif; ?>
		<?php // Check if data array is multidimensional ?>
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
					<?php $form->$element->setAttrib('data-module', $module); ?>
					<?php $form->$element->setValue($child[$element]); ?>
					<?php echo $form->getElement($element); ?>
					<?php if($element == 'email') : ?>
						<?php if($child['email']) : ?>
							<?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$child['email']."'", '', '', ''); ?>
						<?php else : ?>
							<?php echo $this->view->Button('email', 'location.href='."'".'mailto:'.$child['email']."'", '', '', 'display:none'); ?>
						<?php endif; ?>
					<?php endif; ?>
					<?php echo $this->view->Button('delete', 'trash('.$child['id'].', deleteConfirm, \''.$controller.'\', \''.$module.'\');', '', '', ''); ?>
				<?php //Handle multidimensional elements ?>
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
										<?php $form->$field->setAttrib('data-module', $module); ?>
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
								<?php $form->$element->setAttrib('data-module', $module); ?>
								<?php $form->$element->setValue($child[$element]); ?>
								<?php echo $form->getElement($element); ?>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if($controller == 'comment') : ?>
							<dt><?php echo date("d.m.Y H:i:s", strtotime($child['created'])); ?></dt>
						<?php endif; ?>
						<?php if(($controller == 'address') && isset($element['label'])) : ?>
							<dt id="<?php echo $controller; ?>-label"></dt>
						<?php endif; ?>
						<?php echo $this->view->Button('delete', 'trash('.$child['id'].', deleteConfirm, \''.$controller.'\', \''.$module.'\');', '', '', ''); ?>
					</div>
				<?php elseif(is_array($elements)) : ?>
					<div class="sub-group">
						<?php foreach($elements as $element) : ?>
							<?php $form->$element->setAttrib('id', $controller.$child['id']); ?>
							<?php $form->$element->setAttrib('data-id', $child['id']); ?>
							<?php $form->$element->setAttrib('data-ordering', $child['ordering']); ?>
							<?php $form->$element->setAttrib('data-controller', $controller); ?>
							<?php $form->$element->setAttrib('data-module', $module); ?>
							<?php $form->$element->setValue($child[$element]); ?>
							<?php echo $form->getElement($element); ?>
						<?php endforeach; ?>
						<?php echo $this->view->Button('delete', 'trash('.$child['id'].', deleteConfirm, \''.$controller.'\', \''.$module.'\');', '', '', ''); ?>
					</div>
				<?php endif; ?>
				<?php if($childs) : ?>
					<div id="email" class="multiformContainer" data-parentid="<?php echo $child['id']; ?>" data-controller="contactperson">
						<?php echo $this->view->MultiForm('contacts', 'email', $childs[$child['id']], '', 'CONTACTS_EMAIL'); ?>
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
		<?php if(isset($label) || ($elements && is_array($elements[0]))) : ?>
			<?php if(!$childs || ($this->view->action != 'add')) : ?>
				<?php $params = array(); ?>
				<?php $params['module'] = $module; ?>
				<?php $params['controller'] = $controller; ?>
				<?php echo $this->view->Button('addMulti add', $onclick = '', $title = '', $value = '', $style = '', $rel = '', $id = '', $params); ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php if(!isset($data['id'])) : ?>
			</div>
		<?php endif; ?>
<?php }
}
