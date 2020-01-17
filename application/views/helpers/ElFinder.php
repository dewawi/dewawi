<?php
/**
* Class inserts neccery code for initialize file manager elFinder	
*/
class Zend_View_Helper_ElFinder extends Zend_View_Helper_Abstract{

	public function ElFinder() {

		$elfinder_base_uri = $this->view->baseUrl().'/library/elFinder/';

		$this->view->headLink()->appendStylesheet($elfinder_base_uri.'css/elfinder.min.css');
		$this->view->headScript()->appendFile($elfinder_base_uri.'js/i18n/elfinder.de.js');
		$this->view->headScript()->prependFile($elfinder_base_uri.'js/elfinder.min.js');

        $url = $this->view->baseUrl()."/library/elFinder/php/connector.minimal.php?directory='+directory";
        if(is_link(BASE_PATH.'/library')) {
            $path = explode('/', BASE_PATH);
            $url = $url."+'&extrapath=".end($path);
        }

		$this->view->headScript()->captureStart(); ?>
		function elfinder() {
			var directory = 0;
			if(controller == 'contact') directory = id;
			else if($('#contactid').val()) directory = $('#contactid').val();
			else if($('#customerid').val()) directory = $('#customerid').val();
			$('#elfinder').elfinder({
				lang: 'de',
				url: '<?php echo $url; ?>',
				//customData: { contactid : directory },
				rememberLastDir: false,
				allowShortcuts: false,
				defaultView: 'list',
				getFileCallback: function(file, fm) {
					$('#elfinder').elfinder('instance').exec('download');
				},
				uiOptions : {
					// toolbar configuration
					toolbar : [
						['back', 'forward'],
						['reload'],
						//['home', 'up'],
						['mkdir', 'mkfile', 'upload'],
						['open', 'download', 'getfile'],
						//['info'],
						//['quicklook'],
						['copy', 'cut', 'paste'],
						['rm'],
						['duplicate', 'rename', 'edit', 'resize'],
						//['extract', 'archive'],
						['view'],
						['help'],
						['search']
					]
				}
			});
		}
		<?php $this->view->headScript()->captureEnd();
	}
}
