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
		<?php foreach($this->view->contact['phone'] as $phone) : ?>
			<dt id="phone<?php echo $phone['id']; ?>">
				<label><?php echo $phone['phone']; ?></label>
			</dt>
		<?php endforeach; ?>
		<dt id="email"<?php if(!count($this->view->contact['email'])) echo ' style="display:none;"'; ?>>
			<label><?php echo $this->view->translate('CONTACTS_EMAIL'); ?></label>
		</dt>
		<?php foreach($this->view->contact['email'] as $email) : ?>
			<dt id="email<?php echo $email['id']; ?>">
				<label><?php echo $email['email']; ?></label>
			</dt>
		<?php endforeach; ?>
		<dt id="internet"<?php if(!count($this->view->contact['internet'])) echo ' style="display:none;"'; ?>>
			<label><?php echo $this->view->translate('CONTACTS_INTERNET'); ?></label>
		</dt>
		<?php foreach($this->view->contact['internet'] as $internet) : ?>
			<dt id="internet<?php echo $internet['id']; ?>">
				<label><?php echo $internet['internet']; ?></label>
			</dt>
		<?php endforeach;
	}
}
