<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
namespace alto\engine\core;

use alto\engine\generic\Singleton;

/**
 * Класс роутинга
 * Инициализирует ядро, определяет какой экшен запустить согласно URL'у и запускает его
 *
 * @package engine
 * @since 2.0
 */
class PluginManager extends Singleton
{
    /**
     * Файл описания плагина
     *
     * @var string
     */
    const PLUGIN_XML_FILE = 'plugin.xml';

    /**
     * Список скинов плагинов
     *
     * @var array
     */
    static protected $aSkins = [];

    /**
     * Путь к шаблонам с учетом наличия соответствующего skin`a
     *
     * @var array
     */
    static protected $aTemplateDir = [];

    /**
     * Web-адреса шаблонов с учетом наличия соответствующего skin`a
     *
     * @var array
     */
    static protected $aTemplateUrl = [];

    /**
     * Returns normalized name of plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    protected static function _pluginName($xPlugin)
    {
        if (is_object($xPlugin)) {
            $sPlugin = get_class($xPlugin);
        } else {
            $sPlugin = (string)$xPlugin;
        }
        if (0 === strpos($sPlugin, 'Plugin')) {
            if ($nUnderPos = strpos($sPlugin, '_')) {
                $sPluginName = substr($sPlugin, 6, $nUnderPos - 6);
            } else {
                $sPluginName = substr($sPlugin, 6);
            }
        } else {
            $sPluginName = $sPlugin;
        }

        return $sPluginName;
    }

    /**
     * Returns normalized dirname of plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    protected static function _pluginDirName($xPlugin)
    {
        $sPluginName = self::_pluginName($xPlugin);
        if (strpbrk($sPluginName, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')) {
            return \F::strUnderscore($sPluginName);
        }
        return $sPluginName;
    }

    /**
     * Returns normalized name of plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    public static function getPluginName($xPlugin)
    {
        return self::_pluginName($xPlugin);
    }

    /**
     * Make standard class name from plugin id
     *
     * @param string $sPluginId
     *
     * @return string
     */
    public static function getPluginClass($sPluginId)
    {
        if ($sPluginId) {
            return 'Plugin' . \F::strCamelize($sPluginId);
        }
        return null;
    }

    /**
     * Returns decamelized name of plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    public static function getPluginDirName($xPlugin)
    {
        return self::_pluginDirName($xPlugin);
    }


    /**
     * Returns full dir path of plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    public static function getDir($xPlugin)
    {
        $aSeekDirs = (array)\C::get('path.root.seek');

        $sPluginDirName = self::_pluginDirName($xPlugin);
        $aPluginList = \F::getPluginsList(true, false);
        $sManifestFile = null;
        if (isset($aPluginList[$sPluginDirName]['dirname'])) {
            $sManifestFile = \F::File_Exists('plugins/' . $aPluginList[$sPluginDirName]['dirname'] . '/plugin.xml', $aSeekDirs);
        }
        if (!$sManifestFile) {
            $sManifestFile = \F::File_Exists('plugins/' . $sPluginDirName . '/plugin.xml', $aSeekDirs);
        }

        if ($sManifestFile) {
            return dirname($sManifestFile) . '/';
        }

        return null;
    }

    /**
     * Returns array of dirs with languages files of the plugin
     *
     * @param object|string $xPlugin
     *
     * @return array
     */
    public static function getDirLang($xPlugin)
    {
        $aResult = [];

        $aSeekDirs = (array)\C::get('path.root.seek');

        $sPluginDirName = self::_pluginDirName($xPlugin);
        $aPluginList = \F::getPluginsList(true, false);

        if (isset($aPluginList[$sPluginDirName]['dirname'])) {
            $sPluginDirName = $aPluginList[$sPluginDirName]['dirname'];
        }
        foreach($aSeekDirs as $sDir) {
            $sPluginDir = $sDir . '/plugins/' . $sPluginDirName . '/frontend/languages/';
            if (is_dir($sPluginDir)) {
                $aResult[] = \F::File_NormPath($sPluginDir);
            }
        }
        return $aResult;
    }

    /**
     * Returns full URL path to plugin
     *
     * @param object|string $xPlugin
     *
     * @return string
     */
    public static function getUrl($xPlugin)
    {
        $sPluginName = self::_pluginName($xPlugin);

        return \F::File_Dir2Url(self::getDir($sPluginName));
    }

    /**
     * @param object|string $xPlugin
     *
     * @return array
     */
    public static function getSkins($xPlugin)
    {
        $sPluginName = self::_pluginName($xPlugin);
        if (!isset(self::$aSkins[$sPluginName])) {
            $sPluginDir = self::getDir($sPluginName);
            $aPaths = glob($sPluginDir . '/frontend/skin/*', GLOB_ONLYDIR);
            if ($aPaths) {
                $aDirs = array_map('basename', $aPaths);
            } else {
                $aDirs = [];
            }
            self::$aSkins[$sPluginName] = $aDirs;
        }
        return self::$aSkins[$sPluginName];
    }

    /**
     * Returns default skin name
     *
     * @param string $sPluginName
     * @param string $sCompatibility
     *
     * @return string
     */
    public static function getDefaultSkin($sPluginName, $sCompatibility = null)
    {
        $sPluginDirName = self::_pluginDirName($sPluginName);
        if (!$sCompatibility) {
            $sCompatibility = \C::val('view.compatible', 'alto');
        }
        $sResult = \C::get('plugin.' . $sPluginDirName . '.default.skin.' . $sCompatibility);
        if (!$sResult) {
            $sResult = 'default';
        }
        return $sResult;
    }

