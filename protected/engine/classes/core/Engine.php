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

use alto\engine\generic\Component;
use alto\engine\generic\Controller;
use alto\engine\generic\Singleton;
use alto\engine\generic\Module;
use alto\engine\generic\Mapper;
use alto\engine\generic\Entity;

/**
 * Основной класс движка. Ядро.
 *
 * Производит инициализацию плагинов, модулей, хуков.
 * Через этот класс происходит выполнение методов всех модулей,
 * которые вызываются так:
 * <pre>
 * \E::Module('Name')->Method();
 * </pre>
 * Также отвечает за автозагрузку остальных классов движка.
 *
 * @package engine
 * @since 1.0
 */
class Engine extends Singleton
{
    /**
     * Имя плагина
     */
    const CI_PLUGIN     = 1;

    /**
     * Имя контроллера
     */
    const CI_CONTROLLER = 2;

    /**
     * Имя контроллера
     */
    const CI_ACTION = 4;

    /**
     * Имя модуля
     */
    const CI_MODULE = 8;

    /**
     * Имя сущности
     */
    const CI_ENTITY = 16;

    /**
     * Имя маппера
     */
    const CI_MAPPER = 32;

    /**
     * Имя метода
     */
    const CI_METHOD = 64;

    /**
     * Имя хука
     */
    const CI_HOOK = 128;

    /**
     * Имя класс наследования
     */
    const CI_INHERIT = 256;

    /**
     * Имя виджета
     */
    const CI_WIDGET = 512;

    /**
     * Тип компонента
     */
    const CI_TYPE = 4096;

    /**
     * Префикс плагина
     */
    const CI_PPREFIX = 8192;

    /**
     * Разобранный класс наследования
     */
    const CI_INHERITS = 16384;

    /**
     * Путь к файлу класса
     */
    const CI_CLASSPATH = 32768;

    /**
     * Все свойства класса
     */
    const CI_ALL = 65535;

    /**
     * Свойства по-умолчанию
     * CI_ALL ^ (CI_CLASSPATH | CI_INHERITS | CI_PPREFIX)
     */
    const CI_DEFAULT = 8191;

    /**
     * Объекты
     * CI_ACTION | CI_MAPPER | CI_HOOK | CI_PLUGIN | CI_ACTION | CI_MODULE | CI_ENTITY | CI_WIDGET
     */
    const CI_OBJECT = 863;

    const CI_AREA_ENGINE = 1;
    const CI_AREA_COMMON = 2;
    const CI_AREA_WITHOUT_PLUGINS = 3;
    const CI_AREA_ACTIVE_PLUGINS = 4;
    const CI_AREA_ACTUAL = 7;
    const CI_AREA_ALL_PLUGINS = 8;
    const CI_AREA_ANYWHERE = 15;

    const STAGE_INIT        = 1;
    const STAGE_PLUGINS     = 2;
    const STAGE_HOOKS       = 3;
    const STAGE_AUTOLOAD    = 4;
    const STAGE_MODULES     = 5;
    const STAGE_RUN         = 6;
    const STAGE_SHUTDOWN    = 7;
    const STAGE_DONE        = 8;

    /** @var int - Stage of Engine */
    static protected $nStage = 0;

    /**
     * Internal cache of resolved class names
     *
     * @var array
     */
    static protected $aClasses = [];

    /**
     * Internal cache for info of used classes
     *
     * @var array
     */
    static protected $aClassesInfo = [];

    /**
     * Hash of active plugins
     *
     * @var string
     */
    static protected $sPluginsHash;

    /**
     * Список загруженных модулей
     *
     * @var array
     */
    protected $aModules = [];

    /**
     * Map of relations Name => Class
     *
     * @var array
     */
    protected $aModulesMap = [];

    /**
     * Список загруженных плагинов
     *
     * @var array
     */
    protected $aPlugins = [];

    /**
     * Время загрузки модулей в микросекундах
     *
     * @var int
     */
    public $nTimeLoadModule = 0;
    /**
     * Текущее время в микросекундах на момент инициализации ядра(движка).
     * Определается так:
     * <pre>
     * $this->iTimeInit=microtime(true);
     * </pre>
     *
     * @var int|null
     */
    protected $nTimeInit;


    /**
     * Вызывается при создании объекта ядра.
     * Устанавливает время старта инициализации и обрабатывает входные параметры PHP
     *
     */
    protected function __construct()
    {
        parent::__construct();
        $this->nTimeInit = microtime(true);
    }

    public function __destruct()
    {
    }

    /**
     * Ограничиваем объект только одним экземпляром.
     * Функционал синглтона.
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!Loader::configLoaded()) {
            die('Can not use class ' . get_called_class() . ' while the configuration is not complete');
        }
        return parent::getInstance();
    }

    /**
     * Инициализация ядра движка
     *
     */
    public function init()
    {
        if (self::$nStage >= self::STAGE_RUN) return;

        self::$nStage = self::STAGE_INIT;

        // * Загружаем плагины
        $this->_loadPlugins();
        self::$nStage = self::STAGE_PLUGINS;

        // * Инициализируем хуки
        $this->_initHooks();
        self::$nStage = self::STAGE_HOOKS;

        // * Загружаем модули автозагрузки
        $this->_autoloadModules();
        self::$nStage = self::STAGE_AUTOLOAD;

        // * Инициализируем загруженные модули
        $this->_initModules();
        self::$nStage = self::STAGE_MODULES;

        // * Инициализируем загруженные плагины
        $this->_initPlugins();

        self::$nStage = self::STAGE_RUN;

        // * Запускаем хуки для события завершения инициализации Engine
        \HookManager::run('engine_init_complete');
    }

    /**
     * Завершение работы движка
     * Завершает все модули.
     *
     */
    public function shutdown($oResponse)
    {
        if (self::$nStage < self::STAGE_SHUTDOWN) {
            self::$nStage = self::STAGE_SHUTDOWN;
            $oResultResponse = $this->_shutdownModules($oResponse);
            if (is_object($oResultResponse)) {
                $oResponse = $oResultResponse;
            }
            self::$nStage = self::STAGE_DONE;
        }
        return $oResponse;
    }

