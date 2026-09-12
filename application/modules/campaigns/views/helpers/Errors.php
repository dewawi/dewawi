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
					<table id="data">
						<thead>
							<tr>
								<th>ID</th>
								<th>Kontakt-ID</th>
								<th>Kontakt-Nr.</th>
								<th>Name</th>
								<th>E-Mail</th>
								<th>Fehler</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($this->view->contacts as $contact) : ?>
								<?php
								$messages = $this->view->emailmessages[$contact->id] ?? [];
								$errors = array_values(array_filter($messages, function($message) {
									$response = $message['response'] ?? '';
									return $response !== '' && $response !== 'sent' && $response !== 'pending';
								}));

								if(!$errors) continue;
								?>
								<tr>
									<td></td>
									<td><?php echo (int)$contact->id; ?></td>
									<td>
										<a href="<?php echo $this->view->url([
											'module' => 'contacts',
											'controller' => 'contact',
											'action' => 'edit',
											'id' => $contact->id,
										]); ?>">
											<?php echo $this->view->escape($contact->contactid); ?>
										</a>
									</td>
									<td><?php echo $this->view->escape($contact->name1); ?></td>
									<td>
										<?php foreach((array)$contact->emails as $email) : ?>
											<?php if(trim((string)$email) === '') continue; ?>
											<div><?php echo $this->view->escape($email); ?></div>
										<?php endforeach; ?>
									</td>
									<td style="flex-grow: 5;">
										<table>
											<tbody>
												<?php foreach($errors as $emailmessage) : ?>
													<tr>
														<td><?php echo $this->view->escape($emailmessage['recipient']); ?></td>
														<td><?php echo $this->view->escape($emailmessage['messagesent']); ?></td>
														<td><?php echo $this->view->escape($this->view->users[$emailmessage['messagesentby']] ?? ''); ?></td>
														<td>
															<div class="error"><?php echo $this->view->translate('CONTACTS_EMAIL_ERROR'); ?></div>
															<pre><?php echo $this->view->escape($emailmessage['response']); ?></pre>
														</td>
														<td>
															<button name="send" type="button" class="send" onclick="resendMessage(<?php echo (int)$emailmessage['id']; ?>)">Erneut Senden</button>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</td>
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
