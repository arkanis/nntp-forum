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
 * A counter helper useful for pluralization of words.
 * 
 * 	<?= c($count, 'No posts', 'One post', '%s posts'); ?>
 * 
 * This will output "No posts" if count is 0, "One posts" if count is 1 and
 * "x posts" if count is greater than 1 (represented by "x").
 */
function c($count, $none, $one, $many)
{
	if ($count < func_num_args() - 1)
		$selected_arg = 1 + $count;
	else
		$selected_arg = func_num_args() - 1;
	
	return str_replace('%s', $count, func_get_arg($selected_arg));
}

/**
 * A compressed and simplified version of the distance_of_time_in_words helper of
 * Ruby on Rails.
 * 
 * See: http://api.rubyonrails.org/classes/ActionView/Helpers/DateHelper.html#M002261
 */
function distance_of_time_in_words($from_time, $to_time)
{
	$distance_in_minutes = abs($to_time - $from_time) / 60;
	if ($distance_in_minutes <= 44)
		return c(round($distance_in_minutes), 'less than a minute', 'one minute', '%s minutes');
	elseif ($distance_in_minutes <= 1439)
		return c(round($distance_in_minutes / 60), 'about one hour', 'about one hour', 'about %s hours');
	elseif ($distance_in_minutes <= 43199)
		return c(round($distance_in_minutes / 1440), 'one day', 'one day', '%s days');
	elseif ($distance_in_minutes <= 525599)
		return c(round($distance_in_minutes / 43200), 'about one month', 'about one month', 'about %s months');
	else
	{
		$distance_in_years = $distance_in_minutes / 525600;
		$minute_offset_for_leap_year = ($distance_in_years / 4) * 1440;
		$remainder = ($distance_in_minutes - $minute_offset_for_leap_year) % 525600;
		if ($remainder < 131400)
			return c(round($distance_in_years), 'about one year', 'about one year', 'about %s years');
		elseif ($remainder < 394200)
			return c(round($distance_in_years), 'over one year', 'over one year', 'over %s years');
		else
			return c(round($distance_in_years + 1), 'almost one year', 'almost one year', 'almost %s years');
	}
}

?>