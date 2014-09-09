<?php

/**
 * The `MessageParser` is an event based parser for newsgroup messages (like SAX is for XML).
 * 
 * The parser works on a line by line basis. You feed it with lines of the message (e.g. out of an
 * NNTP connection) and it processes each new line. This keeps the memory footprint low (only
 * one line at a time) and helps performance (important when dealing with attachments).
 * 
 * During the parsing the message parser triggers several events:
 * 
 * 	message-header ( part-header <line event>* part-end )+ message-end
 * 
 * - `message-header`: Triggered first and only once after all message headers have been parsed.
 *   The handler gets all message headers. All header names are converted to lower case.
 * - For each MIME part:
 *   - `part-header`: Triggered after all headers of the MIME part have been parsed. The handler
 *     gets the headers, the content type and the content type parameters. If you are interested in the
 *     content of the part the handler has to return the name of the event that will be triggered for each
 *     line of the content (the placeholder `<line event>` is used for this name in the documentation).
 *     The handler can also return `null` in which case the content lines are discarded. This is useful
 *     for skipping over large attachment content if you are only interested in the attachment names
 *     (which are already in the headers given to the `part-header` event).
 *   - `<line event>`: If the `part-header` event returned an event name this event is triggered for each
 *     content line of the part.
 *   - `part-end`: This event is triggered at the end of each MIME part. The handler doesn't get any
 *     arguments. This event can be used to finalize the processing of a part (e.g. compressing an extracted
 *     image).
 * - `message-end`: Triggered as soon as the `end_of_message()` method of the parser is called. The
 *   handler gets no arguments. This event is useful for postprocessing like encoding the message content
 *   to UTF-8 or converting HTML to plain text.
 * 
 * Normal non MIME encoded messages are handled like a MIME message with just one part. This
 * way handlers don't need extra modifications to handle non MIME messages.
 * 
 * Handlers for these events can be given to the constructor as an associative array. Note that any
 * outside variables the handlers changes must be declared with `use(&$var)`. Otherwise the anonymous
 * handler function can not access or change the outside variable. This example code extracts the first
 * `text/plain` part of a message:
 * 
 * 	$message_data = array('subject' => null, 'content' => null);
 * 	$parser = new MessageParser(array(
 * 		'message-header' => function($headers) use(&$message_data){
 * 			// Take the subject out of the message headers
 * 			$message_data['subject'] = $headers['subject'];
 * 		},
 * 		'part-header' => function($headers, $content_type, $content_type_params) use(&$message_data){
 * 			// If we got a `text/plain` part and have no content yet make the parser call
 * 			// `append-content-line` for each line of that part.
 * 			if ($content_type == 'text/plain' and $message_data['content'] == null) {
 * 				return 'append-content-line';
 * 			}
 * 			
 * 			// Ignore the content of all other parts (attachments, etc.)
 * 			return null;
 * 		},
 * 		'append-content-line' => function($line) use(&$message_data){
 * 			// Append each line to the message content
 * 			$message_data['content'] .= $line;
 * 		}
 * 	));
 * 
 * 	while ( ! $con->end_of_message() )
 * 		$parser->parse_line($con->get_line());
 * 	
 * 	var_dump($message_data);
 * 
 */

