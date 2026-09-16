<?php

class Zend_View_Helper_Errors extends Zend_View_Helper_Abstract
{
	public function Errors(): string
	{
		$errors = (array)($this->view->campaignErrors ?? []);
		$users = (array)($this->view->users ?? []);

		ob_start();
		?>
		<div class="dw-list-page">
			<?php if(!$errors) : ?>
				<div class="dw-email-card">
					<?php echo $this->view->escape($this->view->translate('CAMPAIGNS_NO_ERRORS')); ?>
				</div>
			<?php else : ?>
				<div class="dw-table-wrap">
					<table class="dw-table dw-table--campaign-errors">
						<thead>
							<tr>
								<th class="dw-col-id">ID</th>
								<th class="dw-col-id"><?php echo $this->view->escape($this->view->translate('CAMPAIGNS_CONTACT_ID')); ?></th>
								<th><?php echo $this->view->escape($this->view->translate('CAMPAIGNS_CONTACT_CONTACT_ID')); ?></th>
								<th><?php echo $this->view->escape($this->view->translate('CAMPAIGNS_CONTACT_NAME')); ?></th>
								<th><?php echo $this->view->escape($this->view->translate('CAMPAIGNS_EMAIL')); ?></th>
								<th><?php echo $this->view->escape($this->view->translate('CAMPAIGNS_RECIPIENT_FAILED')); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($errors as $error) : ?>
								<?php
								$contactId = (int)($error['contactid'] ?? 0);
								$contactUrl = $contactId > 0 ? $this->view->url([
									'module' => 'contacts',
									'controller' => 'contact',
									'action' => 'edit',
									'id' => $contactId,
								], null, true) : '';
								?>
								<tr class="dw-row">
									<td class="dw-col-id"><?php echo (int)$error['id']; ?></td>
									<td class="dw-col-id">
										<?php if($contactUrl) : ?>
											<a href="<?php echo $this->view->escape($contactUrl); ?>"><?php echo $contactId; ?></a>
										<?php endif; ?>
									</td>
									<td>
										<?php if($contactUrl) : ?>
											<a href="<?php echo $this->view->escape($contactUrl); ?>"><?php echo $this->view->escape($error['contactnumber'] ?? ''); ?></a>
										<?php endif; ?>
									</td>
									<td><?php echo $this->view->escape($error['contactname'] ?? ''); ?></td>
									<td><?php echo $this->view->escape($error['recipient'] ?? ''); ?></td>
									<td>
										<?php if(!empty($error['messagesent'])) : ?>
											<div class="dw-list-value"><?php echo $this->view->escape($error['messagesent']); ?></div>
										<?php endif; ?>

										<?php if(!empty($error['messagesentby'])) : ?>
											<div class="dw-list-value"><?php echo $this->view->escape($users[$error['messagesentby']] ?? ''); ?></div>
										<?php endif; ?>

										<pre><?php echo $this->view->escape($error['response'] ?? ''); ?></pre>
									</td>
									<td>
										<button type="button" class="dw-btn dw-btn--secondary" onclick="resendMessage(<?php echo (int)$error['id']; ?>)">
											<?php echo $this->view->escape($this->view->translate('CAMPAIGNS_RESEND')); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}
}
