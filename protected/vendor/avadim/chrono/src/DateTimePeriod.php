<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

namespace avadim\Chrono;

/**
 * Class DateTimePeriod
 *
 * @package avadim\Chrono
 */
class DateTimePeriod
{
    protected $oDate1;
    protected $oDate2;

    public function __construct($xDate1, $xDate2)
    {
        $this->oDate1 = new DateTime($xDate1);
        $this->oDate2 = new DateTime($xDate2);
    }

    /**
     * @param string $sPeriod
     * @param string $sFormat
     *
     * @return array
     */
    public function sequenceOf($sPeriod, $sFormat = null)
    {
        $aSequence = [];
        $oDate = clone $this->oDate1;
        do {
            if ($sFormat) {
                $oDate->setDefaultFormat($sFormat);
            }
            $aSequence[] = $oDate;
            $oDate = clone $oDate;
            $oDate->modify($sPeriod);
        } while($oDate->compare('<=', $this->oDate2));

        return $aSequence;
    }

    /**
     * @return array
     */
    public function sequenceOfSeconds()
    {
        return $this->sequenceOf('+1 second');
    }

    /**
     * @return array
     */
    public function sequenceOfMinutes()
    {
        return $this->sequenceOf('+1 minute');
    }

    /**
     * @return array
     */
    public function sequenceOfHours()
    {
        return $this->sequenceOf('+1 hour');
    }

    /**
     * @return array
     */
    public function sequenceOfDays()
    {
        return $this->sequenceOf('+1 day');
    }

    /**
     * @return array
     */
    public function sequenceOfWeeks()
    {
        return $this->sequenceOf('+7 days');
    }

    /**
     * @return array
     */
    public function sequenceOfMonths()
    {
        return $this->sequenceOf('+1 month');
    }

    /**
     * @return array
     */
    public function sequenceOfQuarters()
    {
        return $this->sequenceOf('+3 months', 'YQ');
    }

}

// EOF