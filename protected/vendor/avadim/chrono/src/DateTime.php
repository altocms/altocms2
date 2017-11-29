<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

namespace avadim\Chrono;

/**
 * Class DateTime
 *
 * @package avadim\Chrono
 */
class DateTime extends \DateTime
{
    protected $sDefaultFormat = 'Y-m-d H:i:s';

    public function __construct($sDateTime = 'now', $xDateTimeZone = null)
    {
        parent::__construct($sDateTime, DateTimeZone::create($xDateTimeZone));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->format($this->sDefaultFormat);
    }

    /**
     * Format date using strftime() function
     *
     * @param string $sFormat
     *
     * @return string
     */
    public function strFormat($sFormat)
    {
        $sDate = strftime($sFormat, $this->getTimestamp());
        if (false !== strpos($sFormat, '%q')) {
            $sDate = str_replace('%q', $this->getQuarter(), $sDate);
        }
        return $sDate;
    }

    /**
     * @return string
     */
    public function strSecond()
    {
        return $this->format('Y-m-d H:i:s');
    }

    /**
     * @return string
     */
    public function strMinute()
    {
        return $this->format('Y-m-d H:i');
    }

    /**
     * @return string
     */
    public function strHour()
    {
        return $this->format('Y-m-d H');
    }

    /**
     * @return string
     */
    public function strDay()
    {
        return $this->format('Y-m-d');
    }

    /**
     * @param string $sDelimiter
     *
     * @return string
     */
    public function strWeek($sDelimiter = 'W')
    {
        return $this->strFormat('%Y' . $sDelimiter . '%V');
    }

    /**
     * @param string $sDelimiter
     *
     * @return string
     */
    public function strMonth($sDelimiter = '-')
    {
        return $this->strFormat('%Y' . $sDelimiter . '%m');
    }

    /**
     * @param string $sDelimiter
     *
     * @return string
     */
    public function strQuarter($sDelimiter = 'Q')
    {
        return (string)$this->getYear() . $sDelimiter . $this->getQuarter();
    }

    /**
     * @return string
     */
    public function strYear()
    {
        return (string)$this->getYear();
    }

    /**
     * @param string $sFormat
     * @param string $sDateTime
     * @param \DateTimeZone $xDateTimeZone
     *
     * @return bool|\DateTime
     */
    static public function createFromFormat($sFormat, $sDateTime, $xDateTimeZone = null)
    {
        $oDateTime = parent::createFromFormat($sFormat, $sDateTime, DateTimeZone::create($xDateTimeZone));

        return new static($oDateTime->format('Y-m-d H:i:s'), $oDateTime->getTimezone());
    }

