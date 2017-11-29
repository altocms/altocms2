<?php

/** Запрещаем напрямую через браузер обращение к этому файлу.  */
if (!class_exists('Plugin')) {
    die('Hacking attempt!');
}

/**
 * PluginRecaptcha.class.php
 * Файл основного класса плагина Recaptcha
 *
 * @author      Андрей Г. Воронов <andreyv@gladcode.ru>
 * @copyrights  Copyright © 2015, Андрей Г. Воронов
 *              Является частью плагина Recaptcha
 *
 * @method void Viewer_AppendStyle
 * @method void Viewer_AppendScript
 * @method void Viewer_Assign
 *
 * @version     0.0.1 от 08.01.2014 19:04
 */
class PluginRecaptcha extends Plugin {

    /**
     * Выведем вместо стандартной капчи - рекапчу
     *
     * @var array
     */
    public $aDelegates = array(
        'template' => array(
            'tpls/commons/common.captcha.registration.tpl',
        )
    );

    /**
     * Затираем старую капчу и в сущности пользователя добавляем
     * метод проверки рекапчи
     *
     * @var array
     */
    protected $aInherits = array(
        'module' => array(
            'ModuleCaptcha',
        ),
        'entity' => array(
            'ModuleCaptcha_EntityCaptcha',
            'ModuleUser_EntityUser',
        ),
    );

    /**
     * Активация плагина
     *
     * @return bool
     */
    public function activate() {
        return TRUE;
    }

    /**
     * Деактивация плагина
     *
     * @return bool
     */
    public function deactivate() {
        return TRUE;
    }

    /**
     * Инициализация плагина
     */
    public function init() {
        // Подключаем скрипт рекапчи
        E::Module('Viewer')->appendScript('https://www.google.com/recaptcha/api.js');
    }

}