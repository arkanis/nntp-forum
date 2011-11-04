<?php

class NntpException extends Exception { }

/**
 * `NntpConnection` is a class to manage the low level communication of the NNTP protocol.
 * It takes care of sending commands or text and receiving the status and text responses.
 */
class NntpConnection
{
	private $connection;
	private $nntp_command_filter;
	
	/**
	 * Opens a NNTP connection to the specified `$uri`. Also checks the initial server
	 * ready status response.
	 */
	function __construct($uri, $timeout, $options = array()){
		$ssl_context = stream_context_create($options);
		$this->connection = stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ssl_context);
		
		if ($this->connection === false)
			throw new NntpException("Could not open NNTP connection: $errstr ($errno)");
		list($status, $rest) = $this->get_status_response();
		if ( !($status == 200 or $status == 201) )
			throw new NntpException("Expected a 200 or 201 server ready status but got $status: $rest");
		
		// If we got an old INN 2.4 server use the command filter to rewrite some command names
		if ( preg_match('/INN 2.4/', $rest) ){
			$this->nntp_command_filter = function(&$command, &$expected_status_code){
				@list($cmd, $args) = explode(' ', $command, 2);
				if ($cmd == 'over') {
					$command = 'xover ' . $args;
				} else if ($cmd == 'hdr') {
					$command = 'xhdr ' . $args;
					// We need to patch the return code, too. Somehow INN 2.4
					// returns 221 instead of 225.
					$expected_status_code = 221;
				}
			};
		}
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
	 * 
	 * USE WITH CAUTION: User credentials are also logged by this function!
	 */
	private function log($message){
		//file_put_contents(ROOT_DIR . '/nntp.log', $message . "\n", FILE_APPEND);
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
		// If a command filter is set let it handle the command and expected status codes before
		// sending them. The command filter is used to fix up commands for old NNTP servers
		// that do not support them unchanged.
		if ( is_callable($this->nntp_command_filter) ){
			$filter = $this->nntp_command_filter;
			$filter($command, $expected_status_code);
		}
		
		if (strlen($command) > 510)
			throw new NntpException("Command exceeds 512 character limit: $command");
		if (strpos($command, "\n\r") !== false)
			throw new NntpException("Command must not contain a line break: $command");
		
		$this->log('COMMAND: ' . $command);
		
		$bytes_written = fwrite($this->connection, $command . "\r\n");
		if ($bytes_written == false)
			throw new NntpException('fwrite failed on command: ' . $command);
		
		list($status, $rest) = $this->get_status_response();
		$this->check_status_code($expected_status_code, $status, $rest);
		
		return array($status, $rest);
	}
	
	/**
	 * Sends the specified `$text` in one piece thought the NNTP connection. After this
	 * a server response is expected and the returned status code is compared to the one
	 * in `$expected_status_code` (which can be an array).
	 */
	function send_text($text, $expected_status_code){
		$this->log('SEND TEXT: ' . $text);
		
		$bytes_written = fwrite($this->connection, $text . "\r\n.\r\n");
		if ($bytes_written === false)
			throw new NntpException('fwrite failed on text: ' . $text);
		
		list($status, $rest) = $this->get_status_response();
		$this->check_status_code($expected_status_code, $status, $rest);
		
		return array($status, $rest);
	}
	
	/**
	 * Allows to send data over the NNTP channel without first concatenating everything into one
	 * big string (useful for attachments). `$send_function` is assumed to be a function that contains
	 * all the code that has to send data over the connection. It is called onec by this function and the
	 * first parameter contains a function object that can be used to write data to the connection.
	 * 
	 * After `$send_function` has complted we wait for a status response from the server and compare
	 * it to `$expected_status_code`.
	 */
	function send_text_per_chunk($expected_status_code, $send_function){
		$socket = $this->connection;
		$writer = function($text) use($socket){
			$bytes_written = fwrite($socket, $text);
			if ($bytes_written === false)
				throw new NntpException('fwrite failed on text: ' . $text);
			return $bytes_written;
		};
		
		$send_function($writer);
		
		$bytes_written = fwrite($socket, "\r\n.\r\n");
		if ($bytes_written === false)
			throw new NntpException('fwrite failed while sending content terminator');
		
		list($status, $rest) = $this->get_status_response();
		$this->check_status_code($expected_status_code, $status, $rest);
		return array($status, $rest);
	}
	
	/**
	 * Compares the `$expected_status_code` with the `$received_status_code`. The expected
	 * status code can be an array defining a list of valid status codes. In case the received status
	 * code is invalid an NntpException is thrown. `$rest` is added to the exception message and
	 * is expected to be the rest of the status response line.
	 */
	private function check_status_code($expected_status_code, $received_status_code, $rest){
		if ( is_array($expected_status_code) ) {
			if ( !in_array($received_status_code, $expected_status_code) )
				throw new NntpException('Expected one of the status codes ' . join(', ', $expected_status_code) . " but got: $received_status_code $rest");
		} else {
			if ($received_status_code != $expected_status_code)
				throw new NntpException("Expected status code $expected_status_code but got: $received_status_code $rest");
		}
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