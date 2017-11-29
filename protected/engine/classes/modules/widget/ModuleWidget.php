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
 * Модуль управления виджетами
 */
class ModuleWidget extends \Module
{
    const WIDGET_TYPE_UNKNOWN = 0;
    const WIDGET_TYPE_TEMPLATE = 1;
    const WIDGET_TYPE_EXEC = 2;

    protected $aWidgets = [];
    protected $aConfig = [];

    /**
     * Сопоставление заданных путей с текущим
     *
     * @param   string|array    $aPaths
     * @param   bool            $bDefault
     * @return  bool
     */
    protected function _checkPath($aPaths, $bDefault = true)
    {
        if ($aPaths) {
            return \R::cmpControllerPath($aPaths);
        }
        return $bDefault;
    }

    /**
     * Инициализация модуля
     */
    public function init()
    {
    }

    /**
     * Returns full widget data (extends other widget or config dataset if needs)
     *
     * @param string|null $sWidgetId
     * @param array       $aWidgetData
     * @param array       $aWidgets
     *
     * @return array
     */
    protected function _getWidgetData($sWidgetId, $aWidgetData, $aWidgets)
    {
        if (!empty($aWidgetData[\C::KEY_REPLACE])) {
            unset($aWidgetData[\C::KEY_EXTENDS]);
            return $aWidgetData;
        }

        $xExtends = true;
        $bReset = false;
        if (!empty($aWidgetData[\C::KEY_EXTENDS])) {
            $xExtends = $aWidgetData[\C::KEY_EXTENDS];
            unset($aWidgetData[\C::KEY_EXTENDS]);
        }
        if (!empty($aWidgetData[\C::KEY_RESET])) {
            $bReset = $aWidgetData[\C::KEY_RESET];
            unset($aWidgetData[\C::KEY_RESET]);
        }
        if ($xExtends) {
            if (($xExtends === true) && $sWidgetId && isset($aWidgets[$sWidgetId])) {
                if ($bReset) {
                    $aWidgetData =  \F::Array_Merge($aWidgets[$sWidgetId], $aWidgetData);
                } else {
                    $aWidgetData =  \F::Array_MergeCombo($aWidgets[$sWidgetId], $aWidgetData);
                }
            } elseif(is_string($xExtends)) {
                if ($bReset) {
                    $aWidgetData =  \F::Array_Merge(C::get($xExtends), $aWidgetData);
                } else {
                    $aWidgetData =  \F::Array_MergeCombo(C::get($xExtends), $aWidgetData);
                }
            }
        }
        return $aWidgetData;
    }

    /**
     * Загружает список виджетов и конфигурирует их
     *
     * @return array
     */
    protected function _loadWidgetsList()
    {
        // Список виджетов из основного конфига
        $aWidgets = (array)C::get('widgets');

        // Добавляем списки виджетов из конфигов плагинов
        $aPlugins =  \F::GetPluginsList();
        if ($aPlugins) {
            foreach($aPlugins as $sPlugin) {
                if ($aPluginWidgets = \C::get('plugin.' . $sPlugin . '.widgets', null, true)) {
                    foreach ($aPluginWidgets as $xKey => $aWidgetData) {
                        // ID виджета может задаваться либо ключом элемента массива, либо параметром 'id'
                        if (isset($aWidgetData['id'])) {
                            $sWidgetId = $aWidgetData['id'];
                        } elseif (!is_int($xKey)) {
                            $sWidgetId = $aWidgetData['id'] = $xKey;
                        } else {
                            $sWidgetId = null;
                        }
                        if (!empty($aWidgetData['plugin']) && $aWidgetData['plugin'] === true) {
                            $aWidgetData['plugin'] = $sPlugin;
                        }
                        if (!empty($aWidgets[$sWidgetId])) {
                            $aWidgetData = $this->_getWidgetData($sWidgetId, $aWidgetData, $aWidgets);
                        }
                        if ($sWidgetId) {
                            $aWidgets[$sWidgetId] = $aWidgetData;
                        } else {
                            $aWidgets[] = $aWidgetData;
                        }
                    }
                    //$aWidgets =  \F::Array_MergeCombo($aWidgets, $aPluginWidgets);
                }
            }
        }
        $aResult = [];
        if ($aWidgets) {
            // формируем окончательный список виджетов
            foreach ($aWidgets as $sKey => $aWidgetData) {
                if ($aWidgetData) {
                    // Если ID виджета не задан, то он формируется автоматически
                    if (!is_numeric($sKey) && !isset($aWidgetData['id'])) {
                        $aWidgetData['id'] = $sKey;
                    }
                    $oWidget = $this->makeWidget($aWidgetData);
                    $aResult[$oWidget->getId()] = $oWidget;
                }
            }
        }
        return $aResult;
    }

    /**
     * Создает сущность виджета по переданным свойствам
     *
     * @param   array                       $aWidgetData
     *
     * @return  ModuleWidget_EntityWidget
     */
    public function makeWidget($aWidgetData)
    {
        $oWidget = \E::getEntity('Widget', $aWidgetData);

        return $oWidget;
    }

    /**
     * Возвращает массив виджетов
     *
     * @param   bool    $bAll   - если true, то все виджеты, иначе - только те, что должны быть отображены
     * @return  array
     */
    public function getWidgets($bAll = false)
    {
        $aWidgets = $this->_loadWidgetsList();

        // Если массив пустой или фильтровать не нужно, то возвращаем, как есть
        if (!$aWidgets || $bAll) {
            return $aWidgets;
        }
        /** @var ModuleWidget_EntityWidget $oWidget */
        foreach ($aWidgets as $oWidget) {
            if ($oWidget->isDisplay()) {
                if (R::allowControllerPath($oWidget->GetIncludePaths(), $oWidget->GetExcludePaths())) {
                    $this->aWidgets[$oWidget->GetId()] = $oWidget;
                }
            }
        }
        return $this->aWidgets;
    }

    /**
     * Проверяет существование файла класса исполняемого виджета
     *
     * @param   string      $sName
     * @param   string|null $sPlugin
     * @param   bool        $bReturnClassName
     * 
     * @return  string|bool
     */
    public function fileClassExists($sName, $sPlugin = null, $bReturnClassName = false)
    {
        $sSeekName =  \F::StrCamelize($sName);
        $xResult = $this->_fileClassExists($sSeekName, $sPlugin, $bReturnClassName);
        if ($xResult === false) {
            $sSeekName = ucfirst($sName);
            $xResult = $this->_fileClassExists($sSeekName, $sPlugin, $bReturnClassName);
        }

        return $xResult;
    }

    /**
     * @param   string      $sName
     * @param   string|null $sPlugin
     * @param   bool        $bReturnClassName
     *
     * @return  string|bool
     */
    protected function _fileClassExists($sName, $sPlugin = null, $bReturnClassName = false)
    {
        if (!$sPlugin) {
            $aPathSeek = \C::get('path.root.seek');
            $sFile = '/classes/widgets/Widget' . $sName . '.php';
            $sClass = 'Widget' . $sName;
        } else {
            $aPathSeek = \Plugin::GetPath($sPlugin);
            $sFile = '/classes/widgets/Widget' . $sName . '.php';
            $sClass = 'Plugin' .  \F::StrCamelize($sPlugin) . '_Widget' . $sName;
        }
        if (\F::File_Exists($sFile, $aPathSeek)) {
            return $bReturnClassName ? $sClass : $sFile;
        }
        return false;
    }

}

// EOF