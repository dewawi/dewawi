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
		<div data-pagination-change="getCampaignRecipients">
			<?php echo $this->view->Pagination(); ?>

			<div class="dw-list-page">
				<?php echo $list->render(); ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}
