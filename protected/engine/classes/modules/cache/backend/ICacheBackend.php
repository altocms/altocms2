<?php

F::includeFile(LS_DKCACHE_PATH . 'Zend/Cache.php');
F::includeFile(LS_DKCACHE_PATH . 'Zend/Cache/Backend/Interface.php');

interface ICacheBackend
{
    /**
     * @return bool
     */
    public static function isAvailable();

    /**
     * @param $sName
     *
     * @return mixed
     */
    public function load($sName);

    /**
     * @param $data
     * @param $sName
     * @param $aTags
     * @param $nTimeLife
     *
     * @return mixed
     */
    public function save($data, $sName, $aTags = [], $nTimeLife = false);

    /**
     * @param $sName
     *
     * @return mixed
     */
    public function remove($sName);

    /**
     * @param $sMode
     * @param $aTags
     *
     * @return mixed
     */
    public function clean($sMode = Zend_Cache::CLEANING_MODE_ALL, $aTags = []);

    /**
     * @return bool
     */
    public function isMultiLoad();

    /**
     * @return bool
     */
    public function isConcurrent();

}

// EOF