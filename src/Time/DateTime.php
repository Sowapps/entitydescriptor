<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Time;

use DateTime as VanillaDateTime;
use DateTimeZone;
use Exception;
use JsonSerializable;

class DateTime extends VanillaDateTime implements JsonSerializable {
	
	/**
	 * Date constructor
	 *
	 * @param string|null $date SQL Date
	 * @throws Exception
	 */
	public function __construct(?string $date = null) {
		static $timezone;
		if( !$timezone ) {
			$timezone = new DateTimeZone('UTC');
		}
		parent::__construct($date ?? 'now', $timezone);
	}
	
	/**
	 * Get days to reach other date
	 */
	public function getDaysTo(?VanillaDateTime $otherDate = null): int {
		$otherDate = $otherDate ?: new VanillaDateTime();
		return intval($this->diff($otherDate)->format('%R%a'));
	}
	
	/**
	 * Clone as Date
	 *
	 * @throws Exception
	 */
	public function asDate(): Date {
		return new Date(sqlDate($this));
	}
	
	/**
	 * Clone as DateTime
	 *
	 * @throws Exception
	 */
	public function asDateTime(): DateTime {
		return new DateTime(sqlDatetime($this));
	}
	
	/**
	 * Test this date is after the other one
	 */
	public function isAfter(VanillaDateTime $otherDatetime): bool {
		return $this->getTimestamp() > $otherDatetime->getTimestamp();
	}
	
	/**
	 * @throws Exception
	 */
	public function __toString() {
		return dt($this);
	}
	
	public function jsonSerialize(): string {
		return $this->format('c');
	}
}
