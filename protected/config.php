<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
if (!defined('ALTO_DIR_ROOT')) die('');

/*
 *  ALTO_DIR_ROOT   - root directory of current site
 *  ALTO_DIR_MAIN   - protected directory width Alto CMS scripts
 */
defined('ALTO_DIR_MAIN') || define('ALTO_DIR_MAIN', dirname(__FILE__));

/**
 * Настройка путей для первичной загрузки
 */
$config = [];

$config['path']['dir']['engine']        = ALTO_DIR_MAIN . '/engine/';           // Путь к папке движка
$config['path']['dir']['libs']          = ALTO_DIR_MAIN . '/engine/libs/';      // Путь к библиотекам движка по умолчанию
$config['path']['dir']['vendor']        = ALTO_DIR_MAIN . '/vendor/';           // Путь к папке сторонних библиотек и классов
$config['path']['dir']['common']        = ALTO_DIR_MAIN . '/common/';           // Путь к общим компонентам по умолчанию
$config['path']['dir']['config']        = ALTO_DIR_MAIN . '/common/config/';    // Путь к папке конфигурации по умолчанию
$config['path']['dir']['app']           = ALTO_DIR_MAIN . '/app/';              // Путь к папке приложения по умолчанию

return $config;

// EOF