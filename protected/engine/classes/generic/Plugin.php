<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

namespace alto\engine\generic;

/**
 * Абстракция плагина, от которой наследуются все плагины
 *
 * @package engine
 */
abstract class Plugin extends Component 
{
    /**
     * Массив делегатов плагина
     *
     * @var array
     */
    protected $aDelegates = [];

    /**
     * Массив наследуемых классов плагина
     *
     * @var array
     */
    protected $aInherits = [];

    /**
     * @var ModulePlugin_EntityPlugin
     */
    protected $oPluginEntity;

    /**
     * Constructor
     */
    public function __construct($oPluginEntity = null)
    {
        parent::__construct();
        /*
        if ($oPluginEntity)
            $this->oPluginEntity = $oPluginEntity;
        else
            $this->oPluginEntity = $this->GetPluginEntity();
        */
    }

    /**
     * Метод инициализации плагина
     *
     */
    abstract public function init();

    /**
     * Передает информацию о делегатах в модуль ModulePlugin
     * Вызывается Engine перед инициализацией плагина
     *
     * @see Engine::_loadPlugins
     */
    final public function delegate() {

        $aDelegates = $this->getDelegates();
        foreach ($aDelegates as $sObjectName => $aParams) {
            foreach ($aParams as $sFrom => $sTo) {
                \E::PluginManager()->delegate($sObjectName, $sFrom, $sTo, get_class($this));
            }
        }

        $aInherits = $this->getInherits();
        foreach ($aInherits as $aParams) {
            foreach ($aParams as $sFrom => $sTo) {
                \E::PluginManager()->inherit($sFrom, $sTo, get_class($this));
            }
        }
    }

    /**
     * Возвращает массив наследников
     *
     * @return array
     */
    final public function getInherits() {

        $aReturn = [];
        if (is_array($this->aInherits) && count($this->aInherits)) {
            foreach ($this->aInherits as $sObjectName => $aParams) {
                if (is_array($aParams) && count($aParams)) {
                    foreach ($aParams as $sFrom => $sTo) {
                        if (is_int($sFrom)) {
                            $sFrom = $sTo;
                            $sTo = null;
                        }
                        list($sFrom, $sTo) = $this->MakeDelegateParams($sObjectName, $sFrom, $sTo);
                        $aReturn[$sObjectName][$sFrom] = $sTo;
                    }
                }
            }
        }
        return $aReturn;
    }

    /**
     * Возвращает массив делегатов
     *
     * @return array
     */
    final public function getDelegates() {

        $aReturn = [];
        if (is_array($this->aDelegates) && count($this->aDelegates)) {
            foreach ($this->aDelegates as $sObjectName => $aParams) {
                if (is_array($aParams) && count($aParams)) {
                    foreach ($aParams as $sFrom => $sTo) {
                        if (is_int($sFrom)) {
                            $sFrom = $sTo;
                            $sTo = null;
                        }
                        list($sFrom, $sTo) = $this->MakeDelegateParams($sObjectName, $sFrom, $sTo);
                        $aReturn[$sObjectName][$sFrom] = $sTo;
                    }
                }
            }
        }
        return $aReturn;
    }

    /**
     * Преобразовывает краткую форму имен делегатов в полную
     *
     * @param string $sObjectName Название типа объекта делегата
     *
     * @see ModulePlugin::aDelegates
     *
     * @param string $sFrom       Что делегируется
     * @param string $sTo         Кому делегируется
     *
     * @return array
     */
    public function MakeDelegateParams($sObjectName, $sFrom, $sTo) {
        /**
         * Если не указан делегат то, считаем, что делегатом является
         * одноименный объект текущего плагина
         */
        if ($sObjectName === 'template') {
            if (!$sTo) {
                $sTo = \PluginManager::GetTemplateFile(get_class($this), $sFrom);
            } else {
                if (strpos($sTo, '_') === 0) {
                    $sTo = \PluginManager::GetTemplateFile(get_class($this), substr($sTo, 1));
                }
            }
        } else {
            if (!$sTo) {
                $sTo = get_class($this) . '_' . $sFrom;
            } else {
                if (strpos($sTo, '_') === 0) {
                    $sTo = get_class($this) . $sTo;
                }
            }
        }
        return array($sFrom, $sTo);
    }

    /**
     * Метод активации плагина
     *
     * @return bool
     */
    public function activate() 
    {
        return true;
    }

    /**
     * Метод деактивации плагина
     *
     * @return bool
     */
    public function deactivate() 
    {
        return true;
    }

    /**
     * Метод удаления плагина
     *
     * @return bool
     */
    public function remove() 
    {
        $this->ResetConfig();
        $this->resetStorage();

        return true;
    }

    /**
     * Транслирует на базу данных запросы из указанного файла
     * @see ModuleDatabase::ExportSQL
     *
     * @param  string $sFilePath    Полный путь до файла с SQL
     *
     * @return array
     */
    protected function exportSQL($sFilePath)
    {
        return \E::Module('Database')->ExportSQL($sFilePath);
    }

