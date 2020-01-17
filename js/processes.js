$(document).ready(function(){
	$('#data').on('click', 'td.edit', function() {
		//alert('asd');
	});
	$('#positions').on('change', 'tr select.itemType', function() {
		var id = $(this).closest('tr.wrap').find('input.id').val();
		var type = $(this).val();
		$('.position'+id+'.stockItem').hide();
		$('.position'+id+'.deliveryItem').hide();
		$('.position'+id+'.service').hide();
		if(type == 'stockItem') {
			$('.position'+id+'.stockItem').show();
			$('.position'+id+' td.id, .position'+id+' td.ordering, .position'+id+' td.notes, .position'+id+' td.buttons').attr('rowspan', '2');
		} else if(type == 'deliveryItem') {
			$('.position'+id+'.deliveryItem').show();
			$('.position'+id+' td.id, .position'+id+' td.ordering, .position'+id+' td.notes, .position'+id+' td.buttons').attr('rowspan', '2');
		} else if(type == 'service') {
			$('.position'+id+'.service').show();
			$('.position'+id+' td.id, .position'+id+' td.ordering, .position'+id+' td.notes, .position'+id+' td.buttons').attr('rowspan', '1');
		} else {
			$('.position'+id+' td.id, .position'+id+' td.ordering, .position'+id+' td.notes, .position'+id+' td.buttons').attr('rowspan', '1');
		}
	});
	$('#details').on('change', '#deliverystatus', function() {
		var status = $(this).val();
		$('#details td.deliverystatus').removeClass('deliveryIsWaiting partialDelivered deliveryCompleted').addClass(status);
	});
	$('#details').on('change', '#supplierorderstatus', function() {
		var status = $(this).val();
		$('#details td.supplierorderstatus').removeClass('supplierNotOrdered supplierOrdered supplierPayed').addClass(status);
	});
	$('#positions').on('change', 'tr select.deliveryStatus', function() {
		var id = $(this).closest('tr.wrap').find('input.id').val();
		var status = $(this).val();
		$('.position'+id+' td.deliverystatus').removeClass('deliveryIsWaiting partialDelivered deliveryCompleted').addClass(status);
	});
	$('#positions').on('change', 'tr select.supplierOrderStatus', function() {
		var id = $(this).closest('tr.wrap').find('input.id').val();
		var status = $(this).val();
		$('.position'+id+' td.supplierorderstatus').removeClass('supplierNotOrdered supplierOrdered supplierPayed').addClass(status);
	});
	$('.editpositionsseparately').on('change', '#editpositionsseparately', function() {
		if($("#editpositionsseparately").is(':checked')) {
			$("#positions").removeClass("disabled");
			$("#details").addClass("disabled");
			$("#details").find('input, select, textarea').attr("disabled", "disabled");
			$("#positions").find('input, select, textarea').removeAttr('disabled');
		} else {
			$("#details").removeClass("disabled");
			$("#positions").addClass("disabled");
			$("#details").find('input, select, textarea').removeAttr('disabled');
			$("#positions").find('input, select, textarea').attr("disabled", "disabled");
		}
	});
});
/*
	$(document).on('click', 'button.close', function() {
		var id = $(this).closest('tr').find('input#id').val();
		close(id);
	});
	$(document).on('click', 'button.details', function() {
		var id = $(this).closest('tr').find('input#id').val();
		$('tr#process'+id+' tr.details').toggle();
	});
	$(document).on('click', 'button.positions', function() {
		$(this).closest('tr').next().next().toggle();
	});
*/
