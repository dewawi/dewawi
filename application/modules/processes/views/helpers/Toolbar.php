<?php
/**
* Class inserts neccery code for Toolbar
*/
class Zend_View_Helper_Toolbar extends Zend_View_Helper_Abstract
{
	public function Toolbar() { ?>
		<?php if($this->view->action == 'edit') : ?>
			<input class="id" type="hidden" value="<?php echo $this->view->id ?>" name="id"/>
			<?php echo $this->view->toolbar->copy; ?>
			<?php echo $this->view->toolbar->delete; ?>
			<?php echo $this->view->toolbar->state; ?>
		<?php elseif($this->view->action == 'view') : ?>
			<input class="id" type="hidden" value="<?php echo $this->view->id ?>" name="id"/>
			<?php echo $this->view->toolbar->copy; ?>
		<?php elseif($this->view->action == 'index') : ?>
			<?php echo $this->view->toolbar->add; ?>
			<?php echo $this->view->toolbar->edit; ?>
			<?php echo $this->view->toolbar->copy; ?>
			<?php echo $this->view->toolbar->delete; ?>
			<?php echo $this->view->toolbar->filter; ?>
			<?php echo $this->view->toolbar->keyword; ?>
			<?php echo $this->view->toolbar->clear; ?>
			<?php echo $this->view->toolbar->reset; ?>
			<?php echo $this->view->toolbar->limit; ?>
			<div id="filter">
				<form>
					<table>
						<tr>
							<td id="state" class="space">
								<h4><?php echo $this->view->translate('TOOLBAR_STATE'); ?></h4>
								<a class="all"><?php echo $this->view->translate('TOOLBAR_ALL'); ?></a> | <a class="none"><?php echo $this->view->translate('TOOLBAR_NONE'); ?></a></br>
								<?php echo $this->view->toolbar->states; ?>
							</td>
							<td class="space">
								<h4><?php echo $this->view->translate('TOOLBAR_ORDERING'); ?></h4>
								<?php echo $this->view->toolbar->order; ?></br>
								<?php echo $this->view->toolbar->sort; ?></br>
								<h4><?php echo $this->view->translate('TOOLBAR_COUNTRY'); ?></h4>
								<?php echo $this->view->toolbar->country; ?></br>
								<h4><?php echo $this->view->translate('PROCESSES_PAYMENT_STATUS'); ?></h4>
								<?php echo $this->view->toolbar->paymentstatus; ?>
							</td>
							<td id="daterange" class="space">
								<h4><?php echo $this->view->translate('TOOLBAR_DATE_RANGE'); ?></h4>
								<?php echo $this->view->toolbar->daterange; ?>
							</td>
							<td class="daterange"<?php if($this->view->toolbar->getValue('daterange') != 'custom') echo ' style="display:none;"'; ?>>
								<div style="margin-top:0;">
									<?php echo $this->view->translate('TOOLBAR_FROM'); ?>
									<?php echo $this->view->toolbar->from; ?>
									<div id="fromDatePicker"></div>
								</div>
							</td>
							<td class="daterange"<?php if($this->view->toolbar->getValue('daterange') != 'custom') echo ' style="display:none;"'; ?>>
								<div style="margin-top:0;">
									<?php echo $this->view->translate('TOOLBAR_TO'); ?>
									<?php echo $this->view->toolbar->to; ?>
									<div id="toDatePicker"></div>
								</div>
							</td>
						</tr>
					</table>
				</form>
			</div>
			<?php echo $this->view->toolbar->catid; ?>
		<?php endif;
	}
}
