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

use alto\engine\core\Engine;
use \alto\engine\generic\DataArray;

/**
 * Class Loader
 */
class Loader
{
    static protected $bConfigLoaded = false;
    static protected $aConfigClasses;
    static protected $aPrimaryConfig = [];
    static protected $aClassSeekDirs = [];
    static protected $aCoreClasses = [];

    /**
     * @param array $aConfig
     */
    public static function init($aConfig)
    {
        self::$aPrimaryConfig = $aConfig;

        // Register autoloader
        self::_autoloadRegister($aConfig);

        if (!isset($config['url']['request'])) {
            $config['url']['request'] = \F::parseUrl();
        }

        // Load primary config
        Config::load($aConfig);

        $sCommonDir = Config::get('path.dir.common');
        self::_loadFilesFrom($sCommonDir, ['module'], Config::LEVEL_MAIN);
        self::_loadPluginFiles(Config::LEVEL_MAIN);

        // Ups config level
        Config::resetLevel(Config::LEVEL_APP);

        // Пути поиска классов
        self::$aClassSeekDirs = [
            \F::File_NormPath(Config::get('path.dir.app')),
            \F::File_NormPath(Config::get('path.dir.common')),
            \F::File_NormPath(Config::get('path.dir.engine')),
        ];
        // Дополнительные пути поиска в обратном порядке (потом приведем в нужный порядок)
        $aAddClassSeekDirs = [];

        $sAppDir = Config::get('path.dir.app');
        self::_loadFilesFrom($sAppDir, ['module'], Config::LEVEL_APP);
        self::_loadPluginFiles(Config::LEVEL_APP);

        // Ups config level
        Config::resetLevel(Config::LEVEL_CUSTOM);
        while ($sAppDir !== Config::get('path.dir.app')) {
            // if 'path.dir.app' was changed then load new config
            $sAppDir = \F::File_NormPath(Config::get('path.dir.app'));
            if (!$sAppDir || !is_dir($sAppDir) || in_array($sAppDir, $aAddClassSeekDirs)) {
                break;
            }
            self::_loadFilesFrom($sAppDir, ['module'], Config::LEVEL_CUSTOM);
            self::_loadPluginFiles(Config::LEVEL_CUSTOM);
            $aAddClassSeekDirs[] = $sAppDir;
        }

        // Load named config settings (if they exists)
        $aConfigSettings = (array)Config::get('config.settings');
        foreach ($aConfigSettings as $sConfigSetting) {
            $sConfigSettingFile = $sAppDir . '/settings/' . $sConfigSetting;
            if (is_file($sConfigSettingFile)) {
                Config::loadFromFile($sConfigSettingFile, Config::LEVEL_CUSTOM);
            }
        }

        // Пути поиска - от последних к первым
        if ($aAddClassSeekDirs) {
            self::$aClassSeekDirs = array_merge(self::$aClassSeekDirs, array_reverse($aAddClassSeekDirs));
        }
        Config::load(['path.root.seek' => self::$aClassSeekDirs]);

        self::_checkRequiredDirs();

        $sPathSubdir = Config::get('path.root.subdir');
        if ($sPathSubdir === null) {
            if (isset($_SERVER['DOCUMENT_ROOT'])) {
                $sPathSubdir = '/' . \F::File_LocalPath(ALTO_DIR_ROOT, $_SERVER['DOCUMENT_ROOT']);
            } elseif ($iOffset = Config::get('path.offset_request_url')) {
                $aParts = array_slice(explode('/', \F::File_NormPath(ALTO_DIR_ROOT)), -$iOffset);
                $sPathSubdir = '/' . implode('/', $aParts);
            } else {
                $sPathSubdir = '';
            }
            Config::load(['path.root.subdir' => $sPathSubdir]);
        } elseif ($sPathSubdir) {
            if ($sPathSubdir[0] !== '/' || substr($sPathSubdir, -1) === '/') {
                $sPathSubdir = '/' . trim($sPathSubdir, '/');
                Config::load(['path.root.subdir' => $sPathSubdir]);
            }
        }
        if ($sPathSubdir && Config::get('path.offset_request_url') === null) {
            Config::load(['path.offset_request_url' => substr_count($sPathSubdir, '/')]);
        }

        // Load from cache, because database could not be used here
        self::_loadStorageConfig();

        // Задаем локаль по умолчанию
        \F::includeLib('UserLocale/UserLocale.class.php');
        // Устанавливаем признак того, является ли сайт многоязычным
        $aLangsAllow = (array)Config::get('lang.allow');
        if (count($aLangsAllow) > 1) {
            \UserLocale::initLocales($aLangsAllow);
            Config::load(['lang.multilang' => true]);
        } else {
            Config::load(['lang.multilang' => false]);
        }
        \UserLocale::setLocale(
            Config::get('lang.current'),
            ['local' => Config::get('i18n.locale'), 'timezone' => Config::get('i18n.timezone')]
        );
        Config::load(['i18n' => \UserLocale::getLocale()]);

        self::$bConfigLoaded = true;
    }

