<?php

class Zend_View_Helper_TinyMCE extends Zend_View_Helper_Abstract
{
	public function TinyMCE()
	{
		$language = Zend_Registry::get('Zend_Locale');

		$this->view->headScript()->appendFile(
			$this->view->baseUrl() . '/library/TinyMCE/tinymce.min.js'
		);

		$this->view->headScript()->captureStart();
		?>
		var contentCache = {};

		tinymce.init({
			selector: '.editor',
			language: '<?php echo substr($language, 0, 2); ?>',
			menubar: false,
			height: 450,
			valid_elements: 'a[href|target=_blank],p[style],em,div[id|class|style],h1[id|class|style],h2[id|class|style],h3[id|class|style],h4[id|class|style],h5[id|class|style],strong/b,br,ul,ol,li,img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name]',
			toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright | bullist numlist | link | code',
			plugins: 'lists link code',
			contextmenu: '',
			setup: function(editor) {
				editor.on('init', function() {
					contentCache[editor.id] = editor.getContent();

					if(editor.targetElm.readOnly || editor.targetElm.disabled) {
						editor.mode.set('readonly');
						editor.getContainer().classList.add('dw-tinymce-readonly');
						editor.getBody().style.backgroundColor = '#f3f5f7';
						editor.getBody().style.color = '#6b7280';
					}
				});

				editor.on('input change', function() {
					scheduleEditorSave(editor);
				});
			}
		});

		function scheduleEditorSave(editor) {
			clearTimeout(editor._dewawiSaveTimer);

			editor._dewawiSaveTimer = setTimeout(function() {
				saveEditor(editor);
			}, 1000);
		}

		function saveEditor(editor) {
			var $field = $(editor.targetElm);

			if(editor.mode.isReadOnly()) return;
			if($field.closest('form').is('[data-autosave="false"]')) return;

			var value = editor.getContent();

			if(value === contentCache[editor.id]) return;

			Dewawi.setDirty(true);

			var data = {};
			data[editor.targetElm.name] = value;

			var response = edit(data);

			if(response && response.ok !== false) {
				contentCache[editor.id] = value;
			}
		}
		<?php
		$this->view->headScript()->captureEnd();
	}
}