class MessageParser
{
	/**
	 * Constructs a message parser that extracts the first text part and records information about the
	 * attachments of the message (name, size, content type, etc.). For the message content `text/plain`
	 * is prefered but if conly HTML is available it will be converted to plain text. The text content is
	 * automatically converted to UTF-8 as soon as `end_of_message()` is called. The information is
	 * stored in the `$message_data` argument.
	 */
	static function for_text_and_attachments(&$message_data){
		$events = array(
			'message-header' => function($headers) use(&$message_data){
				$newsgroups = array_map('trim', explode(',', $headers['newsgroups']));
				$message_data['newsgroup'] = reset($newsgroups);
				$message_data['newsgroups'] = $newsgroups;
				$message_data['id'] = $headers['message-id'];
			},
			'part-header' => function($headers, $content_type, $content_type_params) use(&$message_data){
				if ( preg_match('#text/(plain|html)#', $content_type) ) {
					// Take the first plain text or HTML part (this is the first condition).
					// If we already have `text/html` content and get a new `text/plain` part take the new text part
					// (this is the other condition). We prefere plain text over HTML since the text from the mail
					// client is probably better than the stripped HTML stuff created in the `message-end` event.
					if ( $message_data['content'] == null or ( $message_data['content_type'] == 'text/html' and $content_type == 'text/plain' ) ){
						$message_data['content'] = '';
						$message_data['content_type'] = $content_type;
						$message_data['content_encoding'] = isset($content_type_params['charset']) ? $content_type_params['charset'] : 'ISO-8859-1';
						return 'append-content-line';
					}
				} else if ( isset($headers['content-disposition']) ) {
					list($disposition_type, $disposition_parms) = MessageParser::parse_type_params_header($headers['content-disposition']);
					if ( isset($content_type_params['name']) )
						$name = $content_type_params['name'];
					if ( isset($disposition_parms['filename']) )
						$name = $disposition_parms['filename'];
					if ( isset($name) ) {
						$message_data['attachments'][] = array('name' => $name, 'type' => $content_type, 'params' => $content_type_params, 'size' => 0);
						return 'record-attachment-size';
					}
				}
			},
			'append-content-line' => function($line) use(&$message_data){
				$message_data['content'] .= $line;
			},
			'record-attachment-size' => function($line) use(&$message_data){
				$current_attachment_index = count($message_data['attachments']) - 1;
				$message_data['attachments'][$current_attachment_index]['size'] += strlen($line);
			},
			'message-end' => function() use(&$message_data){
				// Decode the message content to UTF-8
				$message_data['content'] = iconv($message_data['content_encoding'], 'UTF-8', $message_data['content']);
				unset($message_data['content_encoding']);
				// Strip HTML tags and clear indentions since they would only confuse Markdown
				if ($message_data['content_type'] == 'text/html')
					$message_data['content'] = preg_replace('/^[ \t]+/m', '', strip_tags($message_data['content']));
			}
		);
		return new self($events);
	}
	
	/**
	 * Decodes any "encoded-words" like "=?iso-8859-1?q?this=20is=20some=20text?="
	 * to UTF-8. This is useful to decode headers in mail and NNTP messages. Assumes that
	 * the rest of the string is already UTF-8 encoded and leaves it in peace.
	 * 
	 * The built in function `iconv_mime_decode()` does basically the same but fails on some
	 * cases and handels text outside of encoded words as US-ASCII encoding.
	 */
	static function decode_words($content){
		return preg_replace_callback('/ =\? (?<charset> [^? ]+ ) \? (?<encoding> [^? ]+ ) \? (?<encoded_text> [^?]+ ) \?= /x', function($match){
			// Handle transfer encoding
			if ( strtolower($match['encoding']) == 'q' )
				$word_content = quoted_printable_decode(str_replace('_', ' ', $match['encoded_text']));
			else
				$word_content = base64_decode($match['encoded_text']);
			
			// Decode content to UTF-8
			return iconv($match['charset'], 'UTF-8', $word_content);
		}, $content);
	}
	
	/**
	 * Parses an RFC 2822 dates as found in mail headers (e.g. "Wed, 12 Feb 2014 09:15:58 +0000").
	 * Also works with optional trailing data (e.g. "Wed, 12 Feb 2014 09:15:58 +0000 (UTC)").
	 * 
	 * Returns a PHP DateTime object containing the timestamp and timezone.
	 */
	static function parse_date_and_zone($date_as_string){
		// The "+" modifier at the end will ignore trailing data (like " (UTC)").
		$obj = date_create_from_format(DateTime::RFC2822 . '+', $date_as_string, new DateTimeZone('UTC'));
		return $obj;
	}
	
	/**
	 * Splits a typical `From` header into its mail and name part.
	 * 
	 * 	`Mr. X <test@example.com>` → array('Mr. X', 'test@example.com')
	 * 	`Mr. X <test-at-example.com>` → array('Mr. X', 'test-at-example.com')
	 * 	`test@example.com`	→	array('test', 'test@example.com')
	 * 	`test-at-example.com`	→	array('test-at-example.com', 'test-at-example.com')
	 */
	static function split_from_header($decoded_from_header){
		if ( preg_match('/(.*)<([^>]*)>/', $decoded_from_header, $match) )
			return array(trim($match[1], ' "'), trim($match[2]));
		if ( preg_match('/([^@]+)@.*/', $decoded_from_header, $match) )
			return array($match[1], trim($decoded_from_header));
		$trimmed = trim($decoded_from_header);
		return array($trimmed, $trimmed);
	}
	
	/**
	 * Parses a type-param header values such as the contents of the Content-Type and Content-Disposition
	 * headers. Returns an array with the type as the first element and an associative parameter array as
	 * second element.
	 * 
	 * Examples:
	 * 
	 * 	parse_type_params_header('text/plain; charset=utf-8');
	 * 	// => array('text/plain', array('charset' => 'utf-8'));
	 */
	static function parse_type_params_header($type_params_value){
		$parts = explode(';', $type_params_value);
		$type = array_shift($parts);
		
		$parameters = array();
		foreach($parts as $part){
			@list($name, $value) = explode('=', $part, 2);
			$parameters[strtolower(trim($name))] = ($value) ? trim($value, '"') : null;
		}
		
		return array($type, $parameters);
	}
	
	
	// Event handler functions or callbacks
	public $events = array();
	
