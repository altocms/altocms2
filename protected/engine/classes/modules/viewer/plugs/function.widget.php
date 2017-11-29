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
 * Eval widgets
 *
 * @param   array                    $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 *
 * @return  string
 */
function smarty_function_widget($aParams, $oSmartyTemplate)
{
    if (!isset($aParams['name']) && !isset($aParams['widget']) && !isset($aParams['id'])) {
        $sError = 'Parameter "name" or "widget" or "id" not define in {widget ...} function';
        if ($oSmartyTemplate->template_resource) {
            $sError .= ' (template: ' . $oSmartyTemplate->template_resource . ')';
        }
         \F::sysWarning($sError);
        return null;
    }

    /** @var ModuleWidget_EntityWidget $oWidget */
    $oWidget = null;
    $sWidgetType = '';
    $sWidgetName = '';
    $sWidgetTemplate = '';
    $aWidgetParams = (isset($aParams['params']) ? array_merge($aParams['params'], $aParams): $aParams);

    if (isset($aParams['name'])) {
        $sWidgetName = $aParams['name'];
        $sWidgetType = 'exec';
    } elseif (isset($aParams['id'])) {
        $aWidgets = $oSmartyTemplate->getTemplateVars('aWidgets');
        if (is_array($aWidgets) && isset($aWidgets['_all_'][$aParams['id']])) {
            $oWidget = $aWidgets['_all_'][$aParams['id']];
        }
    } else {
        $oWidget = $aParams['widget'];
    }
    if ($oWidget) {
        $sWidgetType = $oWidget->getType();
        $sWidgetName = $oWidget->getName();
        $sWidgetTemplate = $oWidget->getTemplate();
        $aWidgetParams = array_merge($oWidget->getParams(), $aWidgetParams);
    }

    $aWidgetParams['name'] = $sWidgetName;
    $aWidgetParams['widget'] = $oWidget;

    $sResult = '';
    $aSavedVars = array(
        'aWidgetParams' => $oSmartyTemplate->getTemplateVars('aWidgetParams'),
        'oWidget' => $oSmartyTemplate->getTemplateVars('oWidget'),
        'params' => $oSmartyTemplate->getTemplateVars('params'),
    );
    $oSmartyTemplate->assign('aWidgetParams', $aWidgetParams);
    $oSmartyTemplate->assign('oWidget', $oWidget);

    if ($sWidgetType === 'exec') {
        if (!function_exists('smarty_function_widget_exec')) {
             \F::includeFile('function.widget_exec.php');
        }
        $sResult = smarty_function_widget_exec($aWidgetParams, $oSmartyTemplate);
    } elseif ($sWidgetType === 'template') {
        if (!function_exists('smarty_function_widget_template')) {
             \F::includeFile('function.widget_template.php');
        }
        $aWidgetParams['template'] = $sWidgetTemplate;
        $sResult = smarty_function_widget_template($aWidgetParams, $oSmartyTemplate);
    }
    $oSmartyTemplate->assign($aSavedVars);

    return $sResult;
}


// EOF