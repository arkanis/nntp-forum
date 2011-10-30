<?php

return array(
	// Strings used by the layout (the structure around each page)
	'layout' => array(
		// Name of the root breadcrumbs node. It always links back to the newsgroup list.
		'breadcrumbs_index' => 'Übersicht',
		// Text of the setup howto link in the footer
		'howto_link_text' => 'Newsgroups in E-Mail-Program (z.B. Thunderbird) einrichten',
		// Developer credits.
		// Arguments: user agent name (e.g. 'NNTP-Forum'), version (e.g. '1.0.0'), link to developer
		'credits' => '%s v%s, entwickelt von %s.',
		// Text for 3rd party credits. Is used once for each tool.
		// Arguments: name and link of project, author name and link
		'credits_3rd_party' => '%s von %s.'
	),
	
	// Strings for the newsgroup overview page
	'newsgroups' => array(
		// Page heading
		'title' => 'Forenübersicht',
		// Headings for the overview table
		'newsgroup_header' => 'Newsgroup',
		'post_count_header' => 'Beiträge',
		'last_post_header' => 'Letzter Beitrag',
		// Information text for the last post of a newsgroup.
		// Arguments: message author, message date
		'last_post_info' => 'von %s am %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`
		'last_post_info_date_format' => 'j.m.Y G:i',
		// Text shown if a newsgroup has no latest message (e.g. is empty) or the last
		// message is unknown.
		'no_last_post' => '-'
	),
	
	// Strings used for the form to create topics and write answers
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
  - Eintrag 1a
  - Eintrag 1b
- Zweiter
* Letzter
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
Beginnen mit einem ">"-Zeichen:

> Sein oder nicht sein…
</pre>
				</dd>
		</dl>',
		
		'topic_label' => 'Thema',
		'attachments_label' => 'Anhänge',
		'delete_attachment' => 'löschen',
		
		// Form buttons for the topic creation and answer form
		'preview_button' => 'Vorschau ansehen',
		'create_topic_button' => 'Thema erstellen',
		'create_answer_button' => 'Antwort absenden',
		'cancle_button' => 'Abbrechen',
		
		// Separator text between the buttons
		'button_separator' => 'oder',
		
		// Title of the preview message
		'preview_heading' => 'Vorschau'
	),
	
	'topics' => array(
		// Page heading
		'title' => 'Forum %s',
		// Action links
		'new_topic' => 'Neues Thema eröffnen',
		'all_read' => 'Alles als gelesen markieren',
		
		'topic_header' => 'Thema',
		'post_count_header' => 'Beiträge',
		'last_post_header' => 'Neuster Beitrag',
		
		// Information text for the latest post of a topic.
		// Arguments: message author, message date
		'last_post_info' => 'Von %s am %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`
		'last_post_info_date_format' => 'j.m.Y G:i',
		
		'no_topics' => 'Dieses Forum ist momentan noch leer.'
	),
	
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
	
	'messages' => array(
		'deleted' => 'Dieser Beitrag wurde vom Autor gelöscht.',
		
		// Header text of a message
		// Arguments: message author, message date
		'message_header' => '%s, %s Uhr',
		// Date format used to create the message date string passed to `last_post_info`
		'message_header_date_format' => 'j.m.Y G:i',
		'permalink' => 'permalink',
		
		'answer' => 'Antworten',
		'delete' => 'Nachricht löschen'
	)
);

?>