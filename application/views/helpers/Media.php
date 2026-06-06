<?php

class Zend_View_Helper_Media extends Zend_View_Helper_Abstract
{
	public function Images(array $config = [])
	{
		$v = $this->view;

		$type = $config['type'] ?? 'image';
		$folder = $config['folder'] ?? $v->controller;
		$parentId = $config['parentid'] ?? $v->id;

		ob_start();
		?>
		<h3>Category Images</h3>

		<div class="image-gallery">
			<?php if (count($v->media) > 0): ?>
				<form id="image" enctype="application/x-www-form-urlencoded" action="" method="post">
					<div class="image-grid">
						<?php foreach ($v->media as $file): ?>
							<?php if (($file['type'] ?? '') != 'image') continue; ?>

							<div class="image">
								<a href="<?php echo $this->mediaUrl($v, $folder, $file); ?>" target="_blank">
									<img src="<?php echo $this->mediaUrl($v, $folder, $file); ?>"
										 alt="<?php echo $v->escape($file['title'] ?? ''); ?>"
										 class="image-thumbnail" />
								</a>

								<div class="image-caption">
									<?php echo $v->imageForms[$file['id']]->title; ?>
									(<?php echo $v->escape($file['url']); ?>)
								</div>

								<div class="image-actions">
									<a href="<?php echo $this->deleteUrl($v, $folder, $file); ?>"
									   onclick="return confirm('Are you sure you want to delete this image?');"
									   class="delete-link">Delete</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</form>
			<?php else: ?>
				<p>No images available for this category.</p>
			<?php endif; ?>
		</div>

		<form action="<?php echo $v->url(array('module' => 'default', 'controller' => 'media', 'action' => 'upload')); ?>"
			  method="post"
			  enctype="multipart/form-data">

			<label for="imageUpload">Upload New Image:</label>
			<input type="file" name="media[]" multiple />

			<label for="subfolder">Select Subfolder:</label>
			<select name="subfolder" id="subfolder" required>
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
			<input type="hidden" name="module" value="<?php echo $v->escape($v->module); ?>" />
			<input type="hidden" name="folder" value="<?php echo $folder; ?>" />
			<input type="hidden" name="category_id" value="<?php echo $v->escape($v->id); ?>" />

			<button type="submit">Upload</button>
		</form>
		<?php

		return ob_get_clean();
	}

	protected function mediaUrl($v, $folder, array $file)
	{
		return $v->baseUrl()
			. '/media/'
			. $v->escape($v->mediaPath)
			. '/'
			. $folder
			. '/'
			. $v->escape($file['url']);
	}

	protected function deleteUrl($v, $folder, array $file)
	{
		return $v->url(array(
			'module' => 'default',
			'controller' => 'media',
			'action' => 'delete',
			'folder' => $folder,
			'parentid' => $v->id,
			'id' => $file['id'],
			'url' => $v->module . '|' . $v->controller . '|' . $v->action,
		));
	}
}
