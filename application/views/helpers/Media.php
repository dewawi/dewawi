<?php

class Zend_View_Helper_Media extends Zend_View_Helper_Abstract
{
	public function Media(array $config = [])
	{
		$v = $this->view;

		$module = (string)($config['module'] ?? $v->module ?? '');
		$controller = (string)($config['controller'] ?? $v->controller ?? '');
		$type = (string)($config['type'] ?? 'image');
		$path = trim((string)($config['path'] ?? $type), '/');
		$parentId = (int)($config['parentid'] ?? $v->id ?? 0);

		$media = is_array($v->media ?? null) ? $v->media : [];
		$media = $this->filterMedia($media, $module, $controller, $type, $parentId);

		$subfolders = is_array($v->subfolders[$path] ?? null) ? $v->subfolders[$path] : [];

		ob_start();
		?>

		<div class="media-gallery">
			<?php if (count($media) > 0): ?>
				<div class="image-grid sortable"
					 data-sort-url="<?php echo $v->url([
						 'module' => 'default',
						 'controller' => 'media',
						 'action' => 'sort',
					 ]); ?>">
					<?php foreach ($media as $file): ?>
						<?php $url = $this->mediaUrl($v, $path, $file); ?>

						<div class="image" data-id="<?php echo (int)($file['id'] ?? 0); ?>">
							<a href="<?php echo $url; ?>" target="_blank">
								<img src="<?php echo $url; ?>"
									 alt="<?php echo $v->escape($file['title'] ?? ''); ?>"
									 class="image-thumbnail" />
							</a>

							<div class="image-caption">
								<strong><?php echo $v->escape($file['title'] ?? ''); ?></strong>
								<?php echo $v->escape($file['url'] ?? ''); ?>
							</div>

							<div class="image-actions">
								<a href="<?php echo $this->deleteUrl($v, $parentId, $file, $path); ?>"
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

		<form class="media-upload-form"
			action="<?php echo $v->url([
				'module' => 'default',
				'controller' => 'media',
				'action' => 'upload',
			]); ?>"
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

			<input type="hidden" name="module" value="<?php echo $v->escape($module); ?>" />
			<input type="hidden" name="controller" value="<?php echo $v->escape($controller); ?>" />
			<input type="hidden" name="parentid" value="<?php echo $parentId; ?>" />
			<input type="hidden" name="type" value="<?php echo $v->escape($type); ?>" />
			<input type="hidden" name="path" value="<?php echo $v->escape($path); ?>" />
			<input type="hidden" name="admin" value="1" />

			<button type="submit">Upload</button>
		</form>

		<?php
		return ob_get_clean();
	}

	protected function filterMedia(array $media, string $module, string $controller, string $type, int $parentId): array
	{
		return array_values(array_filter($media, function ($file) use ($module, $controller, $type, $parentId) {
			return ($file['module'] ?? '') === $module
				&& ($file['controller'] ?? '') === $controller
				&& (int)($file['parentid'] ?? 0) === $parentId
				&& ($file['type'] ?? '') === $type;
		}));
	}

	protected function mediaUrl($v, string $path, array $file): string
	{
		$mediaPath = $this->getMediaPath($v);

		return $v->baseUrl()
			. '/media/'
			. $mediaPath
			. '/'
			. trim($path, '/')
			. '/'
			. ltrim((string)($file['url'] ?? ''), '/');
	}

	protected function getMediaPath($v): string
	{
		$mediaPath = trim((string)($v->mediaPath ?? ''), '/');

		if ($mediaPath !== '') {
			return $mediaPath;
		}

		$clientId = (int)($v->client['id'] ?? $v->user['clientid'] ?? 0);
		$client = (string)$clientId;

		$dir1 = substr($client, 0, 1);
		$dir2 = strlen($client) > 1 ? substr($client, 1, 1) : '0';

		return $dir1 . '/' . $dir2 . '/' . $client;
	}

	protected function deleteUrl($v, int $parentId, array $file, string $path): string
	{
		return $v->url([
			'module' => 'default',
			'controller' => 'media',
			'action' => 'delete',
			'parentid' => $parentId,
			'id' => $file['id'] ?? 0,
			'path' => $path,
			'url' => $v->module . '|' . $v->controller . '|' . $v->action,
		]);
	}
}
