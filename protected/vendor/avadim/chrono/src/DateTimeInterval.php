<?php
/**
 * This file is part of the avadim\Chrono package
 * https://github.com/aVadim483/Chrono
 */

namespace avadim\Chrono;

/**
 * Class DateTimeInterval
 *
 * @package avadim\Chrono
 */
class DateTimeInterval
{
    const PT1S = 1; // 1; = 1 second
    const PT1M = 60; // 60 * 1; = 1 minute
    const PT1H = 3600; // 60 * 60 * 1; = 1 hour
    const P1D = 86400; // 60 * 60 * 24 * 1; = 1 day
    const P1W = 604800; // 60 * 60 * 24 * 7; = 1 week
    const P1M = 2592000; // 60 * 60 * 24 * 30; = 1 month
    const P1Y = 31536000; // 60 * 60 * 24 * 365; = 1 year

    protected $sBaseDate;
    protected $oDT;

    /**
     * DateTimeInterval constructor.
     *
     * @param $sInterval
     * @param null $sBaseDate
     */
    public function __construct($sInterval, $sBaseDate = null)
    {
        try {
            if (is_numeric($sInterval)) {
                $this->oDT = new \DateInterval('PT' . $sInterval . 'S');
            } elseif (is_string($sInterval) && $sInterval[0] == 'P') {
                $this->oDT = new \DateInterval($sInterval);
            } else {
                $this->oDT = \DateInterval::createFromDateString($sInterval);
            }
        } catch (\RuntimeException $oE) {
            $this->oDT = new \DateInterval(self::normalize($sInterval));
        }
        if ($sBaseDate) {
            $this->sBaseDate = $sBaseDate;
        }
    }

    /**
     * Normalizes interval according by ISO 8601
     *
     * @param   string  $sInterval
     * @return  string
     */
    static public function normalize($sInterval)
    {
        $sResult = '';
        if (preg_match('/P(?P<y>\d+Y)?(?P<m>\d+M)?(?P<w>\d+W)?(?P<d>\d+D)?(T)?(?P<th>\d+H)?(?P<tm>\d+M)?(?P<ti>\d+I)?(?P<ts>\d+S)?/', $sInterval, $aM)) {
            $sP = '';
            $sT = '';
            if (isset($aM['y'])) {
                $sP .= $aM['y'];
            }
            if (isset($aM['m'])) {
                $sP .= $aM['m'];
            }
            // не может быть использован совместно с D
            if (isset($aM['w']) && !isset($aM['d'])) {
                $sP .= $aM['d'];
            }
            if (isset($aM['d'])) {
                $sP .= $aM['d'];
            }
            if (isset($aM['th'])) {
                $sT .= $aM['th'];
            }
            if (isset($aM['tm'])) {
                $sT .= $aM['tm'];
            }
            // нестандартный I заменяем на M
            if (!isset($aM['tm']) && isset($aM['ti'])) {
                $sT .= str_replace('I', 'M', $aM['ti']);
            }
            if (isset($aM['ts'])) {
                $sT .= $aM['ts'];
            }
            if ($sP || $sT) {
                $sResult = 'P';
            }
            if ($sP) {
                $sResult .= $sP;
            }
            if ($sT) {
                $sResult .= 'T' . $sT;
            }
        }
        if ($sResult) {
            return $sResult;
        }
        return 'PT0S';
    }

    /**
     * Get total seconds of interval
     *
     * @param null $sBaseDate
     *
     * @return float
     */
    public function totalSeconds($sBaseDate = null)
    {
        if (null !== $sBaseDate || $this->sBaseDate) {
            $oDate1 = new \DateTimeImmutable($sBaseDate ?: $this->sBaseDate);
            $oDate2 = $oDate1->add($this->oDT);
            $oInterval = $oDate2->diff($oDate1);
            return (int)$oInterval->format('%a') * self::P1D + $oInterval->h * self::PT1H + $oInterval->i * self::PT1M + $oInterval->s + $this->oDT->f;
        }
        return ($this->oDT->y * self::P1Y)
            + ($this->oDT->m * self::P1M)
            + ($this->oDT->d * self::P1D)
            + ($this->oDT->h * self::PT1H)
            + ($this->oDT->i * self::PT1M)
            + $this->oDT->s + $this->oDT->f;
    }

    /**
     * Get total minutes of interval
     *
     * @param null $sBaseDate
     *
     * @return float
     */
    public function totalMinutes($sBaseDate = null)
    {
        return floor($this->totalSeconds($sBaseDate) / self::PT1M);
    }

    /**
     * Get total hours of interval
     *
     * @param null $sBaseDate
     *
     * @return float
     */
    public function totalHours($sBaseDate = null)
    {
        return floor($this->totalSeconds($sBaseDate) / self::PT1H);
    }

    /**
     * Get total days of interval
     *
     * @param null $sBaseDate
     *
     * @return float
     */
    public function totalDays($sBaseDate = null)
    {
        return floor($this->totalSeconds($sBaseDate) / self::P1D);
    }

    /**
     * @return \DateInterval
     */
    public function interval()
    {
        return $this->oDT;
    }
}

// EOF