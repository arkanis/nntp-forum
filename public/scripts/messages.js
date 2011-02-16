$(document).ready(function(){
	// Reattach the reply form as last element of the article the user want's to answer.
	// We also hide the answer link and focus the textarea for the message.
	$('ul.actions > li.new.message > a').show().click(function(){
		var article = $(this).parents('article');
		$('form.message').hide().detach().appendTo(article).show();
		$(this).parentsUntil('ul').hide();
		$('textarea#message_body').focus();
		return false;
	});
	
	// The cancel button hides the form and shows the reply link again
	$('form.message button.cancel').click(function(){
		$(this).parents('article').eq(0).
			find('> form.message').hide().end().
			find('> ul.actions > li.new.message').show().end();
		return false;
	});
	
	// Validates the form and shows the proper error messages. If the form data is
	// invalid `false` is returned.
	$('form.message').bind('validate', function(){
		var form = $(this);
		
		$('ul.error, ul.error > li').hide();
		
		if ( $.trim(form.find('#message_body').val()) == "" ){
			form.find('ul.error, ul.error > li#message_body_error').show();
			var offset = form.find('ul.error').offset();
			window.scrollTo(0, offset.top);
			return false;
		}
		
		return true;
	});
	
	// Triggers the "validate" event to check if the form content is valid. If so the current post
	// text is converted to markdown (with a background request to the server) and show it in
	// the preview article.
	$('form.message button.preview').click(function(){
		if ( ! $(this).parents('form').triggerHandler('validate') )
			return false;
		
		$.post(window.location.pathname, {'preview_text': $('textarea').val()}, function(data){
			var offset = $('article#post-preview').
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
		
		$('button.preview').removeClass('recommended');
		$('button.create').addClass('recommended');
		
		var article = $(this).parents('article').eq(0);
		var newsgroup = window.location.pathname.split('/')[1];
		var message_number = parseInt(article.attr('data-number'), 10);
		
		$(this).find('button.create').get(0).disabled = true;
		$.ajax('/' + newsgroup + '/' + message_number, {
			type: 'POST',
			data: {'body': $('#message_body').val()},
			context: this,
			complete: function(request){
				if (request.status == 201) {
					// Posted successfully, reload the page to show the new reply
					window.location.href = window.location.href;
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
	
	$('ul.actions > li.destroy.message > a').show().click(function(){
		var article = $(this).parents('article').eq(0);
		var confirmation_form = $('<div class="confirmation"><form>' +
			'<p>Willst du diese Nachricht wirklich löschen?</p>' +
			'<p><button>Ja</button><button>Nein</button></p>' +
		'</form></div>').appendTo(article).find('> form');
		
		confirmation_form.css({
			top: (article.innerHeight() - confirmation_form.height()) / 2 + 'px',
			left: (article.innerWidth() - confirmation_form.width()) / 2 + 'px'
		});
		
		confirmation_form.find('button').
			eq(0).click(function(){
				// Send DELETE request
				return false;
			}).end().
			eq(1).click(function(){
				// Remove confirmation dialog
				$(this).parents('div.confirmation').remove();
				return false;
			}).end();
		return false;
	});
});