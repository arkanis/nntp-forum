<?php

/**
 * Escapes the specified text so it can be safely inserted as HTML tag content.
 * It's UTF-8 safe.
 * 
 * Since this function is made for HTML content it does not escape double
 * quotes ("). If you want to insert something as an attribute value use the
 * ha() function.
 * 
 * This is a shortcut mimicing the Ruby on Rails "h" helper.
 */
function h($text_to_escape)
{
	return htmlspecialchars($text_to_escape, ENT_NOQUOTES, 'UTF-8');
}

/**
 * Escapes the specified text so it can be safely inserted into an HTML attribute.
 * It's UTF-8 safe.
 * 
 * This is a shortcut mimicing the Ruby on Rails "h" helper.
 */
function ha($text_to_escape)
{
	return htmlspecialchars($text_to_escape, ENT_COMPAT, 'UTF-8');
}

/**
 * Converts the specified number of bytes into a more human readable format like KiByte
 * or MiByte. The function name is inspired by Rails but the implementation is was written
 * completely from scratch.
 */
function number_to_human_size($bytes){
	$border = 1024 * 1.5;
	if ($bytes < $border)
		return sprintf('%u Byte', $bytes);
	
	$bytes /= 1024;
	if ($bytes < $border)
		return sprintf('%u KiByte', $bytes);
	
	$bytes /= 1024;
	return sprintf('%.1f MiByte', $bytes);
}

?>