    /**
     * @return int
     */
    public static function getStage()
    {
        return self::$nStage;
    }

    /**
     * Производит инициализацию всех модулей
     *
     */
    protected function _initModules()
    {
        /** @var Decorator $oModule */
        foreach ($this->aModules as $oModule) {
            if (!$oModule->isInit()) {
                $this->_initModule($oModule);
            }
        }
    }

    /**
     * Инициализирует модуль
     *
     * @param Decorator $oModule - Объект модуля
     *
     * @throws \RuntimeException
     */
    protected function _initModule($oModule)
    {
        if ($oModule->inInitProgress()) {
            // Нельзя запускать инициализацию модуля в процессе его инициализации
            throw new \RuntimeException('Recursive initialization of module "' . get_class($oModule) . '"');
        }
        $oModule->setInit(true);
        $oModule->Init();
        $oModule->setInit();
    }

    /**
     * Проверяет модуль на инициализацию
     *
     * @param string $sModuleClass    Класс модуля
     *
     * @return bool
     */
    public function isInitModule($sModuleClass)
    {
        $sModuleClass = static::PluginManager()->getDelegate('module', $sModuleClass);
        if (isset($this->aModules[$sModuleClass]) && $this->aModules[$sModuleClass]->isInit()) {
            return true;
        }
        return false;
    }

    /**
     * Завершаем работу всех модулей
     *
     * @param $oResponse
     *
     * @throws \RuntimeException
     */
    protected function _shutdownModules($oResponse)
    {
        $aModules = $this->aModules;
        array_reverse($aModules);
        // Сначала shutdown модулей, загруженных в процессе работы
        /** @var \Module $oModule */
        foreach ($aModules as $oModule) {
            if (!$oModule->getPreloaded()) {
                $oResultResponse = $this->_shutdownModule($oModule, $oResponse);
                if (is_object($oResultResponse)) {
                    $oResponse = $oResultResponse;
                }
            }
        }
        // Затем предзагруженные модули
        foreach ($aModules as $oModule) {
            if ($oModule->getPreloaded()) {
                $oResultResponse = $this->_shutdownModule($oModule, $oResponse);
                if (is_object($oResultResponse)) {
                    $oResponse = $oResultResponse;
                }
            }
        }
        return $oResponse;
    }

    /**
     * @param \Module $oModule
     *
     * @throws \RuntimeException
     */
    protected function _shutdownModule($oModule, $oResponse)
    {
        if ($oModule->inShutdownProgress()) {
            // Нельзя запускать shutdown модуля в процессе его shutdown`a
            throw new \RuntimeException('Recursive shutdown of module "' . get_class($oModule) . '"');
        }
        $oModule->setDone(false);
        $oResultResponse = $oModule->shutdown($oResponse);
        if (is_object($oResultResponse)) {
            $oResponse = $oResultResponse;
        }
        $oModule->setDone();
        return $oResponse;
    }

    /**
     * Выполняет загрузку модуля по его названию
     *
     * @param  string $sModuleClass    Класс модуля
     * @param  bool $bInit Инициализировать модуль или нет
     *
     * @throws \RuntimeException если класс $sModuleClass не существует
     *
     * @return \Module
     */
    public function loadModule($sModuleClass, $bInit = false)
    {
        $tm1 = microtime(true);

        if (!class_exists($sModuleClass)) {
            throw new \RuntimeException(sprintf('Class "%s" not found!', $sModuleClass));
        }

        // * Создаем объект модуля
        $oModule = new $sModuleClass();
        $oModuleDecorator = Decorator::createComponent($oModule);
        $this->aModules[$sModuleClass] = $oModuleDecorator;
        if ($bInit || $sModuleClass === 'ModuleCache') {
            $this->_initModule($oModuleDecorator);
        }
        $tm2 = microtime(true);
        $this->nTimeLoadModule += $tm2 - $tm1;

        return $oModuleDecorator;
    }

