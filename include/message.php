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
	
	private $headers;
	private $body;
	public $author_name;
	public $author_mail;
	
	function __construct($source){
		@list($head, $this->body) = explode("\n\n", $source, 2);
		$this->headers = iconv_mime_decode_headers($head, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
		
		preg_match('/(.*)<([^@]+@[^>]+)>/', $this->headers['From'], $match);
		$this->author_name = trim($match[1], ' "');
		$this->author_mail = trim($match[2]);
	}
	
	function header($lower_case_header_name){
		$search_name = strtolower(strtr($lower_case_header_name, '_', '-'));
		$found_name = null;
		foreach( array_keys($this->headers) as $camelcase_header_name ){
			if (strtolower($camelcase_header_name) == $search_name)
				$found_name = $camelcase_header_name;
		}
		
		return $found_name ? $this->headers[$found_name] : null;
	}
	
	
	function store_body($body){
		$this->body = $body;
	}
	
	/**
	 * Extracts and decodes the first text/plain MIME part of the mail.
	 */
	function content(){
		$this->content = null;
		self::walk_mime_parts($this->headers, $this->body, array($this, 'mime_parts_walker'));
		/*
		self::walk_mime_parts($this->headers, $this->body, function($mime_type, $params, $headers, $body) use(&$content){
			if ($mime_type == 'text/plain'){
				$source_charset = isset($params['charset']) ? $params['charset'] : 'ISO-8859-1';
				$content = iconv($source_charset, 'UTF-8', $body);
				return false;
			}
		});
		*/
		
		// If no text/plain MIME part was found return the mail as it is
		return is_null($this->content) ? $this->body : $this->content;
	}
	
	private $content;
	
	private function mime_parts_walker($mime_type, $params, $headers, $body){
		if ($mime_type == 'text/plain'){
			$source_charset = isset($params['charset']) ? $params['charset'] : 'ISO-8859-1';
			$this->content = iconv($source_charset, 'UTF-8', $body);
			return false;
		}
	}
	
	/**
	 * Recursivly iterates over all MIME parts of the specified mail. If `$callback` returns
	 * `false` the walk is stopped (useful e.g. if you found what you searched for).
	 */
	private static function walk_mime_parts($headers, $body, $callback){
		if ( isset($headers['Content-Type']) ) {
			list($mime_type, $params) = self::disassemble_content_type($headers['Content-Type']);
		} else {
			$mime_type = 'text/plain';
			$params = array();
		}
		
		if (preg_match('#multipart/.*#', $mime_type)){
			// Split all MIME parts and ignore the first and last (stuff before and after the boundaries)
			$mime_parts = explode("\n--" . $params['boundary'], $body);
			array_shift($mime_parts);
			array_pop($mime_parts);
			
			foreach($mime_parts as $mime_part){
				list($part_head, $part_body) = explode("\n\n", ltrim($mime_part, "\n"));
				$part_headers = iconv_mime_decode_headers($part_head, 0, 'UTF-8');
				$result = self::walk_mime_parts($part_headers, $part_body, $callback);
				if ($result === false)
					return false;
			}
		} else {
			$result = call_user_func($callback, $mime_type, $params, $headers, $body);
			if ($result === false)
				return false;
		}
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
	
	function __get($property_name)
	{
		if ($property_name == 'content')
			return $this->content();
		if (preg_match('/^(.+)_as_time$/i', $property_name, &$matches))
			return strtotime($this->header($matches[1]));
		if (preg_match('/^(.+)_as_array$/i', $property_name, &$matches))
			return (array) $this->header($matches[1]);
		
		return $this->header($property_name);
	}
}

?>