<?php
/**
* Class inserts neccery code for initialize file manager TinyMCE
*/
class Zend_View_Helper_TinyMCE extends Zend_View_Helper_Abstract{

	public function TinyMCE() {
		$language = Zend_Registry::get('Zend_Locale');

		$this->view->headScript()->appendFile($this->view->baseUrl().'/library/TinyMCE/tinymce.min.js');

		$this->view->headScript()->captureStart(); ?>
			var typingTimer;
			var contentCache = [];
			tinymce.init({
				selector: '.editor',
				language: '<?php echo substr($language, 0, 2) ?>',
				menubar: false,
				height: 450,
				valid_elements: 'a[href|target=_blank],p[style],em,div[id|class|style],h1[id|class|style],h2[id|class|style],h3[id|class|style],h4[id|class|style],h5[id|class|style],strong/b,br,ul,ol,li,img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name]',
				toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright | bullist numlist | link | code',
				plugins: 'lists link code',
				contextmenu: '',
				setup: function(editor) {
					editor.on('init', function(e) {
						//Save the actual content to cache to check if it is changed
						contentCache[editor.targetElm.name] = editor.getContent();
					});
					editor.on('keyup', function(e) {
						clearTimeout(typingTimer);
						typingTimer = setTimeout(function () {
							saveEditor(editor);
						}, 2000);
					});
					editor.on('change', function(e) {
						saveEditor(editor);
					});
					editor.on('click', function(e) {
						saveEditor(editor);
					});
				}
			});
			function saveEditor(editor) {
				var data = {};
				data[editor.targetElm.name] = editor.getContent();
				//Save the data if the content is changed
				//Check if the content has changed to prevent unnecessary requests
				if(data[editor.targetElm.name] != contentCache[editor.targetElm.name]) {
					contentCache[editor.targetElm.name] = data[editor.targetElm.name];
					//console.log(data);
					edit(data);
				}
			}
		<?php $this->view->headScript()->captureEnd();
	}
}
