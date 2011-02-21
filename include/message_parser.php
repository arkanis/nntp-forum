<?php

class MessageParser
{
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
	 * Parses a date sting as found in mail headers into a Unix timestamp.
	 */
	static function parse_date($date_as_string){
		return strtotime($date_as_string);
	}
	
	/**
	 * Splits a typical `From` header into its mail and name part.
	 * 
	 * 	`Mr. X <test@example.com>` → array('Mr. X', 'test@example.com')
	 * 	`test@example.com`	→	array('test', 'test@example.com')
	 */
	static function split_from_header($decoded_from_header){
		if ( preg_match('/(.*)<([^@]+@[^>]+)>/', $decoded_from_header, $match) )
			return array(trim($match[1], ' "'), trim($match[2]));
		preg_match('/([^@]+)@.*/', $decoded_from_header, $match);
		return array($match[1], trim($decoded_from_header));
	}
	
	/**
	 * Parses a Content-Type header into its MIME type and additional parameters. Returns
	 * an array with the MIME type as first element and an associative parameter array as
	 * second element.
	 */
	private static function disassemble_content_type($content_type_value){
		$parts = explode(';', $content_type_value);
		$mime_type = array_shift($parts);
		
		$parameters = array();
		foreach($parts as $part){
			@list($name, $value) = explode('=', $part, 2);
			$parameters[strtolower(trim($name))] = ($value) ? trim($value, '"') : null;
		}
		
		return array($mime_type, $parameters);
	}
	
	
	// Event handler functions or callbacks
	private $events = array();
	
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
			'part-header' => function($headers, $content_type, $content_type_params){ }
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
			list($type, $params) = self::disassemble_content_type($content_type);
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
	 * This state is never left because plain text messages contain onthing else after the content.
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
			list($type, $params) = self::disassemble_content_type($this->headers['content-type']);
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
	 * If the current line is no boundary decode the current transfer encoding and call the current content
	 * event if defined. If it isn't defined no one is interested in the content and we can discard the line.
	 */
	private function mime_part_content($line){
		list($boundary, $end_boundary) = end($this->mime_boundaries);
		if ($line == $boundary) {
			$this->headers = array();
			$this->state = 'mime_part_headers';
		} elseif ($line == $end_boundary) {
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