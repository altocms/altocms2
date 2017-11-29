<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 */

namespace alto\engine\generic;

/**
 * Class Singleton
 *
 * @package engine
 * @since   1.3
 */
abstract class Singleton
{
    static protected $aInstances = [];

    /**
     * @return static
     */
    public static function getInstance()
    {
        $sClass = get_called_class();
        if (empty(static::$aInstances[$sClass])) {
            static::$aInstances[$sClass] = new static();
        }
        return static::$aInstances[$sClass];
    }
    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
    }

}

// EOF