<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Запрещаем напрямую через браузер обращение к этому файлу.
 */
if (!class_exists('Plugin')) {
    die('Hacking attempt!');
}

class PluginSphinx extends Plugin
{
    protected $aInherits = [
        'action' => [
            'ActionSearch',
            'ActionSphinx',
        ],
        'module' => [
            'ModuleSphinx',
        ],
    ];


    /**
     * Активация плагина
     */
    public function activate()
    {
        return true;
    }

    /**
     * Инициализация плагина
     */
    public function init()
    {
        return true;
    }
}


// EOF