<?php
/**
 * Loader File for the Entity Descriptor sources
 */

use Orpheus\Time\DateTime;

function now(): DateTime {
	return new DateTime();
}

/**
 * @throws Exception
 */
function dateTime($time = null): DateTime {
	return new DateTime($time ? sqlDatetime($time) : null);
}
