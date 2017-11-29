<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

F::includeFile('./ICacheBackend.php');

/**
 * Class CacheBackendFile
 *
 * Кеш в памяти
 *
 * Рекомендуется для хранения небольших объемов данных, к которым возможно частое обращение
 * в течение обработки одного запроса. Может привести к увеличению требуемой памяти, но самый быстрый
 * из всех видов кеша
 *
 * Категорически НЕЛЬЗЯ использовать, как кеш всего приложения!!!
 */

class CacheBackendTmp extends Dklab_Cache_Backend_Profiler implements ICacheBackend
{
    static protected $aStore = [];

    /**
     * @return bool
     */
    public static function isAvailable()
    {
        return true;
    }

    /**
     * @param $sFuncStats
     *
     * @return CacheBackendTmp
     */
    public static function init($sFuncStats)
    {
        self::$aStore = [];
        $oCache = new self();
        return new self();
    }

    /**
     * Нужно, чтобы переопределить конструктор родителя
     */
    public function __construct()
    {
    }

    /**
     * @return bool
     */
    public function isMultiLoad()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConcurrent()
    {
        return false;
    }

    /**
     * @param string $sName
     * @param bool $bNotTestCacheValidity
     *
     * @return bool
     */
    public function load($sName, $bNotTestCacheValidity = false)
    {
        if (isset(self::$aStore[$sName])) {
            $aData = self::$aStore[$sName];
            if (is_array($aData) && array_key_exists('data', $aData)) {
                return  \F::Unserialize($aData['data'], false);
            }
        }
        return false;
    }

    /**
     * @param string $xData
     * @param string $sName
     * @param array $aTags
     * @param bool $nTimeLife
     *
     * @return bool
     */
    public function save($xData, $sName, $aTags = [], $nTimeLife = false)
    {
        $xValue =  \F::serialize($xData);
        self::$aStore[$sName] = [
            'tags' => (array)$aTags,
            'data' => $xValue,
            'time' => $nTimeLife ? time() + (int)$nTimeLife : false,
        ];
        return true;
    }

    /**
     * @param string $sName
     *
     * @return bool
     */
    public function remove($sName) {

        if (isset(self::$aStore[$sName])) {
            unset(self::$aStore[$sName]);
        }
        return true;
    }

    /**
     * @param string $sMode
     * @param array $aTags
     *
     * @return bool
     */
    public function clean($sMode = Zend_Cache::CLEANING_MODE_ALL, $aTags = [])
    {
        if ($sMode == Zend_Cache::CLEANING_MODE_ALL) {
            // Удаление всех значений
            self::$aStore = [];
        } elseif ($sMode == Zend_Cache::CLEANING_MODE_OLD) {
            // Удаление устаревших значений
            $nTime = time();
            foreach (self::$aStore as $sName=>$aData) {
                if ($aData['time'] && $aData['time'] < $nTime) {
                    unset(self::$aStore[$sName]);
                }
            }
        } elseif ($aTags) {
            // Удаление по тегам
            foreach (self::$aStore as $sName=>$aData) {
                if (Zend_Cache::CLEANING_MODE_MATCHING_TAG && $aData['tags'] && array_intersect($aTags, $aData['tags'])) {
                    unset(self::$aStore[$sName]);
                } elseif (Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG && $aData['tags'] && !array_intersect($aTags, $aData['tags'])) {
                    unset(self::$aStore[$sName]);
                }
            }
        }
        return true;
    }

    public function setDirectives($directives)
    {

    }

    public function test($id)
    {

    }

}

// EOF