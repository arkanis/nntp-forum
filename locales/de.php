<?php

return array(
	// Strings used by the layout (header and footer of each page)
	'layout' => array(
		// Name of the root breadcrumbs node. It always links back to the newsgroup list.
		'breadcrumbs_index' => 'Übersicht',
		// Text for the link in the footer that explains how to setup newsgroups in a mail program
		// like Thunderbird. The URL of that link can be set in the config file since the process is
		// often infrastructure specific.
		'howto_link_text' => 'Newsgroups in E-Mail-Program (z.B. Thunderbird) einrichten',
		// Developer credits.
		// Arguments: user agent name (e.g. 'NNTP-Forum'), version (e.g. '1.0.0'), link to developer
		'credits' => '%s v%s, entwickelt von %s.',
		// Text for 3rd party credits. Is used once for each 3rd party project.
		// Arguments: link to project, link to author or website
		'credits_3rd_party' => '%s von %s.'
	),
	
	// Strings for the newsgroup list page
	'newsgroups' => array(
		// Page heading
		'title' => 'Forenübersicht',
		
		// Headings for the newsgroup table
		'newsgroup_header' => 'Newsgroup',
		'post_count_header' => 'Beiträge',
		'last_post_header' => 'Letzter Beitrag',
		
		// Information text for the last post of a newsgroup. The subject of the message is displayed
		// just before that information.
		// Arguments: message author, message date
		'last_post_info' => 'von %s am %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`. The format
		// of the date string is described in the PHP manual: http://php.net/date
		'last_post_info_date_format' => 'j.m.Y G:i',
		// Text shown if a newsgroup has no latest post (e.g. is empty) or the last
		// post was deleted.
		'no_last_post' => '-'
	),
	
	// Strings for the topic list page
	'topics' => array(
		// Page heading
		'title' => 'Forum %s',
		
		// Action links
		'new_topic' => 'Neues Thema eröffnen',
		'all_read' => 'Alles als gelesen markieren',
		
		// Headings for the topic table
		'topic_header' => 'Thema',
		'post_count_header' => 'Beiträge',
		'last_post_header' => 'Neuster Beitrag',
		
		// Information text for the latest post of a topic.
		// Arguments: message author, message date
		'last_post_info' => 'Von %s am %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`
		'last_post_info_date_format' => 'j.m.Y G:i',
		
		// This message is shown if a group does not contain any posts
		'no_topics' => 'Dieses Forum ist momentan noch leer.'
	),
	
	// Strings for the post list of a topic
	'messages' => array(
		// This text is shown for posts that have been deleted but are still in the cached
		// message list. As soon as the cache is updated the deleted messages will vanish.
		'deleted' => 'Dieser Beitrag wurde vom Autor gelöscht.',
		
		// This is the content of the delete _request_ send to the server. Usually these requests
		// are handled automatically. However some server might require a moderator to accept
		// these requests. This message is what such an moderator will see.
		'deleted_moderator_message' => 'Der Autor beantragt über das Webinterface die Löschung der Nachricht.',
		
		// Header text of a message.
		// Arguments: message author, message date
		'message_header' => '%s, %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`
		'message_header_date_format' => 'j.m.Y G:i',
		// Name of the permanent link to a message
		'permalink' => 'permalink',
		
		// Title of attachment list
		'attachments' => 'Anhänge:',
		
		// Action links of a message
		'answer' => 'Antworten',
		// Either subscribe or unsubscribe is shown
		'subscribe' => 'Abbonieren',
		'unsubscribe' => 'Abbo löschen',
		// This link is only shown for messages the user posted by himself
		'delete' => 'Nachricht löschen',
		
		// Delete post dialog
		'delete_dialog' => array(
			'question' => 'Willst du diese Nachricht wirklich löschen?',
			'yes' => 'Ja',
			'no' => 'Nein'
		),
		
		// Links to collapse quoted messages
		'show_quote' => 'Zitierte Nachricht einblenden',
		'hide_quote' => 'Zitierte Nachricht ausblenden',
		
		// Link text to show or hide replies to a post
		// Arguments: number of replies that will be shown or hidden
		'show_replies' => '%s Antworten einblenden',
		'hide_replies' => '%s Antworten ausblenden'
	),
	
	// Strings for the different error pages. Each error page has a title, an error description
	// and a list of suggestions on what the user can do. The language file should only contain
	// common suggestions (e.g. check the URL in case of an `not_found` error). Infrastructure
	// specific suggestions (e.g. link to a support page) should be added in the configuration.
	'error_pages' => array(
		'forbidden' => array(
			'title' => 'Login ungültig',
			'description' => 'Sorry, aber mit deinen Login hast du leider keinen Zugriff. Der Benutzer ist zwar gültig, aber leider konnte damit die Newsgroup nicht gelesen werden.',
			'suggestions' => array()
		),
		'not_found' => array(
			'title' => 'Unbekannte Adresse',
			'description' => 'Sorry, aber zu der Adresse %s konnte nichts passendes gefunden werden.',
			'suggestions' => array(
				'Vielleicht hast du dich bei der URL nur vertippt. Ein kurzer Blick in die Adressleiste sollte dann reichen.',
				'Das entsprechende Thema oder die entsprechende Newsgroup existiert nicht mehr. In dem Fall hilft leider
					nur in den Newsgroups nach etwas ähnlichem zu suchen.'
			)
		),
		'unauthorized' => array(
			'title' => 'Login nötig',
			'description' => 'Sorry, aber bei dem Login ist irgendwas schief gegangen.',
			'suggestions' => array()
		),
		'send_failed' => array(
			'title' => 'Beitrag konnte nicht gesendet werden',
			'description' => 'Der Beitrag wurde vom Newsgroup-Server leider nicht angenommen. Wahrscheinlich
				verfügst du nicht über die nötigen Rechte um in dieser Newsgroup Beiträge zu schreiben.',
			// This hint is shown before the low level error message. It should explain that the responsible person
			// might find the low level error useful for debugging.
			'error_reporting_hint' => 'Falls du den Fehler melden willst gibt bitte die folgende genaue Fehlerbeschreibung mit an:',
			'suggestions' => array()
		),
		'not_yet_online' => array(
			'title' => 'Beitrag noch nicht online',
			'description' => 'Der Beitrag wurde zwar akzeptiert, scheint aber noch nicht online zu sein. Möglicher
				weise dauert es ein paar Sekunden oder er muss erst vom Moderator bestätigt werden.',
			// Suggestions on what the user should or can do to handle the error.
			// Arguments: path to the newsgroup the message was posted in.
			'suggestions' => array(
				'Damit im Fall aller Fälle nichts verlohren geht kannst zurück zum Formular, den Text
					deines Beitrags kopieren und falls nötig später noch einmal senden.',
				'Ob der Beitrag online ist siehst du wenn er innerhalb der nächsten paar Minuten in
					<a href="%s">der Newsgroup</a> erscheint.'
			)
		)
	),
	
	// Strings used for the form to create topics and write posts
	'message_form' => array(
		// Error messages
		'errors' => array(
			// Only the topic form has a subject field, therefore it's save to assume that
			// the user forgot to enter the topic subject.
			'missing_subject' => 'Du hast vergessen einen Namen für das neue Thema anzugeben.',
			'missing_body' => 'Du hast noch keinen Text für die Nachricht eingeben.'
		),
		
		// Format help shown with the message form
		'format_help' => '
		<h3>Kurze Format-Übersicht</h3>
		
		<dl>
			<dt>Absätze</dt>
				<dd>
<pre>
Absätze werden durch eine
Leerzeile getrennt.

Nächster Absatz.
</pre>
				</dd>
			<dt>Listen</dt>
				<dd>
<pre>
Listen können mit `*` oder `-`
erstellt werden:

- Erster Eintrag
  * Eintrag 1a
  * Eintrag 1b
- Zweiter
- Letzter
</pre>
				</dd>
			<dt>Links</dt>
				<dd>
<pre>
Übersichtlicher [Link][1] im
Fließtext.

[1]: http://www.hdm-stuttgart.de/

Oder ein [direkter
Link](http://www.hdm-stuttgart.de/).
</pre>
				</dd>
			<dt>Code</dt>
				<dd>
<pre>
Code muss mit mindestens 4
Leerzeichen oder einem Tab
eingerückt sein:

    printf("hello world!");
</pre>
				</dd>
			<dt>Zitate</dt>
				<dd>
<pre>
Beginnen mit einem `>`-Zeichen:

> Sein oder nicht sein…
</pre>
				</dd>
		</dl>',
		
		// Field name labels
		'topic_label' => 'Thema',
		'attachments_label' => 'Anhänge',
		'delete_attachment' => 'löschen',
		
		// Form buttons for the topic creation and answer form
		'preview_button' => 'Vorschau ansehen',
		'create_topic_button' => 'Thema erstellen',
		'create_answer_button' => 'Antwort absenden',
		'cancel_button' => 'Abbrechen',
		
		// Separator text between the buttons
		'button_separator' => 'oder',
		
		// Title of the preview message (for answers)
		'preview_heading' => 'Vorschau',
		// Prefix of the topic preview (the name of the topic is appended by JavaScript)
		'preview_heading_prefix' => 'Vorschau:'
	),
	
	'subscriptions' => array(
		'link' => 'Abonnements',
		'title' => 'Deine Abonnements'
	),
	
	'months' => array('Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'),
	'days' => array('Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag')
);

?>