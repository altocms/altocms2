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
 * Плагин для Smarty
 * Позволяет получать данные из конфига
 *
 * @param   array $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 * @return  string
 */
function smarty_function_cfg($aParams, $oSmartyTemplate) {

    if (empty($aParams['name'])) {
        trigger_error('Config: missing "name" parametr', E_USER_WARNING);
        return;
    }

    if (!isset($aParams['instance'])) {
        $aParams['instance'] = null;
    }

    if (!isset($aParams['default'])) {
        $aParams['default'] = null;
    }

    if (!isset($aParams['level'])) {
        $aParams['level'] = null;
    }

    /**
     * Возвращаем значение из конфигурации
     */
    $xResult = \C::get($aParams['name'], $aParams['instance'], $aParams['level']);
    return is_null($xResult) ? $aParams['default'] : $xResult;
}

// EOF
