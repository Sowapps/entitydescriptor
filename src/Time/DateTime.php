<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Time;

use DateTime as VanillaDateTime;
use DateTimeZone;
use Exception;

class DateTime extends VanillaDateTime {
	
	/**
	 * Date constructor
	 *
	 * @param string $date SQL Date
	 * @throws Exception
	 */
	public function __construct($date = null) {
		static $timezone;
		if( !$timezone ) {
			$timezone = new DateTimeZone('UTC');
		}
		parent::__construct($date, $timezone);
	}
	
	/**
	 * Get days to reach other date
	 *
	 * @param VanillaDateTime|null $otherDate
	 * @return int
	 */
	public function getDaysTo(?VanillaDateTime $otherDate = null) {
		$otherDate = $otherDate ?: new VanillaDateTime();
		return intval($this->diff($otherDate)->format('%R%a'));
	}
	
	/**
	 * Clone as Date
	 *
	 * @return Date
	 * @throws Exception
	 */
	public function asDate() {
		return new Date(sqlDate($this));
	}
	
	/**
	 * Clone as DateTime
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	public function asDateTime() {
		return new DateTime(sqlDatetime($this));
	}
	
	public function __toString() {
		return dt($this);
	}
	
}
