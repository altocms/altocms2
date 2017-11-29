<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

defined('ALTO_DIR_ROOT') || define('ALTO_DIR_ROOT', dirname(__FILE__));

// Run engine loader
require_once(ALTO_DIR_ROOT . '/protected/bootloader.php');

// Creates and executes application
App::Create()->Exec();

// EOF