<?php

class Message
{
	/**
	 * Decodes MIME header field content into UTF-8.
	 */
	static function decode($content){
		return iconv_mime_decode($content, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
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
	 */
	static function split_from_header($decoded_from_header){
		preg_match('/(.*)<([^@]+@[^>]+)>/', $decoded_from_header, $match);
		return array(trim($match[1], ' "'), trim($match[2]));
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
	
	
	// Keeps track of the state of the automate. If it's `null` the automate will ignore input.
	private $state = 'message_headers';
	// Just a buffer that is filled with the headers of the message or mime parts
	private $headers = array();
	
	private $content_transfer_encoding = null;
	private $mime_boundary = null;
	private $mime_end_boundary = null;
	
	// Real output variables, these contain information about the message once the automate finished
	public $content_type = 'text/plain';
	public $content_type_params = array();
	public $content = null;
	public $attachments = array();
	
	/**
	 * Takes one line and dispatches it to the appropriet function for the state this parsing
	 * automate is currently in.
	 */
	function parse_line($line){
		if ($this->state == null)
			return;
		
		$state_function = array($this, $this->state);
		return call_user_func($state_function, $line);
	}
	
	/**
	 * Finishes the parsing and decoding of the message and ends the automate. After this call
	 * the decoded message body is available in the `content` property.
	 */
	function finish(){
		// Check for a transfer encoding and decode the content if neccessary
		if ($this->content_transfer_encoding){
			$encoding = strtolower($this->content_transfer_encoding);
			if ($encoding == 'quoted-printable')
				$this->content = quoted_printable_decode($this->content);
			elseif ($encoding == 'base64')
				$this->content = base64_decode($this->content);
		}
		
		// Figure out the charset of the content and decode it to UTF-8
		$source_charset = isset($this->content_type_params['charset']) ? $this->content_type_params['charset'] : 'ISO-8859-1';
		$this->content = iconv($source_charset, 'UTF-8', $this->content);
		
		// Finally hit the break. After this the automate will ignore new
		// lines given to `parse_line()`.
		$this->state = null;
	}
	
	/**
	 * State function for the message headers. Parses all headers into the `$headers` array.
	 * If an empty line is encoutered the content type is safed to `$content_type` and the state
	 * is set to `message_body` for plain text mails or `mime_body` for mails in the mime format.
	 */
	private function message_headers($line){
		if ($line == '') {
			// An empty line ends the header list and starts the message body. Decide if
			// we parse MIME parts or use the entire message body as message content.
			if ( isset($this->headers['content-type']) ){
				list($type, $params) = self::disassemble_content_type($this->headers['content-type']);
				if ( preg_match('#multipart/.*#', $type) ){
					$this->mime_boundary = '--' . $params['boundary'];
					$this->mime_end_boundary = '--' . $params['boundary'] . '--';
					$this->state = 'mime_body';
				} else {
					$this->content_type = $type;
					$this->content_type_params = $params;
					$this->content_transfer_encoding = @$this->headers['content-transfer-encoding'];
					$this->state = 'message_body';
				}
			} else {
				$this->state = 'message_body';
			}
		} elseif ( $line[0] == ' ' or $line[0] == "\t" ) {
			// Lines that start with a whitespace are additional content of the previous header
			$last_header = end(array_keys($this->headers));
			if ($last_header)
				$this->headers[$last_header] .= ' ' . trim($line);
		} else {
			// Lines that start with a letter new headers
			list($header_name, $header_content) = explode(':', $line, 2);
			$this->headers[strtolower($header_name)] = trim($header_content);
		}
	}
	
	/**
	 * State function to parse a plain text message body. Just appends every line to `$content`.
	 * This state is never left because plain text messages contain onthing else after the content.
	 */
	private function message_body($line){
		$this->content .= $line . "\n";
	}
	
	/**
	 * State function that parses a MIME body. As soon as the first boundary is encountered
	 * the `$headers` array is reset (since it will contain the headers of the following part) and
	 * the state is changed to `mime_part_headers` to parse the headers of that part.
	 */
	private function mime_body($line){
		if ($line == $this->mime_boundary){
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
			// An empty line ends the header list and starts the body of that mime part. If the
			// content type indicates plain text and no content is stored yet use the body of this
			// part as the overall message body. If another content type is specified and a
			// name parameter is present add it to the list of message attachments (the content
			// disposition header contains strange stuff for file names…). Otherwise ignore the part.
			list($type, $params) = self::disassemble_content_type($this->headers['content-type']);
			if ($type == 'text/plain' and $this->content == null){
				$this->content_type = $type;
				$this->content_type_params = $params;
				$this->content_transfer_encoding = @$this->headers['content-transfer-encoding'];
				$this->state = 'mime_part_text_body';
			} else {
				if ( isset($params['name']) ){
					$name = self::decode($params['name']);
					$this->attachments[] = array('name' => $name, 'type' => $type, 'params' => $params, 'size' => null);
					$this->state = 'mime_part_attachment_body';
				/*
				if ( isset($this->headers['content-disposition']) ){
					// Actually we do not care much about the disposition. We want to show inline disposition as
					// attachments anyway.
					list($disposition, $disp_params) = self::disassemble_content_type($this->headers['content-disposition']);
					$this->attachments[] = array('name' => $disp_params['filename'], 'type' => $type, 'type-params' => $params, 'size' => null);
					$this->state = 'mime_part_attachment_body';
				*/
				} else {
					// Ignore part
					$this->state = 'mime_part_ignore_body';
				}
			}
			
		} elseif ( $line[0] == ' ' or $line[0] == "\t" ) {
			// Lines that start with a whitespace are additional content of the previous header
			$last_header = end(array_keys($this->headers));
			if ($last_header)
				$this->headers[$last_header] .= ' ' . trim($line);
		} else {
			// Lines that start with a letter new headers
			list($header_name, $header_content) = explode(':', $line, 2);
			$this->headers[strtolower($header_name)] = trim($header_content);
		}
	}
	
	/**
	 * A small helper function for all state functions that operate in mime part bodies. Checks if the
	 * boundary of the current part is reached. If a normal boundary is encountered reset the `headers`
	 * array and change to `mime_part_headers` state to parse the next part. If an end boundary is
	 * found switch to `null` state, that is ignore all lines after that.
	 * 
	 * Returns `true` if a boundary was reached, otherwise `false`.
	 */
	private function mime_part_helper_check_boundary($line){
		if ($line == $this->mime_boundary){
			$this->headers = array();
			$this->state = 'mime_part_headers';
			return true;
		} elseif ($line == $this->mime_end_boundary) {
			$this->state = null;
			return true;
		}
		return false;
	}
	
	/**
	 * State function to parse the mime part that was selected as text content of the message.
	 * If the line is not a boundary add it to the text content of the message.
	 */
	private function mime_part_text_body($line){
		if ( ! $this->mime_part_helper_check_boundary($line) )
			$this->content .= $line . "\n";
	}
	
	/**
	 * State function to parse attachment data. If the line is not a boundary add the estimated
	 * decoded content size to the size of the attachment. The factor of 1.37 is taken from
	 * Wikipedia (http://en.wikipedia.org/wiki/Base64#MIME) to estimate the decoded size.
	 */
	private function mime_part_attachment_body($line){
		if ( ! $this->mime_part_helper_check_boundary($line) ){
			$current_attachment_index = count($this->attachments) - 1;
			$this->attachments[$current_attachment_index]['size'] += strlen($line) / 1.37;
		}
	}
	
	/**
	 * State function that is used for ignored mime parts. Just checks for boundary lines and
	 * throws away all other stuff.
	 */
	private function mime_part_ignore_body($line){
		$this->mime_part_helper_check_boundary($line);
	}
}

?>