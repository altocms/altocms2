<?php
/*-------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *-------------------------------------------------------
 */

require_once \Config::get('path.dir.libs') . '/DbSimple/autoload.php';

return [
    'alias'  => [
        'F' => '\F',
        'R' => '\alto\engine\core\Router',
        'C' => '\alto\engine\core\Config',
        'E' => '\alto\engine\core\Engine',
        'App' => '\alto\engine\core\Application',
        'Config' => '\alto\engine\core\Config',
    ],
    'class'  => [
        //'Component' => '___path.dir.engine___/classes/abstract/Component.class.php',
    ],
    'prefix' => [
        //
    ],
];

// EOF