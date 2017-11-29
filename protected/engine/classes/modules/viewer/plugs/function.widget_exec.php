<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
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
function smarty_function_widget_exec($aParams, $oSmartyTemplate)
{
    if (!isset($aParams['name'])) {
        trigger_error('Parameter "name" does not define in {widget ...} function', E_USER_WARNING);
        return null;
    }

    $sWidgetName = $aParams['name'];
    $sPlugin = (!empty($aParams['plugin']) ? $aParams['plugin'] : '');
    $aWidgetParams = (isset($aParams['params']) ? array_merge($aParams['params'], $aParams): $aParams);

    $sWidget = ucfirst(basename($sWidgetName));
    //$sTemplate = '';

    $sDelegatedClass = \E::PluginManager()->getDelegate('widget', $sWidget);

    // Если делегатов нет, то определаем класс виджета
    if ($sDelegatedClass === $sWidget) {
        // Проверяем наличие класса виджета штатными средствами
        $sWidgetClass = \E::Module('Widget')->fileClassExists($sWidget, $sPlugin, true);
        /*
        if ($sWidgetClass) {
            // Проверяем делегирование найденного класса
            $sWidgetClass = \E::PluginManager()->getDelegate('widget', $sWidgetClass);
            if ($sPlugin) {
                $sPluginTplDir = PluginManager::getTemplateDir($sPlugin);
                //$sTemplate = $sPluginTplDir . 'tpls/widgets/widget.' . $sWidgetName . '.tpl';
                //if ($sFound =  \F::File_Exists('/widgets/widget.' . $sWidgetName . '.tpl', array($sPluginTplDir . 'tpls/', $sPluginTplDir))) {
                //    $sTemplate = $sFound;
                //}
            } else {
                $sTemplate = \E::PluginManager()->getDelegate('template', 'widgets/widget.' . $sWidgetName . '.tpl');
                $sTemplate =  \F::File_Exists($sTemplate, $oSmartyTemplate->smarty->getTemplateDir());
            }
        }
        */
    } else {
        $sWidgetClass = $sDelegatedClass;
    }

    if (!$sWidgetClass) {
        trigger_error('Widget "' . $sWidgetName . '" not found', E_USER_WARNING);
        return null;
    }

    // * Подключаем необходимый обработчик
    /** @var Widget $oWidgetHandler */
    $oWidgetHandler = new $sWidgetClass($aWidgetParams);

    // * Запускаем обработчик
    $sResult = $oWidgetHandler->exec();

    // Если обработчик ничего не вернул, то рендерим шаблон
    /*
    if (!$sResult && $sTemplate) {
        if ($aWidgetParams) {
            $oSmartyTemplate->smarty->assign('aWidgetParams', $aWidgetParams);
        }
        $sResult = $oSmartyTemplate->smarty->fetch($sTemplate);
    }
    */

    return $sResult;
}

// EOF