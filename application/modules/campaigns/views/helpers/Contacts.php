<?php

class Zend_View_Helper_Contacts extends Zend_View_Helper_Abstract
{
	public function Contacts(): string
	{
		$list = new Campaigns_Model_List_Recipients([
			'id' => 'campaign-recipients-list',
			'items' => $this->view->contacts ?? [],
			'view' => $this->view,
			'module' => 'contacts',
			'controller' => 'contact',
			'selectable' => false,
			'options' => [
				'contactPersonsByCompany' => $this->view->contactPersonsByCompany ?? [],
				'emailmessages' => $this->view->emailmessages ?? [],
			],
		]);

		ob_start();
		?>
		<?php $status = $this->view->recipientStatus ?? []; ?>
		<?php $contactCount = (int)($this->view->pagination['records'] ?? 0); ?>

		<div data-pagination-change="getCampaignRecipients">
			<?php if($status) { ?>
				<div class="dw-list-value">
					<strong><?php echo $this->view->translate('CONTACTS'); ?>:</strong>
					<?php echo $contactCount; ?>
					&nbsp;|&nbsp;
					<strong><?php echo $this->view->translate('CAMPAIGNS_EMAIL_RECIPIENTS'); ?>:</strong>
					<?php echo (int)$status['total']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_OPEN'); ?>: <?php echo (int)$status['open']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_PENDING'); ?>: <?php echo (int)$status['pending']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_SENT'); ?>: <?php echo (int)$status['sent']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_DELIVERED'); ?>: <?php echo (int)$status['delivered']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_BOUNCE'); ?>: <?php echo (int)$status['bounce']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_COMPLAINT'); ?>: <?php echo (int)$status['complaint']; ?>
					&nbsp;|&nbsp;
					<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_FAILED'); ?>: <?php echo (int)$status['failed']; ?>
				</div>
			<?php } ?>

			<?php echo $this->view->Pagination(); ?>

			<div class="dw-list-page">
				<?php echo $list->render(); ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}
