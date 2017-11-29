<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Позволяет транслировать данные в json
 *
 * @param  $params
 * @param  $smarty
 * @return string
 */
function smarty_function_json($params, &$smarty) {

    if (!array_key_exists('var', $params)) {
        trigger_error("json: missing 'var' parameter", E_USER_WARNING);
        return;
    }

    if (class_exists('Entity') && is_object($params['var']) && $params['var'] instanceof Entity) {
        $oEntity = $params['var'];
        $aMethods = null;
        if (!empty($params['methods'])) {
            $aMethods = is_array($params['methods'])
                ? $params['methods']
                : explode(',', $params['methods']);
        }
        $var = $oEntity->ToArray($aMethods);
    } else {
        $var = $params['var'];
    }

    $_contents =  \F::jsonEncode($var);

    if (!empty($params['assign'])) {
        $smarty->assign($params['assign'], $_contents);
    } else {
        return $_contents;
    }
}

// EOF