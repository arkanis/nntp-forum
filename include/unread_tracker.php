<?php

/**
 * Tracking file format, one line per newsgroup:
 * 
 * 	newsgroup-name "\t" high-watermark "\t" topic-unread-marker? ( "," topic-unread-marker )*
 * 	topic-unread-marker: topic-number ":" unread-watermark
 * 
 * Example
 * 
 * 	hdm.test.allgemein	102	98:101,31:31
 */
class UnreadTracker
{
	/**
	 * Loads the tracking data form `$file` and returns it as an nested array.
	 */
	static private function load($file){
		$raw_data = @file_get_contents($file);
		if (!$raw_data)
			return array();
		
		$tracking_data = array();
		foreach(explode("\n", $raw_data) as $line){
			list($newsgroup, $high_watermark, $raw_topic_marks) = explode("\t", $line);
			
			$topic_marks = array();
			if ( ! empty($raw_topic_marks) ){
				foreach(explode(',', $raw_topic_marks) as $raw_mark){
					list($topic_number, $unread_watermark) = explode(':', $raw_mark);
					$topic_marks[intval($topic_number)] = intval($unread_watermark);
				}
			}
			
			$tracking_data[$newsgroup] = array(
				'watermark' => intval($high_watermark),
				'topic-marks' => $topic_marks
			);
		}
		
		return $tracking_data;
	}
	
	/**
	 * Writes `$tracking_data` to `$file` in the same format `load()` can red.
	 */
	static private function save($file, $tracking_data){
		$lines = array();
		foreach ($tracking_data as $newsgroup => $details){
			$topic_marks = $details['topic-marks'];
			$topic_mark_pairs = array_map(function($tnr, $mnr){ return  $tnr . ':' . $mnr; }, array_keys($topic_marks), $topic_marks);
			$raw_topic_marks = join(',', $topic_mark_pairs);
			$lines[] = $newsgroup . "\t" . $details['watermark'] . "\t" . $raw_topic_marks;
		}
		return file_put_contents($file, join("\n", $lines));
	}
	
	private $data_file;
	private $data;
	
	/**
	 * Loads the tracking data from the specified file. If the file does not exist or is empty
	 * nothing will be loaded. All other methods will interpret this to mean that all topics
	 * within the topic limit are unread.
	 */
	function __construct($track_data_file){
		$this->data_file = $track_data_file;
		$this->data = self::load($track_data_file);
	}
	
	/**
	 * Updates the list of unread topics and messages for the specified newsgroup by marking
	 * new messages as unread. Only markers for `$topic_limit` numbers of topics are stored,
	 * the rest (including old markers) is discarded. That prevents infinit growing of the
	 * tracking data.
	 */
	function update($newsgroup, $message_tree, $message_infos, $topic_limit){
		$newest_message = end($message_infos);
		$new_high_watermark = $newest_message['number'];
		$new_topic_marks = array();
		
		// If we have tracking data extend if with new topic marks if necessary. Otherwise
		// build the topic marks from scratch assuming that everything is new to the user.
		if ( array_key_exists($newsgroup, $this->data) ) {
			$tracking_data = $this->data[$newsgroup];
			$old_high_watermark = $tracking_data['watermark'];
			
			// Only search for new unread messages if we got new messages since the
			// last check. If not just preserve the old topic marks.
			if ($new_high_watermark > $old_high_watermark){
				foreach(array_reverse($message_tree) as $topic_id => $reply_ids){
					$topic_number = $message_infos[$topic_id]['number'];
					
					// If there is an old topic mark for this topic preserve it. Otherwise check
					// if this topic contains messages newer than the old high watermark. If so
					// add a new mark for that topic.
					if ( isset($tracking_data['topic-marks'][$topic_number]) ) {
						$new_topic_marks[$topic_number] = $tracking_data['topic-marks'][$topic_number];
					} else {
						$topic_tree = array($topic_id => $reply_ids);
						$topic_iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($topic_tree),  RecursiveIteratorIterator::SELF_FIRST );
						foreach($topic_iterator as $id => $children){
							$message_number = $message_infos[$id]['number'];
							if ($message_number > $old_high_watermark){
								$new_topic_marks[$topic_number] = $message_number;
								break;
							}
						}
					}
					
					// Stop marking topics as unread if we reached the limit
					if (count($new_topic_marks) >= $topic_limit)
						break;
				}
			} else {
				$new_topic_marks = $tracking_data['topic-marks'];
			}
		} else {
			// We have no old tracking data for this newsgroup. Mark all topics as unread
			// until we reach the allowed number of unread topics (`$topic_limit`).
			
			foreach(array_reverse($message_tree) as $topic_id => $reply_ids){
				// Mark each topic in the tree as unread by adding a topic mark that
				// says that in this topic all messages including and after the number
				// of the first message are unread. The first element in the resulting
				// array is the topic number and the second the watermark which
				// defines that starting with this message number all later messages
				// are unread.
				$topic_number = $message_infos[$topic_id]['number'];
				$new_topic_marks[$topic_number] = $topic_number;
				
				// Stop marking topics as unread if we reached the limit
				if (count($new_topic_marks) >= $topic_limit)
					break;
			}
		}
		
