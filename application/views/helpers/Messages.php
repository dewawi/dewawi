<?php

class Zend_View_Helper_Messages extends Zend_View_Helper_Abstract
{
	public function getTranslations(): array {
		$keys = ['MESSAGES_CAMPAIGN_LOADING', 'MESSAGES_CAMPAIGN_RECIPIENTS_FAILED', 'MESSAGES_CAMPAIGN_ERRORS_FAILED', 'MESSAGES_UPLOAD_RUNNING', 'MESSAGES_UPLOAD_SELECT', 'MESSAGES_UPLOAD_UNKNOWN', 'MESSAGES_EMAIL_SENDING', 'MESSAGES_EMAIL_SENT_SUCCESS', 'MESSAGES_EMAIL_SENT_ERROR', 'MESSAGES_EMAIL_STATUS_UNKNOWN', 'MESSAGES_EMAIL_RESEND_CONFIRM', 'MESSAGES_EMAIL_RESEND', 'MESSAGES_EMAIL_HISTORY_FAILED', 'MESSAGES_CONTACT_NOT_FOUND', 'MESSAGES_DISMISS', 'MESSAGES_SAVE_FAILED', 'MESSAGES_SAVED', 'MESSAGES_COPY_RUNNING', 'MESSAGES_RECORDS_COPIED', 'MESSAGES_COPY_FAILED', 'MESSAGES_COPY_PARTIAL', 'MESSAGES_COPY_STATUS_UNKNOWN', 'MESSAGES_COPY_OPEN', 'MESSAGES_SORT_FAILED', 'MESSAGES_SEARCH_RUNNING', 'MESSAGES_SEARCH_FAILED', 'MESSAGES_HISTORY_REFRESH_FAILED', 'MESSAGES_SUCCESFULLY_COPIED', 'MESSAGES_SAVING', 'MESSAGES_TAGS_UPDATING', 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNING', 'MESSAGES_CONTACT_APPLYING', 'MESSAGES_CONTACT_APPLY_FAILED', 'MESSAGES_DIALOG_CLOSE', 'MESSAGES_DIALOG', 'MESSAGES_TAG_INVALID', 'MESSAGES_TAGS_ASSIGNED', 'MESSAGES_TAGS_REMOVED', 'MESSAGES_TAGS_UPDATE_FAILED', 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_INVALID', 'MESSAGES_EMAIL_CONTACTPERSON_ASSIGNMENT_FAILED', 'MESSAGES_CHANGE_STATUS_UNKNOWN', 'MESSAGES_POSITION_ADD_FAILED', 'MESSAGES_POSITION_COPY_FAILED', 'MESSAGES_POSITION_DELETE_FAILED', 'MESSAGES_POSITION_SET_FAILED', 'MESSAGES_REQUEST_FAILED', 'MESSAGES_FORM_IS_INVALID', 'MESSAGES_PENDING', 'MESSAGES_CREATED', 'MESSAGES_CREATE_FAILED', 'MESSAGES_SUCCESFULLY_DELETED', 'MESSAGES_RECORDS_SUCCESFULLY_DELETED', 'MESSAGES_CANNOT_BE_DELETED', 'MESSAGES_SUCCESFULLY_CANCELLED', 'MESSAGES_CANCEL_FAILED', 'MESSAGES_ACTION_STATUS_UNKNOWN', 'MESSAGES_PARTIALLY_DELETED', 'DOCUMENTS_PREVIEW_RUNNING', 'DOCUMENTS_PREVIEW_FAILED', 'DOCUMENTS_FINALIZING', 'DOCUMENTS_GENERATING', 'DOCUMENT_STATUS_UNKNOWN', 'DOCUMENT_VALIDATION_INPUT_INVALID', 'DOCUMENT_VALIDATION_REQUEST_FAILED', 'DOCUMENTS_PREVIEW_PDF'];
		$messages = [];
		foreach ($keys as $key) $messages[$key] = (string)$this->view->translate($key);
		return $messages;
	}

	public function Messages() {
		$groups = [];
		foreach ((array)$this->view->messages as $message) {
			$type = 'danger';
			if (is_array($message)) {
				$type = $message['type'] ?? $type;
				$message = $message['message'] ?? '';
			} elseif (preg_match('/^MESSAGES_.*(SUCCESFULLY|SUCCESS|SAVED|CREATED|UPDATED|UPLOADED)/', (string)$message)) {
				$type = 'success';
			} elseif (in_array($message, ['MESSAGES_SEARCH_RETURNED_NO_RESULTS', 'MESSAGES_PENDING'], true)) {
				$type = 'info';
			}
			if (!in_array($type, ['success', 'danger', 'warning', 'info'], true)) $type = 'danger';
			$text = (string)$this->view->translate($message);
			if ($text !== '') $groups[$type][$text] = $text;
		}
		if (!$groups) return;
		?>
		<div id="messages">
			<?php foreach ($groups as $type => $messages) : ?>
				<div class="dw-alert dw-alert--<?php echo $type; ?>" role="<?php echo $type === 'danger' ? 'alert' : 'status'; ?>" aria-live="<?php echo $type === 'danger' ? 'assertive' : 'polite'; ?>" aria-atomic="true">
					<ul>
						<?php foreach ($messages as $message) : ?>
							<li><?php echo $this->view->escape($message); ?></li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="dw-alert__close" aria-label="<?php echo $this->view->escape($this->view->translate('MESSAGES_DISMISS')); ?>">×</button>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
