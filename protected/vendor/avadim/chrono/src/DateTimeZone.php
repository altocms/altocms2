<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

namespace avadim\Chrono;

/**
 * Class DateTimeZone
 *
 * @package avadim\Chrono
 */
class DateTimeZone extends \DateTimeZone
{
    static public function create($xDateTimeZone = null)
    {
        if (null === $xDateTimeZone) {
            $xDateTimeZone = date_default_timezone_get();
        } elseif (is_numeric($xDateTimeZone)) {
            $xDateTimeZone = timezone_name_from_abbr(null, $xDateTimeZone * 3600, true);
        } elseif ($xDateTimeZone instanceof \DateTimeZone) {
            $xDateTimeZone = $xDateTimeZone->getName();
        }
        return new static($xDateTimeZone);
    }
}

// EOF