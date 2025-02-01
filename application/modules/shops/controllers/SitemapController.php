<?php

class Shops_SitemapController extends Zend_Controller_Action
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

		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');

		//Check if the directory is writable
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'item', $this->_flashMessenger);
		//if($this->view->id) $this->view->dirwritable = $this->_helper->Directory->isWritable($this->view->id, 'media', $this->_flashMessenger);

		$this->cart = new Shops_Model_ShoppingCart();

		// Make the cart accessible in all views
		$this->view->cart = $this->cart;
	}

	public function indexAction()
	{
		// Disable the view renderer (we're outputting XML directly)
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout->disableLayout();

		// Set the content type to XML
		$this->getResponse()->setHeader('Content-Type', 'application/xml');

		// Initialize the base URL
		$baseUrl = $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost();

		// Begin XML output
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		// Add the homepage
		echo '<url>';
		echo '<loc>' . $baseUrl . '/</loc>';
		echo '<changefreq>daily</changefreq>';
		echo '<priority>1.0</priority>';
		echo '</url>';

		// Get shop details from the registry
		$shop = Zend_Registry::get('Shop');

		// Add shop's home URL
		echo '<url>';
		echo '<loc>' . $shop['url'] . '</loc>';
		echo '<changefreq>weekly</changefreq>';
		echo '<priority>0.8</priority>';
		echo '</url>';

		// Fetch slugs for the current shop
		$slugTable = new Zend_Db_Table('slug');
		$slugs = $slugTable->fetchAll(['shopid = ?' => $shop['id']]);

		// Organize slugs into a dictionary with entityid as the key
		$slugDict = [];
		foreach ($slugs as $slug) {
			$slugDict[$slug['entityid']] = $slug;
		}

		// Helper function to build the full slug path using entityid with parent-child hierarchy
		$getFullSlug = function ($item, $slugDict) {
			$slug = $item['slug'];
			
			// Continue while the item has a parent
			while ($item['parentid']) {
				// Find the parent item in the slugDict
				if (isset($slugDict[$item['parentid']])) {
					$parentItem = $slugDict[$item['parentid']];
					// Prepend the parent's slug to the current slug
					$slug = $parentItem['slug'] . '/' . $slug;
					$item = $parentItem;
				} else {
					break; // Parent not found, stop the loop
				}
			}
			
			return $slug;
		};

		// Loop through categories and add to the sitemap
		foreach ($slugs as $slug) {
			if (!empty($slug['slug'])) { // Ensure slug exists
				$fullSlug = $getFullSlug($slug, $slugDict); // Get full slug path
				$slugUrl = $shop['url'] . '/' . $fullSlug;
				echo '<url>';
				echo '<loc>' . htmlspecialchars($slugUrl) . '</loc>';
				echo '<changefreq>weekly</changefreq>';
				echo '<priority>0.6</priority>';
				echo '</url>';
			}
		}

		// Get items for this shop
		//$itemTable = new Zend_Db_Table('item');
		//$items = $itemTable->fetchAll(['shopid = ?' => $shop['id']]);

		// Loop through items and add to the sitemap
		/*foreach ($items as $item) {
			$itemUrl = $shop['url'] . '/' . $category['slug'] . '/' . $item['slug'];
			echo '<url>';
			echo '<loc>' . htmlspecialchars($itemUrl) . '</loc>';
			echo '<changefreq>monthly</changefreq>';
			echo '<priority>0.5</priority>';
			echo '</url>';
		}*/

		// Close the XML tags
		echo '</urlset>';
	}
}