    /**
     * @param array $aConfig
     */
    protected static function _autoloadRegister($aConfig)
    {
        // Регистрация автозагрузчика классов
        spl_autoload_register(__NAMESPACE__ . '\Loader::autoload');
        if (isset($aConfig['path']['dir']['vendor']) && is_file($aConfig['path']['dir']['vendor'] . '/autoload.php')) {
            \F::includeFile($aConfig['path']['dir']['vendor'] . '/autoload.php');
        }
        spl_autoload_register(__NAMESPACE__ . '\Loader::autoloadEngine', true, true);

        if (isset(self::$aPrimaryConfig['path']['dir']['engine']) && is_dir(self::$aPrimaryConfig['path']['dir']['engine'])) {
            $sPath = self::$aPrimaryConfig['path']['dir']['engine'] . 'classes/';
            $aFiles = glob($sPath . '*/*.php');
            foreach($aFiles as $sFile) {
                $sClassName = basename($sFile, '.php');
                $sParentClass = 'alto\\engine\\' . str_replace('/', '\\', substr(dirname($sFile), strlen($sPath))) . '\\' . $sClassName;
                self::classAlias($sParentClass, $sClassName);
                if (strpos($sParentClass, '\\core\\')) {
                    self::$aCoreClasses[$sClassName] = $sParentClass;
                }
            }
        }
    }

    /**
     * @param string   $sDir
     * @param string[] $aSubDirs
     * @param int      $nConfigLevel
     */
    protected static function _loadFilesFrom($sDir, $aSubDirs, $nConfigLevel)
    {
        self::_loadIncludeFiles($sDir . '/include/');
        self::_loadConfigFiles($sDir . '/config/', $aSubDirs, $nConfigLevel);
    }

    /**
     * @param string $sDir
     */
    protected static function _loadIncludeFiles($sDir)
    {
        if (is_dir($sDir)) {
            $aIncludeFiles = glob($sDir . '/*.php');
            if ($aIncludeFiles) {
                foreach ($aIncludeFiles as $sPath) {
                    \F::includeFile($sPath);
                }
            }
        }
    }