    /**
     * Возвращает правильный серверный путь к директории шаблонов с учетом текущего скина
     * Если используется скин, которого нет в плагине, то возвращается путь до скина плагина 'default'
     *
     * @param string $sPluginName    Название плагина или его класс
     * @param string $sCompatibility
     *
     * @return string|null
     */
    public static function getTemplateDir($sPluginName, $sCompatibility = null)
    {
        $sPluginName = self::_pluginName($sPluginName);
        $sViewSkin = \C::get('view.skin');
        if (!isset(self::$aTemplateDir[$sViewSkin][$sPluginName][''])) {
            $aSkins = (array)self::getSkins($sPluginName);
            if ($aSkins && in_array(\C::get('view.skin'), $aSkins, true)) {
                $sSkinName = \C::get('view.skin');
            } else {
                $sSkinName = self::getDefaultSkin($sPluginName, $sCompatibility);
            }

            $sDir = self::getDir($sPluginName) . '/frontend/skin/' . $sSkinName . '/';
            self::$aTemplateDir[$sViewSkin][$sPluginName][''] = is_dir($sDir) ? \F::File_NormPath($sDir) : null;
        }
        return self::$aTemplateDir[$sViewSkin][$sPluginName][''];
    }

    /**
     * Seek template for current or default skin
     *
     * @param string $sPluginName
     * @param string $sTemplateName
     *
     * @return string
     */
    public static function getTemplateFile($sPluginName, $sTemplateName)
    {
        $sPluginName = self::_pluginName($sPluginName);
        $sViewSkin = \C::get('view.skin');
        if (!isset(self::$aTemplateDir[$sViewSkin][$sPluginName][$sTemplateName])) {
            $sPluginDir = self::getDir($sPluginName);
            $aDirs = array(
                self::getTemplateDir($sPluginName),
                $sPluginDir . '/frontend/skin/' . \C::get('view.skin'),
                $sPluginDir . '/frontend/skin/' . self::getDefaultSkin($sPluginName),
            );
            if (substr($sTemplateName, -4) === '.tpl') {
                $aSeekDirs = [];
                foreach ($aDirs as $sDir) {
                    $aSeekDirs[] = $sDir . '/tpls/';
                }
                $aSeekDirs = array_merge($aSeekDirs, $aDirs);
            } else {
                $aSeekDirs = $aDirs;
            }
            $sFile = \F::File_Exists($sTemplateName, $aSeekDirs);
            if ($sFile) {
                self::$aTemplateDir[$sViewSkin][$sPluginName][$sTemplateName] = $sFile;
            } else {
                self::$aTemplateDir[$sViewSkin][$sPluginName][$sTemplateName] = $sPluginDir . '/frontend/skin/' . self::getDefaultSkin($sPluginName) . '/' . $sTemplateName;
            }
        }
        return self::$aTemplateDir[$sViewSkin][$sPluginName][$sTemplateName];
    }

    /**
     * Возвращает правильный web-адрес директории шаблонов
     * Если пользователь использует шаблон которого нет в плагине, то возвращает путь до шабона плагина 'default'
     *
     * @param string $sPluginName Название плагина или его класс
     * @param string $sCompatibility
     *
     * @return string
     */
    public static function getTemplateUrl($sPluginName, $sCompatibility = null)
    {
        $sPluginName = self::_pluginName($sPluginName);
        if (!isset(self::$aTemplateUrl[$sPluginName])) {
            if ($sTemplateDir = self::getTemplateDir($sPluginName, $sCompatibility)) {
                self::$aTemplateUrl[$sPluginName] = \F::File_Dir2Url($sTemplateDir);
            } else {
                self::$aTemplateUrl[$sPluginName] = null;
            }
        }
        return self::$aTemplateUrl[$sPluginName];
    }

    /**
     * Устанавливает значение серверного пути до шаблонов плагина
     *
     * @param  string $sPluginName  Имя плагина
     * @param  string $sTemplateDir Серверный путь до шаблона
     *
     * @return bool
     */
    public static function setTemplateDir($sPluginName, $sTemplateDir)
    {
        if (!is_dir($sTemplateDir)) {
            return false;
        }
        $sViewSkin = \C::get('view.skin');
        self::$aTemplateDir[$sViewSkin][$sPluginName][''] = $sTemplateDir;

        return true;
    }

    /**
     * Устанавливает значение web-пути до шаблонов плагина
     *
     * @param  string $sPluginName  Имя плагина
     * @param  string $sTemplateUrl Серверный путь до шаблона
     */
    public static function setTemplateUrl($sPluginName, $sTemplateUrl)
    {
        self::$aTemplateUrl[$sPluginName] = $sTemplateUrl;
    }

    /**
     * Распаковывает архив с плагином и перемещает его в нужную папку
     *
     * @param $sPackFile
     *
     * @return  bool
     */
    public function UnpackPlugin($sPackFile)
    {
        if (!class_exists('ZipArchive')) {
            // TODO: Exception
        }
        $zip = new \ZipArchive;
        if ($zip->open($sPackFile) === true) {
            $sUnpackDir = \F::File_NormPath(dirname($sPackFile) . '/_unpack/');
            if (!$zip->extractTo($sUnpackDir)) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.err_extract_zip_file'), \E::Module('Lang')->get('error'));
                return false;
            } else {
                // Ищем в папках XML-манифест
                $aDirs = glob($sUnpackDir . '*', GLOB_ONLYDIR);
                $sXmlFile = '';
                if ($aDirs) {
                    foreach ($aDirs as $sDir) {
                        if ($sXmlFile = $this->_seekManifest($sDir . '/')) {
                            break;
                        }
                    }
                }
                if (!$sXmlFile) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('action.admin.file_not_found', ['file' => self::PLUGIN_XML_FILE]),
                        \E::Module('Lang')->get('error')
                    );
                    return false;
                }
                $sPluginSrc = dirname($sXmlFile);

