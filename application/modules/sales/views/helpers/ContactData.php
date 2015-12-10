<?php
/**
* Class inserts neccery code for Toolbar	
*/
class Zend_View_Helper_ContactData extends Zend_View_Helper_Abstract{

	public function ContactData() { ?>
		<dt><label><b><?php echo $this->view->translate('CONTACTS_CONTACT_DATA'); ?></b></label></dt>
		<dt id="phone"<?php if(!count($this->view->contact['phone'])) echo ' style="display:none;"'; ?>>
			<label><?php echo $this->view->translate('CONTACTS_PHONE'); ?></label>
		</dt>
		<div id="phones" style="float:left;">
			<?php foreach($this->view->contact['phone'] as $phone) : ?>
				<label><?php echo $phone['phone']; ?></label><br>
			<?php endforeach; ?>
		</div>
		<dt id="email"<?php if(!count($this->view->contact['email'])) echo ' style="display:none;"'; ?>>
			<label><?php echo $this->view->translate('CONTACTS_EMAIL'); ?></label>
		</dt>
		<div id="emails" style="float:left;">
			<?php foreach($this->view->contact['email'] as $email) : ?>
				<label><?php echo $email['email']; ?></label><br>
			<?php endforeach; ?>
		</div>
		<dt id="internet"<?php if(!count($this->view->contact['internet'])) echo ' style="display:none;"'; ?>>
			<label><?php echo $this->view->translate('CONTACTS_INTERNET'); ?></label>
		</dt>
		<div id="internets" style="float:left;">
			<?php foreach($this->view->contact['internet'] as $internet) : ?>
				<label><?php echo $internet['internet']; ?></label><br>
			<?php endforeach; ?>
		</div>
	<?php }
}