	// Keeps track of the state of the state machine.
	private $state = 'message_headers';
	// Name of the event that should be called for each incomming content line.
	private $content_event = null;
	
	// Just a buffer that is filled with the headers of the message or mime part currently parsed
	private $headers = array();
	// Stack of mime part boundaries and end boundaries (each element is an array containing these two
	// strings). The stack makes it possible to parse nested mime parts, e.g. for multipart/mixed for
	// attachments with an enclosed multipart/alternative for a text and html version of the mail. The end
	// boundary is stored too because we don't want to create two new strings for each line we parse.
	private $mime_boundaries = array();
	// Default content type used when no content-type header is available for a part
	private $default_content_type = 'text/plain';
	// The transfer encoding of the current message part. No stack necessary since it should not be nested.
	private $content_transfer_encoding = null;
	
	
	/**
	 * Constructor that sets the event handlers for this parser.
	 */
	function __construct($events = array()){
		$default_events = array(
			'message-header' => function($headers){ },
			'part-header' => function($headers, $content_type, $content_type_params){ },
			'part-end' => function(){ },
			'message-end' => function(){ }
		);
		$this->events = array_merge($default_events, $events);
	}
	
	/**
	 * Resets the state machine to the beginning. It's then ready to parse a new message. Useful if you want
	 * to parse multiple messages with the same state machine and event handler setup.
	 * 
	 * This functionality could also be implemented by tuning the end states to properly detect the end of a
	 * message and automatically reset the state machine. However doing it explicit is more reliable and avoids
	 * high complexity in the end states.
	 */
	function reset(){
		$this->state = 'message_headers';
		$this->headers = array();
		$this->mime_boundaries = array();
	}
	
	/**
	 * Takes one line and dispatches it to the appropriet function for the state this parsing
	 * automate is currently in.
	 */
	function parse_line($line){
		$state_function = array($this, $this->state);
		return call_user_func($state_function, $line);
	}
	
	/**
	 * Markes the end of a message. This triggers the `message-end` event that may be used for post
	 * processing of message data (e.g. convert HTML to plain text). This function also resets the parser
	 * state afterwards by calling `reset()`.
	 * 
	 * If the message was not a MIME message this method also triggers the `part-end` event. This is
	 * done before the `message-end` event. This way you can write code for MIME messages that will
	 * also work for normal messages.
	 */
	function end_of_message(){
		if ($this->state == 'message_body')
			call_user_func($this->events['part-end']);
		call_user_func($this->events['message-end']);
		$this->reset();
	}
	
	/**
	 * State function for the message headers. Parses all headers into the `$headers` array.
	 * If an empty line is encountered the `message-header` event is fired with the `$headers`
	 * array as parameters and the state is changed to `message_body` or `mime_body`
	 * according to the content type of the message.
	 */
	private function message_headers($line){
		if ($line == '') {
			// An empty line ends the header list and starts the message body.
			
			// Fire the message header event so the event handler can extract the subject
			// and other stuff it's interested in.
			call_user_func($this->events['message-header'], $this->headers);
			
			// Now decide if start to parse MIME parts or use the entire message body
			// as message content.
			$content_type = isset($this->headers['content-type']) ? $this->headers['content-type'] : $this->default_content_type;
			list($type, $params) = self::parse_type_params_header($content_type);
			if ( preg_match('#multipart/.*#', $type) ){
				// For a multipart body push the boundary on the stack and prepare to parse it
				array_push($this->mime_boundaries, array('--' . $params['boundary'], '--' . $params['boundary'] . '--'));
				$this->state = 'mime_body';
			} else {
				// For a real message body call the part-header event and store the content
				// transfer encoding so we can decode each content line
				$this->content_event = call_user_func($this->events['part-header'], $this->headers, $type, $params);
				$this->content_transfer_encoding = isset($this->headers['content-transfer-encoding']) ? strtolower($this->headers['content-transfer-encoding']) : null;
				$this->state = 'message_body';
			}
		} elseif ( $line[0] == ' ' or $line[0] == "\t" ) {
			// Lines that start with a whitespace are additional content of the previous header.
			// If there is no last header ignore this line.
			$last_header = end(array_keys($this->headers));
			if ($last_header)
				$this->headers[$last_header] .= ' ' . self::decode_words(trim($line));
		} else {
			// Lines that start with a letter are new headers
			list($header_name, $header_content) = explode(':', $line, 2);
			$this->headers[strtolower($header_name)] = self::decode_words(trim($header_content));
		}
	}
	
