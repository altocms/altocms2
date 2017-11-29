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
F::includeFile(LS_DKCACHE_PATH . 'Zend/Cache/Backend/Xcache.php');

/**
 * Class CacheBackendXcache
 *
 * Кеш на основе XCache
 */
class CacheBackendXcache extends Dklab_Cache_Backend_TagEmuWrapper implements ICacheBackend
{
    /**
     * @return bool
     */
    public static function isAvailable()
    {
        return extension_loaded('xcache');
    }

    /**
     * @param $sFuncStats
     *
     * @return bool|CacheBackendXcache
     */
    public static function Init($sFuncStats)
    {
        if (!self::isAvailable()) {
            return false;
        }

        $aConfigMem = \C::get('xcache');

        $oCahe = new Zend_Cache_Backend_Xcache(is_array($aConfigMem) ? $aConfigMem : []);

        return new self(new Dklab_Cache_Backend_Profiler($oCahe, $sFuncStats));
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
        return true;
    }

    /**
     * @param string $sName
     *
     * @return bool
     */
    public function remove($sName)
    {
        return parent::remove($sName);
    }

    /**
     * @param string $sMode
     * @param array $aTags
     *
     * @return bool
     */
    public function clean($sMode = Zend_Cache::CLEANING_MODE_ALL, $aTags = [])
    {
        return parent::clean($sMode, $aTags);
    }

}

// EOF