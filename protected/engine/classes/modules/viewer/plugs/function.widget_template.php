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
 * Plugin for Smarty
 *
 * @param   array                    $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 *
 * @return  string|null
 */
function smarty_function_widget_template($aParams, $oSmartyTemplate) {

    if (!isset($aParams['name'])) {
        trigger_error('Parameter "name" does not define in {widget ...} function', E_USER_WARNING);
        return null;
    }
    $sWidgetName = $aParams['name'];
    $sWidgetTemplate = (!empty($aParams['template']) ? $aParams['template'] : $sWidgetName);
    $aWidgetParams = (!empty($aParams['params']) ? array_merge($aParams['params'], $aParams): $aParams);

    // Проверяем делигирование
    $sTemplate = \E::PluginManager()->getDelegate('template', $sWidgetTemplate);

    if ($sTemplate) {
        if ($aWidgetParams) {
            $oSmartyTemplate->smarty->assign($aWidgetParams);
            $oSmartyTemplate->smarty->assign('aWidgetParams', $aWidgetParams);
        }
        $sResult = $oSmartyTemplate->smarty->fetch($sTemplate);
    } else {
        $sResult = null;
    }

    return $sResult;
}

// EOF