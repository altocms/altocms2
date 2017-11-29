<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

namespace avadim\Chrono;

/**
 * Class Chrono
 *
 * @package avadim\Chrono
 */
class Chrono
{
    /**
     * @param string $sInterval
     * @param string $sBaseDate
     *
     * @return DateTimeInterval
     */
    static public function createInterval($sInterval, $sBaseDate = null)
    {
        return new DateTimeInterval($sInterval, $sBaseDate);
    }

    /**
     * @param string $sDateTime
     * @param \DateTimeZone|string $xDateTimeZone
     *
     * @return DateTime
     */
    static public function createDate($sDateTime, $xDateTimeZone = null)
    {
        return new DateTime($sDateTime, $xDateTimeZone);
    }

    /**
     * @param \DateTimeZone|string $xDateTimeZone
     *
     * @return DateTime
     */
    static public function now($xDateTimeZone = null)
    {
        return self::createDate('now', $xDateTimeZone);
    }

    /**
     * @param \DateTimeZone|string $xDateTimeZone
     *
     * @return DateTime
     */
    static public function today($xDateTimeZone = null)
    {
        return self::createFrom(null, null, null, 0, 0, 0, $xDateTimeZone);
    }

    /**
     * @param int|null $iYear
     * @param int|null $iMonth
     * @param int|null $iDay
     * @param int|null $iHour
     * @param int|null $iMinute
     * @param int|null $iSecond
     * @param \DateTimeZone|string $sTimeZone
     *
     * @return DateTime
     */
    static public function createFrom($iYear, $iMonth = null, $iDay = null, $iHour = null, $iMinute = null, $iSecond = null, $sTimeZone = null)
    {
        if (func_num_args() < 7) {
            $aArgs = func_get_args();
            $xLast = end($aArgs);
            if (!is_numeric($xLast)) {
                $sTimeZone = array_pop($aArgs);
            }
            if (count($aArgs) < 6) {
                $aArgs = array_merge($aArgs, array_fill(0, 6 - count($aArgs), null));
            }
            list($iYear, $iMonth, $iDay, $iHour, $iMinute, $iSecond) = $aArgs;
        }
        $oDate = static::now($sTimeZone);
        $sDateString = sprintf(
            '%s-%s-%s %s:%02s:%02s',
            (null !== $iYear) ? $iYear : $oDate->getYear(),
            (null !== $iMonth) ? $iMonth : $oDate->getMonth(),
            (null !== $iDay) ? $iDay : $oDate->getDay(),
            (null !== $iHour) ? $iHour : $oDate->getHour(),
            (null !== $iMinute) ? $iMinute : $oDate->getMinute(),
            (null !== $iSecond) ? $iSecond : $oDate->getSecond()
        );
        return DateTime::createFromFormat('Y-m-d H:i:s', $sDateString, $oDate->getTimezone());
    }

    /**
     * @param int|null $iYear
     * @param int|null $iMonth
     * @param int|null $iDay
     * @param \DateTimeZone|string $sTimeZone
     *
     * @return DateTime
     */
    static public function createFromDate($iYear, $iMonth = null, $iDay = null, $sTimeZone = null)
    {
        return static::createFrom($iYear, $iMonth, $iDay, $iHour = null, $iMinute = null, $iSecond = null, $sTimeZone);
    }

    /**
     * @param int|null $iHour
     * @param int|null $iMinute
     * @param int|null $iSecond
     * @param \DateTimeZone|string $sTimeZone
     *
     * @return DateTime
     */
    static public function createFromTime($iHour = null, $iMinute = null, $iSecond = null, $sTimeZone = null)
    {
        return static::createFrom(null, null, null, $iHour, $iMinute, $iSecond, $sTimeZone);
    }

    /**
     * @param $xDate1
     * @param $xDate2
     *
     * @return DateTimePeriod
     */
    static public function createPeriod($xDate1, $xDate2)
    {
        return new DateTimePeriod($xDate1, $xDate2);
    }

