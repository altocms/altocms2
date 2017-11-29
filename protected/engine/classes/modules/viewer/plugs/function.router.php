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
 * Плагин для смарти
 * Позволяет получать данные о роутах
 *
 * @param   array  $aParams
 * @param   Smarty $oSmarty
 *
 * @return  string
 */
function smarty_function_router($aParams, &$oSmarty)
{
    if (empty($aParams['page'])) {
        trigger_error("Router: missing 'page' parametr", E_USER_WARNING);
        return '';
    }

    if (!$sPath = R::getLink($aParams['page'])) {
        trigger_error("Router: unknown 'page' given", E_USER_WARNING);
        return '';
    }

    // * Возвращаем полный адрес к указаному Action
    $sReturn = isset($aParams['extend']) ? $sPath . $aParams['extend'] . '/' : $sPath;

    if (!empty($aParams['assign'])) {
        $oSmarty->assign($aParams['assign'], $sReturn);
        return '';
    }

    return $sReturn;
}

// EOF