    /**
     * Выполняет SQL
     *
     * @see ModuleDatabase::ExportSQLQuery
     *
     * @param string $sSql    Строка SQL запроса
     *
     * @return array
     */
    protected function exportSQLQuery($sSql)
    {
        return \E::Module('Database')->ExportSQLQuery($sSql);
    }

    /**
     * Проверяет наличие таблицы в БД
     * @see ModuleDatabase::isTableExists
     *
     * @param string $sTableName    - Название таблицы, необходимо перед именем таблицы добавлять "prefix_",
     *                                это позволит учитывать произвольный префикс таблиц у пользователя
     * <pre>
     *                              prefix_topic
     * </pre>
     *
     * @return bool
     */
    protected function isTableExists($sTableName)
    {
        return \E::Module('Database')->isTableExists($sTableName);
    }

    /**
     * Проверяет наличие поля в таблице
     * @see ModuleDatabase::isFieldExists
     *
     * @param string $sTableName    - Название таблицы, необходимо перед именем таблицы добавлять "prefix_",
     *                                это позволит учитывать произвольный префикс таблиц у пользователя
     * @param string $sFieldName    - Название поля в таблице
     *
     * @return bool
     */
    protected function isFieldExists($sTableName, $sFieldName)
    {
        return \E::Module('Database')->isFieldExists($sTableName, $sFieldName);
    }

    /**
     * Добавляет новый тип в поле enum(перечисление)
     *
     * @see ModuleDatabase::addEnumType
     *
     * @param string $sTableName       - Название таблицы, необходимо перед именем таблицы добавлять "prefix_",
     *                                   это позволит учитывать произвольный префикс таблиц у пользователя
     * @param string $sFieldName       - Название поля в таблице
     * @param string $sType            - Название типа
     */
    protected function addEnumType($sTableName, $sFieldName, $sType)
    {
        \E::Module('Database')->AddEnumType($sTableName, $sFieldName, $sType);
    }

    /**
     * Returns name of plugin
     *
     * @param bool $bSkipPrefix
     *
     * @return string
     */
    public function getName($bSkipPrefix = true)
    {
        $sName = get_class($this);
        return $bSkipPrefix ? substr($sName, 6) : $sName;
    }

    /**
     * @return ModulePlugin_EntityPlugin
     */
    public function getPluginEntity()
    {
        if (!$this->oPluginEntity) {
            $sPluginId = \F::StrUnderscore($this->getName());
            $this->oPluginEntity = \E::getEntity('Plugin', $sPluginId);
        }
        return $this->oPluginEntity;
    }

    /**
     * Возвращает версию плагина
     *
     * @return string|null
     */
    public function getVersion()
    {
        if ($oPluginEntity = $this->GetPluginEntity()) {
            return $oPluginEntity->GetVersion();
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function engineCompatible()
    {
        if ($oPluginEntity = $this->GetPluginEntity()) {
            return $oPluginEntity->EngineCompatible();
        }
        return null;
    }

    /**
     * @param string|array $xConfigKey
     * @param array|null   $xConfigData
     *
     * @return bool
     */
    public function WriteConfig($xConfigKey, $xConfigData = null) {

        $aConfig = [];
        if (func_num_args() == 1) {
            if (is_array($xConfigKey)) {
                $aConfig = $xConfigKey;
            }
        } else {
            $aConfig = array($xConfigKey => $xConfigData);
        }

        return \C::writePluginConfig($this->oPluginEntity->getId(true), $aConfig);
    }

    /**
     * @param string|null $sConfigKey
     *
     * @return array
     */
    public function ReadConfig($sConfigKey = null) {

        return \C::readPluginConfig($this->oPluginEntity->getId(true), $sConfigKey);
    }

    /**
     * @param string|null $sConfigKey
     */
    public function ResetConfig($sConfigKey = null) {

        \C::resetPluginConfig($this->oPluginEntity->getId(true), $sConfigKey);
    }

    /**
     * @param $sVersion
     *
     * @return bool
     */
    public function WriteStorageVersion($sVersion) {

        $aConfig = array(
            'plugin.' . $this->oPluginEntity->getId(true) . '.version' => $sVersion,
        );
        return \C::writeEngineConfig($aConfig);
    }

    /**
     * @return null
     */
    public function ReadStorageVersion() {

        $sKey = 'plugin.' . $this->oPluginEntity->getId(true) . '.version';

        $aData = \C::readEngineConfig($sKey);
        if (isset($aData[$sKey])) {
            return $aData[$sKey];
        }
        return null;
    }

    /**
     * @param null $sDate
     *
     * @return bool
     */
    public function WriteStorageDate($sDate = null) {

        if (!$sDate) {
            $sDate = date('Y-m-d H:i:s');
        }
        $aConfig = [
            'plugin.' . $this->oPluginEntity->getId(true) . '.date' => $sDate,
        ];
        return \C::writeEngineConfig($aConfig);
    }

    /**
     * @return array
     */
    public function ReadStorageDate() {

        $sKey = 'plugin.' . $this->oPluginEntity->getId(true) . '.date';

        return \C::readEngineConfig($sKey);
    }

    /**
     *
     */
    public function resetStorage()
    {
        $sKey = 'plugin.' . $this->oPluginEntity->getId(true);

        \C::resetEngineConfig($sKey);
    }


}

// EOF
