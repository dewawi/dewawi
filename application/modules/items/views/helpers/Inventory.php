<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Inventory extends Zend_View_Helper_Abstract{

	public function Inventory() {
		$data = false;
		if(count($this->view->inventory)) $data = true;
		if(!$data) { ?>
			<div id="messages"><ul><li><?php echo $this->view->translate('Es sind noch keine Buchungen vorhanden.') ?></li></ul></div>
		<?php } else { ?>
			<h4>Alle Buchungen im Verlauf</h4>
			<table id="data">
				<thead>
					<tr>
						<th id="id"><?php echo $this->view->translate('ITEMS_INVENTORY_ID') ?></th>
						<th id="document"><?php echo $this->view->translate('ITEMS_INVENTORY_DOCUMENT') ?></th>
						<th id="comment"><?php echo $this->view->translate('ITEMS_INVENTORY_COMMENT') ?></th>
						<th id="quantity"><?php echo $this->view->translate('ITEMS_INVENTORY_QUANTITY') ?></th>
						<th id="type"><?php echo $this->view->translate('ITEMS_INVENTORY_TYPE') ?></th>
						<th id="contactid"><?php echo $this->view->translate('CONTACTS_CONTACT_ID') ?></th>
						<th id="date"><?php echo $this->view->translate('ITEMS_INVENTORY_DATE') ?></th>
						<th id="price"><?php echo $this->view->translate('ITEMS_INVENTORY_PRICE') ?></th>
						<th id="total"><?php echo $this->view->translate('ITEMS_INVENTORY_UOM') ?></th>
						<th class="buttons"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->view->inventory as $inventory) : ?>
					<tr>
						<td id="id">
							<input class="id" type="hidden" value="<?php echo $inventory->id ?>" name="id"/>
							<input class="controller" type="hidden" value="inventory" name="controller"/>
							<input class="module" type="hidden" value="items" name="module"/>
							<?php echo $this->view->escape($inventory->id); ?>
						</td>
						<td id="document">
							<?php if($inventory->doctype == 'invoice') : ?>
								<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'view', 'id'=>$inventory->docid));?>">
									<?php echo $this->view->translate('INVOICE'); ?> <?php echo $this->view->escape($inventory->invoiceid);?>
								</a>
							<?php endif; ?>
							<?php if($inventory->doctype == 'creditnote') : ?>
								<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'view', 'id'=>$inventory->docid));?>">
									<?php echo $this->view->translate('CREDIT_NOTE'); ?> <?php echo $this->view->escape($inventory->creditnoteid);?>
								</a>
							<?php endif; ?>
						</td>
						<td id="comment">
							<?php echo $this->view->escape($inventory->comment);?>
						</td>
						<td id="quantity">
							<?php echo $this->view->escape($inventory->quantity);?>
						</td>
						<td id="type">
							<?php if($inventory->type == 'inflow') echo $this->view->translate('ITEMS_INVENTORY_INFLOW'); ?>
							<?php if($inventory->type == 'outflow') echo $this->view->translate('ITEMS_INVENTORY_OUTFLOW'); ?>
						</td>
						<td id="contactid">
							<a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$inventory->contactid));?>">
								<?php echo $this->view->escape($inventory->contactid);?>
							</a>
						</td>
						<td id="date">
							<?php echo $this->view->escape(date("d.m.Y", strtotime($inventory->date)));?>
						</td>
						<td id="price">
							<?php echo $this->view->escape($inventory->price);?>
						</td>
						<td id="total">
							<?php echo $this->view->escape($inventory->uom);?>
						</td>
						<td class="buttons">
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php }
	}
}