    /**
     * @param string   $sDir
     * @param string[] $aSubDirs
     * @param int      $nConfigLevel
     */
    protected static function _loadConfigFiles($sDir, $aSubDirs, $nConfigLevel)
    {
        if (is_dir($sDir)) {
            // загружаем "config.php"
            $sConfigFile = $sDir . '/config.php';
            Config::loadFromFile($sConfigFile, $nConfigLevel);

            // загружаем "config.*.php"
            $aFiles = glob($sDir . '/config.*.php');
            if ($aFiles) {
                foreach ($aFiles as $sConfigFile) {
                    $aConfig = \F::includeFile($sConfigFile, true, true);
                    if (!empty($aConfig) && is_array($aConfig)) {
                        // config.1.foo.bar.php => foo.bar
                        $sName = basename($sConfigFile, '.php');
                        $iPos = strpos($sName, '.', 8);
                        $sKey = $iPos ? substr($sName, $iPos + 1) : '';
                        if (!$sKey || (isset($aConfig[$sKey]) && count($aConfig) === 1)) {
                            Config::load($aConfig, $nConfigLevel, $sConfigFile);
                        } else {
                            Config::load([$sKey => $aConfig], $nConfigLevel, $sConfigFile);
                        }
                    }
                }
            }
            // загружаем "module/search/config.php"
            foreach($aSubDirs as $sSubDir) {
                $sConfigDir = $sDir . $sSubDir;
                if (is_dir($sConfigDir)) {
                    $aFiles = glob($sConfigDir . '/*/config.php');
                    if ($aFiles) {
                        foreach ($aFiles as $sConfigFile) {
                            $aConfig = \F::includeFile($sConfigFile, true, true);
                            if (!empty($aConfig) && is_array($aConfig)) {
                                $sKey = $sSubDir . '.' . basename(dirname($sConfigFile));
                                Config::load([$sKey => $aConfig], $nConfigLevel, $sConfigFile);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param int $nConfigLevel
     */
    protected static function _loadPluginFiles($nConfigLevel)
    {
        $sPluginsDir = \F::getPluginsDir($nConfigLevel === Config::LEVEL_APP);
        if ($aPluginsList = \F::getPluginsList(false, false)) {
            foreach ($aPluginsList as $sPlugin => $aPluginInfo) {
                // Подключаем include-файлы плагина
                $aIncludeFiles = glob($sPluginsDir . '/' . $aPluginInfo['dirname'] . '/include/*.php');
                if ($aIncludeFiles) {
                    foreach ($aIncludeFiles as $sPath) {
                        \F::includeFile($sPath);
                    }
                }
                // Загружаем конфиг-файлы плагина
                $aConfigFiles = [];
                $sConfigFile = $sPluginsDir . '/' . $aPluginInfo['dirname'] . '/config/config.php';
                if (is_file($sConfigFile)) {
                    $aConfigFiles[] = $sConfigFile;
                }
                $aFiles = glob($sPluginsDir . '/' . $aPluginInfo['dirname'] . '/config/config.*.php');
                if ($aFiles) {
                    $aConfigFiles = array_merge($aConfigFiles, $aFiles);
                }
                if ($aConfigFiles) {
                    foreach($aConfigFiles as $sConfigFile) {
                        $aConfig = \F::includeFile($sConfigFile, true, true);
                        if (!empty($aConfig) && is_array($aConfig)) {
                            if (isset($aConfig[Config::KEY_ROOT])) {
                                Config::load($aConfig[Config::KEY_ROOT], $nConfigLevel, $sConfigFile);
                                unset($aConfig[Config::KEY_ROOT]);
                            }
                            Config::set('plugin', [$sPlugin => $aConfig], $nConfigLevel, $sConfigFile);
                        }
                    }
                }
            }
        }
    }

    /**
     * Load saved config from file cache, because database could not be used here
     */
    protected static function _loadStorageConfig()
    {
        $aConfig = Config::readStorageConfig(null, true);
        if ($aConfig) {
            Config::load($aConfig, null, 'storage');
        }
    }

    /**
     * Check required dirs
     */
    protected static function _checkRequiredDirs()
    {
        // Directory of application
        $sDir = Config::get('path.dir.app');
        if (!$sDir) {
            self::_fatalError('Application directory not defined');
        } elseif (!\F::File_CheckDir($sDir, false)) {
            self::_fatalError('Application directory "' . \F::File_LocalDir(Config::get('path.dir.app')) . '" is not exist');
        }

        // Directory for temporary files
        $sDir = Config::get('path.tmp.dir');
        if (!$sDir) {
            self::_fatalError('Directory for temporary files not defined');
        } elseif (!\F::File_CheckDir($sDir, true)) {
            self::_fatalError('Directory for temporary files "' . $sDir . '" does not exist');
        } elseif (!is_writable($sDir)) {
            self::_fatalError('Directory for temporary files "' . \F::File_LocalDir($sDir) . '" is not writeable');
        }

        // Public directory for runtime files (assets)
        $sDir = Config::get('path.runtime.dir');
        if (!$sDir) {
            self::_fatalError('Directory for runtime files not defined');
        } elseif (!\F::File_CheckDir($sDir, true)) {
            self::_fatalError('Directory for runtime files "' . $sDir . '" does not exist');
        } elseif (!is_writable($sDir)) {
            self::_fatalError('Directory for runtime files "' . \F::File_LocalDir($sDir) . '" is not writeable');
        }
    }

    /**
     * @param string $sError
     */
    protected static function _fatalError($sError)
    {
        die($sError);
    }

    /**
     * @param string $sDir
     * @param int    $nConfigLevel
     */
    public static function loadConfigFiles($sDir, $nConfigLevel = null)
    {
        self::_loadConfigFiles($sDir, [], $nConfigLevel);
    }

    /**
     * Автоопределение класса или файла экшена
     *
     * @param   string $sAction
     * @param   string $sEvent
     * @param   bool   $bFullPath
     *
     * @return  string|null
     */
    public static function seekActionClass($sAction, $sEvent = null, $bFullPath = false)
    {
        $bOk = false;
        $sActionClass = '';
        $sFileName = 'Action' . ucfirst($sAction) . '.php';

        // Сначала проверяем файл экшена среди стандартных
        $aSeekDirs = [Config::get('path.dir.app'), Config::get('path.dir.common')];
        if ($sActionFile = \F::File_Exists('/classes/actions/' . $sFileName, $aSeekDirs)) {
            $sActionClass = 'Action' . ucfirst($sAction);
            $bOk = true;
        } else {
            // Если нет, то проверяем файл экшена среди плагинов
            $aPlugins = \F::getPluginsList(false, false);
            foreach ($aPlugins as $sPlugin => $aPluginInfo) {
                if ($sActionFile = \F::File_Exists('plugins/' . $aPluginInfo['dirname'] . '/classes/actions/' . $sFileName, $aSeekDirs)) {
                    $sActionClass = 'Plugin' . \F::StrCamelize($sPlugin) . '_Action' . ucfirst($sAction);
                    $bOk = true;
                    break;
                }
            }
        }
        if ($bOk) {
            return $bFullPath ? $sActionFile : $sActionClass;
        }
        return null;
    }

    /**
     * @param string $sFile
     * @param string $sCheckClassName
     *
     * @return bool|mixed
     */
    protected static function _includeFile($sFile, $sCheckClassName = null)
    {
        if (class_exists('F', false)) {
            $xResult = \F::includeFile($sFile);
        } else {
            $xResult = include_once $sFile;
        }
        if ($sCheckClassName) {
            return class_exists($sCheckClassName, false);
        }
        return $xResult;
    }

    /**
     * @param $sKey
     *
     * @return mixed|null
     */
    protected static function _configGet($sKey)
    {
        if(class_exists(__NAMESPACE__ . '\Config', false)) {
            return Config::get($sKey);
        }
        return null;
    }

    /**
     * @param string $sKey
     *
     * @return mixed
     */
    protected static function _getConfigClasses($sKey)
    {
        if (!empty(self::$aConfigClasses)) {
            return self::$aConfigClasses[$sKey];
        }
        return self::_configGet($sKey);
    }

    /**
     * @return bool
     */
    public static function configLoaded()
    {
        return (bool)self::$bConfigLoaded;
    }

    /**
     * Автозагрузка классов
     *
     * @param string $sClassName    Название класса
     *
     * @return bool
     */
    public static function autoload($sClassName)
    {
        if (empty(self::$aConfigClasses) && self::configLoaded()) {
            $aData = (array)Config::get('classes');
            self::$aConfigClasses = new DataArray(['classes' => $aData]);
        }

        if ($sParentClass = self::_getConfigClasses('classes.alias.' . $sClassName)) {
            return self::classAlias($sParentClass, $sClassName);
        }
        if (self::_autoloadDefinedClass($sClassName)) {
            return true;
        }

        if (false === strpos($sClassName, 'alto\\engine\\') && 0 === strpos($sClassName, 'alto\\')) {
            if (self::_autoloadAlto($sClassName)) {
                return true;
            }
        }

        if (class_exists(__NAMESPACE__ . '\Engine', false) && (Engine::getStage() >= Engine::STAGE_INIT)) {
            $aInfo = Engine::getClassInfo($sClassName, Engine::CI_CLASSPATH | Engine::CI_INHERIT);
            if ($aInfo[Engine::CI_INHERIT]) {
                $sInheritClass = $aInfo[Engine::CI_INHERIT];
                $sParentClass = Engine::PluginManager()->GetParentInherit($sInheritClass);
                return self::classAlias($sParentClass, $sClassName);
            }
            if ($aInfo[Engine::CI_CLASSPATH]) {
                return self::_includeFile($aInfo[Engine::CI_CLASSPATH], $sClassName);
            }
        }
        if (self::_autoloadPSR($sClassName)) {
            return true;
        }
        return false;
    }

    /**
     * Try to load class using config info
     *
     * @param string $sClassName
     *
     * @return bool
     */
    protected static function _autoloadDefinedClass($sClassName)
    {
        if ($sFile = self::_getConfigClasses('classes.class.' . $sClassName)) {
            // defined file name for the class
            if (is_array($sFile)) {
                $sFile = isset($sFile['file']) ? $sFile['file'] : null;
            }
            if ($sFile) {
                return self::_includeFile($sFile, $sClassName);
            }
        }
        // May be Namespace_Package or Namespace\Package
        if (strpos($sClassName, '\\') || strpos($sClassName, '_')) {
            $aPrefixes = (array)self::_getConfigClasses('classes.prefix');
            foreach ($aPrefixes as $sPrefix => $aOptions) {
                if (strpos($sClassName, $sPrefix) === 0) {
                    // defined prefix for vendor/library
                    if (is_array($aOptions)) {
                        if (isset($aOptions['path'])) {
                            $sPath = $aOptions['path'];
                        } else {
                            $sPath = '';
                        }
                    } else {
                        $sPath = $aOptions;
                    }
                    if ($sPath) {
                        if (isset($aOptions['classmap'][$sClassName])) {
                            $sFile = $sPath . '/' . $aOptions['classmap'][$sClassName];
                            return self::_includeFile($sFile, $sClassName);
                        }
                        return self::_autoloadPSR($sClassName, $sPath);
                    }
                }
            }
        }
        return false;
    }

    static protected $_aFailedClasses = [];

    /**
     * @param string       $sClassName
     * @param string|array $xPath
     *
     * @return bool
     */
    protected static function _autoloadPSR($sClassName, $xPath = null)
    {
        if (strpos($sClassName, '\\')) {
            return self::_autoloadPSR4($sClassName) || self::_autoloadPSR0($sClassName, $xPath);
        }
        return self::_autoloadPSR0($sClassName, $xPath);
    }

    /**
     * Try to load class using PRS-0 naming standard
     *
     * @param string       $sClassName
     * @param string|array $xPath
     *
     * @return bool
     */
    protected static function _autoloadPSR0($sClassName, $xPath = null)
    {
        if (!$xPath) {
            $xPath = self::_configGet('path.dir.libs');
        }

        $sCheckKey = json_encode([$sClassName, $xPath]);
        if (!isset(self::$_aFailedClasses[$sCheckKey])) {
            if (strpos($sClassName, '\\')) {
                // Namespaces
                $sFileName = str_replace('\\', DIRECTORY_SEPARATOR, $sClassName);
            } elseif (strpos($sClassName, '_')) {
                // Old style with '_'
                $sFileName = str_replace('_', DIRECTORY_SEPARATOR, $sClassName);
            } else {
                $sFileName = $sClassName . DIRECTORY_SEPARATOR . $sClassName;
            }
            if ($sFile = \F::File_Exists($sFileName . '.php', $xPath)) {
                return self::_includeFile($sFile, $sClassName);
            }
        }
        self::$_aFailedClasses[$sCheckKey] = false;

        return false;
    }

    /**
     * Try load class using PSR-4 standards
     * Used code from http://www.php-fig.org/psr/psr-4/examples/
     *
     * @param string $sClassName
     *
     * @return bool
     */
    protected static function _autoloadPSR4($sClassName)
    {
        // An associative array where the key is a namespace prefix and the value
        // is an array of base directories for classes in that namespace.
        $aVendorNamespaces = self::_getConfigClasses('classes.namespace');
        if (!$aVendorNamespaces || !strpos($sClassName, '\\')) {
            return false;
        }

        // the current namespace prefix
        $sPrefix = $sClassName;

        // work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $iPos = strrpos($sPrefix, '\\')) {

            // seeking namespace prefix
            $sPrefix = substr($sClassName, 0, $iPos);

            // the rest is the relative class name
            $sRelativeClass = substr($sClassName, $iPos + 1);
            $sFileName = str_replace('\\', DIRECTORY_SEPARATOR, $sRelativeClass) . '.php';

            // try to load a mapped file for the prefix and relative class
            if (isset($aVendorNamespaces[$sPrefix])) {
                if ($sFile = \F::File_Exists($sFileName, $aVendorNamespaces[$sPrefix])) {
                    return self::_includeFile($sFile, $sClassName);
                }
                if ($sFile = \F::File_Exists($sFileName, $aVendorNamespaces[$sPrefix] . '/' . pathinfo($sFileName, PATHINFO_FILENAME))) {
                    return self::_includeFile($sFile, $sClassName);
                }
            }
        }
        // файл так и не был найден
        return false;
    }

    /**
     * @param string $sClassName
     *
     * @return bool
     */
    protected static function _autoloadAlto($sClassName)
    {
        $aDirs = self::_configGet('path.dir');
        if ((null === $aDirs) && isset(self::$aPrimaryConfig['path']['dir'])) {
            $aDirs = self::$aPrimaryConfig['path']['dir'];
        }
        if ($aDirs) {
            if (strpos($sClassName, '\\')) {
                $aParts = explode('\\', $sClassName, 3);
                if (count($aParts) === 3 && isset($aDirs[$aParts[1]])) {
                    $sFile = $aDirs[$aParts[1]] . 'classes/' . str_replace('\\', '/', $aParts[2]) . '.php';
                    if (is_file($sFile)) {
                        return (bool)self::_includeFile($sFile, $sClassName);
                    }
                    $sShortClassName = substr($sClassName, strrpos($sClassName, '\\') + 1);
                    if ($sParentClass = self::_getConfigClasses('classes.alias.' . $sShortClassName)) {
                        return self::classAlias($sParentClass, $sClassName);
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param string $sClassName
     *
     * @return bool
     */
    public static function autoloadEngine($sClassName)
    {
        if (0 === strpos($sClassName, 'alto\\engine\\')) {
            return self::_autoloadAlto($sClassName);
        }
        return false;
    }

    /**
     * @var array Array of class aliases
     */
    static protected $_aClassAliases = [];

    /**
     * Creates an alias for a class
     *
     * @param string $sOriginal
     * @param string $sAlias
     * @param bool   $bAutoload
     *
     * @return bool
     */
    public static function classAlias($sOriginal, $sAlias, $bAutoload = TRUE)
    {
        $bResult = class_alias($sOriginal, $sAlias, $bAutoload);

        if (defined('ALTO_DEBUG') && ALTO_DEBUG) {
            self::$_aClassAliases[$sAlias] = [
                'original'  => $sOriginal,
                'autoload'  => $bAutoload,
                'result'    => $bResult,
            ];
        }

        return $bResult;
    }

    /**
     * Returns of class aliases
     *
     * @return array
     */
    public static function getAliases()
    {
        return self::$_aClassAliases;
    }

}

// EOF