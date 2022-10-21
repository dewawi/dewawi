<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Attributes extends Zend_View_Helper_Abstract{

	public function Attributes() {
		$i = 1;
		$data = false;
		if($length = count($this->view->attributesByGroup)) $data = true;
		--$length;
		//print_r($this->view->attributesByGroup);
		if(!$data) { ?>
			<div id="messages"><ul><li><?php echo $this->view->translate('Es sind noch keine Eigenschaften vorhanden.') ?></li></ul></div>
		<?php } else { ?>
			<?php foreach($this->view->attributesByGroup as $attributesByGroup) : ?>
				<h4><?php echo $attributesByGroup['title']; ?></h4>
				<p><?php echo $attributesByGroup['description']; ?></p>
				<?php if(isset($attributesByGroup['id'])) : ?>
				<div class="sub-group">
					<input type="hidden" name="id" value="<?php echo $attributesByGroup['id']; ?>" id="id">
					<dd id="attributegroup<?php echo $attributesByGroup['id']; ?>-element">
						<input type="text" name="title" id="attributegroup<?php echo $attributesByGroup['id']; ?>" value="<?php echo $attributesByGroup['title']; ?>" size="30" data-id="<?php echo $attributesByGroup['id']; ?>" data-ordering="<?php echo $attributesByGroup['ordering']; ?>" data-controller="attributegroup">
					</dd>
					<dd id="attributegroup<?php echo $attributesByGroup['id']; ?>-element">
						<input type="text" name="description" id="attributegroup<?php echo $attributesByGroup['id']; ?>" value="<?php echo $attributesByGroup['description']; ?>" size="30" data-id="<?php echo $attributesByGroup['id']; ?>" data-ordering="<?php echo $attributesByGroup['ordering']; ?>" data-controller="attributegroup">
					</dd>
					<button type="button" class="delete nolabel" onclick="del(<?php echo $attributesByGroup['id']; ?>, deleteConfirm, 'attributegroup');"></button>
					<?php if($i>1 && $i<=$length) : ?>
						<button name="sortup" id="sortup" type="button" class="up nolabel" data-id="3404156" data-ordering="1" data-controller="attributegroup"></button>
					<?php endif; ?>
					<?php if($i<$length) : ?>
						<button name="sortdown" id="sortdown" type="button" class="down nolabel" data-id="3404156" data-ordering="1" data-controller="attributegroup"></button>
					<?php endif; ?>
				</div>
				<?php endif;?>
				<?php echo $this->view->MultiForm('items', 'attribute', $attributesByGroup['attributes'], array('title', 'value'), '', $attributesByGroup['id']); ?>
				<?php ++$i; ?>
			<?php endforeach;?>
		<?php }
	}
}
