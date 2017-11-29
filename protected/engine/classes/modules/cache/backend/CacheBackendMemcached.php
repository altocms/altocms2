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
F::includeFile(LS_DKCACHE_PATH . 'Zend/Cache/Backend/Memcached.php');

/**
 * Class CacheBackendMemcached
 *
 * Кеш на основе Memcached
 */
class CacheBackendMemcached extends Dklab_Cache_Backend_TagEmuWrapper implements ICacheBackend
{
    /**
     * @return bool
     */
    public static function isAvailable()
    {
        return extension_loaded('memcache');
    }

    /**
     * @param $sFuncStats
     *
     * @return bool|CacheBackendMemcached
     */
    public static function init($sFuncStats)
    {
        if (!self::isAvailable()) {
            return false;
        }

        $aConfigMem = \C::get('memcache');

        $oCache = new Dklab_Cache_Backend_MemcachedMultiload($aConfigMem);
        return new self(new Dklab_Cache_Backend_Profiler($oCache, $sFuncStats));
    }

    /**
     * @return bool
     */
    public function isMultiLoad()
    {
        return true;
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