                // try to define plugin's dirname
                $oXml = @simplexml_load_file($sXmlFile);
                if (!$oXml) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('action.admin.err_read_xml', ['file' => $sXmlFile]),
                        \E::Module('Lang')->get('error')
                    );
                    return false;
                }
                $sPluginDir = (string)$oXml->dirname;
                if (!$sPluginDir) {
                    $sPluginDir = (string)$oXml->id;
                }
                if (!$sPluginDir) {
                    $sPluginDir = basename($sPluginSrc);
                }
                // Old style compatible
                if ($sPluginDir && preg_match('/^alto-plugin-([a-z]+)-[\d\.]+$/', $sPluginDir, $aM)) {
                    $sPluginDir = $aM[1];
                }

                $sPluginPath = $this->GetPluginsDir() . '/' . $sPluginDir . '/';
                if (\F::File_CopyDir($sPluginSrc, $sPluginPath)) {
                    \E::Module('Message')->addNotice(\E::Module('Lang')->get('action.admin.plugin_added_ok'));
                } else {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.plugin_added_err'), \E::Module('Lang')->get('error'));
                }
            }
            $zip->close();
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('action.admin.err_open_zip_file'), \E::Module('Lang')->get('error'));
        }
        return true;
    }

    /**
     * Путь к директории с плагинами
     *
     * @var string
     */
    protected $sPluginsCommonDir;

    /** @var  \ModulePlugin_EntityPlugin[] List of plugins' enities */
    protected $aPluginsList;

    /** @var  array List of active plugins from PLUGINS.DAT */
    protected $aActivePlugins;

    /**
     * Список engine-rewrite`ов (модули, экшены, сущности, шаблоны)
     * Определяет типы объектов, которые может переопределить/унаследовать плагин
     *
     * @var array
     */
    protected $aDelegates
        = [
            'module'   => [],
            'mapper'   => [],
            'action'   => [],
            'entity'   => [],
            'template' => [],
        ];

    /**
     * Стек наследований
     *
     * @var array
     */
    protected $aInherits = [];

    protected $aReverseMap = [];

    /**
     * Инициализация модуля
     */
    public function init()
    {
        $this->sPluginsCommonDir = \F::getPluginsDir();
    }

    /**
     * Возвращает путь к папке с плагинами
     *
     * @return string
     */
    public function getPluginsDir() {

        return $this->sPluginsCommonDir;
    }

    /**
     * Возвращает XML-манифест плагина
     *
     * @param string $sPluginId
     *
     * @return string|bool
     */
    public function getPluginManifest($sPluginId)
    {
        $aPlugins = \F::getPluginsList(true, false);
        if (!empty($aPlugins[$sPluginId]['manifest'])) {
            $sXmlFile = $aPlugins[$sPluginId]['manifest'];
        } else {
            if (!empty($aPlugins[$sPluginId]['dirname'])) {
                $sPluginDir = $aPlugins[$sPluginId]['dirname'];
            } else {
                $sPluginDir = $sPluginId;
            }
            $sXmlFile = $this->sPluginsCommonDir . $sPluginDir . '/' . PluginManager::PLUGIN_XML_FILE;
        }
        return $this->getPluginManifestFrom($sXmlFile);
    }

    /**
     * @param string $sPluginId
     *
     * @return string
     */
    public function getPluginManifestFile($sPluginId)
    {
        $aPlugins = \F::getPluginsList(true, false);
        if (!empty($aPlugins[$sPluginId]['manifest'])) {
            $sXmlFile = $aPlugins[$sPluginId]['manifest'];
        } else {
            if (!empty($aPlugins[$sPluginId]['dirname'])) {
                $sPluginDir = $aPlugins[$sPluginId]['dirname'];
            } else {
                $sPluginDir = $sPluginId;
            }
            $sXmlFile = $this->sPluginsCommonDir . $sPluginDir . '/' . PluginManager::PLUGIN_XML_FILE;
        }
        return $sXmlFile;
    }

    /**
     * @param string $sPluginXmlFile
     *
     * @return string|bool
     */
    public function getPluginManifestFrom($sPluginXmlFile)
    {
        if ($sPluginXmlFile && ($sXml = \F::File_GetContents($sPluginXmlFile))) {
            return $sXml;
        }
        return false;
    }

    /**
     * Получает список информации обо всех плагинах, загруженных в plugin-директорию
     *
     * @param   array   $aFilter
     * @param   bool    $bAsArray
     *
     * @return  array
     */
    public function getList($aFilter = [], $bAsArray = true)
    {
        if (null === $this->aPluginsList) {
            // Если списка плагинов нет, то создаем его
            $aAllPlugins = \F::getPluginsList(true, false);
            $aActivePlugins = $this->getActivePlugins();
            if ($aAllPlugins) {
                $iCnt = 0;
                foreach ($aAllPlugins as $sPluginId => $aPluginInfo) {
                    if ($bActive = isset($aActivePlugins[$sPluginId])) {
                        $nNum = ++$iCnt;
                    } else {
                        $nNum = -1;
                    }

                    // Создаем сущность плагина по его манифесту
                    /** @var \ModulePlugin_EntityPlugin $oPluginEntity */
                    $oPluginEntity = \E::getEntity('Plugin', $aPluginInfo);
                    if ($oPluginEntity->getId()) {
                        // Если сущность плагина создана, то...
                        $oPluginEntity->setNum($nNum);
                        $oPluginEntity->setIsActive($bActive);
                        $this->aPluginsList[$sPluginId] = $oPluginEntity;
                    }
                }
            } else {
                $this->aPluginsList = [];
            }
        }

        // Формируем список на выдачу
        $aPlugins = [];
        if ($bAsArray || isset($aFilter['active'])) {
            foreach ($this->aPluginsList as $sPluginId => $oPluginEntity) {
                if (!isset($aFilter['active'])
                    || ($aFilter['active'] && $oPluginEntity->getIsActive())
                    || (!$aFilter['active'] && !$oPluginEntity->getIsActive())
                ) {

                    if ($bAsArray) {
                        $aPlugins[$sPluginId] = $oPluginEntity->getAllProps();
                    } else {
                        $aPlugins[$sPluginId] = $oPluginEntity;
                    }
                }
            }
        } else {
            $aPlugins = $this->aPluginsList;
        }
        // Если нужно, то сортируем плагины
        if ($aPlugins && isset($aFilter['order'])) {
            if ($aFilter['order'] === 'name') {
                uasort($aPlugins, [$this, '_PluginCompareByName']);
            } elseif ($aFilter['order'] === 'priority') {
                uasort($aPlugins, [$this, '_PluginCompareByPriority']);
            }
        }
        return $aPlugins;
    }

    /**
     * Возвращает список плагинов
     *
     * @param   bool|null   - $bActive
     *
     * @return  \ModulePlugin_EntityPlugin[]
     */
    public function getPluginsList($bActive = null)
    {
        $aFilter = ['order' => 'priority'];
        if (null !== $bActive) {
            $aFilter['active'] = (bool)$bActive;
        }
        $aPlugins = $this->getList($aFilter, false);
        return $aPlugins;
    }

    /**
     * @param $aPlugin1
     * @param $aPlugin2
     *
     * @return int
     */
    public function _PluginCompareByName($aPlugin1, $aPlugin2)
    {
        if ((string)$aPlugin1['property']->name->data === (string)$aPlugin2['property']->name->data) {
            return 0;
        }
        return ((string)$aPlugin1['property']->name->data < (string)$aPlugin2['property']->name->data) ? -1 : 1;
    }

    /**
     * @param \ModulePlugin_EntityPlugin|array $aPlugin1
     * @param \ModulePlugin_EntityPlugin|array $aPlugin2
     *
     * @return int
     */
    public function _pluginCompareByPriority($aPlugin1, $aPlugin2)
    {
        if (is_object($aPlugin1)) {
            $aPlugin1 = $aPlugin1->getAllProps();
        }
        if (is_object($aPlugin2)) {
            $aPlugin2 = $aPlugin2->getAllProps();
        }
        $aPlugin1['is_active'] = (isset($aPlugin1['is_active']) ? $aPlugin1['is_active'] : false);
        $aPlugin2['is_active'] = (isset($aPlugin2['is_active']) ? $aPlugin2['is_active'] : false);

        if ($aPlugin1['priority'] === $aPlugin2['priority']) {
            if (!$aPlugin1['is_active'] && !$aPlugin2['is_active']) {
                // оба плагина не активированы - сортировка по имени
                if ($aPlugin1['id'] === $aPlugin2['id']) {
                    return 0;
                } else {
                    return ($aPlugin1['id'] < $aPlugin2['id']) ? -1 : 1;
                }
            } elseif (!$aPlugin1['is_active'] || !$aPlugin2['is_active']) {
                // неактивированные плагины идут ниже
                if (!$aPlugin1['is_active'] == -1) {
                    return 1;
                } elseif (!$aPlugin2['is_active'] == -1) {
                    return -1;
                }
                return ($aPlugin1['num'] < $aPlugin2['num']) ? -1 : 1;
            }
        }
        if (strtolower($aPlugin1['priority']) === 'top') {
            return -1;
        } elseif (strtolower($aPlugin2['priority']) === 'top') {
            return 1;
        }
        return (($aPlugin1['priority'] > $aPlugin2['priority']) ? -1 : 1);
    }

    /**
     * @param string $sPluginId
     * @param bool   $bActive
     *
     * @return \ModulePlugin_EntityPlugin|null
     */
    protected function _getPluginEntityById($sPluginId, $bActive)
    {
        $aPlugins = $this->getPluginsList($bActive);
        if (!isset($aPlugins[$sPluginId])) {
            return null;
        }
        return $aPlugins[$sPluginId];
    }

    /**
     * @param string $sPluginId
     * @param bool   $bActive
     *
     * @return \Plugin|null
     */
    protected function _getPluginById($sPluginId, $bActive)
    {
        $oPlugin = null;
        $oPluginEntity = $this->_getPluginEntityById($sPluginId, $bActive);

        if ($oPluginEntity) {
            $sClassName = $oPluginEntity->getPluginClass();
            $sPluginClassFile = $oPluginEntity->getPluginClassFile();
            if ($sClassName && $sPluginClassFile) {
                \F::includeFile($sPluginClassFile);
                if (class_exists($sClassName, false)) {
                    /** @var \Plugin $oPlugin */
                    $oPlugin = new $sClassName($oPluginEntity);
                }
            }
        }

        return $oPlugin;
    }

    /**
     * Активация плагина
     *
     * @param   string  $sPluginId  - код плагина
     *
     * @return  bool
     */
    public function Activate($sPluginId)
    {
        $aConditions = [
            '<'  => 'lt', 'lt' => 'lt',
            '<=' => 'le', 'le' => 'le',
            '>'  => 'gt', 'gt' => 'gt',
            '>=' => 'ge', 'ge' => 'ge',
            '==' => 'eq', '=' => 'eq', 'eq' => 'eq',
            '!=' => 'ne', '<>' => 'ne', 'ne' => 'ne'
        ];

        /** @var \Plugin $oPlugin */
        $oPlugin = $this->_getPluginById($sPluginId, false);
        if ($oPlugin) {
            /** @var \ModulePlugin_EntityPlugin $oPluginEntity */
            $oPluginEntity = $oPlugin->getPluginEntity();

            // Проверяем совместимость с версией Alto
            if (!$oPluginEntity->engineCompatible()) {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get(
                        'action.admin.plugin_activation_version_error',
                        array(
                            'version' => $oPluginEntity->requiredAltoVersion(),
                        )
                    ),
                    \E::Module('Lang')->get('error'),
                    true
                );
                return false;
            }

            // * Проверяем системные требования
            if ($oPluginEntity->requiredPhpVersion()) {
                // Версия PHP
                if (!version_compare(PHP_VERSION, $oPluginEntity->requiredPhpVersion(), '>=')) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get(
                            'action.admin.plugin_activation_error_php',
                            [
                                'version' => $oPluginEntity->requiredPhpVersion(),
                            ]
                        ),
                        \E::Module('Lang')->get('error'),
                        true
                    );
                    return false;
                }
            }

            // * Проверяем наличие require-плагинов
            if ($aRequiredPlugins = $oPluginEntity->requiredPlugins()) {
                $aActivePlugins = array_keys($this->getActivePlugins());
                $iError = 0;
                foreach ($aRequiredPlugins as $oReqPlugin) {

                    // * Есть ли требуемый активный плагин
                    if (!in_array((string)$oReqPlugin, $aActivePlugins, true)) {
                        $iError++;
                        \E::Module('Message')->addError(
                            \E::Module('Lang')->get(
                                'action.admin.plugin_activation_requires_error',
                                [
                                    'plugin' => ucfirst($oReqPlugin),
                                ]
                            ),
                            \E::Module('Lang')->get('error'),
                            true
                        );
                    } // * Проверка требуемой версии, если нужно
                    else {
                        if (isset($oReqPlugin['name'])) {
                            $sReqPluginName = (string)$oReqPlugin['name'];
                        }
                        else {
                            $sReqPluginName = ucfirst($oReqPlugin);
                        }

                        if (isset($oReqPlugin['version'])) {
                            $sReqVersion = $oReqPlugin['version'];
                            if (isset($oReqPlugin['condition']) && array_key_exists((string)$oReqPlugin['condition'], $aConditions)) {
                                $sReqCondition = $aConditions[(string)$oReqPlugin['condition']];
                            } else {
                                $sReqCondition = 'eq';
                            }
                            $sClassName = "Plugin{$oReqPlugin}";
                            /** @var \ModulePlugin_EntityPlugin $oReqPluginInstance */
                            $oReqPluginInstance = new $sClassName;

                            // Получаем версию требуемого плагина
                            $sReqPluginVersion = $oReqPluginInstance->getVersion();

                            if (!$sReqPluginVersion) {
                                $iError++;
                                \E::Module('Message')->addError(
                                    \E::Module('Lang')->get(
                                        'action.admin.plugin_havenot_getversion_method',
                                        ['plugin' => $sReqPluginName]
                                    ),
                                    \E::Module('Lang')->get('error'),
                                    true
                                );
                            } else {
                                // * Если требуемый плагин возвращает версию, то проверяем ее
                                if (!version_compare($sReqPluginVersion, $sReqVersion, $sReqCondition)) {
                                    $sTextKey = 'action.admin.plugin_activation_reqversion_error_' . $sReqCondition;
                                    $iError++;
                                    \E::Module('Message')->addError(
                                        \E::Module('Lang')->get(
                                            $sTextKey,
                                            [
                                                'plugin'  => $sReqPluginName,
                                                'version' => $sReqVersion
                                            ]
                                        ),
                                        \E::Module('Lang')->get('error'),
                                        true
                                    );
                                }
                            }
                        }
                    }
                }
                if ($iError) {
                    return false;
                }
            }

            // * Проверяем, не вступает ли данный плагин в конфликт с уже активированными
            // * (по поводу объявленных делегатов)
            $aPluginDelegates = $oPlugin->getDelegates();
            $iError = 0;
            foreach ($this->aDelegates as $sGroup => $aReplaceList) {
                $iCount = 0;
                if (isset($aPluginDelegates[$sGroup])
                    && is_array($aPluginDelegates[$sGroup])
                    && $iCount = count($aOverlap = array_intersect_key($aReplaceList, $aPluginDelegates[$sGroup]))
                ) {
                    $iError += $iCount;
                    foreach ($aOverlap as $sResource => $aConflict) {
                        \E::Module('Message')->addError(
                            \E::Module('Lang')->get(
                                'action.admin.plugin_activation_overlap',
                                array(
                                    'resource' => $sResource,
                                    'delegate' => $aConflict['delegate'],
                                    'plugin'   => $aConflict['sign']
                                )
                            ),
                            \E::Module('Lang')->get('error'), true
                        );
                    }
                }
                if ($iCount) {
                    return false;
                }
            }
            $bResult = $oPlugin->activate();
            if ($bResult && ($sVersion = $oPlugin->getVersion())) {
                $oPlugin->WriteStorageVersion($sVersion);
                $oPlugin->WriteStorageDate();
            }
        } else {
            // * Исполняемый файл плагина не найден
            $sPluginClassFile = PluginManager::getPluginClass($sPluginId) . '.php';
            \E::Module('Message')->addError(
                \E::Module('Lang')->get('action.admin.plugin_file_not_found', ['file' => $sPluginClassFile]),
                \E::Module('Lang')->get('error'),
                true
            );
            return false;
        }

        if ($bResult) {
            // Запрещаем кеширование
            \E::Module('Cache')->setDesabled(true);
            // Надо обязательно очистить кеш здесь
            \E::Module('Cache')->clean();
            \E::Module('Viewer')->clearAll();

            // Переопределяем список активированных пользователем плагинов
            if (!$this->_addActivePlugins($oPluginEntity)) {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get('action.admin.plugin_write_error', ['file' => F::getPluginsDatFile()]),
                    \E::Module('Lang')->get('error'), true
                );
                $bResult = false;
            }
        }
        return $bResult;

    } // function Activate(...)

    /**
     * @param \ModulePlugin_EntityPlugin $oActivePluginEntity
     *
     * @return \ModulePlugin_EntityPlugin[]
     */
    protected function _addActivePlugins($oActivePluginEntity)
    {
        $aPluginsList = $this->getPluginsList(true);
        $oActivePluginEntity->setIsActive(true);
        $aPluginsList[$oActivePluginEntity->getId()] = $oActivePluginEntity;
        if (count($aPluginsList)) {
            uasort($aPluginsList, [$this, '_PluginCompareByPriority']);
        }
        $aActivePlugins = [];
        /** @var \ModulePlugin_EntityPlugin $oPluginEntity */
        foreach($aPluginsList as $sPlugin => $oPluginEntity) {
            $aActivePlugins[$sPlugin] = [
                'id'        => $oPluginEntity->getId(),
                'dirname'   => $oPluginEntity->getDirname(),
                'name'      => $oPluginEntity->getName(),
            ];
        }
        $this->setActivePlugins($aActivePlugins);

        return $aPluginsList;
    }

    /**
     * Деактивация
     *
     * @param   string  $sPluginId
     * @param   bool    $bRemove
     *
     * @return  null|bool
     */
    public function Deactivate($sPluginId, $bRemove = false)
    {
        // get activated plugin by ID
        $oPlugin = $this->_getPluginById($sPluginId, true);

        if ($oPlugin) {
            /**
             * TODO: Проверять зависимые плагины перед деактивацией
             */
            $bResult = $oPlugin->deactivate();
            if ($bRemove) {
                $oPlugin->remove();
            }
        } else {
            // Исполняемый файл плагина не найден
            $sPluginClassFile = PluginManager::getPluginClass($sPluginId) . '.php';
            \E::Module('Message')->addError(
                \E::Module('Lang')->get('action.admin.plugin_file_not_found', ['file' => $sPluginClassFile]),
                \E::Module('Lang')->get('error'),
                true
            );
            return false;
        }

        if ($bResult) {
            // * Переопределяем список активированных пользователем плагинов
            $aActivePlugins = $this->getActivePlugins();

            // * Вносим данные в файл о деактивации плагина
            unset($aActivePlugins[$sPluginId]);

            // * Сбрасываем весь кеш, т.к. могут быть закешированы унаследованые плагинами сущности
            \E::Module('Cache')->SetDesabled(true);
            \E::Module('Cache')->clean();
            if (!$this->setActivePlugins($aActivePlugins)) {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get('action.admin.plugin_activation_file_write_error'),
                    \E::Module('Lang')->get('error'),
                    true
                );
                return false;
            }

            // * Очищаем компилированные шаблоны Smarty
            \E::Module('Viewer')->clearSmartyFiles();
        }
        return $bResult;
    }

    /**
     * Возвращает список активированных плагинов в системе
     *
     * @param bool $bIdOnly
     *
     * @return array
     */
    public function getActivePlugins($bIdOnly = false)
    {
        if (null === $this->aActivePlugins) {
            $this->aActivePlugins = F::getPluginsList(false, $bIdOnly);
        }
        return $this->aActivePlugins;
    }

    /**
     * Активирован ли указанный плагин
     *
     * @param $sPlugin
     *
     * @return bool
     */
    public function isActivePlugin($sPlugin)
    {
        $aPlugins = $this->getActivePlugins();

        return isset($aPlugins[$sPlugin]);
    }

    /**
     * Записывает список активных плагинов в файл PLUGINS.DAT
     *
     * @param array $aPlugins    Список плагинов
     *
     * @return bool
     */
    public function setActivePlugins($aPlugins)
    {
        if (!is_array($aPlugins)) {
            $sPlugin = (string)$aPlugins;
            $aPlugins = [
                $sPlugin => [
                    'id' => $sPlugin,
                    'dirname' => $sPlugin,
                ],
            ];
        }
        //$aPlugins = array_unique(array_map('trim', $aPlugins));

        $aSaveData = array(
            date(';Y-m-d H:i:s'),
        );
        foreach($aPlugins as $sPlugin => $aPluginInfo) {
            $aSaveData[] = $sPlugin . ' '
                . (!empty($aPluginInfo['dirname']) ? $aPluginInfo['dirname'] : $sPlugin)
                . (!empty($aPluginInfo['name']) ? ' ;' . $aPluginInfo['name'] : '');
        }
        // * Записываем данные в файл PLUGINS.DAT
        $sFile = \F::GetPluginsDatFile();
        if (\F::File_PutContents($sFile, implode(PHP_EOL, $aSaveData)) !== false) {
            return true;
        }
        return false;
    }

    /**
     * Удаляет плагины с сервера
     *
     * @param array $aPlugins    Список плагинов для удаления
     */
    public function delete($aPlugins)
    {
        if (!is_array($aPlugins)) {
            $aPlugins = [$aPlugins];
        }

        $aActivePlugins = $this->getActivePlugins();
        foreach ($aPlugins as $sPluginId) {
            if (!is_string($sPluginId)) {
                continue;
            }

            // * Если плагин активен, деактивируем его
            if (in_array($sPluginId, $aActivePlugins, true)) {
                $this->Deactivate($sPluginId);
            }
            $oPlugin = $this->_getPluginById($sPluginId, false);
            if ($oPlugin) {
                $oPlugin->Remove();
            }

            // * Удаляем директорию с плагином
            F::File_RemoveDir($this->sPluginsCommonDir . $sPluginId);
        }
    }

    /**
     * Перенаправление вызовов на модули, экшены, сущности
     *
     * @param  string $sType
     * @param  string $sFrom
     * @param  string $sTo
     * @param  string $sSign
     */
    public function delegate($sType, $sFrom, $sTo, $sSign = __CLASS__)
    {
        // * Запрещаем неподписанные делегаты
        if (!$sSign || !is_string($sSign)) {
            return;
        }
        $sFrom = trim($sFrom);
        $sTo = trim($sTo);
        if (!array_key_exists($sType, $this->aDelegates) || !$sFrom || !$sTo) {
            return;
        }

        $this->aDelegates[$sType][$sFrom] = array(
            'delegate' => $sTo,
            'sign'     => $sSign
        );
        $this->aReverseMap['delegates'][$sTo] = $sFrom;
    }

    /**
     * Добавляет в стек наследника класса
     *
     * @param string $sFrom
     * @param string $sTo
     * @param string $sSign
     */
    public function inherit($sFrom, $sTo, $sSign = __CLASS__)
    {
        if (!is_string($sSign) || !$sSign) {
            return;
        }
        $sFrom = trim($sFrom);
        $sTo = trim($sTo);
        if (!$sFrom || !$sTo) {
            return;
        }

        $this->aInherits[$sFrom]['items'][] = [
            'inherit' => $sTo,
            'sign'    => $sSign
        ];
        $this->aInherits[trim($sFrom)]['position'] = count($this->aInherits[trim($sFrom)]['items']) - 1;
        $this->aReverseMap['inherits'][$sTo][] = $sFrom;
    }

    /**
     * Return all inheritance rules
     *
     * @return array
     */
    public function getInheritances()
    {
        return $this->aInherits;
    }

    /**
     * Return all delegation rules
     *
     * @return array
     */
    public function getDelegations()
    {
        return $this->aDelegates;
    }

    /**
     * Получает следующего родителя у наследника.
     * ВНИМАНИЕ! Данный метод нужно вызвать только из __autoload()
     *
     * @param string $sFrom
     *
     * @return string
     */
    public function getParentInherit($sFrom)
    {
        if (!isset($this->aInherits[$sFrom]['items']) || count($this->aInherits[$sFrom]['items']) <= 1
            || $this->aInherits[$sFrom]['position'] < 1
        ) {
            return $sFrom;
        }
        $this->aInherits[$sFrom]['position']--;
        return $this->aInherits[$sFrom]['items'][$this->aInherits[$sFrom]['position']]['inherit'];
    }

    /**
     * Возвращает список наследуемых классов
     *
     * @param string $sFrom
     *
     * @return null|array
     */
    public function getInherits($sFrom)
    {
        if (isset($this->aInherits[trim($sFrom)])) {
            return $this->aInherits[trim($sFrom)]['items'];
        }
        return null;
    }

    /**
     * Возвращает последнего наследника в цепочке
     *
     * @param $sFrom
     *
     * @return null|string
     */
    public function getLastInherit($sFrom)
    {
        if (isset($this->aInherits[trim($sFrom)])) {
            return $this->aInherits[trim($sFrom)]['items'][count($this->aInherits[trim($sFrom)]['items']) - 1];
        }
        return null;
    }

    /**
     * Возвращает делегат модуля, экшена, сущности.
     * Если делегат не определен, пытается найти наследника, иначе отдает переданный в качестве sender`a параметр
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return string
     */
    public function getDelegate($sType, $sFrom)
    {
        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return $this->aDelegates[$sType][$sFrom]['delegate'];
        }
        if ($aInherit = $this->getLastInherit($sFrom)) {
            return $aInherit['inherit'];
        }
        return $sFrom;
    }

    /**
     * @param string $sType
     * @param string $sFrom
     *
     * @return array|null
     */
    public function getDelegates($sType, $sFrom)
    {
        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return array($this->aDelegates[$sType][$sFrom]['delegate']);
        }
        if ($aInherits = $this->getInherits($sFrom)) {
            $aReturn = [];
            foreach (array_reverse($aInherits) as $v) {
                $aReturn[] = $v['inherit'];
            }
            return $aReturn;
        }
        return null;
    }

    /**
     * Возвращает цепочку делегатов
     *
     * @param string $sType
     * @param string $sTo
     *
     * @return array
     */
    public function getDelegationChain($sType, $sTo)
    {
        $sRootDelegater = $this->getRootDelegater($sType, $sTo);
        return $this->collectAllDelegatesRecursive($sType, [$sRootDelegater]);
    }

    /**
     * Возвращает делегируемый класс
     *
     * @param string $sType
     * @param string $sTo
     *
     * @return string
     */
    public function getRootDelegater($sType, $sTo)
    {
        if ($sTo) {
            $sItem = $sTo;
            $sItemDelegater = $this->getDelegater($sType, $sTo);
            $sRootDelegater = null;
            while (empty($sRootDelegater)) {
                if ($sItem === $sItemDelegater) {
                    $sRootDelegater = $sItem;
                }
                $sItem = $sItemDelegater;
                $sItemDelegater = $this->getDelegater($sType, $sItemDelegater);
            }
            return $sRootDelegater;
        }
        return $sTo;
    }

    /**
     * Составляет цепочку делегатов
     *
     * @param string $sType
     * @param array  $aDelegates
     *
     * @return array
     */
    public function collectAllDelegatesRecursive($sType, $aDelegates)
    {
        foreach ($aDelegates as $sClass) {
            if ($aNewDelegates = $this->getDelegates($sType, $sClass)) {
                $aDelegates = array_merge($this->collectAllDelegatesRecursive($sType, $aNewDelegates), $aDelegates);
            }
        }
        return $aDelegates;
    }

    /**
     * Возвращает делегирующий объект по имени делегата
     *
     * @param  string $sType Объект
     * @param  string $sTo   Делегат
     *
     * @return string
     */
    public function getDelegater($sType, $sTo)
    {
        $aDelegateMapper = [];
        foreach ($this->aDelegates[$sType] as $sFrom => $aInfo) {
            if ($aInfo['delegate'] === $sTo) {
                $aDelegateMapper[$sFrom] = $aInfo;
            }
        }
        if ($aDelegateMapper) {
            $aKeys = array_keys($aDelegateMapper);
            return reset($aKeys);
        }
        foreach ($this->aInherits as $sFrom => $aInfo) {
            $aInheritMapper = [];
            foreach ($aInfo['items'] as $iOrder => $aData) {
                if ($aData['inherit'] === $sTo) {
                    $aInheritMapper[$iOrder] = $aData;
                }
            }
            if ($aInheritMapper) {
                return $sFrom;
            }
        }
        return $sTo;
    }

    /**
     * Возвращает подпись делегата модуля, экшена, сущности.
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return string|null
     */
    public function getDelegateSign($sType, $sFrom)
    {
        if (isset($this->aDelegates[$sType][$sFrom]['sign'])) {
            return $this->aDelegates[$sType][$sFrom]['sign'];
        }
        if ($aInherit = $this->getLastInherit($sFrom)) {
            return $aInherit['sign'];
        }
        return null;
    }

    /**
     * Возвращает true, если установлено правило делегирования
     * и класс является базовым в данном правиле
     *
     * @param  string $sType
     * @param  string $sFrom
     *
     * @return bool
     */
    public function isDelegater($sType, $sFrom)
    {
        if (isset($this->aDelegates[$sType][$sFrom]['delegate'])) {
            return true;
        } elseif ($aInherit = $this->getLastInherit($sFrom)) {
            return true;
        }
        return false;
    }

    /**
     * Возвращает true, если устано
     *
     * @param  string $sType
     * @param  string $sTo
     *
     * @return bool
     */
    public function isDelegated($sType, $sTo)
    {
        // * Фильтруем маппер делегатов/наследников
        $aDelegateMapper = [];
        foreach ($this->aDelegates[$sType] as $sKey => $xVal) {
            if ($xVal['delegate'] === $sTo) {
                $aDelegateMapper[$sKey] = $xVal;
            }
        }
        if (is_array($aDelegateMapper) && count($aDelegateMapper)) {
            return true;
        }
        foreach ($this->aInherits as $k => $v) {
            $aInheritMapper = [];
            foreach ($v['items'] as $sKey => $xVal) {
                if ($xVal['inherit'] === $sTo) {
                    $aInheritMapper[$sKey] = $xVal;
                }
            }
            if (is_array($aInheritMapper) && count($aInheritMapper)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Возвращает список объектов, доступных для делегирования
     *
     * @return string[]
     */
    public function getDelegateObjectList()
    {
        return array_keys($this->aDelegates);
    }

    /**
     * Рекурсивно ищет манифест плагина в подпапках
     *
     * @param   string  $sDir
     *
     * @return  string|bool
     */
    protected function _seekManifest($sDir)
    {
        if ($aFiles = glob($sDir . PluginManager::PLUGIN_XML_FILE)) {
            return array_shift($aFiles);
        } else {
            $aSubDirs = glob($sDir . '*', GLOB_ONLYDIR);
            foreach ($aSubDirs as $sSubDir) {
                if ($sFile = $this->_seekManifest($sSubDir . '/')) {
                    return $sFile;
                }
            }
        }
        return false;
    }

}

// EOF