    /**
     * @param string $sMethod
     * @param string $sInterval
     * @param string $sBaseDate
     *
     * @return float
     */
    static private function calcTotal($sMethod, $sInterval, $sBaseDate = null)
    {
        if (is_numeric($sInterval)) {
            return (float)$sInterval;
        }
        if (!is_string($sInterval)) {
            return null;
        }

        $oInterval = static::createInterval($sInterval, $sBaseDate);

        return $oInterval->$sMethod();
    }

    /**
     * Преобразует интервал в число секунд
     *
     * @param string $sInterval  - значение интервала по спецификации ISO 8601 или в человекочитаемом виде
     * @param string $sBaseDate
     *
     * @return  int
     */
    static public function totalSeconds($sInterval, $sBaseDate = null)
    {
        return static::calcTotal('totalSeconds', $sInterval, $sBaseDate);
    }

    /**
     * Преобразует интервал в число секунд
     *
     * @param string $sInterval  - значение интервала по спецификации ISO 8601 или в человекочитаемом виде
     * @param string $sBaseDate
     *
     * @return  int
     */
    static public function totalMinutes($sInterval, $sBaseDate = null)
    {
        return static::calcTotal('totalMinutes', $sInterval, $sBaseDate);
    }

    /**
     * Преобразует интервал в число секунд
     *
     * @param string $sInterval  - значение интервала по спецификации ISO 8601 или в человекочитаемом виде
     * @param string $sBaseDate
     *
     * @return  int
     */
    static public function totalHours($sInterval, $sBaseDate = null)
    {
        return static::calcTotal('totalHours', $sInterval, $sBaseDate);
    }

    /**
     * Преобразует интервал в число секунд
     *
     * @param string $sInterval  - значение интервала по спецификации ISO 8601 или в человекочитаемом виде
     * @param string $sBaseDate
     *
     * @return  int
     */
    static public function totalDays($sInterval, $sBaseDate = null)
    {
        return static::calcTotal('totalDays', $sInterval, $sBaseDate);
    }

    /**
     * @param string $sDate
     * @param string $sInterval
     * @param string $sFormat
     *
     * @return string
     */
    static public function dateAddFormat($sDate, $sInterval, $sFormat = 'Y-m-d H:i:s')
    {
        $oDate = static::createDate($sDate);
        $oInterval = static::createInterval($sInterval);
        $oDate->add($oInterval->interval());

        return $oDate->format($sFormat);
    }

    /**
     * @param string $sDate
     * @param string $sInterval
     * @param string $sFormat
     *
     * @return string
     */
    static public function dateSubFormat($sDate, $sInterval, $sFormat = 'Y-m-d H:i:s')
    {
        $oDate = static::createDate($sDate);
        $oInterval = static::createInterval($sInterval);
        $oDate->sub($oInterval->interval());

        return $oDate->format($sFormat);
    }

    /**
     * @param string $sDate1
     * @param string $sDate2
     *
     * @return int
     */
    static public function dateDiffSeconds($sDate1, $sDate2)
    {
        $oDate1 = static::createDate($sDate1);
        $oDate2 = static::createDate($sDate2);
        $nDiff = $oDate2->getTimestamp() - $oDate1->getTimestamp();

        return $nDiff;
    }

    /**
     * @param $xDate1
     * @param $sOperator
     * @param $xDate2
     *
     * @return bool
     */
    static public function compare($xDate1, $sOperator, $xDate2)
    {
        $oDate1 = static::createDate($xDate1);
        $oDate2 = static::createDate($xDate2);

        return $oDate1->compare($sOperator, $oDate2);
    }

    /**
     * @param $xDate1
     * @param $xDate2
     *
     * @return int
     */
    static public function compareWidth($xDate1, $xDate2)
    {
        $oDate1 = static::createDate($xDate1);
        $oDate2 = static::createDate($xDate2);

        return $oDate1->compareWidth($oDate2);
    }

    /**
     * @param $xComparedDate
     * @param $xDate1
     * @param $xDate2
     * @param bool $bInclude
     *
     * @return bool
     */
    static public function between($xComparedDate, $xDate1, $xDate2, $bInclude = true)
    {
        $oComparedDate = static::createDate($xComparedDate);
        $oDate1 = static::createDate($xDate1);
        $oDate2 = static::createDate($xDate2);

        return $oComparedDate->between($oDate1, $oDate2, $bInclude);
    }

}

// EOF