<?php
/**
* Class inserts necessary code for Images	
*/
class Zend_View_Helper_Images extends Zend_View_Helper_Abstract{

	public function Images() {
		$v = $this->view;
		$folder = 'images';

		$h = '<h3>Item Images</h3>';
		$h .= '<div class="image-gallery">';

		if (!empty($v->images) && count($v->images) > 0) {
			$h .= '<div class="image-grid">';

			foreach ($v->images as $image) {
				$imgUrl =
					$v->baseUrl() . '/media/' .
					$v->escape($v->imagePath) . '/' .
					$folder . '/' .
					$v->escape($image->url);

				$deleteUrl = $v->url([
					'module' => 'default',
					'controller' => 'image',
					'action' => 'delete',
					'folder' => $folder,
					'parentid' => $v->id,
					'id' => $image->id,
					'url' => $v->module . '|' . $v->controller . '|' . $v->action
				]);

				$h .= '<div class="image">';
				$h .= '<a href="' . $v->escape($imgUrl) . '" target="_blank">';
				$h .= '<img src="' . $v->escape($imgUrl) . '" ' .
					'alt="' . $v->escape($image->title) . '" ' .
					'class="image-thumbnail" />';
				$h .= '</a>';

				$h .= '<div class="image-caption">' .
					$v->escape($image->title) .
					'</div>';

				$h .= '<div class="image-actions">';
				$h .= '<a href="' . $v->escape($deleteUrl) . '" ' .
					'onclick="return confirm(\'Are you sure you want to delete this image?\');" ' .
					'class="delete-link">Delete</a>';
				$h .= '</div>';

				$h .= '</div>';
			}

			$h .= '</div>';
		} else {
			$h .= '<p>No images available for this item.</p>';
		}

		$h .= '</div>'; // image-gallery

		// Upload form
		$uploadUrl = $v->url([
			'module' => 'default',
			'controller' => 'image',
			'action' => 'upload'
		]);

		$h .= '<form action="' . $v->escape($uploadUrl) . '" ' .
			'method="post" enctype="multipart/form-data">';

		$h .= '<label for="imageUpload">Upload New Image:</label>';
		$h .= '<input type="file" name="image[]" multiple />';

		$h .= '<label for="subfolder">Select Subfolder:</label>';
		$h .= '<select name="subfolder" id="subfolder" required>';
		$h .= '<option value="0">Main Folder</option>';

		if (!empty($v->subfolders)) {
			foreach ($v->subfolders as $subfolder) {
				$h .= '<option value="' . $v->escape($subfolder) . '">' .
					$v->escape($subfolder) .
					'</option>';
			}
		}

		$h .= '</select>';

		$h .= '<input type="hidden" name="controller" value="item" />';
		$h .= '<input type="hidden" name="module" value="items" />';
		$h .= '<input type="hidden" name="folder" value="' . $folder . '" />';
		$h .= '<input type="hidden" name="item_id" value="' .
			$v->escape($v->id) . '" />';

		$h .= '<button type="submit">Upload</button>';
		$h .= '</form>';

		return $h;
	}
}
