jQuery(document).ready(function($) {

	$('.wicket_acc_group_child').select2();
	//$('select#wicket_acc_icon_fld').select2();

	$('nav.woocommerce-MyAccount-navigation ul').sortable();
	$( "nav.woocommerce-MyAccount-navigation ul" ).disableSelection();

	$('#wicket_acc_icon_fld').change(function(){
		
		var value = $(this).children('option:selected').text();
		console.log(value);
		$('div.dispay_icon').addClass( value );

	});

});

jQuery(document).ready(function($) {
	change_meta_fields();
	$(document).on('ready' , change_meta_fields );
	$(document).on('change' , $("#wicket_acc_endpType_fld") , change_meta_fields );

	function change_meta_fields() {
		var type = $('#wicket_acc_endpType_fld').children("option:selected").val();

		if ( type === 'sendpoint') {

			$('.admin_notes_fld').show();

			$('.link_fld').hide();

			$('.placement_fld').hide();

			$('.page_fld').hide();

			$('.placement_fld').hide();

			$('.wicket_acc_group_child_filed').hide();

		} else if ( type == 'cendpoint' ) {

			$('.admin_notes_fld').show();

			$('.link_fld').hide();

			$('.page_fld').hide();

			$('.placement_fld').show();

			$('.wicket_acc_group_child_filed').hide();

		} else if ( type == 'lendpoint' ) {

			$('.admin_notes_fld').show();

			$('.link_fld').show();

			$('.page_fld').hide();

			$('.placement_fld').hide();

			$('.wicket_acc_group_child_filed').hide();

		} else if ( type == 'group_endpoint' ) {

			$('.admin_notes_fld').show();

			$('.link_fld').hide();

			$('.page_fld').hide();

			$('.placement_fld').hide();

			$('.wicket_acc_group_child_filed').show();

		} else if ( type == 'pendpoint' ) {

			$('.admin_notes_fld').show();

			$('.link_fld').hide();

			$('.page_fld').show();

			$('.placement_fld').hide();

			$('.wicket_acc_group_child_filed').hide();

		}

	}

	var wicket_acc_ep_as = $("#wicket_acc_set_ep_as_fld option:selected").val();

	if (wicket_acc_ep_as == 'wicket_acc_ep_tab') {

		$('.wicket_acc_ep_as').hide();

	} else if (wicket_acc_ep_as == 'wicket_acc_ep_sidebar') {

		$('.wicket_acc_ep_as').show();

	}

});

function wicket_acc_ep_as(value) {

	"use strict";

	if (value == 'wicket_acc_ep_sidebar') {

		jQuery('.wicket_acc_ep_as').show();

	} else if (value == 'wicket_acc_ep_tab') {

		jQuery('.wicket_acc_ep_as').hide();

	}
  
}