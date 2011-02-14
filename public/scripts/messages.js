$(document).ready(function(){
	// Show the new topic link and make it show the topic form when it's clicked
	$('ul.actions > li.new.message > a').show().click(function(){
		var article = $(this).parents('article');
		$('form.message').hide().detach().appendTo(article).show();
		$(this).parentsUntil('ul').hide();
		$('input#message_subject').focus();
		return false;
	});
	
	// Let the cancle link hide the form
	$('form.message button.cancel').click(function(){
		$(this).parents('article').eq(0).
			find('> form.message').hide().end().
			find('> ul.actions > li.new.message').show().end();
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
			console.log(offset);
			window.scrollTo(0, offset.top);
			
		}
		
		return valid;
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
	
	// Triggers the "validate" event to check if the form content is valid. If so sends the current
	// form data to the server to be posted. Depending on the returned status code we redirect
	// to the page of the new message or show the appropriate error message.
	$('form.message').submit(function(){
		if ( ! $(this).triggerHandler('validate') )
			return false;
		
		$(this).find('button.create').get(0).disabled = true;
		$.ajax(window.location.pathname, {
			type: 'POST',
			data: {'subject': $('#message_subject').val(), 'body': $('#message_body').val()},
			context: this,
			complete: function(request){
				if (request.status == 201) {
					// Posted
					window.location.href = request.getResponseHeader('Location');
				} else {
					$(this).find('button.create').get(0).disabled = false;
					if (request.status == 202) {
						// Accepted
						var offset = $(this).find('#message-accepted').show().offset();
						window.scrollTo(0, offset.top);
					} else {
						// Newsgroup not found, invalid data or something exploded
						var offset = $(this).find('#message-post-error').
							find('samp').text(request.responseText).end().
							show().offset();
						window.scrollTo(0, offset.top);
					}
				}
			}
		});
		return false;
	});
});