    /**
     * @param $sFormat
     *
     * @return $this
     */
    public function setDefaultFormat($sFormat)
    {
        $this->sDefaultFormat = $sFormat;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuarter()
    {
        return floor(($this->getMonth() - 1) / 3) + 1;
    }

    /**
     * @return int
     */
    public function getYear()
    {
        return (int)$this->format('Y');
    }

    /**
     * @return int
     */
    public function getMonth()
    {
        return (int)$this->format('m');
    }

    /**
     * @return int
     */
    public function getWeek()
    {
        return (int)$this->format('W');
    }

    /**
     * @return int
     */
    public function getDay()
    {
        return (int)$this->format('d');
    }

    /**
     * @return int
     */
    public function getHour()
    {
        return (int)$this->format('H');
    }

    /**
     * @return int
     */
    public function getMinute()
    {
        return (int)$this->format('i');
    }

    /**
     * @return int
     */
    public function getSecond()
    {
        return (int)$this->format('s');
    }

    /**
     * @param int    $iIntervalValue
     * @param string $sIntervalSymbol
     *
     * @return $this|static
     */
    private function addInterval($iIntervalValue, $sIntervalSymbol)
    {
        $iIntervalValue = (int)$iIntervalValue;
        if ($iIntervalValue > 0) {
            return $this->add(new \DateInterval('P' . $iIntervalValue . $sIntervalSymbol));
        } elseif ($iIntervalValue < 0) {
            return $this->sub(new \DateInterval('P' . -$iIntervalValue . $sIntervalSymbol));
        }
        return $this;
    }

    /**
     * @param int $iYears
     *
     * @return static
     */
    public function addYears($iYears)
    {
        return $this->addInterval($iYears, 'Y');
    }

    /**
     * @param int $iYears
     *
     * @return static
     */
    public function subYears($iYears)
    {
        return $this->addYears(-$iYears);
    }

    /**
     * @param int $iYear
     *
     * @return static
     */
    public function setYear($iYear)
    {
        return $this->addYears($iYear - $this->getYear());
    }

    /**
     * @param int $iMonths
     *
     * @return static
     */
    public function addMonths($iMonths)
    {
        return $this->addInterval($iMonths, 'n');
    }

    /**
     * @param int $iMonths
     *
     * @return static
     */
    public function subMonths($iMonths)
    {
        return $this->addMonths(-$iMonths);
    }

    /**
     * @param int $iMonth
     *
     * @return static
     */
    public function setMonth($iMonth)
    {
        return $this->addMonths($iMonth - $this->getMonth());
    }

    /**
     * @param int $iDays
     *
     * @return static
     */
    public function addDays($iDays)
    {
        return $this->addInterval($iDays, 'j');
    }

    /**
     * @param int $iDays
     *
     * @return static
     */
    public function subDays($iDays)
    {
        return $this->addDays(-$iDays);
    }

    /**
     * @param int $iDay
     *
     * @return static
     */
    public function setDay($iDay)
    {
        return $this->addDays($iDay - $this->getDay());
    }

    /**
     * @param $iHours
     *
     * @return static
     */
    public function addHours($iHours)
    {
        return $this->addInterval($iHours, 'H');
    }

    /**
     * @param int $iHours
     *
     * @return static
     */
    public function subHours($iHours)
    {
        return $this->addHours(-$iHours);
    }

    /**
     * @param int $iHour
     *
     * @return static
     */
    public function setHour($iHour)
    {
        return $this->addHours($iHour - $this->getHour());
    }

    /**
     * @param $iMinutes
     *
     * @return static
     */
    public function addMinutes($iMinutes)
    {
        return $this->addInterval($iMinutes, 'i');
    }

    /**
     * @param int $iMinutes
     *
     * @return static
     */
    public function subMinutes($iMinutes)
    {
        return $this->addMinutes(-$iMinutes);
    }

    /**
     * @param int $iMinutes
     *
     * @return static
     */
    public function setMinute($iMinutes)
    {
        return $this->addDays($iMinutes - $this->getMinute());
    }

    /**
     * @param int $iSeconds
     *
     * @return static
     */
    public function addSeconds($iSeconds)
    {
        return $this->addInterval($iSeconds, 's');
    }

    /**
     * @param int $iSeconds
     *
     * @return static
     */
    public function subSeconds($iSeconds)
    {
        return $this->addSeconds(-$iSeconds);
    }

    /**
     * @param int $iSecond
     *
     * @return static
     */
    public function setSecond($iSecond)
    {
        return $this->addDays($iSecond - $this->getSecond());
    }

    /**
     * @param string   $sOperator
     * @param DateTime $oDate
     *
     * @return bool
     */
    public function compare($sOperator, $oDate)
    {
        $sDate1 = $this->format('Y-m-d H:i:s');
        $sDate2 = $oDate->format('Y-m-d H:i:s');
        switch ($sOperator) {
            case '<':
            case 'lt':
                return $sDate1 < $sDate2;
            case '<=':
            case 'le':
            case 'lte':
                return $sDate1 < $sDate2;
            case '=':
            case '==':
            case 'eq':
                return $sDate1 < $sDate2;
            case '!=':
            case '<>':
            case 'ne':
                return $sDate1 < $sDate2;
            case '>=':
            case 'gte':
            case 'ge':
                return $sDate1 < $sDate2;
            case '>':
            case 'gt':
                return $sDate1 < $sDate2;
        }
        return false;
    }

    /**
     * @param DateTime $oDate
     *
     * @return int
     */
    public function compareWidth($oDate)
    {
        $sDate1 = $this->format('Y-m-d H:i:s');
        $sDate2 = $oDate->format('Y-m-d H:i:s');
        if ($sDate1 < $sDate2) {
            return -1;
        }
        if ($sDate1 > $sDate2) {
            return 1;
        }
        return 0;
    }

    /**
     * @param $oDate1
     * @param $oDate2
     * @param bool $bInclude
     *
     * @return bool
     */
    public function between($oDate1, $oDate2, $bInclude = true)
    {
        if ($bInclude) {
            return $this->compare('>=', $oDate1) && $this->compare('<=', $oDate2);
        }
        return $this->compare('>', $oDate1) && $this->compare('<', $oDate2);
    }

    /**
     * @return int
     */
    public function isLeapYear()
    {
        return (int)$this->format('L');
    }

}

// EOF

// EOF