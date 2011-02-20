$(document).ready(function(){
	// Show the new topic link and make it show the topic form when it's clicked
	$('ul.actions > li.new.topic > a').show().click(function(){
		$('form.message').show();
		$('input#message_subject').focus();
		return false;
	});
	
	// Validates the form and shows the proper error messages. If the form data is
	// invalid `false` is returned.
	$('form.message').bind('validate', function(){
		var valid = true;
		var form = $(this);
		
		$('ul.error, ul.error > li').hide();
		
		if ( $.trim(form.find('#message_subject').val()) == "" ){
			form.find('ul.error > li#message_subject_error').show();
			valid = false;
		}
		if ( $.trim(form.find('#message_body').val()) == "" ){
			form.find('ul.error > li#message_body_error').show();
			valid = false;
		}
		
		if ( !valid ){
			var offset = form.find('ul.error').show().offset();
			window.scrollTo(0, offset.top);
			
		}
		
		return valid;
	});
	
	// Let the cancle link hide the form
	$('form.message button.cancel').click(function(){
		$(this).parents('form').hide();
		return false;
	});
	
	// Triggers the "validate" event to check if the form content is valid. If so the current post
	// text is converted to markdown (with a background request to the server) and show it in
	// the preview article.
	$('form.message button.preview').click(function(){
		if ( ! $(this).parents('form').triggerHandler('validate') )
			return false;
		
		$.post(window.location.pathname, {'preview_text': $('textarea').val()}, function(data){
			var offset = $('article#post-preview').
				find('> header > p').text( 'Vorschau: ' + $('input#message_subject').val() ).end().
				find('> div').html(data).end().
				show().offset();
			window.scrollTo(0, offset.top);
			$('button.preview').removeClass('recommended');
			$('button.create').addClass('recommended');
		});
		return false;
	});
	
	// Triggers the "validate" event to check if the form content is valid. If it's not abort the form
	// submission.
	$('form.message').submit(function(){
		if ( ! $(this).triggerHandler('validate') )
			return false;
	});
	
	// Mange the attachment list. Allow to remove all but the last file input field in the list and
	// create a new empty file input after the user chose a file for one.
	$('form.message dl').find('a').click(function(){
		var dd_element = $(this).parent();
		if ( dd_element.next().length == 1 )
			dd_element.remove();
		else
			$(this).siblings('input[type="file"]').val('');
		return false;
	}).end().
	find("input[type='file']").change(function(){
		var dd_element = $(this).parent();
		if ( dd_element.next().length == 0 )
			dd_element.clone(false).find('input[type="file"]').replaceWith('<input name="attachments[]" type="file" />').end().insertAfter(dd_element);
		return false;
	});
});