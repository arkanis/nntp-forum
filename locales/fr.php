<?php
# French Translation by Mr Xhark (http://blogmotion.fr)

return array(
	// Strings used by the layout (header and footer of each page)
	'layout' => array(
		// Name of the root breadcrumbs node. It always links back to the newsgroup list.
		'breadcrumbs_index' => 'Aperçu',
		// Text for the link in the footer that explains how to setup newsgroups in a mail program
		// like Thunderbird. The URL of that link can be set in the config file since the process is
		// often infrastructure specific.
		'howto_link_text' => 'Comment lire les news dans un logiciel comme Thunderbird',
		// Developer credits.
		// Arguments: user agent name (e.g. 'NNTP-Forum'), version (e.g. '1.0.0'), link to developer
		'credits' => '%s v%s, développé par %s.',
		// Text for 3rd party credits. Is used once for each 3rd party project.
		// Arguments: link to project, link to author or website
		'credits_3rd_party' => '%s par %s.'
	),
	
	// Strings for the newsgroup list page
	'newsgroups' => array(
		// Page heading
		'title' => 'Aperçu du forum',
		
		// Headings for the newsgroup table
		'newsgroup_header' => 'Groupe de discussion',
		'post_count_header' => 'Posts',
		'last_post_header' => 'Dernier post',
		
		// Information text for the last post of a newsgroup. The subject of the message is displayed
		// just before that information.
		// Arguments: message author, message date
		'last_post_info' => 'par %s le %s',
		// Date format used to create the message date string passed to `last_post_info`. The format
		// of the date string is described in the PHP manual: http://php.net/date
		'last_post_info_date_format' => 'j M Y G:i',
		// Text shown if a newsgroup has no latest post (e.g. is empty) or the last
		// post was deleted.
		'no_last_post' => '-'
	),
	
	// Strings for the topic list page
	'topics' => array(
		// Page heading
		'title' => 'Forum %s',
		
		// Action links
		'new_topic' => 'Créer un nouveau fil',
		'all_read' => 'Tout marquer comme lus',
		
		// Headings for the topic table
		'topic_header' => 'Fil',
		'post_count_header' => 'Posts',
		'last_post_header' => 'Dernier post',
		
		// Information text for the latest post of a topic.
		// Arguments: message author, message date
		'last_post_info' => 'Par %s le %s',
		// Date format used to create the message date string passed to `last_post_info`
		'last_post_info_date_format' => 'j M Y G:i',
		
		// This message is shown if a group does not contain any posts
		'no_topics' => 'Aucun message dans ce groupe.'
	),
	
	// Strings for the post list of a topic
	'messages' => array(
		// This text is shown for posts that have been deleted but are still in the cached
		// message list. As soon as the cache is updated the deleted messages will vanish.
		'deleted' => 'Ce message a été supprimé par son auteur.',
		
		// This is the content of the delete _request_ send to the server. Usually these requests
		// are handled automatically. However some server might require a moderator to accept
		// these requests. This message is what such an moderator will see.
		'deleted_moderator_message' => 'Ce message a été annulé par son auteur.',
		
		// Header text of a message.
		// Arguments: message author, message date
		'message_header' => '%s, %s',
		// Date format used to create the message date string passed to `last_post_info`
		'message_header_date_format' => 'j M Y G:i',
		// Name of the permanent link to a message
		'permalink' => 'permalien',
		
		// Title of attachment list
		'attachments' => 'Pièces jointes:',
		
		// Action links of a message
		'answer' => 'Répondre',
		// Either subscribe or unsubscribe is shown
		'subscribe' => 'Subscribe',
		'unsubscribe' => 'Unsubscribe',
		// This link is only shown for messages the user posted by himself
		'delete' => 'Retirer mon message',
		
		// Delete post dialog
		'delete_dialog' => array(
			'question' => 'Etes-vous sûr de vouloir détruire ce post ?',
			'yes' => 'Oui',
			'no' => 'Non'
		),
		
		// Links to collapse quoted messages
		'show_quote' => 'Afficher les messages cités',
		'hide_quote' => 'Masquer les messages cités',
		// Link text to show or hide replies to a post
		// Arguments: number of replies that will be shown or hidden
		'show_replies' => 'Afficher %s réponses',
		'hide_replies' => 'Masquer %s réponses'
	),
	
	// Strings for the different error pages. Each error page has a title, an error description
	// and a list of suggestions on what the user can do. The language file should only contain
	// common suggestions (e.g. check the URL in case of an `not_found` error). Infrastructure
	// specific suggestions (e.g. link to a support page) should be added in the configuration.
	'error_pages' => array(
		'forbidden' => array(
			'title' => 'Interdit',
			'description' => 'Désolé, l\'accès est interdit. Votre identifiant semble correct mais vos permissions insuffisantes.',
			'suggestions' => array()
		),
		'not_found' => array(
			'title' => 'Adresse inconnue',
			'description' => 'Désolé, aucun résultat pour l\'adresse %s.',
			'suggestions' => array(
				'Une erreur de frappe peut-être à l\'origine de cette erreur, vérifiez la barre d\'adresse URL.',
				'Le fil ou groupe que vous cherchez n\'existe pas ou plus.
					Tentez une autre recherche.'
			)
		),
		'unauthorized' => array(
			'title' => 'Identification requise',
			'description' => 'Les informations d\'identification sont incorrectes.',
			'suggestions' => array()
		),
		'send_failed' => array(
			'title' => 'Votre message ne peut-être envoyé',
			'description' => "Désolé, le serveur de groupe de discussion n\'a pas accepté votre message. Vos permissions sont probablement insuffisantes
				pour écrire dans ce groupe.",
			// This hint is shown before the low level error message. It should explain that the responsible person
			// might find the low level error useful for debugging.
			'error_reporting_hint' => 'Si vous souhaitez remonter cette erreur merci d\'envoyer le contenu de l\'erreur :',
			'suggestions' => array()
		),
		'not_yet_online' => array(
			'title' => 'Le message n\'est pas encore en ligne',
			'description' => 'Votre message a été enregistré sur le serveur, mais il n\'est pas encore en ligne.
				Cela peut prendre quelques secondes ou minutes pour qu\'un modérateur le valide.',
			// Suggestions on what the user should or can do to handle the error.
			// Arguments: path to the newsgroup the message was posted in.
			'suggestions' => array(
				'Pour être certain de ne pas perdre votre message retournez sur le formulaire et copiez votre
					message. Si nécessaire vous pouvez envoyer le message plus tard.',
				'Dès que votre message sera en ligne il sera visible dans <a href="%s">le groupe</a>.
					Gardez un œil ouvert pour ne pas le râter.'
			)
		)
	),
	
	// Strings used for the form to create topics and write posts
	'message_form' => array(
		// Error messages
		'errors' => array(
			// Only the topic form has a subject field, therefore it's save to assume that
			// the user forgot to enter the topic subject.
			'missing_subject' => 'Vous n\'avez pas précisé de sujet pour votre message.',
			'missing_body' => "Votre message est vide."
		),
		
		// Format help shown with the message form
		'format_help' => '
		<h3>Aperçu du formatage</h3>
		
		<dl>
			<dt>Paragraphes</dt>
				<dd>
<pre>
Les paragraphes sont délimités par
une ligne vide.

Paragraphe suivant.
</pre>
				</dd>
			<dt>Listes</dt>
				<dd>
<pre>
Utilisez un `*` ou `-` pour créer une liste:

- Premier point
  * Point 1a
  * Point 1b
- Deuxième
- Dernier
</pre>
				</dd>
			<dt>Liens</dt>
				<dd>
<pre>
Supprimer [liens][1] dans le texte.

[1]: http://www.exemple.com/

Ou utiliser [liens
directs](http://www.exemple.com/).
</pre>
				</dd>
			<dt>Code</dt>
				<dd>
<pre>
Le code doit être indenté
avec 4 espaces ou plus, ou avec
au moins une tabulation:

    printf("bonjour le monde!");
</pre>
				</dd>
			<dt>Citations</dt>
				<dd>
<pre>
Les citations commencent avec un signe `>` :

> Être ou ne pas être...
</pre>
				</dd>
		</dl>',
		
		// Field name labels
		'topic_label' => 'Fil',
		'attachments_label' => 'Pièces jointes',
		'delete_attachment' => 'Supprimer',
		
		// Form buttons for the topic creation and answer form
		'preview_button' => 'Prévisualiser',
		'create_topic_button' => 'Créer un fil',
		'create_answer_button' => 'Répondre',
		'cancel_button' => 'Annuler',
		
		// Separator text between the buttons
		'button_separator' => 'ou',
		
		// Title of the preview message (for answers)
		'preview_heading' => 'Aperçu',
		// Prefix of the topic preview (the name of the topic is appended by JavaScript)
		'preview_heading_prefix' => 'Aperçu:'
	),
	
	'subscriptions' => array(
		'link' => 'Subscriptions',
		'title' => 'Your subscriptions'
	)
);

?>