<?php

class Zend_View_Helper_Downloads extends Zend_View_Helper_Abstract
{
	public function Downloads()
	{
		$v = $this->view;

		$type = 'download';
		$folder = 'downloads';

		ob_start();
		?>
		<h3>Category Downloads</h3>

		<div class="download-list">
			<?php if (count($v->media) > 0): ?>
				<form id="download" enctype="application/x-www-form-urlencoded" action="" method="post">
					<ul>
						<?php foreach ($v->media as $file): ?>
							<?php if ($file->type != 'download') continue; ?>

							<li class="download">
								<a href="<?php echo $this->mediaUrl($v, $folder, $file); ?>" target="_blank">
									<?php echo $v->escape($file->title); ?>
								</a>
								| <?php echo $v->escape($file->url); ?>

								<div class="download-actions">
									<a href="<?php echo $this->deleteUrl($v, $folder, $file); ?>"
									   onclick="return confirm('Are you sure you want to delete this download?');"
									   class="delete-link">Delete</a>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</form>
			<?php else: ?>
				<p>No downloads available for this category.</p>
			<?php endif; ?>
		</div>

		<form action="<?php echo $v->url(array('module' => 'default', 'controller' => 'media', 'action' => 'upload')); ?>"
			  method="post"
			  enctype="multipart/form-data">

			<label for="downloadUpload">Upload New Download:</label>
			<input type="file" name="media[]" multiple />

			<label for="subfolderDownloads">Select Subfolder:</label>
			<select name="subfolder" id="subfolderDownloads" required>
				<option value="0">Main Folder</option>
				<?php foreach ($v->subfolders[$folder] as $subfolder): ?>
					<option value="<?php echo $v->escape($subfolder); ?>">
						<?php echo $v->escape($subfolder); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="hidden" name="admin" value="1" />
			<input type="hidden" name="type" value="<?php echo $type; ?>" />
			<input type="hidden" name="controller" value="category" />
			<input type="hidden" name="module" value="<?php echo $this->getModule($v); ?>" />
			<input type="hidden" name="folder" value="<?php echo $folder; ?>" />
			<input type="hidden" name="category_id" value="<?php echo $v->escape($v->id); ?>" />

			<button type="submit">Upload</button>
		</form>
		<?php

		return ob_get_clean();
	}

	protected function mediaUrl($v, $folder, $file)
	{
		return $v->baseUrl()
			. '/media/'
			. $v->escape($v->mediaPath)
			. '/'
			. $folder
			. '/'
			. $v->escape($file->url);
	}

	protected function deleteUrl($v, $folder, $file)
	{
		return $v->url(array(
			'module' => 'default',
			'controller' => 'media',
			'action' => 'delete',
			'folder' => $folder,
			'parentid' => $v->id,
			'id' => $file->id,
			'url' => $v->module . '|' . $v->controller . '|' . $v->action,
		));
	}

	protected function getModule($v)
	{
		$type = $v->form->getValue('type');

		if ($type == 'shop') {
			return 'shops';
		}

		if ($type == 'item') {
			return 'items';
		}

		if ($type == 'contact') {
			return 'contacts';
		}

		return '';
	}
}