    /**
     * Загружает модули из авто-загрузки
     *
     */
    protected function _autoloadModules()
    {
        $aAutoloadModules = (array)\C::get('engine.autoload');
        if (!empty($aAutoloadModules)) {
            foreach ($aAutoloadModules as $sModuleName) {
                $sModuleClass = 'Module' . $sModuleName;
                $sModuleClass = static::PluginManager()->getDelegate('module', $sModuleClass);

                if (!isset($this->aModules[$sModuleClass]) && $this->loadModule($sModuleClass)) {
                    // Устанавливаем для модуля признак предзагрузки
                    $this->aModules[$sModuleClass]->setPreloaded(true);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getLoadedModules()
    {
        return $this->aModules;
    }

    /**
     * Регистрирует хуки из /classes/hooks/
     *
     */
    protected function _initHooks()
    {
        $aPathSeek = array_reverse(\C::get('path.root.seek'));
        $aHookFiles = [];
        foreach ($aPathSeek as $sDirHooks) {
            $aFiles = glob($sDirHooks . '/classes/hooks/Hook*.class.php');
            if ($aFiles) {
                foreach ($aFiles as $sFile) {
                    $aHookFiles[basename($sFile)] = $sFile;
                }
            }
        }

        if ($aHookFiles) {
            foreach ($aHookFiles as $sFile) {
                if (preg_match('/Hook([^_]+)\.class\.php$/i', basename($sFile), $aMatch)) {
                    $sClassName = 'Hook' . $aMatch[1];
                    /** @var \Hook $oHook */
                    $oHook = new $sClassName;
                    $oHook->registerHook();
                }
            }
        }

        // * Подгружаем хуки активных плагинов
        $this->_initPluginHooks();
    }

    /**
     * Инициализация хуков активированных плагинов
     *
     */
    protected function _initPluginHooks()
    {
        if ($aPluginList = \F::getPluginsList(false, false)) {
            $sPluginsDir = \F::getPluginsDir();

            foreach ($aPluginList as $aPluginInfo) {
                $aFiles = glob($sPluginsDir . $aPluginInfo['dirname'] . '/classes/hooks/Hook*.class.php');
                if ($aFiles && count($aFiles)) {
                    foreach ($aFiles as $sFile) {
                        if (preg_match('/Hook([^_]+)\.class\.php$/i', basename($sFile), $aMatch)) {
                            //require_once($sFile);
                            $sPluginName = \F::strCamelize($aPluginInfo['id']);
                            $sClassName = "Plugin{$sPluginName}_Hook{$aMatch[1]}";
                            /** @var \Hook $oHook */
                            $oHook = new $sClassName;
                            $oHook->registerHook();
                        }
                    }
                }
            }
        }
    }

    /**
     * Загрузка плагинов и делегирование
     *
     */
    protected function _loadPlugins()
    {
        if ($aPluginList = \F::getPluginsList()) {
            foreach ($aPluginList as $sPluginName) {
                $sClassName = 'Plugin' . \F::strCamelize($sPluginName);
                /** @var \Plugin $oPlugin */
                $oPlugin = new $sClassName;
                $oPlugin->delegate();
                $this->aPlugins[$sPluginName] = $oPlugin;
            }
        }
    }

    /**
     * Инициализация активированных(загруженных) плагинов
     *
     */
    protected function _initPlugins()
    {
        /** @var \Plugin $oPlugin */
        foreach ($this->aPlugins as $oPlugin) {
            $oPlugin->init();
        }
    }

    /**
     * Возвращает список активных плагинов
     *
     * @return array
     */
    public function getPlugins()
    {
        return $this->aPlugins;
    }

    /**
     * Вызывает метод нужного модуля
     *
     * @param string $sName    Название метода в полном виде.
     * Например <pre>Module_Method</pre>
     * @param array $aArgs    Список аргументов
     *
     * @return mixed
     */
    public function _CallModule($sName, &$aArgs)
    {
        list($oModule, $sModuleName, $sMethod) = $this->getModuleMethod($sName);
        $aArgsRef = [];
        foreach ($aArgs as $iKey => $xVal) {
            $aArgsRef[] =& $aArgs[$iKey];
        }
        if ($oModule instanceof Decorator) {
            $xResult = $oModule->callMethod($sMethod, $aArgsRef);
        } else {
            $xResult = call_user_func_array(array($oModule, $sMethod), $aArgsRef);
        }
        return $xResult;
    }

    /**
     * Возвращает объект модуля, имя модуля и имя вызванного метода
     *
     * @param $sCallName - Имя метода модуля в полном виде
     * Например <pre>Module_Method</pre>
     *
     * @return array
     * @throws \RuntimeException
     */
    public function getModuleMethod($sCallName)
    {
        if (isset($this->aModulesMap[$sCallName])) {
            list($sModuleClass, $sModuleName, $sMethod) = $this->aModulesMap[$sCallName];
        } else {
            $sName = $sCallName;
            if (strpos($sCallName, 'Module') !== false || strpos($sCallName, 'Plugin') !== false || substr_count($sCallName, '_') > 1) {
                // * Поддержка полного синтаксиса при вызове метода модуля
                $aInfo = static::getClassInfo($sName, self::CI_MODULE | self::CI_PPREFIX | self::CI_METHOD);
                if ($aInfo[self::CI_MODULE]) {
                    $sName = $aInfo[self::CI_MODULE] . '_' . ($aInfo[self::CI_METHOD] ? $aInfo[self::CI_METHOD] : '');
                    if ($aInfo[self::CI_PPREFIX]) {
                        $sName = $aInfo[self::CI_PPREFIX] . $sName;
                    }
                }
            }

            $aName = explode('_', $sName);

            switch (count($aName)) {
                case 1:
                    $sModuleName = $sName;
                    $sModuleClass = 'Module' . $sName;
                    $sMethod = null;
                    break;
                case 2:
                    $sModuleName = $aName[0];
                    $sModuleClass = 'Module' . $aName[0];
                    $sMethod = $aName[1];
                    break;
                case 3:
                    $sModuleName = $aName[0] . '_' . $aName[1];
                    $sModuleClass = $aName[0] . '_Module' . $aName[1];
                    $sMethod = $aName[2];
                    break;
                default:
                    throw new \RuntimeException('Undefined method module: ' . $sName);
            }

            // * Получаем делегат модуля (в случае наличия такового)
            $sModuleClass = static::PluginManager()->getDelegate('module', $sModuleClass);
            $this->aModulesMap[$sCallName] = [$sModuleClass, $sModuleName, $sMethod];
        }

        if (isset($this->aModules[$sModuleClass])) {
            $oModule = $this->aModules[$sModuleClass];
        } else {
            $oModule = $this->loadModule($sModuleClass, true);
        }

        return [$oModule, $sModuleName, $sMethod];
    }

    /**
     * Возвращает объект модуля
     *
     * @param string $sModuleName Имя модуля
     *
     * @return object|null
     *
     * @throws \RuntimeException
     */
    public function getModule($sModuleName)
    {
        // $sCallName === 'User' or $sCallName === 'ModuleUser' or $sCallName === 'PluginUser\User' or $sCallName === 'PluginUser\ModuleUser'
        $sPrefix = substr($sModuleName, 0, 6);
        if ($sPrefix === 'Module' && preg_match('/^(Module)?([A-Z].*)$/', $sModuleName, $aMatches)) {
            $sModuleName = $aMatches[2];
        } elseif ($sPrefix === 'Plugin' && preg_match('/^Plugin([A-Z][\w]*)\\\\(Module)?([A-Z].*)$/', $sModuleName, $aMatches)) {
            $sModuleName = 'Plugin' . $aMatches[1] . '_Module' . $aMatches[3];
        }
        if ($sModuleName) {
            $aData = $this->getModuleMethod($sModuleName);

            return $aData[0];
        }
        return null;
    }

    /**
     * Возвращает статистику выполнения
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function getStats() {
        /**
         * Подсчитываем время выполнения
         */
        $nTimeInit = $this->getTimeInit();
        $nTimeFull = microtime(true) - $nTimeInit;
        if (!empty($_SERVER['REQUEST_TIME_FLOAT'])) {
            $nExecTime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
        } elseif (!empty($_SERVER['REQUEST_TIME'])) {
            $nExecTime = round(microtime(true) - $_SERVER['REQUEST_TIME'], 3);
        } else {
            $nExecTime = 0;
        }
        return [
            'sql' => $this->getModule('Database')->getStats(),
            'cache' => $this->getModule('Cache')->getStats(),
            'engine' => [
                'time_load_module' => number_format(round($this->nTimeLoadModule, 3), 3),
                'full_time' => number_format(round($nTimeFull, 3), 3),
                'exec_time' => number_format(round($nExecTime, 3), 3),
                'files_count' => \F::File_GetIncludedCount(),
                'files_time' => number_format(round(\F::File_GetIncludedTime(), 3), 3),
            ],
        ];
    }

    /**
     * Возвращает время старта выполнения движка в микросекундах
     *
     * @return int
     */
    public function getTimeInit() {

        return $this->nTimeInit;
    }

    /**
     * Блокируем копирование/клонирование объекта ядра
     *
     */
    protected function __clone() {

    }

    /**
     * Получает объект маппера
     *
     * @param string                 $sClassName Класс модуля маппера
     * @param string|null            $sName      Имя маппера
     *
     * @return null|Mapper
     */
    public static function getMapper($sClassName, $sName = null) {

        $sModuleName = static::getClassInfo($sClassName, self::CI_MODULE, true);
        if ($sModuleName) {
            if (!$sName) {
                $sName = $sModuleName;
            }
            $sClass = $sClassName . '_Mapper' . $sName;
            $sClass = static::PluginManager()->getDelegate('mapper', $sClass);
            return new $sClass(static::Module('Database'));
        }
        return null;
    }

    /**
     * Возвращает класс сущности, контролируя варианты кастомизации
     *
     * @param  string $sName Имя сущности, возможны сокращенные варианты.
     *                       Например, <pre>ModuleUser_EntityUser</pre> эквивалентно <pre>User_User</pre>
     *                       и эквивалентно <pre>User</pre>, т.к. имя сущности совпадает с именем модуля
     *
     * @return string
     * @throws \RuntimeException
     */
    public static function getEntityClass($sName) 
    {
        if (!isset(self::$aClasses[$sName])) {
            /*
             * Сущности, имеющие такое же название как модуль,
             * можно вызывать сокращенно. Например, вместо User_User -> User
             */
            switch (substr_count($sName, '_')) {
                case 0:
                    $sEntity = $sModule = $sName;
                    break;

                case 1:
                    // * Поддержка полного синтаксиса при вызове сущности
                    $aInfo = static::getClassInfo($sName, self::CI_ENTITY | self::CI_MODULE | self::CI_PLUGIN);
                    if ($aInfo[self::CI_MODULE] && $aInfo[self::CI_ENTITY]) {
                        $sName = $aInfo[self::CI_MODULE] . '_' . $aInfo[self::CI_ENTITY];
                    }

                    list($sModule, $sEntity) = explode('_', $sName, 2);
                    /*
                     * Обслуживание короткой записи сущностей плагинов
                     * PluginTest_Test -> PluginTest_ModuleTest_EntityTest
                     */
                    if ($aInfo[self::CI_PLUGIN]) {
                        $sPlugin = $aInfo[self::CI_PLUGIN];
                        $sModule = $sEntity;
                    }
                    break;

                case 2:
                    // * Поддержка полного синтаксиса при вызове сущности плагина
                    $aInfo = static::getClassInfo($sName, self::CI_ENTITY | self::CI_MODULE | self::CI_PLUGIN);
                    if ($aInfo[self::CI_PLUGIN] && $aInfo[self::CI_MODULE] && $aInfo[self::CI_ENTITY]) {
                        $sName = 'Plugin' . $aInfo[self::CI_PLUGIN]
                            . '_' . $aInfo[self::CI_MODULE]
                            . '_' . $aInfo[self::CI_ENTITY];
                    }
                    // * Entity плагина
                    if ($aInfo[self::CI_PLUGIN]) {
                        list(, $sModule, $sEntity) = explode('_', $sName);
                        $sPlugin = $aInfo[self::CI_PLUGIN];
                    } else {
                        throw new \RuntimeException("Unknown entity '{$sName}' given.");
                    }
                    break;

                default:
                    throw new \RuntimeException("Unknown entity '{$sName}' given.");
            }

            $sClass = isset($sPlugin)
                ? 'Plugin' . $sPlugin . '_Module' . $sModule . '_Entity' . $sEntity
                : 'Module' . $sModule . '_Entity' . $sEntity;

            // * If Plugin Entity doesn't exist, search among it's Module delegates
            if (isset($sPlugin) && !static::getClassPath($sClass)) {
                $aModulesChain = (array)static::PluginManager()->GetDelegationChain('module', 'Plugin' . $sPlugin . '_Module' . $sModule);
                foreach ($aModulesChain as $sModuleName) {
                    $sClassTest = $sModuleName . '_Entity' . $sEntity;
                    if (static::getClassPath($sClassTest)) {
                        $sClass = $sClassTest;
                        break;
                    }
                }
                if (!static::getClassPath($sClass)) {
                    $sClass = 'Module' . $sModule . '_Entity' . $sEntity;
                }
            }

            /**
             * Определяем наличие делегата сущности
             * Делегирование указывается только в полной форме!
             */
            //$sClass = static::getInstance()->Plugin_GetDelegate('entity', $sClass);
            if ($sClass !== 'ModulePlugin_EntityPlugin') {
                $sClass = static::PluginManager()->getDelegate('entity', $sClass);
            }

            self::$aClasses[$sName] = $sClass;
        } else {
            $sClass = self::$aClasses[$sName];
        }
        return $sClass;
    }

    /**
     * Создает объект сущности, контролируя варианты кастомизации
     *
     * @param  string $sName Имя сущности, возможны сокращенные варианты.
     *                       Например, <pre>ModuleUser_EntityUser</pre> эквивалентно <pre>User_User</pre>
     *                       и эквивалентно <pre>User</pre> т.к. имя сущности совпадает с именем модуля
     *
     * @param  array  $aParams
     *
     * @throws \RuntimeException
     *
     * @return \Entity
     */
    public static function getEntity($sName, $aParams = []) 
    {
        $sClass = static::getEntityClass($sName);
        /** @var \Entity $oEntity */
        $oEntity = new $sClass($aParams);
        $oEntity->init();

        return $oEntity;
    }

    /**
     * Returns array of entity objects
     *
     * @param string $sName - Entity name
     * @param array $aRows
     * @param array|null $aOrderIdx
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public static function getEntityRows($sName, $aRows = [], $aOrderIdx = null) {

        $aResult = [];
        if ($aRows) {
            $sClass = static::getEntityClass($sName);
            if (is_array($aOrderIdx) && count($aOrderIdx)) {
                foreach ($aOrderIdx as $iIndex) {
                    if (isset($aRows[$iIndex])) {
                        /** @var \Entity $oEntity */
                        $oEntity = new $sClass($aRows[$iIndex]);
                        $oEntity->init();
                        $aResult[$iIndex] = $oEntity;
                    }
                }
            } else {
                foreach ($aRows as $nI => $aRow) {
                    /** @var \Entity $oEntity */
                    $oEntity = new $sClass($aRow);
                    $oEntity->init();
                    $aResult[$nI] = $oEntity;
                }
            }
        }
        return $aResult;
    }

    /**
     * Возвращает имя плагина модуля, если модуль принадлежит плагину
     * Например, <pre>Openid</pre>
     *
     * @static
     *
     * @param Module|string $xModule - Объект модуля
     *
     * @return string|null
     */
    public static function getPluginName($xModule)
    {
        return static::getClassInfo($xModule, self::CI_PLUGIN, true);
    }

    /**
     * Возвращает префикс плагина
     * Например, <pre>PluginOpenid_</pre>
     *
     * @static
     *
     * @param Module|string $xModule Объект модуля
     *
     * @return string    Если плагина нет, возвращает пустую строку
     */
    public static function getPluginPrefix($xModule)
    {
        return static::getClassInfo($xModule, self::CI_PPREFIX, true);
    }

    /**
     * Возвращает имя модуля
     *
     * @static
     *
     * @param Module|string $xModule Объект модуля
     *
     * @return string|null
     */
    public static function getModuleName($xModule)
    {
        return static::getClassInfo($xModule, self::CI_MODULE, true);
    }

    /**
     * Возвращает имя сущности
     *
     * @static
     *
     * @param Entity|string $xEntity Объект сущности
     *
     * @return string|null
     */
    public static function getEntityName($xEntity)
    {
        return static::getClassInfo($xEntity, self::CI_ENTITY, true);
    }

    /**
     * Возвращает имя контроллера
     *
     * @static
     *
     * @param Controller|string $xController
     *
     * @return string|null
     */
    public static function getControllerName($xController)
    {
        return static::getClassInfo($xController, self::CI_CONTROLLER, true);
    }

    /**
     * Возвращает имя экшена
     *
     * @static
     *
     * @param Action|string $xAction    Объект экшена
     *
     * @return string|null
     */
    public static function getActionName($xAction)
    {
        return static::getClassInfo($xAction, self::CI_CONTROLLER, true);
    }

    /**
     * @param $sClassName
     *
     * @return array
     */
    protected static function _parseClassInfo($sClassName)
    {
        if (!empty(self::$aClassesInfo[$sClassName])) {
            return self::$aClassesInfo[$sClassName];
        }
        $aInfo = [
            self::CI_PLUGIN     => null,
            self::CI_CONTROLLER => null,
            self::CI_MODULE     => null,
            self::CI_ENTITY     => null,
            self::CI_MAPPER     => null,
            self::CI_HOOK       => null,
            self::CI_INHERIT    => null,
            self::CI_WIDGET     => null,
            self::CI_PPREFIX    => null,
            self::CI_INHERITS   => null,
            self::CI_CLASSPATH  => null,
        ];
        $sRegex = '/^(Plugin(?P<plugin>[A-Z][^_]+)_(?P<inherits>Inherits?_)?)?(?P<component>[A-Z][a-z]+)(?P<compname>[^_]+)(_(?P<subcomponent>[A-Z][a-z]+)(?P<subcompname>[^_]+))?$/';
        if (preg_match($sRegex, $sClassName, $aM)) {
            if (!empty($aM['plugin'])) {
                $aInfo[self::CI_PLUGIN] = $aM['plugin'];
            }
            if (!empty($aM['component'])) {
                switch ($aM['component']) {
                    case 'Plugin':
                        $aInfo[self::CI_PLUGIN] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Controller':
                        $aInfo[self::CI_CONTROLLER] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Action':
                        $aInfo[self::CI_ACTION] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Module':
                        $aInfo[self::CI_MODULE] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Entity':
                        $aInfo[self::CI_ENTITY] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Mapper':
                        $aInfo[self::CI_MAPPER] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Hook':
                        $aInfo[self::CI_HOOK] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                    case 'Widget':
                        $aInfo[self::CI_WIDGET] = $aM['compname'];
                        $aInfo[self::CI_TYPE] = $aM['component'];
                        break;
                }
            }

            if (!empty($aM['subcomponent'])) {
                switch ($aM['subcomponent']) {
                    case 'Entity':
                        $aInfo[self::CI_ENTITY] = $aM['subcompname'];
                        $aInfo[self::CI_TYPE] = $aM['subcomponent'];
                        break;
                    case 'Mapper':
                        $aInfo[self::CI_MAPPER] = $aM['subcompname'];
                        $aInfo[self::CI_TYPE] = $aM['subcomponent'];
                        break;
                }
            }

            if (!empty($aM['inherits'])) {
                $aInfo[self::CI_INHERIT] = $aM['inherits'];
            }
        }
        self::$aClassesInfo[$sClassName] = $aInfo;

        return $aInfo;
    }

    /**
     * Возвращает информацию об объекте или классе
     *
     * @static
     *
     * @param Component|string $oObject  Объект или имя класса
     * @param int              $iBitMask Маска по которой нужно вернуть рузультат. Доступные маски определены в константах CI_*
     *                                 Например, получить информацию о плагине и модуле:
     *                                 <pre>
     *                                 Engine::GetClassInfo($oObject,Engine::CI_PLUGIN | Engine::CI_MODULE);
     *                                 </pre>
     * @param bool             $bSingle  Возвращать полный результат или только первый элемент
     *
     * @return array|string|null
     */
    public static function getClassInfo($oObject, $iBitMask = self::CI_DEFAULT, $bSingle = false)
    {
        $aResult = [];

        $iTime = microtime(true);
        $sClassName = is_string($oObject) ? $oObject : get_class($oObject);
        //$aInfo = (!empty(self::$aClassesInfo[$sClassName]) ? self::$aClassesInfo[$sClassName] : []);
        $aInfo = self::_parseClassInfo($sClassName);

        // The first call because it sets other parts in self::$aClassesInfo
        if ($iBitMask & self::CI_CLASSPATH) {
            if (!isset($aInfo[self::CI_CLASSPATH])) {
                $aInfo[self::CI_CLASSPATH] = static::getClassPath($sClassName);
                self::$aClassesInfo[$sClassName][self::CI_CLASSPATH] = $aInfo[self::CI_CLASSPATH];
            }
            $aResult[self::CI_CLASSPATH] = $aInfo[self::CI_CLASSPATH];
        }
        // Flag of finalization
        $bBreak = false;
        if ($iBitMask & self::CI_PLUGIN) {
            if (!isset($aInfo[self::CI_PLUGIN])) {
                $aInfo[self::CI_PLUGIN] = preg_match('/^Plugin([^_]+)/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_PLUGIN] = $aInfo[self::CI_PLUGIN];
                // It's plugin class only
                if ($aInfo[self::CI_PLUGIN] && $aMatches[0] == $sClassName) {
                    $bBreak = true;
                }
            }
            $aResult[self::CI_PLUGIN] = $aInfo[self::CI_PLUGIN];
        }

        if ($iBitMask & self::CI_CONTROLLER) {
            if ($bBreak) {
                $aInfo[self::CI_CONTROLLER] = false;
            } elseif (!isset($aInfo[self::CI_CONTROLLER])) {
                $aInfo[self::CI_CONTROLLER] = preg_match('/^(?:Plugin[^_]+_|)Action([^_]+)/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_CONTROLLER] = $aInfo[self::CI_CONTROLLER];
                // it's an Action
                $bBreak = !empty($aInfo[self::CI_CONTROLLER]);
            }
            $aResult[self::CI_CONTROLLER] = $aInfo[self::CI_CONTROLLER];
        }

        if ($iBitMask & self::CI_HOOK) {
            if ($bBreak) {
                $aInfo[self::CI_HOOK] = false;
            } elseif (!isset($aInfo[self::CI_HOOK])) {
                $aInfo[self::CI_HOOK] = preg_match('/^(?:Plugin[^_]+_|)Hook([^_]+)$/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_HOOK] = $aInfo[self::CI_HOOK];
                // it's a Hook
                $bBreak = !empty($aInfo[self::CI_HOOK]);
            }
            $aResult[self::CI_HOOK] = $aInfo[self::CI_HOOK];
        }

        if ($iBitMask & self::CI_WIDGET) {
            if ($bBreak) {
                $aInfo[self::CI_WIDGET] = false;
            } elseif (!isset($aInfo[self::CI_WIDGET])) {
                $aInfo[self::CI_WIDGET] = preg_match('/^(?:Plugin[^_]+_|)Widget([^_]+)$/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_WIDGET] = $aInfo[self::CI_WIDGET];
                // it's a Widget
                $bBreak = !empty($aInfo[self::CI_WIDGET]);
            }
            $aResult[self::CI_WIDGET] = $aInfo[self::CI_WIDGET];
        }

        if ($iBitMask & self::CI_MODULE) {
            if ($bBreak) {
                $aInfo[self::CI_MODULE] = false;
            } elseif (!isset($aInfo[self::CI_MODULE])) {
                $aInfo[self::CI_MODULE] = preg_match('/^(?:Plugin[^_]+_|)Module(?:ORM|)([^_]+)/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_MODULE] = $aInfo[self::CI_MODULE];
            }
            $aResult[self::CI_MODULE] = $aInfo[self::CI_MODULE];
        }

        if ($iBitMask & self::CI_ENTITY) {
            if ($bBreak) {
                $aInfo[self::CI_ENTITY] = false;
            } elseif (!isset($aInfo[self::CI_ENTITY])) {
                $aInfo[self::CI_ENTITY] = preg_match('/_Entity(?:ORM|)([^_]+)/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_ENTITY] = $aInfo[self::CI_ENTITY];
            }
            $aResult[self::CI_ENTITY] = $aInfo[self::CI_ENTITY];
        }

        if ($iBitMask & self::CI_MAPPER) {
            if ($bBreak) {
                $aInfo[self::CI_MAPPER] = false;
            } elseif (!isset($aInfo[self::CI_MAPPER])) {
                $aInfo[self::CI_MAPPER] = preg_match('/_Mapper(?:ORM|)([^_]+)/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_MAPPER] = $aInfo[self::CI_MAPPER];
            }
            $aResult[self::CI_MAPPER] = $aInfo[self::CI_MAPPER];
        }

        if ($iBitMask & self::CI_METHOD) {
            if (!isset($aInfo[self::CI_METHOD])) {
                $sModuleName = isset($aInfo[self::CI_MODULE])
                    ? $aInfo[self::CI_MODULE]
                    : static::getClassInfo($sClassName, self::CI_MODULE, true);
                $aInfo[self::CI_METHOD] = preg_match('/_([^_]+)$/', $sClassName, $aMatches)
                    ? ($sModuleName && strtolower($aMatches[1]) == strtolower('module' . $sModuleName) ? null : $aMatches[1])
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_METHOD] = $aInfo[self::CI_METHOD];
            }
            $aResult[self::CI_METHOD] = $aInfo[self::CI_METHOD];
        }
        if ($iBitMask & self::CI_PPREFIX) {
            if (!isset($aInfo[self::CI_PPREFIX])) {
                $sPluginName = isset($aInfo[self::CI_PLUGIN])
                    ? $aInfo[self::CI_PLUGIN]
                    : static::getClassInfo($sClassName, self::CI_PLUGIN, true);
                $aInfo[self::CI_PPREFIX] = $sPluginName
                    ? "Plugin{$sPluginName}_"
                    : '';
                self::$aClassesInfo[$sClassName][self::CI_PPREFIX] = $aInfo[self::CI_PPREFIX];
            }
            $aResult[self::CI_PPREFIX] = $aInfo[self::CI_PPREFIX];
        }
        if ($iBitMask & self::CI_INHERIT) {
            if (!isset($aInfo[self::CI_INHERIT])) {
                $aInfo[self::CI_INHERIT] = preg_match('/_Inherits?_(\w+)$/', $sClassName, $aMatches)
                    ? $aMatches[1]
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_INHERIT] = $aInfo[self::CI_INHERIT];
            }
            $aResult[self::CI_INHERIT] = $aInfo[self::CI_INHERIT];
        }
        if ($iBitMask & self::CI_INHERITS) {
            if (!isset($aInfo[self::CI_INHERITS])) {
                $sInherit = isset($aInfo[self::CI_INHERIT])
                    ? $aInfo[self::CI_INHERIT]
                    : static::getClassInfo($sClassName, self::CI_INHERIT, true);
                $aInfo[self::CI_INHERITS] = $sInherit
                    ? static::getClassInfo($sInherit, self::CI_OBJECT, false)
                    : false;
                self::$aClassesInfo[$sClassName][self::CI_INHERITS] = $aInfo[self::CI_INHERITS];
            }
            $aResult[self::CI_INHERITS] = $aInfo[self::CI_INHERITS];
        }

        self::$aClassesInfo[$sClassName]['calls'][] = ['flag' => $iBitMask, 'time' => round(microtime(true) - $iTime, 6)];

        return $bSingle ? end($aResult) : $aResult;
    }


    /**
     * Возвращает информацию о пути до файла класса.
     * Используется в {@link autoload автозагрузке}
     *
     * @static
     *
     * @param Component|string $xObject Объект - модуль, экшен, плагин, хук, сущность
     * @param int              $iArea   В какой области проверять (классы движка, общие классы, плагины)
     *
     * @return null|string
     */
    public static function getClassPath($xObject, $iArea = self::CI_AREA_ACTUAL)
    {
        //$aInfo = static::getClassInfo($xObject, self::CI_OBJECT);
        $aInfo = self::_parseClassInfo($xObject);
        $sPluginDir = '';
        if (!empty($aInfo[self::CI_PLUGIN])) {
            $sPlugin = \F::strUnderscore($aInfo[self::CI_PLUGIN]);
            $aPlugins = \F::getPluginsList($iArea & self::CI_AREA_ALL_PLUGINS, false);
            if (isset($aPlugins[$sPlugin]['dirname'])) {
                $sPluginDir = $aPlugins[$sPlugin]['dirname'];
            } else {
                $sPluginDir = $sPlugin;
            }
            $sComponentPlugin = 'plugins/' . $sPluginDir . '/';
        } else {
            $sComponentPlugin = '';
        }
        $aPathSeek = \C::get('path.root.seek');
        if (!empty($aInfo[self::CI_ENTITY])) {
            // Сущность модуля
            $sFile = $sComponentPlugin . '/classes/modules/' . \F::strUnderscore($aInfo[self::CI_MODULE]) . '/entity/Entity' . $aInfo[self::CI_ENTITY] . '.php';
        } elseif (!empty($aInfo[self::CI_MAPPER])) {
            // Маппер
            $sFile = $sComponentPlugin . '/classes/modules/' . \F::strUnderscore($aInfo[self::CI_MODULE]) . '/mapper/Mapper' . $aInfo[self::CI_MAPPER] . '.php';
        } elseif (!empty($aInfo[self::CI_CONTROLLER])) {
            // Контроллер
            $sFile = $sComponentPlugin . 'classes/controllers/Controller' . $aInfo[self::CI_CONTROLLER] . '.php';
        } elseif (!empty($aInfo[self::CI_ACTION])) {
            // Экшн
            $sFile = $sComponentPlugin . 'classes/actions/Action' . $aInfo[self::CI_ACTION] . '.php';
        } elseif (!empty($aInfo[self::CI_MODULE])) {
            // Модуль
            $sFile = $sComponentPlugin . 'classes/modules/' . \F::strUnderscore($aInfo[self::CI_MODULE]) . '/Module' . $aInfo[self::CI_MODULE] . '.php';
        } elseif (!empty($aInfo[self::CI_HOOK])) {
            // Хук
            $sFile = $sComponentPlugin . 'classes/hooks/Hook' . $aInfo[self::CI_HOOK] . '.php';
        } elseif (!empty($aInfo[self::CI_WIDGET])) {
            // Виджет
            $sFile = $sComponentPlugin . 'classes/widgets/Widget' . $aInfo[self::CI_WIDGET] . '.php';
        } elseif (!empty($aInfo[self::CI_PLUGIN])) {
            // Плагин
            $sFile = 'plugins/' . $sPluginDir . '/Plugin' . $aInfo[self::CI_PLUGIN] . '.php';
        } else {
            $sClassName = is_string($xObject) ? $xObject : get_class($xObject);
            $sFile = $sClassName . '.php';
            $aPathSeek = [
                \C::get('path.dir.engine') . '/classes/core/',
                \C::get('path.dir.engine') . '/classes/abstract/',
            ];
        }
        $sPath = \F::File_Exists($sFile, $aPathSeek);
        if (empty($sPath)) {
            $sFile = substr($sFile, 0, -4) . '.class.php';
            $sPath = \F::File_Exists($sFile, $aPathSeek);
        }

        return $sPath ? $sPath : null;
    }

    /**
     * @param string $sName
     * @param array  $aArgs
     *
     * @return mixed
     */
    public static function __callStatic($sName, $aArgs = []) {

        if (0 === strpos($sName, 'Module')) {
            $oModule = static::Module($sName);
            if ($oModule) {
                return $oModule;
            }
        }
        $oEngine = Engine::getInstance();
        if (method_exists($oEngine, $sName)) {
            return call_user_func_array([$oEngine, $sName], $aArgs);
        } else {
            return $oEngine->_CallModule($sName, $aArgs);
        }
    }

    /**
     * @param $sModuleName
     *
     * @return Module
     */
    public static function Module($sModuleName)
    {
        static $aModules = [];

        if (static::getStage() < self::STAGE_INIT) {
            die('Module ' . $sModuleName . ' was called before Engine initiation');
        }
        if (0 === strpos($sModuleName, 'Module')) {
            $sCheckName = substr($sModuleName, 6);
        } else {
            $sCheckName = $sModuleName;
        }
        if (static::getStage() < self::STAGE_PLUGINS) {
            die('Module ' . $sModuleName . ' was called before Engine loaded plugins');
        }

        if (!empty($aModules[$sCheckName])) {
            $oModule = $aModules[$sCheckName];
        } else {
            $oModule = static::getInstance()->getModule($sModuleName);
            $aModules[$sCheckName] = $oModule;
        }
        return $oModule;
    }

    /** @var \ModuleUser_EntityUser */
    static protected $oCurrentUser = false;

    /**
     * Returns current user
     *
     * @return \ModuleUser_EntityUser
     */
    public static function User()
    {
        if (self::$oCurrentUser === false) {
            self::$oCurrentUser = static::Module('User')->getCurrentUser();
        }
        return self::$oCurrentUser;
    }

    /**
     * If user is authorized
     *
     * @return bool
     */
    public static function isAuth()
    {
        return null !== static::User();
    }

    /**
     * If user is authorized
     *
     * @return bool
     */
    public static function isUser()
    {
        return null !== static::User();
    }

    /**
     * If user is authorized && admin
     *
     * @return bool
     */
    public static function isAdmin()
    {
        $oUser = static::User();
        return ($oUser && $oUser->isAdministrator());
    }

    /**
     * If user is authorized && moderator
     *
     * @return bool
     */
    public static function isModerator()
    {
        $oUser = static::User();
        return ($oUser && $oUser->isModerator());
    }

    /**
     * Is the user an administrator or a moderator?
     *
     * @return bool
     */
    public static function isAdminOrModerator()
    {
        $oUser = static::User();
        return ($oUser && ($oUser->isAdministrator() || $oUser->isModerator()));
    }

    /**
     * If user is authorized && not admin
     *
     * @return bool
     */
    public static function isNotAdmin()
    {
        $oUser = static::User();
        return ($oUser && !$oUser->isAdministrator());
    }

    /**
     * Returns UserId if user is authorized
     *
     * @return int|null
     */
    public static function userId()
    {
        if ($oUser = static::User()) {
            return (int)$oUser->getId();
        }
        return null;
    }

    /**
     * @return \alto\engine\core\PluginManager
     */
    public static function PluginManager()
    {
        if (!isset(self::$aClasses['PluginManager'])) {
            self::$aClasses['PluginManager'] = PluginManager::getInstance();
        }
        return self::$aClasses['PluginManager'];
    }

    /**
     * @return \alto\engine\core\HookManager
     */
    public static function HookManager()
    {
        if (!isset(self::$aClasses['HookManager'])) {
            self::$aClasses['HookManager'] = HookManager::getInstance();
        }
        return self::$aClasses['HookManager'];
    }

    /**
     * If plugin is activated
     *
     * @param   string  $sPlugin
     * @return  bool
     */
    public static function activePlugin($sPlugin)
    {
        return static::PluginManager()->isActivePlugin($sPlugin);
    }

    /**
     * @return array
     */
    public static function getActivePlugins()
    {
        return static::getInstance()->getPlugins();
    }

    /**
     * @return string
     */
    public static function getActivePluginsHash()
    {
        if (self::$sPluginsHash === null) {
            self::$sPluginsHash = '';
            $aPlugins = static::getActivePlugins();
            foreach($aPlugins as $oPlugin) {
                /** @var \ModulePlugin_EntityPlugin $oPluginEntity */
                $oPluginEntity = $oPlugin->GetPluginEntity();
                if (self::$sPluginsHash) {
                    self::$sPluginsHash .= ',';
                }
                self::$sPluginsHash .= $oPluginEntity->getId() . '(' . $oPluginEntity->getVersion() . ')';
            }
        }
        return self::$sPluginsHash;
    }

    /**
     * hook:hook_name
     * text:just_a_text
     * func:func_name
     * conf:some.config.key
     * 
     * @param mixed $xExpression
     * @param array $aParams
     *
     * @return mixed
     */
    public static function evaluate($xExpression, $aParams = [])
    {
        if (is_bool($xExpression)) {
            return $xExpression;
        }
        if (is_numeric($xExpression)) {
            return (int)$xExpression;
        }
        if (is_object($xExpression)) {
            return $xExpression();
        }
        if (is_string($xExpression) && strpos($xExpression, ':')) {
            list($sType, $sName) = explode(':', $xExpression, 2);
            if ($sType === 'hook') {
                return \HookManager::run($sName, $aParams, false);
            }
            if ($sType === 'text') {
                return $sName;
            }
            if ($sType === 'func') {
                return $sName($aParams);
            }
            if ($sType === 'call') {
                return $sName($aParams);
            }
            if ($sType === 'conf') {
                return \C::get($sName);
            }
        }
        
        return $xExpression;
    }

}

// EOF
