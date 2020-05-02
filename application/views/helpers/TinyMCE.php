<?php
/**
* Class inserts neccery code for initialize file manager TinyMCE
*/
class Zend_View_Helper_TinyMCE extends Zend_View_Helper_Abstract{

	public function TinyMCE() {
		$this->view->headScript()->appendFile($this->view->baseUrl().'/library/TinyMCE/tinymce.min.js');

		$this->view->headScript()->captureStart(); ?>
            var typingTimer;
            var contentCache = [];
            tinymce.init({
                selector: '.editor',
                language: 'de',
                menubar: false,
                height: 450,
                valid_elements: 'a[href|target=_blank],p[style],em,h1,h2,h3,h4,h5,strong/b,br,ul,ol,li',
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
