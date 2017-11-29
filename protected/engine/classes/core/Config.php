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

use \alto\engine\generic\DataArray;
//F::IncludeFile('Storage.class.php');
//F::IncludeFile('DataArray.class.php');

/**
 * Управление простым конфигом в виде массива
 *
 * @package engine.lib
 * @since   1.0
 *
 * @method static Config getInstance
 */
class Config extends Storage
{
    const LEVEL_MAIN        = 0;
    const LEVEL_APP         = 1;
    const LEVEL_CUSTOM      = 2;
    const LEVEL_ACTION      = 3;
    const LEVEL_SKIN        = 4;
    const LEVEL_SKIN_CUSTOM = 5;

    /**
     * Default config root key
     *
     * @var string
     */
    const DEFAULT_CONFIG_ROOT = '__config__';

    const KEY_LINK_STR = '___';
    const KEY_LINK_PREG = '~___([\S|\.]+)(___/|___)~Ui';
    const KEY_ROOT = '$root$';
    const KEY_EXTENDS = '$extends$';
    const KEY_REPLACE = '$replace$';
    const KEY_RESET   = '$reset$';

    const CUSTOM_CONFIG_PREFIX = 'custom.config.';
    const ENGINE_CONFIG_PREFIX = 'engine.';

    const ROOT_KEY = '$root$';

    const ALTO_UNIQUE_KEY = 'engine.alto.uniq_key';

    static protected $aElapsedTime = [];

    /**
     * Mapper rules for Config Path <-> Constant Name relations
     *
     * @var array
     */
    static protected $aMapper = [];

    static protected $bRereadCustomConfig = false;

    /**
     * Local quick cache
     *
     * @var array
     */
    static public $aQuickMap = [];

    protected $sConfigRoot;

    /**
     * Stack levels
     *
     * @var array
     */
    protected $aLevel = [];

    /**
     * Current level
     *
     * @var int
     */
    protected $nLevel = 0;

    /**
     * Sources of config values
     *
     * @var array
     */
    protected $aSources = [];

    /**
     * Constructor
     */
    protected function __construct()
    {
        parent::__construct();
        self::$aElapsedTime = ['set' => 0.0, 'get' => 0.0];
        $this->nSaveMode = self::SAVE_MODE_ARR;
        $this->setRootKey(self::DEFAULT_CONFIG_ROOT);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (ALTO_DEBUG) {
            //var_dump('Config', self::$aElapsedTime);
        }
    }

    /**
     * @param string $sSource
     */
    protected function _addSource($sSource)
    {
        if (ALTO_DEBUG) {
            $aStack = array_slice(debug_backtrace(false), 1, null);
            $aPoint = [];
            foreach($aStack as $aCaller) {
                if (empty($aCaller['class']) || ($aCaller['class'] !== 'Config' && !is_subclass_of($aCaller['class'], 'Config'))) {
                    break;
                }
                $aPoint = $aCaller;
            }
            $this->aSources[] = [
                'source' => $sSource,
                'caller' => $aPoint,
            ];
        }
    }

    /**
     * Clear quick map storage
     */
    protected function _clearQuickMap()
    {
        self::$aQuickMap = null;
        self::$aQuickMap = [];
        $this->_restoreKeyExtensions();
    }

    /**
     * Reload configuration array from file
     *
     * @param string $sConfigFile Путь до файла конфига
     * @param int    $nLevel      Уровень конфига
     * @param string $sConfigKey  Секция конфига, куда нужно загружать данные
     *
     * @return  bool|Config
     */
    public static function resetFromFile($sConfigFile, $nLevel = null, $sConfigKey = null)
    {
        // Check if file exists
        if ($sConfigFile = \F::File_Exists($sConfigFile)) {
            // Get config from file
            $aConfig = \F::File_IncludeFile($sConfigFile, true, true);
            if ($aConfig) {
                if (!empty($sConfigKey)) {
                    return static::reset([$sConfigKey => $aConfig], $nLevel, $sConfigFile);
                } else {
                    return static::reset($aConfig, $nLevel, $sConfigFile);
                }
            }
        }
        return false;
    }

    /**
     * Add configuration array from file
     *
     * @param string $sConfigFile
     * @param int    $nLevel
     * @param string $sConfigKey
     *
     * @return bool
     */
    public static function loadFromFile($sConfigFile, $nLevel = null, $sConfigKey = null)
    {
        // Check if file exists
        if ($sConfigFile = \F::File_Exists($sConfigFile)) {
            // Get config from file
            $aConfig = \F::File_IncludeFile($sConfigFile, true, true);
            if ($aConfig) {
                if (!empty($sConfigKey)) {
                    return static::load([$sConfigKey => $aConfig], $nLevel, $sConfigFile);
                } else {
                    return static::load($aConfig, $nLevel, $sConfigFile);
                }
            }
        }
        return false;
    }

