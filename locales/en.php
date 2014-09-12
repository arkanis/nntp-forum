<?php

return array(
	// Strings used by the layout (header and footer of each page)
	'layout' => array(
		// Name of the root breadcrumbs node. It always links back to the newsgroup list.
		'breadcrumbs_index' => 'Overview',
		// Text for the link in the footer that explains how to setup newsgroups in a mail program
		// like Thunderbird. The URL of that link can be set in the config file since the process is
		// often infrastructure specific.
		'howto_link_text' => 'Howto read newsgroups in a mail program like Thunderbird',
		// Developer credits.
		// Arguments: user agent name (e.g. 'NNTP-Forum'), version (e.g. '1.0.0'), link to developer
		'credits' => '%s v%s, developed by %s.',
		// Text for 3rd party credits. Is used once for each 3rd party project.
		// Arguments: link to project, link to author or website
		'credits_3rd_party' => '%s by %s.'
	),
	
	// Strings for the newsgroup list page
	'newsgroups' => array(
		// Page heading
		'title' => 'Forum overview',
		
		// Headings for the newsgroup table
		'newsgroup_header' => 'Newsgroup',
		'post_count_header' => 'Posts',
		'last_post_header' => 'Latest post',
		
		// Information text for the last post of a newsgroup. The subject of the message is displayed
		// just before that information.
		// Arguments: message author, message date
		'last_post_info' => 'by %s on %s',
		// Date format used to create the message date string passed to `last_post_info`. The format
		// of the date string is described in the PHP manual: http://php.net/date
		'last_post_info_date_format' => 'jS M Y g:i a',
		// Text shown if a newsgroup has no latest post (e.g. is empty) or the last
		// post was deleted.
		'no_last_post' => '-'
	),
	
	// Strings for the topic list page
	'topics' => array(
		// Page heading
		'title' => 'Forum %s',
		
		// Action links
		'new_topic' => 'Open a new topic',
		'all_read' => 'Mark all topics as read',
		
		// Headings for the topic table
		'topic_header' => 'Topic',
		'post_count_header' => 'Posts',
		'last_post_header' => 'Latest post',
		
		// Information text for the latest post of a topic.
		// Arguments: message author, message date
		'last_post_info' => 'By %s on %s',
		// Date format used to create the message date string passed to `last_post_info`
		'last_post_info_date_format' => 'jS M Y g:i a',
		
		// This message is shown if a group does not contain any posts
		'no_topics' => 'This forum is currently empty.'
	),
	
	// Strings for the post list of a topic
	'messages' => array(
		// This text is shown for posts that have been deleted but are still in the cached
		// message list. As soon as the cache is updated the deleted messages will vanish.
		'deleted' => 'This post has been deleted by the author.',
		
		// This is the content of the delete _request_ send to the server. Usually these requests
		// are handled automatically. However some server might require a moderator to accept
		// these requests. This message is what such an moderator will see.
		'deleted_moderator_message' => 'The autor requests deletion of the message via webinterface.',
		
		// Header text of a message.
		// Arguments: message author, message date
		'message_header' => '%s, %s',
		// Date format used to create the message date string passed to `last_post_info`
		'message_header_date_format' => 'jS M Y g:i a',
		// Name of the permanent link to a message
		'permalink' => 'permalink',
		
		// Title of attachment list
		'attachments' => 'Attachments:',
		
		// Action links of a message
		'answer' => 'Reply',
		// Either subscribe or unsubscribe is shown
		'subscribe' => 'Subscribe',
		'unsubscribe' => 'Unsubscribe',
		// This link is only shown for messages the user posted by himself
		'delete' => 'Delete post',
		
		// Delete post dialog
		'delete_dialog' => array(
			'question' => 'Do you really want to delete this post?',
			'yes' => 'Yes',
			'no' => 'No'
		),
		
		// Links to collapse quoted messages
		'show_quote' => 'Show quoted post',
		'hide_quote' => 'Hide quoted post',
		// Link text to show or hide replies to a post
		// Arguments: number of replies that will be shown or hidden
		'show_replies' => 'Show %s replies',
		'hide_replies' => 'Hide %s replies'
	),
	
	// Strings for the different error pages. Each error page has a title, an error description
	// and a list of suggestions on what the user can do. The language file should only contain
	// common suggestions (e.g. check the URL in case of an `not_found` error). Infrastructure
	// specific suggestions (e.g. link to a support page) should be added in the configuration.
	'error_pages' => array(
		'forbidden' => array(
			'title' => 'Forbidden',
			'description' => 'Sorry, but access is forbidden. Your login seems to be valid but it was not possible to read the newsgroup with it.',
			'suggestions' => array()
		),
		'not_found' => array(
			'title' => 'Unknown address',
			'description' => 'Sorry, but we could not find anything for the address %s.',
			'suggestions' => array(
				'Maybe you misspelled the URL. A short check of the address bar should fix that.',
				'The topic or newsgroup you want to read no longer exist. Unfortunately there is not much you can do.
					Searching the newsgroups for something similar might help.'
			)
		),
		'unauthorized' => array(
			'title' => 'Login required',
			'description' => 'Sorry, but something went wrong during the login.',
			'suggestions' => array()
		),
		'send_failed' => array(
			'title' => 'Your message could not be send',
			'description' => "Unfortunately the newsgroup server did not accept your post. You probably don't have the required
				permissions to post in this newsgroup.",
			// This hint is shown before the low level error message. It should explain that the responsible person
			// might find the low level error useful for debugging.
			'error_reporting_hint' => 'In case you want to report that error please send along this error description:',
			'suggestions' => array()
		),
		'not_yet_online' => array(
			'title' => 'Post not yet online',
			'description' => 'Your message was accepted by the newsgroups server. However it is not online yet.
				It might take a few seconds or minutes to show up or a moderator first needs to confirm it.',
			// Suggestions on what the user should or can do to handle the error.
			// Arguments: path to the newsgroup the message was posted in.
			'suggestions' => array(
				'If you want to go absolutely sure your message is not lost go back to the form and copy the
					message text. If necessary you can resend the message later.',
				'As soon as your message goes online it will show up in <a href="%s">the newsgroup</a>.
					Keep an eye on it if you don\'t want to miss it.'
			)
		)
	),
	
	// Strings used for the form to create topics and write posts
	'message_form' => array(
		// Error messages
		'errors' => array(
			// Only the topic form has a subject field, therefore it's save to assume that
			// the user forgot to enter the topic subject.
			'missing_subject' => 'You forgot to give the new topic a name.',
			'missing_body' => "You have not yet entered any text for you message."
		),
		
		// Format help shown with the message form
		'format_help' => '
		<h3>A short format overview</h3>
		
		<dl>
			<dt>Paragraphs</dt>
				<dd>
<pre>
Paragraphs are separated by
an empty line.

Next paragraph.
</pre>
				</dd>
			<dt>Lists</dt>
				<dd>
<pre>
Use a `*` or `-` to create a list:

- First item
  * Item 1a
  * Item 1b
- Second
- Last
</pre>
				</dd>
			<dt>Links</dt>
				<dd>
<pre>
Clear [links][1] within text.

[1]: http://www.example.com/

Or use [direct
links](http://www.example.com/).
</pre>
				</dd>
			<dt>Code</dt>
				<dd>
<pre>
Code needs to be indented
with 4 or more spaces or at
least one tab:

    printf("hello world!");
</pre>
				</dd>
			<dt>Quotes</dt>
				<dd>
<pre>
Quotes star with a `>` sign:

> To be or not to be…
</pre>
				</dd>
		</dl>',
		
		// Field name labels
		'topic_label' => 'Topic',
		'attachments_label' => 'Attachments',
		'delete_attachment' => 'delete',
		
		// Form buttons for the topic creation and answer form
		'preview_button' => 'Show preview',
		'create_topic_button' => 'Create topic',
		'create_answer_button' => 'Send reply',
		'cancel_button' => 'Cancel',
		
		// Separator text between the buttons
		'button_separator' => 'or',
		
		// Title of the preview message (for answers)
		'preview_heading' => 'Preview',
		// Prefix of the topic preview (the name of the topic is appended by JavaScript)
		'preview_heading_prefix' => 'Preview:'
	),
	
	'subscriptions' => array(
		// Link shown in the navigation
		'link' => 'Subscriptions',
		// The title of the subscription page
		'title' => 'Your subscriptions',
		
		// Information text shown for each subscription. The subject of the subscribed message
		// is displayed just before that information.
		// Arguments: subject, author, data, list of newsgoups the subscribed message was posted in
		'subscription_info' => 'by %s on %s in %s',
		
		// Date format used to create the message date string passed to `subscription_info`. The
		// format of the date string is described in the PHP manual: http://php.net/date
		'subscription_info_date_format' => 'jS M Y g:i a',
		
		// Text shown if the user has no subscriptions.
		'no_subscriptions' => "You haven't subscribed to any posts."
	),
	
	'months' => array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
	'days' => array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
);

?>