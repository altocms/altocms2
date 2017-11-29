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
F::includeFile(LS_DKCACHE_PATH . 'Zend/Cache/Backend/File.php');

/**
 * Class CacheBackendFile
 *
 * Файловый кеш
 */

class CacheBackendFile extends Dklab_Cache_Backend_Profiler implements ICacheBackend
{
    /**
     * @return bool
     */
    public static function isAvailable()
    {
        return  \F::File_CheckDir(C::get('sys.cache.dir'), true);
    }

    /**
     * @param $sFuncStats
     *
     * @return bool|CacheBackendFile
     */
    public static function init($sFuncStats)
    {
        if (!self::isAvailable()) {
           return false;
        }

        $oCache = new Zend_Cache_Backend_File(
            [
                'cache_dir'              => \C::get('sys.cache.dir'),
                'file_name_prefix'       => \E::Module('Cache')->GetCachePrefix(),
                'read_control_type'      => 'crc32',
                'hashed_directory_level' => \C::get('sys.cache.directory_level'),
                'read_control'           => true,
                'file_locking'           => true,
            ]
        );
        return new self($oCache, $sFuncStats);
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
     * @param bool $bNotTestCacheValidity
     *
     * @return false|mixed
     */
    public function load($sName, $bNotTestCacheValidity = false)
    {
        $xData = parent::load($sName, $bNotTestCacheValidity);
        if ($xData && is_string($xData)) {
            return unserialize($xData);
        }
        return $xData;
    }

    /**
     * @param string $xData
     * @param string $sName
     * @param array $aTags
     * @param bool $nTimeLife
     *
     * @return bool
     */
    public function save($xData, $sName, $aTags = array(), $nTimeLife = false)
    {
        return parent::save(serialize($xData), $sName, $aTags, $nTimeLife);
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
    public function clean($sMode = Zend_Cache::CLEANING_MODE_ALL, $aTags = array())
    {
        return parent::clean($sMode, $aTags);
    }

}

// EOF