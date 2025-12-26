<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_Ledger extends Zend_View_Helper_Abstract{

	public function Ledger() {
		$data = false;
		if(count($this->view->ledger)) $data = true;
		if(!$data) { ?>
			<div id="messages"><ul><li><?php echo $this->view->translate('Es sind noch keine Buchungen vorhanden.') ?></li></ul></div>
		<?php } else { ?>
			<h4>Alle Buchungen im Verlauf</h4>
			<table id="data">
				<thead>
					<tr>
						<th id="id"><?php echo $this->view->translate('ITEMS_LEDGER_ID') ?></th>
						<th id="document"><?php echo $this->view->translate('ITEMS_LEDGER_DOCUMENT') ?></th>
						<th id="comment"><?php echo $this->view->translate('ITEMS_LEDGER_COMMENT') ?></th>
						<th id="quantity"><?php echo $this->view->translate('ITEMS_LEDGER_QUANTITY') ?></th>
						<th id="type"><?php echo $this->view->translate('ITEMS_LEDGER_TYPE') ?></th>
						<th id="contactid"><?php echo $this->view->translate('CONTACTS_CONTACT_ID') ?></th>
						<th id="ledgerdate"><?php echo $this->view->translate('ITEMS_LEDGER_DATE') ?></th>
						<th id="price"><?php echo $this->view->translate('ITEMS_LEDGER_PRICE') ?></th>
						<th id="uom"><?php echo $this->view->translate('ITEMS_LEDGER_UOM') ?></th>
						<th class="buttons"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($this->view->ledger as $ledger) : ?>
					<tr>
						<td id="id">
							<input class="id" type="hidden" value="<?php echo $ledger->id ?>" name="id"/>
							<input class="controller" type="hidden" value="ledger" name="controller"/>
							<input class="module" type="hidden" value="items" name="module"/>
							<?php echo $this->view->escape($ledger->id); ?>
						</td>
						<td id="document">
							<?php if($ledger->doctype == 'invoice') : ?>
								<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'view', 'id'=>$ledger->docid));?>">
									<?php echo $this->view->translate('INVOICE'); ?> <?php echo $this->view->escape($ledger->invoiceid);?>
								</a>
							<?php endif; ?>
							<?php if($ledger->doctype == 'creditnote') : ?>
								<a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'view', 'id'=>$ledger->docid));?>">
									<?php echo $this->view->translate('CREDIT_NOTE'); ?> <?php echo $this->view->escape($ledger->creditnoteid);?>
								</a>
							<?php endif; ?>
						</td>
						<td id="comment">
							<?php echo $this->view->escape($ledger->comment);?>
						</td>
						<td id="quantity">
							<?php echo $this->view->escape($ledger->quantity);?>
						</td>
						<td id="type">
							<?php if($ledger->type == 'inflow') echo $this->view->translate('ITEMS_LEDGER_INFLOW'); ?>
							<?php if($ledger->type == 'outflow') echo $this->view->translate('ITEMS_LEDGER_OUTFLOW'); ?>
						</td>
						<td id="contactid">
							<a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$ledger->contactid));?>">
								<?php echo $this->view->escape($ledger->contactid);?>
							</a>
						</td>
						<td id="ledgerdate">
							<?php echo $this->view->escape(date("d.m.Y", strtotime($ledger->ledgerdate)));?>
						</td>
						<td id="price">
							<?php echo $this->view->escape($ledger->price);?>
						</td>
						<td id="uom">
							<?php if($ledger->uom) echo $this->view->escape($ledger->uom);?>
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