		$this->data[$newsgroup] = array(
			'watermark' => intval($new_high_watermark),
			'topic-marks' => $new_topic_marks
		);
	}
	
	/**
	 * Updates the tracker data and saves the new data to the tracking file.
	 */
	function update_and_save($newsgroup, $message_tree, $message_infos, $topic_limit){
		$this->update($newsgroup, $message_tree, $message_infos, $topic_limit);
		self::save($this->data_file, $this->data);
	}
	
	/**
	 * Checks if the specified topic is unread. Returns the message number of the first unread
	 * message if the topic contains unread messages, otherwise `false`. Also returns `false` if
	 * no tracking information is available.
	 */
	function is_topic_unread($newsgroup, $topic_number){
		if ( isset($this->data[$newsgroup]['topic-marks'][$topic_number]) )
			return $this->data[$newsgroup]['topic-marks'][$topic_number];
		return false;
	}
	
	/**
	 * Checks if the message `$message_number` in the topic `$topic_number` in the
	 * newsgroup `$newsgroup` is unread.
	 * 
	 * Returns `true` if the message is marked as unread, otherwise `false`. Also returns
	 * `false` if no tracking information is available.
	 */
	function is_message_unread($newsgroup, $topic_number, $message_number){
		if ( isset($this->data[$newsgroup]['topic-marks'][$topic_number]) )
			return ($message_number >= $this->data[$newsgroup]['topic-marks'][$topic_number]);
		return false;
	}
	
	/**
	 * Marks the specified `$topic_number` in `$newsgroup` as read by removing its
	 * mark pair and saves the tracking data to the file.
	 */
	function mark_topic_read($newsgroup, $topic_number){
		unset($this->data[$newsgroup]['topic-marks'][$topic_number]);
		self::save($this->data_file, $this->data);
	}
	
	/**
	 * Marks all topics in the specified newsgroup as read by deleting all topic markers.
	 * The changes are automatically saved to the tracker file.
	 */
	function mark_all_topics_read($newsgroup){
		if ( isset($this->data[$newsgroup]['topic-marks']) ){
			$this->data[$newsgroup]['topic-marks'] = array();
			self::save($this->data_file, $this->data);
		}
	}
	
	/**
	 * Return `true` if the newsgroup contains unread material, `false` otherwise.
	 */
	function is_newsgroup_unread($newsgroup, $current_high_watermark){
		if ( isset($this->data[$newsgroup]) ) {
			if ($current_high_watermark > $this->data[$newsgroup]['watermark'])
				// If there are new messages not yet tracked show the newsgroup
				// as unread.
				return true;
			else
				// If there are no new messages mark the newsgroup unread if
				// at least one topic is marked as unread.
				return ( count($this->data[$newsgroup]['topic-marks']) > 0 );
		}
		
		// No tracking data available so the user probably didn't visited the
		// newgroup in a long time. Therefore mark as unread.
		return true;
	}
}

?>