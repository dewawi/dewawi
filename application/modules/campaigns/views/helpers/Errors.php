<?php

class Zend_View_Helper_Errors extends Zend_View_Helper_Abstract
{
	public function Errors(): string
	{
		ob_start();
		?>
		<form id="campaign-errors" enctype="application/x-www-form-urlencoded" action="" method="post">
			<div class="row">
				<div class="col-sm-12 col-lg-12">
					Kontakte: <?php echo count($this->view->contacts); ?>
					Nachrichten: <?php echo count($this->view->emailmessages); ?>

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
								<?php if(isset($this->view->emailmessages[$contact->id]) && $this->view->emailmessages[$contact->id][0]['response']) : ?>
									<tr>
										<td></td>
										<td><?php echo $contact->id; ?></td>
										<td><?php echo $contact->contactid; ?></td>
										<td><?php echo $contact->name1; ?></td>
										<td>
											<?php echo str_replace(',', '<br>', $this->view->escape($contact->emails)); ?>
										</td>
										<td style="flex-grow: 5;">
											<table>
												<tbody>
													<?php foreach($this->view->emailmessages[$contact->id] as $emailmessage) : ?>
														<tr>
															<td><?php echo $emailmessage['recipient']; ?></td>
															<td><?php echo $emailmessage['messagesent']; ?></td>
															<td><?php echo $this->view->users[$emailmessage['messagesentby']]; ?></td>
															<td>
																<?php if($emailmessage['response']) : ?>
																	<div class="error"><?php echo $this->view->translate('CONTACTS_EMAIL_ERROR'); ?></div>
																	<pre><?php echo $this->view->escape($emailmessage['response']); ?></pre>
																<?php else : ?>
																	<div class="successful"><?php echo $this->view->translate('CONTACTS_EMAIL_SUCCESSFUL'); ?></div>
																<?php endif; ?>
															</td>
															<td>
																<button name="send" type="button" class="send" onclick="resendMessage(<?php echo $emailmessage['id']; ?>)">Erneut Senden</button>
															</td>
															<td></td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</td>
										<td></td>
									</tr>
								<?php endif; ?>
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
