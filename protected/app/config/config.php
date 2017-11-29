<?php
/*-------------------------------------------------------
 * @Project: Alto CMS v.2.x.x v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *-------------------------------------------------------
 */
if (!defined('LOCALHOST')) {
    if (
        ($_SERVER['SERVER_ADDR'] === '127.0.0.1' AND $_SERVER['REMOTE_ADDR'] === '127.0.0.1')
        OR (substr($_SERVER['SERVER_NAME'], -4) === '.loc')
        OR (substr($_SERVER['SERVER_NAME'], -6) === '.local')
        OR (substr($_SERVER['SERVER_NAME'], -9) === 'localhost')
    ) {
        define('LOCALHOST', 1);
    } else {
        define('LOCALHOST', 0);
    }
}

defined('ALTO_DEBUG') || define('ALTO_DEBUG', 0);

$_SERVER['HTTP_APP_ENV'] = 'test';

$config['view']['skin'] = 'start-kit';
$config['view']['wysiwyg'] = 1;    // использовать или нет визуальный редактор

/*
 * Config for your site
 * You need to rename this file to "config.local.php" and fill by your settings
 */

/* ============================================================================
 * SETTINGS OF ROOT PATHS FOR YOUR SITE
 * ----------------------------------------------------------------------------
 *
 * If you need to install Alto CMS in a subdirectory (not in the root of the domain, e.g. in site.com/subdir),
 * you should do so:
 *
 * $config['path']['root']['url'] = 'http://site.com/subdir/';
 * $config['path']['root']['dir'] = '/var/www/user/data/www/site.com/subdir/';
 * $config['path']['runtime']['url'] = '/subdir/_run/';
 * $config['path']['runtime']['dir'] = '/var/www/user/data/www/site.com/subdir/_run/';
 *
 * And maybe you should increase value of $config['path']['offset_request_url'] by the number of subdirectories,
 * e.g. for site.ru/subdir/:
 *
 * $config['path']['offset_request_url']   = 1;
 */
//$config['path']['root']['url'] = 'http://altocms.13x.loc/';
//$config['path']['root']['dir'] = ALTO_DIR_ROOT . '/';

//$config['path']['offset_request_url'] = '0';

//$config['path']['runtime']['url'] = '/_run/';
//$config['path']['runtime']['dir'] = ALTO_DIR_ROOT . '/_run/';

/* ============================================================================
 * SETTINGS FOR CONNECTION TO DATABASE
 * ----------------------------------------------------------------------------
 */
$config['db'][0]['host'] = 'localhost';
$config['db'][0]['port'] = '3306';
$config['db'][0]['user'] = 'root';
$config['db'][0]['pass'] = '';
$config['db'][0]['type']   = 'mysqli';
$config['db'][0]['dbname'] = 'alto2xx';
$config['db'][0]['charset'] = 'utf8mb4';

$config['db'][0]['table_prefix'] = 'prefix_';
$config['db'][0]['engine'] = 'InnoDB';
$config['db'][0]['init_sql'] = [
    "set character_set_client='%%charset%%', character_set_results='%%charset%%', collation_connection='utf8_bin' ",
];

$config['sys']['logs']['error_extinfo']     = 1;        // выводить ли дополнительную информацию в лог ошибок
$config['sys']['logs']['error_callstack']   = 1;        // выводить стек вызовов в лог ошибок

/* ============================================================================
 * "SALT" TO ENHANCE THE SECURITY OF HASHABLE DATA
 * ----------------------------------------------------------------------------
 */
$config['security']['salt_sess'] = 'MFRpmhsdTGHphqcz9lLD3qS87RQmZPBm96ED2KWiySXJ_KEfLH5ojA3N2JcSTakd';
$config['security']['salt_pass'] = 'mJt09L3_J2fBVBjhQCgmHsV1pZgWF9ArHvQ_9kRzYyfQjQ67NT_7jxq2UCdxu4TS';
$config['security']['salt_auth'] = 'gtqg1ViAfHY4o3KuC9Vp2P4P3CRlRMeRwE9ameskeyJ3m2R5uAjvDE9ASa9wq3in';

$config['config']['settings'] = [];
if (defined('LOCALHOST')) {
    $config['config']['settings'][] = 'localhost.php';
}

return $config;

// EOF