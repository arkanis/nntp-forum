$(document).ready(function(){
	// Reattach the reply form as last element of the article the user want's to answer
	// and update the form action URL so the answer is really associated with that message.
	// We also hide the answer link and focus the textarea for the message. Note that first
	// all answer links are shown again. This is to prevent other answer links from vanishing
	// when this form is moved to another article.
	$('ul.actions > li.new.message > a').show().click(function(){
		var article = $(this).parents('article');
		var newsgroup = window.location.pathname.split('/')[1];
		var message_number = parseInt(article.attr('data-number'), 10);
		
		$('form.message').hide().detach().appendTo(article).show().
			attr('action', '/' + newsgroup + '/' + message_number);
		$('article > ul.actions > li.new.message').show();
		$(this).parents('li.new.message').hide();
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
	
	// Triggers the "validate" event to check if the form content is valid. If it's not abort the form
	// submission.
	$('form.message').submit(function(){
		if ( ! $(this).triggerHandler('validate') )
			return false;
	});
	
	// Create a confirmation dialog. If the user confirms that he want's to delete the message
	// kick of background request. Otherwise just destroy the confirmation dialog.
	$('ul.actions > li.destroy.message > a').show().click(function(){
		var article = $(this).parents('article').eq(0);
		var newsgroup_and_topic_number = window.location.pathname.split('/');
		var newsgroup = newsgroup_and_topic_number[1];
		var topic_number = parseInt(newsgroup_and_topic_number[2], 10);
		var message_number = parseInt(article.attr('data-number'), 10);
		
		var confirmation_form = $('<div class="confirmation"><form>' +
			'<p>' + locale.delete_dialog.question + '</p>' +
			'<p><button>' + locale.delete_dialog.yes + '</button><button>' + locale.delete_dialog.no + '</button></p>' +
		'</form></div>').appendTo(article).find('> form');
		
		confirmation_form.css({
			top: (article.innerHeight() - confirmation_form.height()) / 2 + 'px',
			left: (article.innerWidth() - confirmation_form.width()) / 2 + 'px'
		});
		
		confirmation_form.find('button').
			eq(0).click(function(){
				// Send DELETE request to delete the message
				$.ajax('/' + newsgroup + '/' + message_number, {
					type: 'DELETE',
					context: this,
					complete: function(request){
						if (request.status == 204 || request.status == 404) {
							// In case of 204 the message has been deleted successfully. 404 means the message
							// is already deleted. In both case reload the page to show the updated information.
							// If we just deleted the root message of the topic load the newgroup topic list.
							if (topic_number == message_number)
								window.location.href = window.location.protocol + '//' + window.location.host + '/' + newsgroup;
							else
								window.location.reload();
						} else {
							// 422 happend… I'm lazy now, just hide the confirmation form and show an alert box
							// with the error message.
							$(this).parents('div.confirmation').remove();
							alert(request.responseText);
						}
					}
				});
				
				return false;
			}).end().
			eq(1).click(function(){
				// Remove confirmation dialog
				$(this).parents('div.confirmation').remove();
				return false;
			}).end();
		
		return false;
	});
	
	// Mange the attachment list. Allow to remove all but the last file input field in the list and
	// create a new empty file input after the user chose a file for one.
	$('form.message dl a').live('click', function(){
		var dd_element = $(this).parent();
		if ( dd_element.next().length == 1 )
			dd_element.remove();
		else
			$(this).siblings('input[type="file"]').val('');
		return false;
	});
	$('form.message dl input[type="file"]').live('change', function(){
		var dd_element = $(this).parent();
		if ( dd_element.next().length == 0 )
			dd_element.clone(false).find('input[type="file"]').replaceWith('<input type="file" />').end().insertAfter(dd_element);
		return false;
	});
	
	// Collapse the block quotes of the previous messages. It's somewhat nice that mail clients do that
	// but in a forum it's just visual clutter since the previous post is displayed right above the current
	// one.
	$('article > p + blockquote').each(function(){
		// Ignore blockquotes with less than 3 paragraphs or blockquotes. Seems to be a good rule of
		// thumb to leave small quotes in tact but yet catch the big message quotes.
		if ( $(this).find('> p, > blockquote').length >= 3 )
			$(this).prev('p').addClass('quote-guardian collapsed').attr('title', locale.show_quote);
	});
	
	$('p.quote-guardian').live('click', function(){
		if ( $(this).toggleClass('collapsed').hasClass('collapsed') )
			$(this).attr('title', locale.show_quote);
		else
			$(this).attr('title', locale.hide_quote);
		return false;
	})
	
	// Add links that collapse all replies to a message ("reply guardians"). The main work is done by the
	// style sheet that uses different styles for collapsed reply lists. We only toggle the `collapsed` class
	// here.
	$('article + ul').each(function(){
		var reply_count = $(this).find('article').length;
		var title = locale.hide_replies.replace('%s', reply_count);
		$(this).prepend('<li class="reply-guardian" title="' + title + '"><a href="#">' + title + '</a></li>');
	});
	$('li.reply-guardian').live('click', function(){
		var reply_list = $(this).parent('ul');
		var reply_count = reply_list.find('article').length;
		if ( reply_list.toggleClass('collapsed').hasClass('collapsed') )
			var title = locale.show_replies.replace('%s', reply_count);
		else
			var title = locale.hide_replies.replace('%s', reply_count);
		$(this).attr('title', title).find('a').text(title);
		
		return false;
	})
});