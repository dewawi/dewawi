<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract{

	public function Attributes() {
		$data = false;
		if(count($this->view->attributesByGroup)) $data = true;
		if(!$data) { ?>
			<div id="messages"><ul><li><?php echo $this->view->translate('Es sind noch keine Eigenschaften vorhanden.') ?></li></ul></div>
		<?php } else { ?>
			<?php foreach($this->view->attributesByGroup as $attributesByGroup) : ?>
				<h4><?php echo $attributesByGroup['title']; ?></h4>
				<p><?php echo $attributesByGroup['description']; ?></p>
				<table id="data">
					<thead>
						<tr>
							<th id="id"><?php echo $this->view->translate('ITEMS_ATTRIBUTE_ID') ?></th>
							<th id="title"><?php echo $this->view->translate('ITEMS_ATTRIBUTE_TITLE') ?></th>
							<th id="value"><?php echo $this->view->translate('ITEMS_ATTRIBUTE_VALUE') ?></th>
							<th class="buttons"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($attributesByGroup['attributes'] as $attribute) : ?>
						<tr>
							<td id="id">
								<input class="id" type="hidden" value="<?php echo $attribute->id ?>" name="id"/>
								<input class="controller" type="hidden" value="attribute" name="controller"/>
								<input class="module" type="hidden" value="items" name="module"/>
								<?php echo $this->view->escape($attribute['id']); ?>
							</td>
							<td id="title">
								<?php echo $this->view->escape($attribute['title']);?>
							</td>
							<td id="value">
								<?php echo $this->view->escape($attribute['value']);?>
							</td>
							<td class="buttons">
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach;?>
		<?php }
	}
}
