<?php
/**
* Class inserts neccery code for Messages	
*/
class Zend_View_Helper_EmailMessages extends Zend_View_Helper_Abstract{

	public function EmailMessages() { ?>
		<form id="emailmessage" enctype="application/x-www-form-urlencoded" action="" method="post">
			<div class="row">
				<div class="col-sm-12 col-lg-7">
					<dl class="form">
						<?php if($this->view->module != 'campaigns') : ?>
							<?php echo $this->view->emailForm->recipient; ?>
						<?php endif; ?>
						<?php echo $this->view->emailForm->cc; ?>
						<?php echo $this->view->emailForm->bcc; ?>
						<?php echo $this->view->emailForm->replyto; ?>
						<?php echo $this->view->emailForm->subject; ?>
						<dt id="attachment-label">
							<label for="attachment" class="optional">Anhang</label>
						</dt>
						<dd id="attachment-element">
							<div id="attachments">
								<?php foreach($this->view->attachments as $attachment) : ?>
									<?php //$headers = get_headers('https://'.$_SERVER['HTTP_HOST'].'/files/attachments/'.$attachment['module'].'/'.$attachment['controller'].'/'.$this->view->url.'/'.$attachment['filename']); ?>
									<?php //if(stripos($headers[0], '200 OK')) : ?>
									<div id="attachment<?php echo $attachment['id']; ?>" class="file" style="clear: left;">
										<input id="file<?php echo $attachment['id']; ?>" type="checkbox" value="<?php echo $attachment['id']; ?>" size="5" name="file" style="float: left;"/>
										<a href="<?php echo $this->view->baseUrl(); ?>/files/attachments/<?php echo $attachment['module']; ?>/<?php echo $attachment['controller']; ?>/<?php echo $this->view->documentUrl; ?>/<?php echo $attachment['filename']; ?>" target="_blank">
											<span class="filename"><?php echo $attachment['filename']; ?></span>
											<span class="filesize">(<?php echo $this->view->HumanFileSize($attachment['filesize']); ?>)</span>
										</a>
										<button type="button" class="delete nolabel" onclick="del('<?php echo $attachment['id']; ?>', deleteConfirm, 'attachment', 'contacts');"></button><br>
									</div>
									<?php //endif; ?>
								<?php endforeach; ?>
							</div>
						</dd>
						<?php echo $this->view->emailForm->body; ?>
						<button name="send" id="save" type="button" class="send" onclick="sendMessage()">Senden</button>
					</dl>
				</div>
				<div class="col-sm-12 col-lg-5">
					<iframe src="<?php echo $this->view->baseUrl(); ?>/contacts/attachment/upload/cmodule/<?php echo $this->view->module; ?>/ccontroller/<?php echo $this->view->controller; ?>/id/<?php echo $this->view->id; ?>" width="100%" height="400px"></iframe>
				</div>
			</div>
		</form>
		<br>
		<div id="emailmessages"></div>
		<?php
	}
}
