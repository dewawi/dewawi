<?php
/**
* Class inserts neccery code for initialize file manager TinyMCE
*/
class Zend_View_Helper_TinyMCE extends Zend_View_Helper_Abstract{

	public function TinyMCE() {
		$this->view->headScript()->appendFile($this->view->baseUrl().'/library/TinyMCE/tinymce.min.js');

		$this->view->headScript()->captureStart(); ?>
          tinymce.init({
            selector: '.editor',
            language: 'de',
            menubar: false,
            toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright | bullist numlist | link | code',
            plugins: 'lists link code',
            contextmenu: '',
              setup: function(editor) {
                editor.on('change keyup paste', function(e) {
                   var data = {};
                   data[editor.targetElm.name] = editor.getContent();
				   edit(data);
				   //console.log(edit(data));
                   //console.log(data);
                   //console.log(editor);
                });
              }
          });
		<?php $this->view->headScript()->captureEnd();
	}
}
