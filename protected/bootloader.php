<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
if (!defined('ALTO_DIR_ROOT')) {
    die('');
}

/**
 * Versions
 */
define('ALTO_VERSION', '2.0.0-dev');
define('ALTO_PHP_REQUIRED', '5.6'); // required version of PHP
define('ALTO_MYSQL_REQUIRED', '5.0'); // required version of PHP

if (version_compare(phpversion(), ALTO_PHP_REQUIRED) < 0) {
    die ('PHP version ' . ALTO_PHP_REQUIRED . ' or more requires for Alto CMS');
}

// Available since PHP 5.4.0, so fix it
if (empty($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}

define('ALTO_DEBUG_PROFILE', 1);
define('ALTO_DEBUG_FILES', 2);

if (is_file(__DIR__ . '/config.define.php')) {
    include(__DIR__ . '/config.define.php');
}
defined('ALTO_DEBUG') || define('ALTO_DEBUG', 0);

// load basic config with paths
$config = include(__DIR__ . '/config.php');
if (!$config) {
    die('Fatal error: Cannot load file "' . __DIR__ . '/config.php"');
}

// load system functions
$sFuncFile = $config['path']['dir']['engine'] . 'include/Func.php';
if (!is_file($sFuncFile) || !include($sFuncFile)) {
    die('Fatal error: Cannot load file "' . $sFuncFile . '"');
}

if (!defined('ALTO_NO_LOADER')) {
    // load Loader class
    F::includeFile($config['path']['dir']['engine'] . '/classes/core/Loader.php');
    \alto\engine\core\Loader::init($config);
}

// EOF
