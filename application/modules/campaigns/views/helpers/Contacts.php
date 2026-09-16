<?php

class Zend_View_Helper_Contacts extends Zend_View_Helper_Abstract
{
	public function Contacts(): string
	{
		$status = (array)($this->view->recipientStatus ?? []);

		$list = new Campaigns_Model_List_Recipients([
			'id' => 'campaign-recipients',
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
		<div class="dw-email-card">
			<strong><?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_STATUS'); ?></strong><br>
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_TOTAL'); ?>: <?php echo (int)($status['total'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_ELIGIBLE'); ?>: <?php echo (int)($status['eligible'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_SUPPRESSED'); ?>: <?php echo (int)($status['suppressed'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_OPEN'); ?>: <?php echo (int)($status['open'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_PENDING'); ?>: <?php echo (int)($status['pending'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_SENT'); ?>: <?php echo (int)($status['sent'] ?? 0); ?> |
			<?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_FAILED'); ?>: <?php echo (int)($status['failed'] ?? 0); ?>
		</div>

		<div class="dw-list-page">
			<?php echo $list->render(); ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
