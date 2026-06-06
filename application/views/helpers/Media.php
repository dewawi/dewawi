<?php

class Zend_View_Helper_Media extends Zend_View_Helper_Abstract
{
	public function Media(array $config = [])
	{
		$v = $this->view;

		$type = $config['type'] ?? 'image';
		$folder = $config['folder'] ?? $v->controller;
		$controller = $config['controller'] ?? $v->controller;
		$parentId = (int)($config['parentid'] ?? $v->id ?? 0);

		$media = is_array($v->media ?? null) ? $v->media : [];

		if (empty($media) && isset($v->form)) {
			$image = (string)$v->form->getValue('image');

			if ($image !== '') {
				$media[] = [
					'id' => $parentId,
					'type' => $type,
					'url' => $image,
					'title' => (string)$v->form->getValue('title'),
				];
			}
		}

		$subfolders = is_array($v->subfolders[$folder] ?? null) ? $v->subfolders[$folder] : [];

		ob_start();
		?>

		<div class="media-gallery">
			<?php if (count($media) > 0): ?>
				<div class="image-grid">
					<?php foreach ($media as $file): ?>
						<?php if (($file['type'] ?? '') !== $type) continue; ?>

						<div class="image">
							<a href="<?php echo $this->mediaUrl($v, $folder, $file); ?>" target="_blank">
								<img src="<?php echo $this->mediaUrl($v, $folder, $file); ?>"
									 alt="<?php echo $v->escape($file['title'] ?? ''); ?>"
									 class="image-thumbnail" />
							</a>

							<div class="image-caption">
								<?php echo $v->escape($file['title'] ?? ''); ?>
								(<?php echo $v->escape($file['url'] ?? ''); ?>)
							</div>

							<div class="image-actions">
								<a href="<?php echo $this->deleteUrl($v, $folder, $parentId, $file); ?>"
								   onclick="return confirm('Are you sure you want to delete this file?');"
								   class="delete-link">Delete</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p>No media available.</p>
			<?php endif; ?>
		</div>

		<form action="<?php echo $v->url(['module' => 'default', 'controller' => 'media', 'action' => 'upload']); ?>"
			  method="post"
			  enctype="multipart/form-data">

			<input type="file" name="media[]" multiple />

			<select name="subfolder">
				<option value="0">Main Folder</option>
				<?php foreach ($subfolders as $subfolder): ?>
					<option value="<?php echo $v->escape($subfolder); ?>">
						<?php echo $v->escape($subfolder); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="hidden" name="admin" value="1" />
			<input type="hidden" name="type" value="<?php echo $v->escape($type); ?>" />
			<input type="hidden" name="module" value="<?php echo $v->escape($v->module); ?>" />
			<input type="hidden" name="controller" value="<?php echo $v->escape($controller); ?>" />
			<input type="hidden" name="folder" value="<?php echo $v->escape($folder); ?>" />
			<input type="hidden" name="parentid" value="<?php echo $parentId; ?>" />

			<button type="submit">Upload</button>
		</form>

		<?php
		return ob_get_clean();
	}

	protected function mediaUrl($v, string $folder, array $file): string
	{
		return $v->baseUrl()
			. '/media/'
			. $v->escape($v->mediaPath)
			. '/'
			. $folder
			. '/'
			. $v->escape($file['url'] ?? '');
	}

	protected function deleteUrl($v, string $folder, int $parentId, array $file): string
	{
		return $v->url([
			'module' => 'default',
			'controller' => 'media',
			'action' => 'delete',
			'folder' => $folder,
			'parentid' => $parentId,
			'id' => $file['id'] ?? 0,
			'url' => $v->module . '|' . $v->controller . '|' . $v->action,
		]);
	}
}
