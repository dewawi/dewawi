<?php

class MediaController extends Zend_Controller_Action
{
	protected $_date = null;

	protected $_user = null;

	/**
	 * FlashMessenger
	 *
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	public function init()
	{
		$params = $this->_getAllParams();

		$this->_date = date('Y-m-d H:i:s');

		$this->view->id = isset($params['id']) ? $params['id'] : 0;
		$this->view->action = $params['action'];
		$this->view->controller = $params['controller'];
		$this->view->module = $params['module'];
		$this->view->client = Zend_Registry::get('Client');
		$this->view->user = $this->_user = Zend_Registry::get('User');
		$this->view->mainmenu = $this->_helper->MainMenu->getMainMenu();

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);
		if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'export', $this->_flashMessenger);
	}

	public function uploadAction()
	{
		$request = $this->getRequest();
		$id = $this->_getParam('id', 0);
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();

		if ($request->isPost()) {
			$data = $request->getPost();
			$type = $data['type'];
			$folder = $data['folder'];
			$subfolder = $data['subfolder'];

			// Get media path
			$clientid = $this->view->client['id'];
			$dir1 = substr($clientid, 0, 1);
			if (strlen($clientid) > 1) {
				$dir2 = substr($clientid, 1, 1);
			} else {
				$dir2 = '0';
			}
			$mediaPath = $dir1 . '/' . $dir2 . '/' . $clientid;

			// Check if files are uploaded and the necessary data is provided
			if (!empty($_FILES['media']['name'][0]) && $data['controller'] && $data['module']) {
				foreach ($_FILES['media']['name'] as $key => $mediaName) {
					if (!empty($mediaName)) {
						// Determine the directory to upload to
						if (empty($subfolder)) {
							$uploadDir = BASE_PATH . '/media/' . $mediaPath . '/' . $folder . '/';
							$mediaUrl = $mediaName; // Media URL without subfolder
						} else {
							$uploadDir = BASE_PATH . '/media/' . $mediaPath . '/' . $folder . '/' . $subfolder . '/';
							$mediaUrl = $subfolder . '/' . $mediaName; // Media URL with subfolder
						}

						$uploadFile = $uploadDir . basename($mediaName);

						// Attempt to move the uploaded file
						if (move_uploaded_file($_FILES['media']['tmp_name'][$key], $uploadFile)) {
							$mediaModel = new Application_Model_DbTable_Media();
							// Determine the new ordering value
							$maxOrdering = $mediaModel->getMaxOrdering($id, $type);
							$newOrdering = $maxOrdering + 1;

							// Save to database
							$media = array();
							$media['parentid'] = $id;
							$media['module'] = $data['module'];
							$media['controller'] = $data['controller'];
							$media['type'] = $type;
							$media['title'] = $mediaName;
							$media['url'] = $mediaUrl;
							$media['ordering'] = $newOrdering;
							$mediaModel->addMedia($media);
						} else {
							$this->_helper->flashMessenger->addMessage('Failed to upload media: ' . $mediaName);
						}
					}
				}

				$this->_helper->flashMessenger->addMessage('Media uploaded successfully.');
			} else {
				$this->_helper->flashMessenger->addMessage('No media selected for upload.');
			}

			// Redirect after uploading
			$module = isset($data['admin']) ? 'admin' : $data['module'];
			$this->_helper->redirector->gotoSimple('edit', $data['controller'], $module, array('id' => $id));
		}
	}

	public function deleteAction()
	{
		$id = $this->_getParam('id', 0);
		$folder = $this->_getParam('folder');
		$parentid = $this->_getParam('parentid', 0);
		$mediaModel = new Application_Model_DbTable_Media();

		// Fetch the media data
		$media = $mediaModel->fetchRow('id = ' . (int)$id);
		if ($media) {
			// Construct the full path to the media
			$clientid = $this->view->client['id'];
			$dir1 = substr($clientid, 0, 1);
			$dir2 = strlen($clientid) > 1 ? substr($clientid, 1, 1) : '0';
			$mediaPath = BASE_PATH . '/media/' . $dir1 . '/' . $dir2 . '/' . $clientid . '/' . $folder . '/' . $media->url;

			// Delete the media file
			if (file_exists($mediaPath)) {
				unlink($mediaPath);
			}

			// Remove the record from the database
			$mediaModel->deleteMedia($id);

			$this->_helper->flashMessenger->addMessage('Media deleted successfully.');
		} else {
			$this->_helper->flashMessenger->addMessage('Media not found.');
		}

		//Get target url
		$target = $this->_getParam('url', null);

		//Redirect if url is defined
		if($target) {
			$url = explode("|", $this->_getParam('url', null));
			$this->_helper->redirector->gotoSimple($url[2], $url[1], $url[0], array('id' => $parentid));
		}
	}
}
