<?php

class Zend_View_Helper_Contacts extends Zend_View_Helper_Abstract
{
	public function Contacts(): string
	{
		ob_start();
		?>
		<form id="campaign-contacts" enctype="application/x-www-form-urlencoded" action="" method="post">
			<div class="row">
				<div class="col-sm-12 col-lg-12">
					<?php $status = $this->view->recipientStatus ?? []; ?>

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

					<table id="data">
						<thead>
							<tr>
								<th>ID</th>
								<th>ID</th>
								<th>ID</th>
								<th>Name</th>
								<th></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($this->view->contacts as $contact) : ?>
								<tr>
									<td></td>
									<td><?php echo $contact->id; ?></td>
									<td>
										<a href="<?php echo $this->view->url([
											'module' => 'contacts',
											'controller' => 'contact',
											'action' => 'edit',
											'id' => $contact->id,
										]); ?>"><?php echo $contact->contactid; ?></a>
									</td>
									<td><?php echo $contact->name1; ?></td>
									<td style="flex-grow: 2;">
										<?php echo str_replace(',', '<br>', $this->view->escape($contact->emails)); ?>

										<?php if(!empty($this->view->contactPersonsByCompany[$contact->id])) : ?>
											<div style="margin-top:8px; border-top:1px solid #eee; padding-top:6px;">
												<strong><?php echo $this->view->translate('Kontaktpersonen'); ?>:</strong>
												<ul class="list-unstyled" style="margin:6px 0 0 0;">
													<?php foreach($this->view->contactPersonsByCompany[$contact->id] as $person) : ?>
														<li style="margin-bottom:4px;">
															<?php echo $this->view->escape($person['display_name']); ?>

															<?php if(!empty($person['email_list'])) : ?>
																<div style="font-size:90%;">
																	<?php echo str_replace(',', '<br>', $this->view->escape($person['email_list'])); ?>
																</div>
															<?php endif; ?>
														</li>
													<?php endforeach; ?>
												</ul>
											</div>
										<?php endif; ?>
									</td>
									<td style="flex-grow: 5;">
										<table>
											<tbody>
												<?php if(isset($this->view->emailmessages[$contact->id])) : ?>
													<?php foreach($this->view->emailmessages[$contact->id] as $emailmessage) : ?>
														<tr>
															<td><?php echo $emailmessage['recipient']; ?></td>
															<td><?php echo $emailmessage['messagesent']; ?></td>
															<td><?php echo $this->view->users[$emailmessage['messagesentby']]; ?></td>
															<td>
																<?php if($emailmessage['response'] === 'sent') : ?>
																	<div class="successful"><?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_SENT'); ?></div>
																<?php elseif($emailmessage['response'] === 'pending') : ?>
																	<div><?php echo $this->view->translate('CAMPAIGNS_RECIPIENT_PENDING'); ?></div>
																<?php else : ?>
																	<div class="error"><?php echo $this->view->translate('CONTACTS_EMAIL_ERROR'); ?></div>
																	<pre><?php echo $this->view->escape($emailmessage['response']); ?></pre>
																<?php endif; ?>
															</td>
															<td>
																<button name="send" type="button" class="send" onclick="resendMessage(<?php echo $emailmessage['id']; ?>)">Erneut Senden</button>
															</td>
															<td></td>
														</tr>
													<?php endforeach; ?>
												<?php endif; ?>
											</tbody>
										</table>
									</td>
									<td></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</form>
		<?php

		return ob_get_clean();
	}
}
