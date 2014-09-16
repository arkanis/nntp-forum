$(document).ready(function(){
	$('li.destroy.subscription > a').click(function(){
		var message_id = $(this).closest('ul').closest('li').data('id');
		
		$(this).closest('li').addClass('in_progress');
		$.ajax('/your/subscriptions/' + encodeURIComponent(message_id), {
			type: 'DELETE',
			context: this,
			complete: function(request){
				$(this).removeClass('in_progress failed');
				if (request.status == 204) {
					$(this).closest('ul').closest('li').remove();
				} else {
					$(this).text(locale.unsubscribe_failed).closest('li').removeClass('in_progress').addClass('failed');
				}
			}
		});
		
		return false;
	});
});