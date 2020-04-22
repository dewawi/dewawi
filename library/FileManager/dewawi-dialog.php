<?php
//Modifications to be included in dialog.php
?>
        <!--CSS code for DEWAWI-->
        <style>
            .container-fluid {
                padding: 0 20px !important;
            }
            .featherlight {
              display: none!important;
            }
            /*.ff-item-type-1 {
              display: none!important;
            }
            .navbar + div.row-fluid {
              display: none!important;
            }*/
            div.entire.types {
              display: none!important;
            }
            div.view-controller {
                float: right!important;
                text-align: right;
            }
            .modal-body {
                max-height: 550px;
            }
            /*.modal.fade.in {
                top: 5%;
            }*/
        </style>

        <!--Javascript code for DEWAWI-->
        <script type="text/javascript">
            $(document).ready(function(){
	            /*$('html').on('click', function() {
		            console.log(this);
	            });*/
                if(window.parent.controller == 'item') $('li').remove('li.ff-item-type-1');
                $('#multiple-selection').appendTo($('.filters .row-fluid'));
                $('#multiple-selection').addClass('span6');
	            $('#main-item-container').on('click', 'figure', function() {
                    //$.featherlight.close()
		            data = {};
		            data['image'] = $(this).data('name');
		            window.parent.edit(data);
                    $("input#image", parent.document).val(data['image']);
                    $("img#preview", parent.document).attr('src', window.parent.baseUrl+'/files/images/'+data['image']);
	            });
            });
        </script>

        <?php //add to line 360 ?>
        <?php //include 'dewawi-dialog.php'; ?>


        <?php //add to line 1176 ?>
        <?php //echo $filename.'.'.$file_array['extension'];?>