    /**
     * @param string $sConfigDir
     * @param int    $nLevel
     * @param string $sPrefix
     *
     * @return bool
     */
    public static function setFromDir($sConfigDir, $nLevel = null, $sPrefix = 'config')
    {
        if (is_dir($sConfigDir)) {
            $aConfigFiles = [];
            if (is_file($sFile = $sConfigDir . '/' . $sPrefix . '.php')) {
                $aConfigFiles[] = $sFile;
            }
            if ($aFiles = glob($sConfigDir . '/' . $sPrefix . '.*.php')) {
                $aConfigFiles = array_merge($aConfigFiles, $aFiles);
            }
            if ($aConfigFiles) {
                $nPrefixLen = strlen($sPrefix);
                foreach ($aConfigFiles as $sConfigFile) {
                    // config.foo.bar.php => foo.bar
                    $sKey = substr(basename($sConfigFile, '.php'), $nPrefixLen);
                    static::loadFromFile($sConfigFile, $nLevel, $sKey);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Loads configuration
     *
     * @param array  $aConfig  Массив конфига
     * @param int    $nLevel   Уровень конфига
     * @param string $sSource  Источник
     *
     * @return  bool|Config
     */
    public static function reset($aConfig, $nLevel = null, $sSource = null)
    {
        // Check if it`s array
        if (!is_array($aConfig)) {
            return false;
        }
        // Set config to current or handle instance
        return static::_set($aConfig, true, $nLevel, $sSource);
    }

    /**
     * Set config value(s)
     * Usage:
     *   \C::set('key', $xData, ...);
     *
     * @param string    $sKey    Key
     * @param mixed     $xValue  Value(s)
     * @param int       $nLevel  Level of config
     * @param string    $sSource Source of data
     *
     * @return bool
     */
    public static function set($sKey, $xValue, $nLevel = null, $sSource = null)
    {
        return static::_set([$sKey => $xValue], false, $nLevel, $sSource);
    }

    /**
     * Load configuration
     * Usage:
     *   \C::load(['key' => $xData], ...);
     *
     * @param array  $aConfig
     * @param int    $nLevel
     * @param string $sSource
     *
     * @return bool
     */
    public static function load($aConfig, $nLevel = null, $sSource = null)
    {
        // Check if it`s array
        if (!is_array($aConfig)) {
            return false;
        }
        // Set config to current or handle instance
        return static::_set($aConfig, false, $nLevel, $sSource);
    }

    /**
     * Makes storage key using root key & level
     *
     * @param int    $nLevel
     *
     * @return string
     */
    protected function _storageKey($nLevel = null)
    {
        if ($nLevel === null) {
            $nLevel = ($this->nLevel ?: 0);
        }
        $sRootKey = $this->getRootKey();

        return '[' . $sRootKey . '.__' . $nLevel . '__]';
    }

    /**
     * @param $sRootKey
     */
    public function setRootKey($sRootKey)
    {
        $this->sConfigRoot = $sRootKey;
    }

    /**
     * @return string
     */
    public function getRootKey()
    {
        return $this->sConfigRoot;
    }

    /**
     * Return all config array or its part (if composite key passed)
     *
     * @param int    $nLevel   Config level
     * @param string $sKey     Composite key of config item
     *
     * @return array|mixed|null
     */
    public function _getConfigByLevel($nLevel = null, $sKey = null)
    {
        if ($nLevel === null) {
            $nLevel = $this->nLevel;
        }
        $sStorageKey = $this->_storageKey($nLevel);

        if ($sKey === null) {
            $xResult = parent::getStorage($sStorageKey);
            if (!$xResult) {
                $xResult = [];
            }
        } else {
            $xResult = parent::getStorageItem($sStorageKey, $sKey);
        }
        return $xResult;
    }

    /**
     * Устанавливает значения конфига
     *
     * @param int    $nLevel   Уровень конфига
     * @param array  $aConfig  Массив конфига
     * @param bool   $bReset   Сбросить старые значения
     * @param string $sSource  Источник
     *
     * @return  bool
     */
    public function _setConfigByLevel($nLevel = null, $aConfig = [], $bReset = true, $sSource = null)
    {
        if ($nLevel === null) {
            $nLevel = $this->nLevel;
        }
        $sStorageKey = $this->_storageKey($nLevel);

        $bResult = parent::setStorage($sStorageKey, $aConfig, $bReset);
        if (ALTO_DEBUG) {
            $this->_addSource($sSource);
        }
        $this->_clearQuickMap();

        return $bResult;
    }

    /**
     * Checks if the key exists
     *
     * @param string $sKey
     *
     * @return mixed|null
     */
    public function _isExists($sKey)
    {
        $sStorageKey = $this->_storageKey();
        return parent::isExists($sStorageKey, $sKey);
    }

    /**
     * Очистка заданного (или текущего) уровня конфигурации
     *
     * @param int $nLevel
     */
    public function _clearLevel($nLevel = null)
    {
        $this->_setConfigByLevel($nLevel, null, true);
    }

    /**
     * Установка нового уровня конфигурации
     *
     * @param int  $nLevel
     * @param bool $bSafe  (true - safe mode, false - nonsafe mode, null - auto mode)
     */
    public function _setLevel($nLevel = null, $bSafe = null)
    {
        if ($nLevel > $this->nLevel) {
            $aConfig = $this->_getConfigByLevel($this->nLevel);
            while ($nLevel > $this->nLevel) {
                ++$this->nLevel;
                if ($bSafe === false) {
                    $this->_setConfigByLevel($this->nLevel, $aConfig, false);
                } else {
                    // If $bSafe is null then it is "auto" mode
                    if (($bSafe === null) && $aConfig && !$this->_getConfigByLevel($this->nLevel)) {
                        $this->_setConfigByLevel($this->nLevel, $aConfig, false);
                    } else {
                        $this->_setConfigByLevel($this->nLevel, [], false);
                    }
                }
            }
        } elseif ($nLevel < $this->nLevel) {
            while ($nLevel < $this->nLevel) {
                if (!$bSafe) {
                    $this->_clearLevel($this->nLevel);
                }
                --$this->nLevel;
            }
        } else {
            if ($bSafe === false) {
                $aConfig = $this->_getConfigByLevel($nLevel-1);
                if ($aConfig) {
                    $this->_setConfigByLevel($nLevel, $aConfig, true);
                }
            }
        }
        $this->nLevel = $nLevel;
    }

    /**
     * @return int
     */
    public function _getLevel()
    {
        return $this->nLevel;
    }

    /**
     * Set config level
     *
     * @param int  $nLevel
     * @param bool $bSafe   (true - safe mode, false - nonsafe mode, null - auto mode)
     */
    public static function setLevel($nLevel, $bSafe = null)
    {
        static::getInstance()->_setLevel($nLevel, $bSafe);
    }

    /**
     * Set config level
     *
     * @param int $nLevel
     */
    public static function resetLevel($nLevel)
    {
        $oInstance = static::getInstance();
        $oInstance->_setLevel($nLevel, null);
        $oInstance->_setLevel($nLevel, false);
    }

    /**
     * Get config level
     *
     * @return int
     */
    public static function getLevel()
    {
        return static::getInstance()->_getLevel();
    }

    /**
     * Retrieve information from configuration array
     *
     * @param string $sKey    Key
     * @param int    $nLevel  Level (if null then current level)
     * @param bool   $bRaw    Raw result
     *
     * @return mixed
     */
    public static function get($sKey = null, $nLevel = null, $bRaw = false)
    {
        if (ALTO_DEBUG) {
            $nTime = microtime(true);
        }

        // Return all config array
        if (!$sKey) {
            if (ALTO_DEBUG && !empty($nTime)) {
                self::$aElapsedTime['get'] += (microtime(true) - $nTime);
            }

            return static::getInstance()->_getConfigByLevel($nLevel);
        }
        $xResult = static::getInstance()->getValue($sKey, $nLevel, $bRaw);

        if (ALTO_DEBUG && !empty($nTime)) {
            $nTime = microtime(true) - $nTime;
            self::$aElapsedTime['get'] += $nTime;
        }
        return $xResult;
    }

    /**
     * @param string $sKey
     *
     * @return DataArray
     */
    public static function getData($sKey = '')
    {
        $xData = static::get($sKey);
        return new DataArray($xData);
    }

    /**
     * As a method Get() but with default value
     *
     * @param string $sKey
     * @param mixed  $xDefault
     *
     * @return mixed|null
     */
    public static function val($sKey = '', $xDefault = null)
    {
        $sValue = static::get($sKey);
        return ($sValue === null) ? $xDefault : $sValue;
    }

    /**
     * Получает значение из конфигурации по переданному ключу
     *
     * @param string $sKey     - Ключ
     * @param int    $nLevel
     * @param bool   $bRaw
     *
     * @return mixed
     */
    public function getValue($sKey, $nLevel = null, $bRaw = false)
    {
        if ($bRaw) {
            // return raw data
            $xConfigData = $this->_getConfigByLevel($nLevel, $sKey);
            return $xConfigData;
        }

        // We cache a current level only, if other level required then we return result without caching
        if ($nLevel !== null && $nLevel !== $this->nLevel) {
            $sKeyMap = false;
        } else {
            if ($nLevel === null) {
                $nLevel = $this->nLevel;
            }
            /** @var string $sKeyMap */
            $sKeyMap = $this->_storageKey('*') . '.' . $sKey;
        }

        // Config section inherits of other (use $extends$ key)
        if (!$sKeyMap || !empty(self::$aKeyExtends[$sKeyMap])) {

            $xConfigData = $this->_getConfigByLevel($nLevel, $sKey);
            if (is_array($xConfigData) && !empty($xConfigData[self::KEY_EXTENDS]) && is_string($xConfigData[self::KEY_EXTENDS])) {
                $xConfigData = $this->_extendsConfig($sKey, $xConfigData, $nLevel);
            }
            if (is_string($xConfigData) && strpos($xConfigData, self::KEY_LINK_STR) !== false) {
                $xConfigData = $this->_resolveKeyLink($xConfigData, $nLevel);
            }
            if ($sKeyMap) {
                // SET QUICK MAP AND CLEAR KEY EXTENDS
                self::$aQuickMap[$sKeyMap] = $xConfigData;
                //if (isset(self::$aKeyExtends[$sKeyMap])) {
                //    unset(self::$aKeyExtends[$sKeyMap]);
                //}
                $this->_clearKeyExtension($sKeyMap);
            }

            return $xConfigData;
        }

        if (!isset(self::$aQuickMap[$sKeyMap]) && !array_key_exists($sKeyMap, self::$aQuickMap)) {

            // if key has '.' then it has a parent key
            $sParentKey = strstr($sKey, '.', true);

            if ($sParentKey) {
                // If parent section was inserted in quick map we can quickly find subsection in it
                if (self::$aQuickMap) {
                    $xConfigData = $this->_checkQuickMapForParent($sKeyMap, $nLevel);
                    if ($xConfigData !== null) {
                        self::$aQuickMap[$sKeyMap] = $xConfigData;
                        return $xConfigData;
                    }
                }

                // May be parent section inherits of other so we need to resolve it
                if (self::$aKeyExtends) {
                    $xConfigData = $this->_checkExtendsForParent($sKeyMap, $nLevel);
                    if ($xConfigData !== null) {
                        self::$aQuickMap[$sKeyMap] = $xConfigData;
                        return $xConfigData;
                    }
                }
            }

            $xConfigData = $this->_getConfigByLevel($nLevel, $sKey);
            if (!empty($xConfigData)) {
                if (is_array($xConfigData)) {
                    $xConfigData = $this->_keyReplace($sKey, $xConfigData, $nLevel);
                } elseif (is_string($xConfigData) && strpos($xConfigData, self::KEY_LINK_STR) !== false) {
                    $xConfigData = $this->_resolveKeyLink($xConfigData, $nLevel);
                }
            }
            self::$aQuickMap[$sKeyMap] = $xConfigData;
        }

        return self::$aQuickMap[$sKeyMap];
    }

    /**
     * @param string $sKeyMap
     * @param int    $nLevel
     *
     * @return mixed|null
     */
    protected function _checkExtendsForParent($sKeyMap, $nLevel)
    {
        foreach(self::$aKeyExtends as $sKey => $sSourceKey) {
            if (false !== strpos($sKeyMap, $sKey)) {
                $sEnd = substr($sKeyMap, strlen($sKey));
                if ($sEnd[0] === '.') {
                    $aSubKeys = explode('.', substr($sEnd, 1));
                    if ($iOffset = strpos($sKey, '__].')) {
                        $sParentKey = substr($sKey, $iOffset + 4);
                    } else {
                        $sParentKey = $sKey;
                    }
                    $xData = $this->getValue($sParentKey);
                    foreach($aSubKeys as $sSubKey) {
                        if (isset($xData[$sSubKey])) {
                            $xData = $xData[$sSubKey];
                        } else {
                            self::$aQuickMap[$sKeyMap] = null;
                            return null;
                        }
                    }
                    self::$aQuickMap[$sKeyMap] = $xData;
                    return $xData;
                }
            }
        }
        return null;
    }

    /**
     * @param string $sKeyMap
     * @param int    $nLevel
     *
     * @return mixed|null
     */
    protected function _checkQuickMapForParent($sKeyMap, $nLevel)
    {
        $aSubKeys = [];
        $sParentKey = $sKeyMap;
        $iOffset = strpos($sKeyMap, ']');
        while($iPos = strrpos($sParentKey, '.', $iOffset)) {
            $aSubKeys[] = substr($sParentKey, $iPos + 1);
            $sParentKey = substr($sParentKey, 0, $iPos);
            if (isset(self::$aQuickMap[$sParentKey])) {
                $xData = self::$aQuickMap[$sParentKey];
                while ($sSubKey = array_pop($aSubKeys)) {
                    if (isset($xData[$sSubKey])) {
                        $xData = $xData[$sSubKey];
                    } else {
                        self::$aQuickMap[$sKeyMap] = null;
                        return null;
                    }
                }
                self::$aQuickMap[$sKeyMap] = $xData;
                return $xData;
            }
        }
        return null;
    }

    /**
     * Заменяет плейсхолдеры ключей в значениях конфига
     *
     * @static
     *
     * @param string|array $xConfigData Значения конфига
     * @param int          $nLevel
     *
     * @return mixed
     */
    public static function keyReplace($xConfigData, $nLevel = null)
    {
        if ($nLevel === null) {
            $nLevel = static::getInstance()->getLevel();
        }
        return static::getInstance()->_keyReplace(null, $xConfigData, $nLevel);
    }

    /**
     * Replace all placeholders and extend config sections from parent data
     *
     * @param string       $sKeyPath
     * @param array|string $xConfigData
     * @param int          $nLevel
     *
     * @return mixed
     */
    public function _keyReplace($sKeyPath, $xConfigData, $nLevel)
    {
        $xResult = $xConfigData;

        if (is_array($xConfigData)) {
            // $xConfigData is array
            /** @var array $xResult */
            $xResult = [];
            // e.g.: '$extends$' => '___module.uploader.images.default___',
            if (!empty($xConfigData[self::KEY_EXTENDS]) && is_string($xConfigData[self::KEY_EXTENDS])) {
                $xConfigData = $this->_extendsConfig($sKeyPath, $xConfigData, $nLevel);
            }
            foreach ($xConfigData as $sKey => $xData) {
                if (is_string($sKey) && !is_numeric($sKey) && strpos($sKey, self::KEY_LINK_STR) !== false) {
                    $sNewKey = $this->_keyReplace(null, $sKey, $nLevel);
                    if (!is_scalar($sNewKey)) {
                        $sNewKey = $sKey;
                    }
                } else {
                    $sNewKey = $sKey;
                }
                // Changes placeholders for array or string only
                if (is_array($xData)) {
                    $xResult[$sNewKey] = $this->_keyReplace($sKeyPath ? ($sKeyPath . '.' . $sNewKey) : $sNewKey, $xData, $nLevel);
                } elseif (is_string($xData) && strpos($xData, self::KEY_LINK_STR) !== false) {
                    $xResult[$sNewKey] = $this->_resolveKeyLink($xData, $nLevel);
                } else {
                    $xResult[$sNewKey] = $xData;
                }
            }
        } elseif (is_string($xConfigData) && !is_numeric($xConfigData)) {
            // $xConfigData is string
            if (strpos($xConfigData, self::KEY_LINK_STR) !== false) {
                $xResult = $this->_resolveKeyLink($xConfigData, $nLevel);
            }
        }
        return $xResult;
    }

    /**
     * @param string $sKeyPath
     * @param array  $xConfigData
     * @param int    $nLevel
     *
     * @return array
     */
    protected function _extendsConfig($sKeyPath, $xConfigData, $nLevel)
    {
        if (isset($xConfigData[self::KEY_EXTENDS])) {
            $aParentData = [];
            if (is_string($xConfigData[self::KEY_EXTENDS])) {
                $sLinkKey = $this->_storageKey('*') . '.' . $xConfigData[self::KEY_EXTENDS];
                if (isset(self::$aQuickMap[$sLinkKey])) {
                    $aParentData = self::$aQuickMap[$sLinkKey];
                } elseif (!$sKeyPath || (strpos($xConfigData[self::KEY_EXTENDS], $sKeyPath) === false)) {
                    // ^^^ Prevents self linking
                    $aParentData = $this->_keyReplace($sKeyPath, $xConfigData[self::KEY_EXTENDS], $nLevel);
                    self::$aQuickMap[$sLinkKey] = $aParentData;
                }
            }
            unset($xConfigData[self::KEY_EXTENDS]);
            if (!empty($aParentData) && is_array($aParentData)) {
                if (!empty($xConfigData[self::KEY_RESET])) {
                    $xConfigData = \F::Array_Merge($aParentData, $xConfigData);
                } else {
                    $xConfigData = \F::Array_MergeCombo($aParentData, $xConfigData);
                }
            }
            $sKeyMap = $this->_storageKey('*') . '.' . $sKeyPath;
            // SET QUICK MAP AND CLEAR KEY EXTENDS
            self::$aQuickMap[$sKeyMap] = $xConfigData;
            //if (isset(self::$aKeyExtends[$sKeyMap])) {
            //    unset(self::$aKeyExtends[$sKeyMap]);
            //}
            $this->_clearKeyExtension($sKeyMap);
        }

        return $xConfigData;
    }

    /**
     * @param string $sKeyLink
     * @param int    $nLevel
     *
     * @return mixed
     */
    protected function _resolveKeyLink($sKeyLink, $nLevel)
    {
        $xResult = $sKeyLink;
        if (preg_match_all(self::KEY_LINK_PREG, $sKeyLink, $aMatch, PREG_SET_ORDER)) {
            if (count($aMatch) === 1 && $aMatch[0][0] === $sKeyLink) {
                $xResult = $this->getValue($aMatch[0][1], $nLevel);
            } else {
                foreach ($aMatch as $aItem) {
                    $sReplacement = $this->getValue($aItem[1]);
                    if ($aItem[2] === '___/' && substr($sReplacement, -1) !== '/' && substr($sReplacement, -1) !== '\\') {
                        $sReplacement .= '/';
                    }
                    $xResult = str_replace(self::KEY_LINK_STR . $aItem[1] . $aItem[2], $sReplacement, $xResult);
                }
            }
        }
        return $xResult;
    }

    /**
     * @param $sKeyMap
     */
    protected function _clearKeyExtension($sKeyMap)
    {
        if (isset(self::$aKeyExtends[$sKeyMap])) {
            self::$aClearedKeyExtensions[$sKeyMap] = self::$aKeyExtends[$sKeyMap];
            unset(self::$aKeyExtends[$sKeyMap]);
        }
    }

    /**
     *
     */
    protected function _restoreKeyExtensions()
    {
        foreach(self::$aClearedKeyExtensions as $sKey => $sVal) {
            if (empty(self::$aKeyExtends)) {
                self::$aKeyExtends[$sKey] = $sVal;
            }
        }
    }

    /**
     * Try to find element by given key
     * Using function ARRAY_KEY_EXISTS (like in SPL)
     *
     * Workaround for http://bugs.php.net/bug.php?id=40442
     *
     * @param string $sKey  - Path to needed value
     *
     * @return bool
     */
    public static function isKeyExists($sKey)
    {
        return static::getInstance()->_isExists($sKey);
    }

    /**
     * Set config value(s)
     *
     * @param array     $aConfigData    Config data array
     * @param bool      $bReset         Reset previous values
     * @param int       $nLevel         Level of config
     * @param string    $sSource        Source of data
     *
     * @return bool
     */
    protected static function _set($aConfigData, $bReset, $nLevel, $sSource)
    {
        if (ALTO_DEBUG) {
            $nTime = microtime(true);
        }

        if (!empty($aConfigData)) {
            // Check for KEY_ROOT in config data
            if (!empty($aConfigData[self::KEY_ROOT]) && is_array($aConfigData[self::KEY_ROOT])) {
                /** @var array $aRootConfig */
                $aRootConfig = $aConfigData[self::KEY_ROOT];
                foreach ($aRootConfig as $sRootConfigKey => $xRootConfigVal) {
                    static::_set([$sRootConfigKey => $xRootConfigVal], $bReset, $nLevel, $sSource);
                }
                unset($aConfigData[self::KEY_ROOT]);
            }

            if ($aConfigData) {
                /** @var Config $oConfig */
                $oConfig = static::getInstance();

                // Check for KEY_REPLACE in config data
                $aClearConfig = self::_extractForReplacement($aConfigData, $oConfig->_storageKey('*'));
                if ($aClearConfig) {
                    $oConfig->_setConfigByLevel($nLevel, $aClearConfig, false, $sSource);
                }

                $oConfig->_setConfigByLevel($nLevel, $aConfigData, $bReset, $sSource);
            }
        }
        if (ALTO_DEBUG) {
            self::$aElapsedTime['set'] += (microtime(true) - $nTime);
        }

        return true;
    }

    static protected $bKeyReplace = false;
    static protected $aKeyExtends = [];
    static protected $aClearedKeyExtensions = [];

    public static function _checkForReplacement(&$xItem, $xKey)
    {
        if (!self::$bKeyReplace) {
            self::$bKeyReplace = ($xKey === self::KEY_REPLACE || $xKey === self::KEY_EXTENDS);
        }
    }

    /**
     * Filters config array and extract structure data for replacement
     *
     * @param array  $aConfig
     * @param string $sParentKey
     *
     * @return array|bool
     */
    protected static function _extractForReplacement(&$aConfig, $sParentKey)
    {
            self::$bKeyReplace = false;
            array_walk_recursive($aConfig, __NAMESPACE__ . '\Config::_checkForReplacement');

            if (!self::$bKeyReplace) {
                // Has no KEY_REPLACE in data
                return [];
            }

        return self::_extractForReplacementData($aConfig, 0, $sParentKey);
    }

    /**
     * Filters array and extract structure data for replacement
     *
     * @param array  $aConfig
     * @param int    $iDataLevel
     * @param string $sParentKey
     *
     * @return array|bool
     */
    protected static function _extractForReplacementData(&$aConfig, $iDataLevel = 0, $sParentKey = null)
    {
        $aResult = [];

        if ($iDataLevel) {
            // KEY_REPLACE on this level
            if (isset($aConfig[self::KEY_REPLACE])) {
                if (is_array($aConfig[self::KEY_REPLACE])) {
                    unset($aConfig[self::KEY_REPLACE]);
                    $aResult = array_fill_keys($aConfig[self::KEY_REPLACE], null);
                } else {
                    //unset($aConfig[self::KEY_REPLACE]);
                    //$aResult = true;
                }
                //return $aResult;
            }
            if (isset($aConfig[self::KEY_EXTENDS]) && is_string($aConfig[self::KEY_EXTENDS])) {
                self::$aKeyExtends[$sParentKey] = $aConfig[self::KEY_EXTENDS];
            }
        }

        // KEY_REPLACE on deeper levels
        foreach($aConfig as $xKey => &$xVal) {
            if(is_array($xVal)) {
                $xSubResult = self::_extractForReplacementData($xVal, ++$iDataLevel, $sParentKey . '.' . $xKey);
                if ($xSubResult === true) {
                    $aResult[$xKey] = null;
                } elseif (!empty($xSubResult)) {
                    $aResult[$xKey] = (array)$xSubResult;
                }
            }
        }
        return $aResult;
    }

    /**
     * Find all keys recursively in config array
     *
     * @return array
     */
    public function getKeys()
    {
        $aConfig = $this->_getConfigByLevel();
        // If it`s not array, return key
        if (!is_array($aConfig) || !count($aConfig)) {
            return [];
        }
        // If it`s array, get array_keys recursive
        return \F::Array_KeysRecursive($aConfig);
    }

    /**
     * Write config data to storage and cache
     *
     * @param string $sPrefix
     * @param array  $aConfig
     * @param bool   $bCacheOnly
     * @param int    $iOrder
     *
     * @return  bool
     */
    protected static function _writeConfig($sPrefix, $aConfig, $bCacheOnly = false, $iOrder = 1)
    {
        if ($sPrefix && substr($sPrefix, -1) !== '.') {
            $sPrefix .= '.';
        }
        $aData = [];
        foreach ($aConfig as $sKey => $sVal) {
            $aData[] = array(
                'storage_key' => $sPrefix . $sKey,
                'storage_val' => serialize($sVal),
                'storage_ord' => $iOrder
            );
        }
        if (\E::Module('Admin')->updateStorageConfig($aData)) {
            //self::_putFileCfg($aConfig);
            self::_deleteFileCfg();
            self::_reReadConfig();
            return true;
        }
        return false;
    }

    /**
     * @param array  $aData
     * @param string $sPrefix
     *
     * @return array
     */
    protected static function _explodeData($aData, $sPrefix = null)
    {
        if (count($aData) === 1 && isset($aData[$sPrefix]) && $aData[$sPrefix]['storage_key'] === $sPrefix) {
            // single value
            $xVal = @unserialize($aData[$sPrefix]['storage_val']);
            return $xVal;
        }
        $aResult = new DataArray();
        if ($sPrefix) {
            $aPrefix = array(
                $sPrefix => strlen($sPrefix),
            );
        } else {
            $aPrefix = array(
                self::ENGINE_CONFIG_PREFIX => 0,
                self::CUSTOM_CONFIG_PREFIX => strlen(self::CUSTOM_CONFIG_PREFIX),
            );
        }
        $aExpData = array_fill_keys(array_keys($aPrefix), []);

        foreach ($aData as $aRow) {
            foreach($aPrefix as $sPrefixKey => $iPrefixLen) {
                if (strpos($aRow['storage_key'], $sPrefixKey) === 0) {
                    if ($iPrefixLen) {
                        $sKey = trim(substr($aRow['storage_key'], $iPrefixLen), '.');
                    } else {
                        $sKey = $aRow['storage_key'];
                    }
                    $xVal = @unserialize($aRow['storage_val']);
                    $aExpData[$sPrefixKey][$sKey] = $xVal;
                }
            }
        }
        if ($aExpData) {
            foreach($aExpData as $aDataValues) {
                $aResult->merge($aDataValues);
            }
        }
        return $aResult->getArrayCopy();
    }

    /**
     * @param string  $sPrefix
     * @param string  $sConfigKeyPrefix
     * @param bool    $bCacheOnly
     * @param bool    $bRaw
     *
     * @return array
     */
    protected static function _readConfig($sPrefix, $sConfigKeyPrefix = null, $bCacheOnly = false, $bRaw = false)
    {
        if ($sPrefix && substr($sPrefix, -1) !== '.') {
            $sPrefix .= '.';
        }
        $aConfig = [];
        if (!$bRaw && self::_checkFileCfg(!$bCacheOnly)) {
            $aConfig = self::_getFileCfg();
        }
        if (!$aConfig) {
            if (!$bCacheOnly && class_exists('E', false)) {
                // Reread config from db
                $sPrefix .= $sConfigKeyPrefix;
                $aData = \E::Module('Admin')->getStorageConfig($sPrefix);
                $aConfig = self::_explodeData($aData, $sPrefix);
                if ($bRaw) {
                    return $aConfig;
                }
                if (isset($aConfig['plugin'])) {
                    $aConfigPlugins = array_keys($aConfig['plugin']);
                    $aActivePlugins = \F::getPluginsList(false, true);
                    if (!$aActivePlugins) {
                        unset($aConfig['plugin']);
                    } else {
                        $bRootConfig = false;
                        foreach($aConfigPlugins as $sPlugin) {
                            if (!in_array($sPlugin, $aActivePlugins, true)) {
                                unset($aConfig['plugin'][$sPlugin]);
                            } else {
                                if (isset($aConfig['plugin'][$sPlugin][self::KEY_ROOT])) {
                                    $bRootConfig = true;
                                }
                            }
                        }
                        if (empty($aConfig['plugin'])) {
                            unset($aConfig['plugin']);
                        }
                        // Need to prepare config data
                        if ($bRootConfig) {
                            $aConfigResult = [];
                            foreach($aConfig as $sKey => $xVal) {
                                if ($sKey === 'plugin') {
                                    // sort plugin config by order of active pligin list
                                    foreach($aActivePlugins as $sPluginId) {
                                        if (isset($aConfig['plugin'][$sPluginId])) {
                                            $aPluginConfig = $aConfig['plugin'][$sPluginId];
                                            if (isset($aPluginConfig[self::KEY_ROOT])) {
                                                if (is_array($aPluginConfig[self::KEY_ROOT])) {
                                                    foreach($aPluginConfig[self::KEY_ROOT] as $sRootKey => $xRootVal) {
                                                        if (isset($aConfigResult[$sRootKey])) {
                                                            $aConfigResult[$sRootKey] = \F::Array_MergeCombo($aConfigResult[$sRootKey], $xRootVal);
                                                        } else {
                                                            $aConfigResult[$sRootKey] = $xRootVal;
                                                        }
                                                    }
                                                }
                                                unset($aPluginConfig[self::KEY_ROOT]);
                                            }
                                            if (!empty($aPluginConfig)) {
                                                $aConfigResult['plugin'][$sPluginId] = $aPluginConfig;
                                            }
                                        }
                                    }

                                } else {
                                    $aConfigResult[$sKey] = $xVal;
                                }
                            }
                            $aConfig = $aConfigResult;
                        } // $bRootConfig
                    }
                }
                // Признак того, что кеш конфига синхронизирован с базой
                $aConfig['_db_'] = time();
                self::_putFileCfg($aConfig);
            } else {
                // Признак того, что кеш конфига НЕ синхронизиован с базой
                $aConfig['_db_'] = false;
            }
        } elseif ($sConfigKeyPrefix) {
            $aData = new DataArray($aConfig);
            if ($sPrefix === self::ENGINE_CONFIG_PREFIX) {
                $sConfigKeyPrefix = $sPrefix . $sConfigKeyPrefix;
            }
            return $aData[$sConfigKeyPrefix];
        }
        return $aConfig;
    }

    /**
     * @return array
     */
    protected static function _reReadConfig()
    {
        return self::_readConfig(null, null, false);
    }

    /**
     * @param string $sPrefix
     * @param string|null $sConfigKey
     */
    protected static function _resetConfig($sPrefix, $sConfigKey = null)
    {
        if ($sPrefix && substr($sPrefix, -1) !== '.') {
            $sPrefix .= '.';
        }
        $sPrefix .= $sConfigKey;
        // удаляем настройки конфига из базы
        \E::Module('Admin')->deleteStorageConfig($sPrefix);
        // удаляем кеш-файл
        self::_deleteFileCfg();
        // перестраиваем конфиг в кеш-файле
        self::_reReadConfig();
    }

    /**
     * @param string|null $sConfigKey
     * @param bool        $bCacheOnly
     *
     * @return array
     */
    public static function readStorageConfig($sConfigKey = null, $bCacheOnly = false)
    {
        return self::_readConfig('', $sConfigKey, $bCacheOnly);
    }

    /**
     * @return array
     */
    public static function reReadStorageConfig()
    {
        return self::_readConfig('', null, false);
    }

    /**
     * Записывает кастомную конфигурацию
     *
     * @param array $aConfig
     * @param bool  $bCacheOnly
     *
     * @return  bool
     */
    public static function writeCustomConfig($aConfig, $bCacheOnly = false)
    {
        return self::_writeConfig(self::CUSTOM_CONFIG_PREFIX, $aConfig, $bCacheOnly);
    }

    /**
     * @param string|null $sConfigKey
     * @param bool        $bCacheOnly
     *
     * @return array
     */
    public static function readCustomConfig($sConfigKey = null, $bCacheOnly = false)
    {
        return self::_readConfig(self::CUSTOM_CONFIG_PREFIX, $sConfigKey, $bCacheOnly);
    }

    /**
     * @return array
     */
    public static function reReadCustomConfig()
    {
        return self::_readConfig(self::CUSTOM_CONFIG_PREFIX, null, false);
    }

    /**
     * @param string|null $sConfigKey
     */
    public static function resetCustomConfig($sConfigKey = null)
    {
        self::_resetConfig(self::CUSTOM_CONFIG_PREFIX, $sConfigKey);
    }

    /**
     * Write plugin's configuration
     *
     * @param string $sPluginId
     * @param array  $aConfig
     * @param bool   $bCacheOnly
     *
     * @return  bool
     */
    public static function writePluginConfig($sPluginId, $aConfig, $bCacheOnly = false)
    {
        if (strpos($sPluginId, 'plugin.') === 0) {
            $sPluginKey = $sPluginId;
        } else {
            $sPluginKey = 'plugin.' . $sPluginId;
        }

        if (!is_array($aConfig) || empty($aConfig)) {
            $aSaveConfig = [$sPluginKey => $aConfig];
        } else {
            $aSaveConfig = [];
            foreach($aConfig as $sKey => $xVal) {
                $aSaveConfig[$sPluginKey . '.' . $sKey] = $xVal;
            }
        }
        return self::_writeConfig(self::CUSTOM_CONFIG_PREFIX, $aSaveConfig, $bCacheOnly);
    }

    /**
     * Read plugin's config
     *
     * @param string      $sPluginId
     * @param string|null $sConfigKey
     * @param bool        $bCacheOnly
     *
     * @return array
     */
    public static function readPluginConfig($sPluginId, $sConfigKey = null, $bCacheOnly = false)
    {
        if (strpos($sPluginId, 'plugin.') === 0) {
            $sPluginKey = $sPluginId;
        } else {
            $sPluginKey = 'plugin.' . $sPluginId;
        }

        if ($sConfigKey) {
            $sConfigKey = $sPluginKey . '.' . $sConfigKey;
        } else {
            $sConfigKey = $sPluginKey;
        }
        return self::_readConfig(self::CUSTOM_CONFIG_PREFIX, $sConfigKey, $bCacheOnly, true);
    }

    /**
     * Reset plugin's config
     *
     * @param string      $sPluginId
     * @param string|null $sConfigKey
     */
    public static function resetPluginConfig($sPluginId, $sConfigKey = null)
    {
        if (strpos($sPluginId, 'plugin.') === 0) {
            $sPluginKey = $sPluginId;
        } else {
            $sPluginKey = 'plugin.' . $sPluginId;
        }

        if ($sConfigKey) {
            $sConfigKey = $sPluginKey . '.' . $sConfigKey;
        } else {
            $sConfigKey = $sPluginKey;
        }
        self::_resetConfig(self::CUSTOM_CONFIG_PREFIX, $sConfigKey);
    }

    /**
     * @param array  $aConfig
     * @param bool   $bCacheOnly
     *
     * @return  bool
     */
    public static function writeEngineConfig($aConfig, $bCacheOnly = false)
    {
        if (!empty($aConfig) && is_array($aConfig)) {
            $aSaveConfig = [];
            foreach($aConfig as $sKey => $xVal) {
                if (is_string($sKey) && !is_numeric($sKey)) {
                    if (strpos($sKey, self::ENGINE_CONFIG_PREFIX) === 0) {
                        $sKey = substr($sKey, strlen(self::ENGINE_CONFIG_PREFIX));
                    }
                    $aSaveConfig[$sKey] = $xVal;
                }
            }
            return self::_writeConfig(self::ENGINE_CONFIG_PREFIX, $aSaveConfig, $bCacheOnly);
        }
        return false;
    }

    /**
     * @param string|null $sConfigKey
     * @param bool        $bCacheOnly
     *
     * @return array
     */
    public static function readEngineConfig($sConfigKey = null, $bCacheOnly = false)
    {
        return self::_readConfig(self::ENGINE_CONFIG_PREFIX, $sConfigKey, $bCacheOnly);
    }

    /**
     * Reset plugin's config
     *
     * @param string|null $sConfigKey
     */
    public static function resetEngineConfig($sConfigKey = null)
    {
        self::_resetConfig(self::ENGINE_CONFIG_PREFIX, $sConfigKey);
    }

    /**
     * Invalidate cache of custom configuration
     */
    public static function invalidateCachedConfig()
    {
        // удаляем кеш-файл
        self::_deleteFileCfg();
    }

    /**
     * Возвращает полный путь к кеш-файлу кастомной конфигуации
     * или просто проверяет его наличие
     *
     * @param bool $bCheckOnly
     *
     * @return  string
     */
    protected static function _checkFileCfg($bCheckOnly = false)
    {
        $sFile = self::get('sys.cache.dir') . 'data/custom.cfg';
        if ($bCheckOnly) {
            return \F::File_Exists($sFile);
        }
        return $sFile;
    }

    /**
     * Удаляет кеш-файл кастомной конфигуации
     *
     */
    protected static function _deleteFileCfg()
    {
        $sFile = self::_checkFileCfg(true);
        if ($sFile) {
            \F::File_Delete($sFile);
        }
    }

    /**
     * Сохраняет в файловом кеше кастомную конфигурацию
     *
     * @param array $aConfig
     * @param bool  $bReset
     */
    protected static function _putFileCfg($aConfig, $bReset = false)
    {
        if (is_array($aConfig) && ($sFile = self::_checkFileCfg())) {
            if (!$bReset) {
                // Объединяем текущую конфигурацию с сохраняемой
                $aOldConfig = self::_getFileCfg();
                if ($aOldConfig) {
                    $aData = new DataArray($aOldConfig);
                    foreach($aConfig as $sKey => $xVal) {
                        $aData[$sKey] = $xVal;
                    }
                    $aConfig = $aData->getArrayCopy();
                }
            }
            $aConfig['_timestamp_'] = time();
            $aConfig['_alto_hash_'] = self::_getHash();
            \F::File_PutContents($sFile, \F::Serialize($aConfig), LOCK_EX);
        }
    }

    /**
     * Читает из файлового кеша кастомную конфигурацию
     *
     * @return array
     */
    protected static function _getFileCfg()
    {
        if (($sFile = self::_checkFileCfg()) && ($sData = \F::File_GetContents($sFile))) {
            $aConfig = \F::Unserialize($sData);
            if (is_array($aConfig) && isset($aConfig['_alto_hash_']) && $aConfig['_alto_hash_'] === self::_getHash()) {
                return $aConfig;
            }
        }
        return [];
    }

    /**
     * @return string
     */
    protected static function _getHash()
    {
        return md5(ALTO_VERSION . serialize(\F::getPluginsList(false, true)));
    }

}

// EOF