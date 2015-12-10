<?php
/**
* Class inserts neccery code for initialize file manager elFinder	
*/
class Zend_View_Helper_CKEditor extends Zend_View_Helper_Abstract{

	public function CKEditor() {
		$this->view->headScript()->appendFile($this->view->baseUrl().'/library/CKEditor/ckeditor.js')
					->appendFile($this->view->baseUrl().'/library/CKEditor/adapters/jquery.js');

		if($this->view->controller == "email") $width = 600;
		else $width = 455;

		$this->view->headScript()->captureStart(); ?>
		$(document).ready(function(){
			//Editor
			var editorConfig =
			{
				height: 225,
				width: <?php echo $width; ?>,
				language: 'de',
				linkShowAdvancedTab: false,
				scayt_autoStartup: true,
				enterMode: Number(2)
			};
			$('textarea.editor').ckeditor(editorConfig);

			var header = CKEDITOR.instances.header;
			header.on('change', function(e) {
				if(header.checkDirty()) {
					isDirty = true;
					var data = {};
					data[this.name] = $('#header').val();
					edit(data);
					header.resetDirty();
				}
			});

			var footer = CKEDITOR.instances.footer;
			footer.on('change', function(e) {
				if(footer.checkDirty()) {
					isDirty = true;
					var data = {};
					data[this.name] = $('#footer').val();
					edit(data);
					footer.resetDirty();
				}
			});


			/*for(var i in CKEDITOR.instances) {
				CKEDITOR.instances[i].resetDirty();
			}

			//Check data every 5 seconds
			setInterval(function() {
				if(isDirty) {
					save();
					$('#loading').hide();
					for(var i in CKEDITOR.instances) {
						CKEDITOR.instances[i].resetDirty();
						console.log(CKEDITOR.instances[i].checkDirty());
					}
				} else {
					for(var i in CKEDITOR.instances) {
						if(typeof CKEDITOR.instances[i].checkDirty() !== 'undefined') {
							isDirty = CKEDITOR.instances[i].checkDirty();
						}
						CKEDITOR.instances[i].resetDirty();
						if(isDirty) break;
					}
				}
			}, 1000); //5 seconds*/
		});
		<?php $this->view->headScript()->captureEnd();
	}
}
