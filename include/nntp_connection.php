<?php

class NntpException extends Exception { }

class NntpConnection
{
	private $connection;
	
	/**
	 * Opens a NNTP connection to the specified host and port. Also checks the initial server
	 * ready status response.
	 * 
	 * TODO: Verify HdM certificate?
	 */
	function __construct($host, $port){
		//$ssl_context = stream_context_create(array('ssl' => array('verify_peer' => false)));
		$this->connection = fsockopen($host, $port, $errno, $errstr);
		if ($this->connection === false)
			throw new NntpException("Could not open NNTP connection: $errstr ($errno)");
		list($status, $rest) = $this->get_status_response();
		if ( !($status == 200 or $status == 201) )
			throw new NntpException("Expected a 200 or 201 server ready status but got $status: $rest");
	}
	
	/**
	 * Just for safety: close the socket connection if the connection object is destroyed.
	 */
	function __destruct(){
		if (is_resource($this->connection))
			$this->close();
	}
	
	/**
	 * Logs the NNTP traffic for debugging.
	 */
	private function log($message){
		//file_put_contents(ROOT_DIR . 'nntp.log', $message . "\n", FILE_APPEND);
	}
	
	/**
	 * Closes the NNTP connection.
	 */
	function close(){
		$this->command('quit', 205);
		fclose($this->connection);
	}
	
	/**
	 * Fetches the status line from the current connection. This should be used directly after
	 * an NNTP command has be send. Returns an array with the first element the status code
	 * and the second the rest of the line.
	 * 
	 * TODO: Find a proper max length for a status response line
	 */
	function get_status_response(){
		$status_line = stream_get_line($this->connection, 4096, "\r\n");
		$this->log('STATUS: ' . $status_line);
		return explode(' ', $status_line, 2);
	}
	
	/**
	 * Fetches the response body of a command. Also replaces double dots at the start of text
	 * lines with a single dot (as required by NNTP.
	 * 
	 * TODO: Find a proper max length for a text response line.
	 */
	function get_text_response(){
		$text = '';
		$line = '';
		do {
			$text = $text . "\n" . $line;
			// stream_get_line() seems to hang while reading the mail header (stops after
			// the first header and waits forever). Therefore use fgets(). Not that standard
			// conform but more robust.
			//$line = stream_get_line($this->connection, 4096, "\r\n");
			$line = rtrim(fgets($this->connection));
		} while($line != '.');
		
		$decoded_text = ltrim(preg_replace('/^\.\./m', '.', $text), "\n");
		$this->log("TEXT:\n" . $decoded_text);
		
		return $decoded_text;
	}
	
	/**
	 * Retrieves the text response and calls the `$line_handler` callback once per received line.
	 */
	function get_text_response_per_line($line_handler){
		$line = null;
		do {
			$line = rtrim(fgets($this->connection));
			if ($line != '.')
				call_user_func($line_handler, preg_replace('/^\.\./', '.', $line));
		} while($line != '.');
	}
	
	/**
	 * Sends a command over the NNTP connection. Checks that the command is not longer
	 * than the limit of 512 characters (including trailing CR-LF) and checks the status code
	 * returned by the following status response. `$expected_status_code` can also be an
	 * array in which case all of these status codes are accepted.
	 * 
	 * Returns an array with two elements. The first element is the received status code, the
	 * second the rest of the status response.
	 */
	function command($command, $expected_status_code){
		if (strlen($command) > 510)
			throw new NntpException("Command exceeds 512 character limit: $command");
		if (strpos($command, "\n\r") !== false)
			throw new NntpException("Command must not contain a line break: $command");
		
		$this->log('COMMAND: ' . $command);
		
		$bytes_written = fwrite($this->connection, $command . "\r\n");
		if ($bytes_written == false)
			throw new NntpException('fwrite failed on command: ' . $command);
		
		list($status, $rest) = $this->get_status_response();
		if ( is_array($expected_status_code) ) {
			if ( !in_array($status, $expected_status_code) )
				throw new NntpException('Expected one of the status codes ' . join(', ', $expected_status_code) . " but got: $status $rest");
		} else {
			if ($status != $expected_status_code)
				throw new NntpException("Expected status code $expected_status_code but got: $status $rest");
		}
		
		return array($status, $rest);
	}
	
	function send_text($text, $expected_status_code){
		$this->log('SEND TEXT: ' . $text);
		
		$bytes_written = fwrite($this->connection, $text . "\r\n.\r\n");
		if ($bytes_written == false)
			throw new NntpException('fwrite failed on text: ' . $text);
		
		list($status, $rest) = $this->get_status_response();
		if ( is_array($expected_status_code) ) {
			if ( !in_array($status, $expected_status_code) )
				throw new NntpException('Expected one of the status codes ' . join(', ', $expected_status_code) . " but got: $status $rest");
		} else {
			if ($status != $expected_status_code)
				throw new NntpException("Expected status code $expected_status_code but got: $status $rest");
		}
		
		return array($status, $rest);
	}
	
	/**
	 * Authenticates the given user for this NNTP connection.
	 * 
	 * Returns `true` if the authentication was successful, otherwise `false`.
	 */
	function authenticate($user, $password){
		$this->command('authinfo user ' . $user, 381);
		list($status,) = $this->command('authinfo pass ' . $password, array(281, 481));
		return ($status == 281);
	}
}

?>