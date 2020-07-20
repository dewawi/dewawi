<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_History extends Zend_View_Helper_Abstract{

	public function History() {
        $history = false;
        foreach($this->view->history as $data) {
            if(count($data)) $history = true;
        }
		if(!$history) { ?>
            <div id="messages"><ul><li><?php echo $this->view->translate('Es sind noch keine Aktivitäten vorhanden.') ?></li></ul></div>
		<?php } else {
		    if(count($this->view->history['quotes'])) : ?>
		        <h3><?php echo $this->view->translate('QUOTES') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="quoteid"><?php echo $this->view->translate('QUOTES_QUOTE_ID') ?></th>
					        <th id="title"><?php echo $this->view->translate('QUOTES_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('QUOTES_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('QUOTES_CONTACT') ?></th>
					        <th id="quotedate"><?php echo $this->view->translate('QUOTES_QUOTE_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('QUOTES_MODIFIED') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('QUOTES_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('QUOTES_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('QUOTES_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['quotes'] as $quote) : ?>
				        <tr>
					        <td id="quoteid">
						        <input class="id" type="hidden" value="<?php echo $quote->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="quote" name="controller"/>
						        <input class="module" type="hidden" value="sales" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'edit', 'id'=>$quote->id));?>">
							        <?php echo $this->view->escape($quote->quoteid) ? $this->view->escape($quote->quoteid) : $this->view->translate('QUOTE')." ".$this->view->escape($quote->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'quote', 'action'=>'edit', 'id'=>$quote->id));?>">
							        <?php echo $quote->title ? $this->view->escape($quote->title) : $this->view->escape($quote->id);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($quote->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($quote->billingname1);?>
							        <?php if($quote->billingname2) echo "<br/>".$this->view->escape($quote->billingname2);?>
						        </a>
					        </td>
					        <td id="quotedate">
						        <?php echo $this->view->escape($quote->quotedate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($quote->modified);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($quote->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($quote->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$quote->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($quote->state == '105' || $quote->state == '106') : ?>
						            <?php $view = $this->view->toolbar->view->setLabel(''); ?>
						            <?php echo $view->setAttrib('class', 'view nolabel'); ?>
						        <?php else : ?>
						            <?php $edit = $this->view->toolbar->edit->setLabel(''); ?>
						            <?php echo $edit->setAttrib('class', 'edit nolabel'); ?>
						        <?php endif; ?>
						        <?php $copy = $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $copy->setAttrib('class', 'copy nolabel'); ?>
						        <?php $pdf = $this->view->toolbar->pdf->setLabel(''); ?>
						        <?php echo $pdf->setAttrib('class', 'pdf nolabel'); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['salesorders'])) : ?>
		        <h3><?php echo $this->view->translate('SALES_ORDERS') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="salesorderid"><?php echo $this->view->translate('SALES_ORDERS_SALES_ORDER_ID') ?></th>
					        <th id="title"><?php echo $this->view->translate('SALES_ORDERS_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('SALES_ORDERS_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('SALES_ORDERS_CONTACT') ?></th>
					        <th id="salesorderdate"><?php echo $this->view->translate('SALES_ORDERS_SALES_ORDER_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('SALES_ORDERS_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('SALES_ORDERS_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('SALES_ORDERS_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('SALES_ORDERS_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('SALES_ORDERS_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['salesorders'] as $salesorder) : ?>
				        <tr>
					        <td id="salesorderid">
						        <input class="id" type="hidden" value="<?php echo $salesorder->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="salesorder" name="controller"/>
						        <input class="module" type="hidden" value="sales" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'edit', 'id'=>$salesorder->id));?>">
							        <?php echo $this->view->escape($salesorder->salesorderid) ? $this->view->escape($salesorder->salesorderid) : $this->view->translate('SALES_ORDER')." ".$this->view->escape($salesorder->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'salesorder', 'action'=>'edit', 'id'=>$salesorder->id));?>">
							        <?php echo $salesorder->title ? $this->view->escape($salesorder->title) : $this->view->escape($salesorder->id);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($salesorder->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($salesorder->billingname1);?>
							        <?php if($salesorder->billingname2) echo "<br/>".$this->view->escape($salesorder->billingname2);?>
						        </a>
					        </td>
					        <td id="salesorderdate">
						        <?php echo $this->view->escape($salesorder->salesorderdate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($salesorder->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($salesorder->deliverydate != "0000-00-00") echo $this->view->escape($salesorder->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($salesorder->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($salesorder->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$salesorder->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($salesorder->state == '105' || $salesorder->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['invoices'])) : ?>
		        <h3><?php echo $this->view->translate('INVOICES') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="invoiceid"><?php echo $this->view->translate('INVOICES_INVOICE_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('INVOICES_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('INVOICES_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('INVOICES_CONTACT') ?></th>
					        <th id="invoicedate"><?php echo $this->view->translate('INVOICES_INVOICE_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('INVOICES_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('INVOICES_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('INVOICES_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('INVOICES_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('INVOICES_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['invoices'] as $invoice) : ?>
				        <tr>
					        <td id="invoiceid">
						        <input class="id" type="hidden" value="<?php echo $invoice->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="invoice" name="controller"/>
						        <input class="module" type="hidden" value="sales" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'edit', 'id'=>$invoice->id));?>">
							        <?php echo $this->view->escape($invoice->invoiceid) ? $this->view->escape($invoice->invoiceid) : $this->view->translate('INVOICE')." ".$this->view->escape($invoice->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'invoice', 'action'=>'edit', 'id'=>$invoice->id));?>">
							        <?php echo $invoice->title ? $this->view->escape($invoice->title) : $this->view->translate('INVOICE')." ".$this->view->escape($invoice->invoiceid);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($invoice->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($invoice->billingname1);?>
							        <?php if($invoice->billingname2) echo "<br/>".$this->view->escape($invoice->billingname2);?>
						        </a>
					        </td>
					        <td id="invoicedate">
						        <?php echo $this->view->escape($invoice->invoicedate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($invoice->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($invoice->deliverydate != "0000-00-00") echo $this->view->escape($invoice->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($invoice->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($invoice->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$invoice->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($invoice->state == '105' || $invoice->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['deliveryorders'])) : ?>
		        <h3><?php echo $this->view->translate('DELIVERY_ORDERS') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="deliveryorderid"><?php echo $this->view->translate('DELIVERY_ORDERS_DELIVERY_ORDER_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('DELIVERY_ORDERS_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('DELIVERY_ORDERS_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('DELIVERY_ORDERS_CONTACT') ?></th>
					        <th id="deliveryorderdate"><?php echo $this->view->translate('DELIVERY_ORDERS_DELIVERY_ORDER_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('DELIVERY_ORDERS_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('DELIVERY_ORDERS_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('DELIVERY_ORDERS_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('DELIVERY_ORDERS_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('DELIVERY_ORDERS_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['deliveryorders'] as $deliveryorder) : ?>
				        <tr>
					        <td id="deliveryorderid">
						        <input class="id" type="hidden" value="<?php echo $deliveryorder->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="deliveryorder" name="controller"/>
						        <input class="module" type="hidden" value="sales" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'deliveryorder', 'action'=>'edit', 'id'=>$deliveryorder->id));?>">
							        <?php echo $this->view->escape($deliveryorder->deliveryorderid) ? $this->view->escape($deliveryorder->deliveryorderid) : $this->view->translate('DELIVERY_ORDER')." ".$this->view->escape($deliveryorder->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'deliveryorder', 'action'=>'edit', 'id'=>$deliveryorder->id));?>">
							        <?php echo $deliveryorder->title ? $this->view->escape($deliveryorder->title) : $this->view->translate('DELIVERY_ORDER')." ".$this->view->escape($deliveryorder->deliveryorderid);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($deliveryorder->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($deliveryorder->billingname1);?>
							        <?php if($deliveryorder->billingname2) echo "<br/>".$this->view->escape($deliveryorder->billingname2);?>
						        </a>
					        </td>
					        <td id="deliveryorderdate">
						        <?php echo $this->view->escape($deliveryorder->deliveryorderdate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($deliveryorder->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($deliveryorder->deliverydate != "0000-00-00") echo $this->view->escape($deliveryorder->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($deliveryorder->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($deliveryorder->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$deliveryorder->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($deliveryorder->state == '105' || $deliveryorder->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['creditnotes'])) : ?>
		        <h3><?php echo $this->view->translate('CREDIT_NOTES') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="creditnoteid"><?php echo $this->view->translate('CREDIT_NOTES_CREDIT_NOTE_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('CREDIT_NOTES_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('CREDIT_NOTES_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('CREDIT_NOTES_CONTACT') ?></th>
					        <th id="creditnotedate"><?php echo $this->view->translate('CREDIT_NOTES_CREDIT_NOTE_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('CREDIT_NOTES_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('CREDIT_NOTES_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('CREDIT_NOTES_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('CREDIT_NOTES_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('CREDIT_NOTES_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['creditnotes'] as $creditnote) : ?>
				        <tr>
					        <td id="creditnoteid">
						        <input class="id" type="hidden" value="<?php echo $creditnote->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="creditnote" name="controller"/>
						        <input class="module" type="hidden" value="sales" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'edit', 'id'=>$creditnote->id));?>">
							        <?php echo $this->view->escape($creditnote->creditnoteid) ? $this->view->escape($creditnote->creditnoteid) : $this->view->translate('CREDIT_NOTE')." ".$this->view->escape($creditnote->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'sales', 'controller'=>'creditnote', 'action'=>'edit', 'id'=>$creditnote->id));?>">
							        <?php echo $creditnote->title ? $this->view->escape($creditnote->title) : $this->view->translate('CREDIT_NOTE')." ".$this->view->escape($creditnote->creditnoteid);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($creditnote->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($creditnote->billingname1);?>
							        <?php if($creditnote->billingname2) echo "<br/>".$this->view->escape($creditnote->billingname2);?>
						        </a>
					        </td>
					        <td id="creditnotedate">
						        <?php echo $this->view->escape($creditnote->creditnotedate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($creditnote->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($creditnote->deliverydate != "0000-00-00") echo $this->view->escape($creditnote->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($creditnote->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($creditnote->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$creditnote->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($creditnote->state == '105' || $creditnote->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['quoterequests'])) : ?>
		        <h3><?php echo $this->view->translate('QUOTE_REQUESTS') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="quoterequestid"><?php echo $this->view->translate('QUOTE_REQUESTS_QUOTE_REQUEST_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('QUOTE_REQUESTS_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('QUOTE_REQUESTS_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('QUOTE_REQUESTS_CONTACT') ?></th>
					        <th id="quoterequestdate"><?php echo $this->view->translate('QUOTE_REQUESTS_QUOTE_REQUEST_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('QUOTE_REQUESTS_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('QUOTE_REQUESTS_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('QUOTE_REQUESTS_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('QUOTE_REQUESTS_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('QUOTE_REQUESTS_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['quoterequests'] as $quoterequest) : ?>
				        <tr>
					        <td id="quoterequestid">
						        <input class="id" type="hidden" value="<?php echo $quoterequest->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="quoterequest" name="controller"/>
						        <input class="module" type="hidden" value="purchases" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'quoterequest', 'action'=>'edit', 'id'=>$quoterequest->id));?>">
							        <?php echo $this->view->escape($quoterequest->quoterequestid) ? $this->view->escape($quoterequest->quoterequestid) : $this->view->translate('QUOTE_REQUEST')." ".$this->view->escape($quoterequest->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'quoterequest', 'action'=>'edit', 'id'=>$quoterequest->id));?>">
							        <?php echo $quoterequest->title ? $this->view->escape($quoterequest->title) : $this->view->translate('QUOTE_REQUEST')." ".$this->view->escape($quoterequest->quoterequestid);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($quoterequest->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($quoterequest->billingname1);?>
							        <?php if($quoterequest->billingname2) echo "<br/>".$this->view->escape($quoterequest->billingname2);?>
						        </a>
					        </td>
					        <td id="quoterequestdate">
						        <?php echo $this->view->escape($quoterequest->quoterequestdate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($quoterequest->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($quoterequest->deliverydate != "0000-00-00") echo $this->view->escape($quoterequest->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($quoterequest->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($quoterequest->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$quoterequest->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($quoterequest->state == '105' || $quoterequest->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['purchaseorders'])) : ?>
		        <h3><?php echo $this->view->translate('PURCHASE_ORDERS') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="purchaseorderid"><?php echo $this->view->translate('PURCHASE_ORDERS_PURCHASE_ORDER_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('PURCHASE_ORDERS_TITLE') ?></th>
					        <th id="contactid"><?php echo $this->view->translate('PURCHASE_ORDERS_CONTACT_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('PURCHASE_ORDERS_CONTACT') ?></th>
					        <th id="purchaseorderdate"><?php echo $this->view->translate('PURCHASE_ORDERS_PURCHASE_ORDER_DATE') ?></th>
					        <th id="modified"><?php echo $this->view->translate('PURCHASE_ORDERS_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('PURCHASE_ORDERS_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('PURCHASE_ORDERS_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('PURCHASE_ORDERS_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('PURCHASE_ORDERS_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['purchaseorders'] as $purchaseorder) : ?>
				        <tr>
					        <td id="purchaseorderid">
						        <input class="id" type="hidden" value="<?php echo $purchaseorder->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="purchaseorder" name="controller"/>
						        <input class="module" type="hidden" value="purchases" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'edit', 'id'=>$purchaseorder->id));?>">
							        <?php echo $this->view->escape($purchaseorder->purchaseorderid) ? $this->view->escape($purchaseorder->purchaseorderid) : $this->view->translate('PURCHASE_ORDER')." ".$this->view->escape($purchaseorder->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'purchases', 'controller'=>'purchaseorder', 'action'=>'edit', 'id'=>$purchaseorder->id));?>">
							        <?php echo $purchaseorder->title ? $this->view->escape($purchaseorder->title) : $this->view->translate('PURCHASE_ORDER')." ".$this->view->escape($purchaseorder->purchaseorderid);?>
						        </a>
					        </td>
					        <td id="contactid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($purchaseorder->contactid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($purchaseorder->billingname1);?>
							        <?php if($purchaseorder->billingname2) echo "<br/>".$this->view->escape($purchaseorder->billingname2);?>
						        </a>
					        </td>
					        <td id="purchaseorderdate">
						        <?php echo $this->view->escape($purchaseorder->purchaseorderdate);?>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($purchaseorder->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($purchaseorder->deliverydate != "0000-00-00") echo $this->view->escape($purchaseorder->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($purchaseorder->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($purchaseorder->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$purchaseorder->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($purchaseorder->state == '105' || $purchaseorder->state == '106') : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
						        <?php echo $this->view->toolbar->pdf->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif; ?>
		    <?php if(count($this->view->history['processes'])) : ?>
		        <h3><?php echo $this->view->translate('PROCESSES') ?></h3>
		        <table id="data">
			        <thead>
				        <tr>
					        <th id="id"><?php echo $this->view->translate('PROCESSES_ID'); ?></th>
					        <th id="title"><?php echo $this->view->translate('PROCESSES_TITLE') ?></th>
					        <th id="customerid"><?php echo $this->view->translate('PROCESSES_CUSTOMER_ID') ?></th>
					        <th id="contact"><?php echo $this->view->translate('PROCESSES_CUSTOMER') ?></th>
					        <th id="modified"><?php echo $this->view->translate('PROCESSES_MODIFIED') ?></th>
					        <th id="deliverydate"><?php echo $this->view->translate('PROCESSES_DELIVERY_DATE') ?></th>
					        <th id="subtotal"><?php echo $this->view->translate('PROCESSES_SUBTOTAL') ?></th>
					        <th id="total"><?php echo $this->view->translate('PROCESSES_TOTAL') ?></th>
					        <th id="state"><?php echo $this->view->translate('PROCESSES_STATE') ?></th>
					        <th class="buttons"></th>
				        </tr>
			        </thead>
			        <tbody>
				        <?php foreach($this->view->history['processes'] as $process) : ?>
				        <tr>
					        <td id="id">
						        <input class="id" type="hidden" value="<?php echo $process->id ?>" name="id"/>
						        <input class="controller" type="hidden" value="process" name="controller"/>
						        <input class="module" type="hidden" value="processes" name="module"/>
						        <a href="<?php echo $this->view->url(array('module'=>'processes', 'controller'=>'process', 'action'=>'edit', 'id'=>$process->id));?>">
							        <?php echo $this->view->escape($process->id) ? $this->view->escape($process->id) : $this->view->translate('PROCESS')." ".$this->view->escape($process->id); ?>
						        </a>
					        </td>
					        <td id="title">
						        <a href="<?php echo $this->view->url(array('module'=>'processes', 'controller'=>'process', 'action'=>'edit', 'id'=>$process->id));?>">
							        <?php echo $process->title ? $this->view->escape($process->title) : $this->view->translate('PROCESS')." ".$this->view->escape($process->id);?>
						        </a>
					        </td>
					        <td id="customerid">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($process->customerid);?>
						        </a>
					        </td>
					        <td id="contact">
						        <a href="<?php echo $this->view->url(array('module'=>'contacts', 'controller'=>'contact', 'action'=>'edit', 'id'=>$this->view->id));?>">
							        <?php echo $this->view->escape($process->billingname1);?>
							        <?php if($process->billingname2) echo "<br/>".$this->view->escape($process->billingname2);?>
						        </a>
					        </td>
					        <td id="modified">
						        <?php echo $this->view->escape($process->modified);?>
					        </td>
					        <td id="deliverydate">
						        <?php if($process->deliverydate != "0000-00-00") echo $this->view->escape($process->deliverydate);?>
					        </td>
					        <td id="subtotal">
						        <?php echo $this->view->escape($process->subtotal);?>
					        </td>
					        <td id="total">
						        <?php echo $this->view->escape($process->total);?>
					        </td>
					        <td id="state">
						        <?php echo $this->view->translate($this->view->escape($this->view->states[$process->state]));?>
					        </td>
					        <td class="buttons">
						        <?php if($process->completed || $process->cancelled) : ?>
							        <?php echo $this->view->toolbar->view->setLabel(''); ?>
						        <?php else : ?>
							        <?php echo $this->view->toolbar->edit->setLabel(''); ?>
						        <?php endif; ?>
						        <?php echo $this->view->toolbar->copy->setLabel(''); ?>
					        </td>
				        </tr>
				        <?php endforeach; ?>
			        </tbody>
		        </table>
		    <?php endif;
		}
	}
}
