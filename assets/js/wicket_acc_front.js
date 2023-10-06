jQuery(document).ready(function($) {

	'use strict';
	
	var readURL = function(input) {
		if (input.files && input.files[0]) {
			var reader = new FileReader();

			reader.onload = function (e) {
				$('.profile-pic').attr('src', e.target.result);
			}
	
			reader.readAsDataURL(input.files[0]);
		}
	}

	$('li.group_endpoint').each( function(){

		if ( $( this ).find('.nav-selected').length > 0 ) {
			$( this ).addClass('nav-selected');
		}
	})	

	$('li.group_endpoint a.group_endpoint_a').on('click' , function(e){
		e.preventDefault();
		$( this ).parent().find('ul').toggle();

		if ( $( this ).parent().find('ul li.nav-selected').length > 0 ) {
			if ( $( this ).parent().hasClass('nav-selected') ) {
				$( this ).parent().removeClass('nav-selected');
			} else {
				$( this ).parent().addClass('nav-selected');
			}
		}
			
	});

	$("#wicket_acc_file_upload").on('change', function(){
		
		$("form.wicket_acc_profile_Pic_form").submit();
	});
	
	$(".wicket_acc_upload_button").on('click', function() {
		
		$("#wicket_acc_file_upload").click();
	});
});