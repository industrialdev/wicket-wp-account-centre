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

	$(document).on('click', ".dropdown__toggle--menu", function(event) {
    event.preventDefault();
    $(this).toggleClass("open");
    var targetContent = '#' + $(this).attr('aria-controls');
    if ($(this).hasClass("open")) {
			$('.wicket-acc-menu .nav__submenu, .wicket-acc-menu-mobile .nav__submenu').not($(targetContent)).slideUp('fast').attr("aria-expanded", "false");
			$('.dropdown__toggle--menu').not($(this)).removeClass('open').attr("aria-expanded", "true");
			$(targetContent).slideDown('fast').attr("aria-expanded", "true");
			$(this).attr("aria-expanded", "true");
    } else {
			$(targetContent).slideUp('fast').attr("aria-expanded", "false");
			$(this).attr("aria-expanded", "false");
    }
    $(this).focus();
	});

	var contextualNav = $('.myaccount-nav');
	if(contextualNav.length >= 1){
		var currentPageItem = contextualNav.find('.current-menu-item');
		var currentDropdown = currentPageItem.closest('.nav__submenu');
		if(currentDropdown.length >= 1) {
			currentDropdown.show();
			currentDropdown.attr("aria-expanded", "true");
			var dropdownButton = '#' + currentDropdown.attr('aria-labelledby');
			$(dropdownButton).addClass('open').attr("aria-expanded", "true");
		}
	}

	$(document).on('click', ".dropdown__toggle", function(event) {
    event.preventDefault();
    $(this).toggleClass("open");
    var targetContent = '#' + $(this).attr('aria-controls');
    if ($(this).hasClass("open")) {
        $(targetContent).slideDown('fast').attr("aria-expanded", "true");
        $(this).attr("aria-expanded", "true");
    } else {
        $(targetContent).slideUp('fast').attr("aria-expanded", "false");
        $(this).attr("aria-expanded", "false");
    }
    $(this).focus();
	});

});