	/**
	 * Helper function that decodes the specified line with the transfer encoding set by the last header event.
	 */
	private function decode_content_line($line){
		if ($this->content_transfer_encoding){
			switch ($this->content_transfer_encoding){
				case 'quoted-printable':
					$last_char = (strlen($line) > 1) ? $line[strlen($line) - 1] : null;
					if ($last_char == '=')
						return quoted_printable_decode($line);
					return quoted_printable_decode($line) . "\n";
				case 'base64':
					return base64_decode($line);
			}
		}

		return $line . "\n";
	}
	
	/**
	 * State function to parse a plain text message body. 
	 * This state is never left because plain text messages contain nothing else after the content.
	 */
	private function message_body($line){
		if ($this->content_event){
			$line_event = $this->events[$this->content_event];
			if ( is_callable($line_event) )
				call_user_func($line_event, $this->decode_content_line($line));
		}
	}
	
	/**
	 * State function that parses a MIME body. As soon as the first boundary is encountered
	 * the `$headers` array is reset (since it will contain the headers of the following part) and
	 * the state is changed to `mime_part_headers` to parse the headers of that part.
	 */
	private function mime_body($line){
		list($boundary, $end_boundary) = end($this->mime_boundaries);
		if ($line == $boundary){
			$this->headers = array();
			$this->state = 'mime_part_headers';
		}
	}
	
	/**
	 * State function to parse the mime part headers. Much like the `message_headers` state but
	 * if an empty line is found it is decided to use the part as text content (change to `mime_part_text_body`
	 * state), append it to the list of attachments or ignore it.
	 */
	private function mime_part_headers($line){
		if ($line == '') {
			// An empty line ends the header list and starts the body of that mime part. If we have
			// multipart content push the boundary on the stack and parse it, otherwise call the
			// `part-header` event and prepare to parse the content lines.
			list($type, $params) = self::parse_type_params_header($this->headers['content-type']);
			if ( preg_match('#multipart/.*#', $type) ){
				// For a multipart body push the boundary on the stack and prepare to parse it
				array_push($this->mime_boundaries, array('--' . $params['boundary'], '--' . $params['boundary'] . '--'));
				$this->state = 'mime_body';
			} else {
				// For a real part body call the part-header event and store the content
				// transfer encoding so we can decode each content line
				$this->content_event = call_user_func($this->events['part-header'], $this->headers, $type, $params);
				$this->content_transfer_encoding = isset($this->headers['content-transfer-encoding']) ? strtolower($this->headers['content-transfer-encoding']) : null;
				$this->state = 'mime_part_content';
			}
		} elseif ( $line[0] == ' ' or $line[0] == "\t" ) {
			// Lines that start with a whitespace are additional content of the previous header
			$last_header = end(array_keys($this->headers));
			if ($last_header)
				$this->headers[$last_header] .= ' ' . self::decode_words(trim($line));
		} else {
			// Lines that start with a letter new headers
			list($header_name, $header_content) = explode(':', $line, 2);
			$this->headers[strtolower($header_name)] = self::decode_words(trim($header_content));
		}
	}
	
	/**
	 * State function to parse the content of a mime part. If the topmost boundary on the stack is encoutered
	 * we clear the `$header` array for the next parts headers and change back to the `mime_part_headers`
	 * state. If an end boundary is encoutered we pop the topmost boundary from the stack (because we're
	 * done with that multipart entity) and change back to the `mime_body` state.
	 * 
	 * Since both types of boundaries end a part the `part-end` event is triggered. If the current line is no
	 * boundary decode the current transfer encoding and call the current content event if defined. If it isn't
	 * defined no one is interested in the content and we can discard the line.
	 */
	private function mime_part_content($line){
		list($boundary, $end_boundary) = end($this->mime_boundaries);
		if ($line == $boundary) {
			call_user_func($this->events['part-end']);
			$this->headers = array();
			$this->state = 'mime_part_headers';
		} elseif ($line == $end_boundary) {
			call_user_func($this->events['part-end']);
			array_pop($this->mime_boundaries);
			$this->state = 'mime_body';
		} else {
			if ($this->content_event){
				$line_event = $this->events[$this->content_event];
				if ( is_callable($line_event) )
					call_user_func($line_event, $this->decode_content_line($line));
			}
		}
	}
}

?>