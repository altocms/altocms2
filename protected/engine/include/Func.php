<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Static class of engine functions
 */
class Func
{
    const ERROR_LOGFILE = 'error.log';

    const ERROR_LOG_EXTINFO = 1;
    const ERROR_LOG_CALLSTACK = 2;

    static protected $nFatalErrors;

    static protected $aExtensions = [];

    /**
     * Errors for logging
     *
     * @var int
     */
    static protected $nErrorTypes = E_ALL;

    /**
     * Errors for display
     *
     * @var int
     */
    static protected $nErrorDisplay = E_ALL;

    static protected $aErrorCollection = [];

    /**
     * Init function
     */
    public static function init()
    {
        static::$nFatalErrors = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING;
        static::_loadExtension('Main');
        static::_loadExtension('File');
        if (!defined('ALTO_INSTALL')) {
            register_shutdown_function('F::done');
            set_error_handler('F::_errorHandler');
            set_exception_handler('F::_exceptionHandler');

            if (ini_get('display_errors')) {
                self::$nErrorDisplay = error_reporting();
            } else {
                self::$nErrorDisplay = 0;
            }
        }
    }

    /**
     * Shutdown function
     */
    public static function done()
    {
        if ($aError = error_get_last()) {
            // Other errors catchs by error handler
            if ($aError['type'] & static::$nFatalErrors) {
                static::_errorHandler($aError['type'], $aError['message'], $aError['file'], $aError['line']);
            }
        }
    }

    /**
     * @return string
     */
    public static function _errorLogFile()
    {
        return static::_getConfig('sys.logs.error_file', static::ERROR_LOGFILE);
    }

    /**
     * @return bool
     */
    public static function _errorLogExtInfo()
    {
        $nExtInfo = 0;
        if ((bool)static::_getConfig('sys.logs.error_extinfo', true)) {
            $nExtInfo += self::ERROR_LOG_EXTINFO;
        }
        if ((bool)static::_getConfig('sys.logs.error_callstack', true)) {
            $nExtInfo += self::ERROR_LOG_CALLSTACK;
        }
        return $nExtInfo;
    }

    /**
     * @return bool
     */
    public static function _errorLogNoRepeat()
    {
        return (bool)static::_getConfig('sys.logs.error_norepeat', true);
    }

    /**
     * @param $sError
     * @param $aLogTrace
     */
    public static function _errorLog($sError, $aLogTrace = null)
    {
        $sError = mb_convert_encoding($sError, 'UTF-8', 'auto');
        $sText = $sError;
        $nErrorExtInfo = static::_errorLogExtInfo();
        if ($nErrorExtInfo) {
            $sText .= "\n";
            if (($nErrorExtInfo & self::ERROR_LOG_EXTINFO) && isset($_SERVER) && is_array($_SERVER)) {
                $sText .= "--- server vars ---\n";
                foreach ($_SERVER as $sKey => $sVal) {
                    if (!in_array($sKey, ['PATH', 'SystemRoot', 'COMSPEC', 'PATHEXT', 'WINDIR'], true)) {
                        $sText .= "  _SERVER['$sKey']=";
                        if (is_scalar($sVal)) {
                            $sText .= $sVal;
                        } else {
                            $sText .= gettype($sVal);
                            if (is_array($sVal)) {
                                $sText .= '(';
                                $nCnt = 0;
                                foreach ($sVal as $xIdx => $sItem) {
                                    if ($nCnt++) {
                                        $sText .= ',';
                                    }
                                    if (is_scalar($sItem)) {
                                        $sText .= $xIdx . '=>' . $sItem;
                                    } else {
                                        $sText .= $xIdx . '=>' . gettype($sItem) . '()';
                                    }
                                }
                                $sText .= ')';
                            } else {
                                $sText .= '()';
                            }
                        }
                        $sText .= "\n";
                    }
                }
            }

            if (($nErrorExtInfo & self::ERROR_LOG_CALLSTACK) && ($aCallStack = ($aLogTrace ? $aLogTrace : static::_callStackError()))) {
                $sText .= "--- call stack ---\n";
                foreach ($aCallStack as $aCaller) {
                    $sText .= static::_callerToString($aCaller) . "\n";
                }
            }

            $sText .= "--- end ---\n";
        }

        if (!static::_log($sText, static::_errorLogFile(), 'ERROR')) {
            // Если не получилось вывести в лог-файл, то выводим ошибку на экран
            echo $sError;
        }
    }

    /**
     * @param string $sText
     * @param string $sLogFile
     * @param string $sLevel
     *
     * @return bool
     */
    public static function _log($sText, $sLogFile, $sLevel = null)
    {
        if (class_exists('ModuleLogger', false) || (class_exists('Loader', false) && Loader::autoload('ModuleLogger'))) {
            // Если загружен модуль Logger, то логгируем ошибку с его помощью
            return \E::Module('Logger')->Dump($sLogFile, $sText, $sLevel);
        }
        if (class_exists('Config', false)) {
            // Если логгера нет, но есть конфиг, то самостоятельно пишем в файл
            $sFile = Config::get('sys.logs.dir') . $sLogFile;
            if (!$sFile) {
                // Непонятно, куда писать
            } else {
                $sText = '[' . date('Y-m-d H:i:s') . ']' . "\n" . $sText;
                return F::File_PutContents($sFile, $sText, FILE_APPEND | LOCK_EX);
            }
        }
        return false;
    }

    /**
     * @param string $sError
     * @param string $sLogFile
     */
    public static function _errorDisplay($sError, $sLogFile = null)
    {
        if (!static::ajaxRequest()) {
            echo '<span style="display:none;">"></span><br/></div>' . $sError . '<br/>';
            if ($sLogFile) {
                echo 'See details in ' . $sLogFile;
            }
        }
    }

    /**
     * Push error info into internal collection
     *
     * @param int    $nErrNo
     * @param string $sErrMsg
     * @param string $sErrFile
     * @param int    $nErrLine
     *
     * @return bool
     */
    protected static function _pushError($nErrNo, $sErrMsg, $sErrFile, $nErrLine)
    {
        $aError = array(
            'err_no' => $nErrNo,
            'err_msg' => $sErrMsg,
            'err_file' => $sErrFile,
            'err_line' => $nErrLine,
        );
        $sKey = md5(serialize($aError));
        if (!isset(self::$aErrorCollection[$sKey])) {
            self::$aErrorCollection[$sKey] = $aError;
            return true;
        }
        return false;
    }

    /**
     * Returns info of last error
     *
     * @return array|null
     */
    protected static function _getLastError()
    {
        if (self::$aErrorCollection) {
            return end(self::$aErrorCollection);
        }
        return null;
    }

    /**
     * @param int    $nErrNo
     * @param string $sErrMsg
     * @param string $sErrFile
     * @param int    $nErrLine
     *
     * @return bool
     */
    public static function _errorHandler($nErrNo, $sErrMsg, $sErrFile, $nErrLine)
    {
        $aErrors = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        );

        if (!static::_errorLogNoRepeat() || static::_pushError($nErrNo, $sErrMsg, $sErrFile, $nErrLine)) {
            if (($nErrNo & self::$nErrorDisplay) && ($nErrNo & error_reporting())) {
                if (\F::isDebug()) {
                    static::_errorDisplay("{$aErrors[$nErrNo]} [$nErrNo] $sErrMsg ($sErrFile on line $nErrLine)");
                } else {
                    static::_errorDisplay("{$aErrors[$nErrNo]} [$nErrNo] $sErrMsg", static::_errorLogFile());
                }
            }
            if ($nErrNo & self::$nErrorTypes) {
                static::_errorLog("{$aErrors[$nErrNo]} [$nErrNo] $sErrMsg ($sErrFile on line $nErrLine)");
            }
        }

        /* Don't execute PHP internal error handler */
        return true;
    }

    /**
     * @param Exception|Throwable $oException
     */
    public static function _exceptionHandler($oException)
    {
        static::_errorDisplay('Exception: ' . $oException->getMessage());
        $sLogMsg = 'Exception: ' . $oException->getMessage();
        if (property_exists($oException, 'sAdditionalInfo')) {
            $sLogMsg .= "\n" . $oException->sAdditionalInfo;
        } elseif ($oException instanceof SmartyException) {
            $aTrace = $oException->getTrace();
            $aTemplateStack = [];
            foreach ($aTrace as $aCaller) {
                if (isset($aCaller['args']) && count($aCaller['args'])) {
                    foreach((array)$aCaller['args'] as $oTpl) {
                        if(is_object($oTpl) && $oTpl instanceof Smarty_Internal_Template) {
                            $aTemplateStack = self::_getSmartyTemplateStack($oTpl);
                            break 2;
                        }
                    }
                }
            }
            $sLogMsg .= "\nTemplates stack:\n" . implode("\n", $aTemplateStack);
        } else {
            $aLogTrace = $oException->getTrace();
            if (is_array($aLogTrace) && $oException->getFile() && $oException->getLine()) {
                array_unshift($aLogTrace, array('file' => $oException->getFile(), 'line' => $oException->getLine()));
            }
        }
        static::_errorLog($sLogMsg, !empty($aLogTrace) ? $aLogTrace : null);
    }

    /**
     * @param Smarty_Internal_Template $oTpl
     *
     * @return array
     */
    protected static function _getSmartyTemplateStack($oTpl)
    {
        $aTemplateStack = [];
        while ($oTpl) {
            if ((null === $oTpl->template_resource) || !($sTemplate = $oTpl->template_resource)) {
                break;
            }
            if ((null !== $oTpl->source) && $oTpl->source->filepath && $oTpl->source->filepath != $sTemplate) {
                $sTemplate .= ' (' . $oTpl->source->filepath . ')';
            }
            $aTemplateStack[] = $sTemplate;
            $oTpl = $oTpl->parent;
        }
        return $aTemplateStack;
    }

    /**
     * @param string $sName
     * @param array  $aArgs
     *
     * @return mixed
     */
    public static function __callStatic($sName, $aArgs)
    {
        if ($nPos = strpos($sName, '_')) {
            $sExtension = substr($sName, 0, $nPos);
            $sMethod = substr($sName, $nPos + 1);
        } else {
            $sExtension = 'Main';
            $sMethod = $sName;
        }
        if (!isset(static::$aExtensions[$sExtension])) {
            static::_loadExtension($sExtension);
        }
        if (isset(static::$aExtensions[$sExtension]) && method_exists(static::$aExtensions[$sExtension], $sMethod)) {
            $sClass = static::$aExtensions[$sExtension];
            switch (count($aArgs)) {
                case 0:
                    $xResult = $sClass::$sMethod();
                    break;
                case 1:
                    $xResult = $sClass::$sMethod($aArgs[0]);
                    break;
                case 2:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1]);
                    break;
                case 3:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2]);
                    break;
                case 4:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3]);
                    break;
                case 5:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4]);
                    break;
                case 6:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5]);
                    break;
                case 7:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6]);
                    break;
                case 8:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6], $aArgs[7]);
                    break;
                case 9:
                    $xResult = $sClass::$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6], $aArgs[7], $aArgs[8]);
                    break;
                default:
                    $xResult = call_user_func_array($sClass . '::' . $sMethod, $aArgs);
            }
            return $xResult;
        }

        // Function not found
        $aCaller = static::_getCaller(2);
        $sCallerStr = 'Func::' . $sName . '()';
        $sPosition = '';
        if ($aCaller) {
            if (isset($aCaller['class'], $aCaller['function'], $aCaller['type'])) {
                $sCallerStr = $aCaller['class'] . $aCaller['type'] . $aCaller['function'] . '()';
            }
            if (isset($aCaller['file'], $aCaller['line'])) {
                $sPosition = ' in ' . $aCaller['file'] . ' on line ' . $aCaller['line'];
            }
        }
        $sMsg = 'Call to undefined method ' . $sCallerStr . $sPosition;
        static::_fatalError($sMsg);
    }

    /**
     * Loads func extension
     *
     * @param string $sExtension
     */
    protected static function _loadExtension($sExtension)
    {
        if (!isset(static::$aExtensions[$sExtension])) {
            // сначала проверяем кастомные функции
            if (is_file($sFile = dirname(dirname(__DIR__)) . '/include/functions/' . $sExtension . '.php')) {
                static::includeFile($sFile);
                static::$aExtensions[$sExtension] = 'AppFunc_' . $sExtension;
            } elseif (is_file($sFile = __DIR__ . '/functions/' . $sExtension . '.php')) {
                static::includeFile($sFile);
                static::$aExtensions[$sExtension] = 'AltoFunc_' . $sExtension;
            } else {
                static::_fatalError('Cannot found functions set (extension) "' . $sExtension . '"');
            }
        }
    }

    public static function _getExtensions()
    {
        return static::$aExtensions;
    }

    /**
     * TODO: Не выводить полностью текст ошибки на экран, а логгировать
     *
     * @param string $sMessage
     * @throws Exception
     */
    protected static function _fatalError($sMessage)
    {
        echo '<p>Fatal error: ' . $sMessage . '</p>';
        throw new Exception('Fatal error');
    }

    /**
     * Returns caller (class/function) using call stack
     *
     * @param int  $nOffset
     * @param bool $bString
     *
     * @return array|string
     */
    protected static function _getCaller($nOffset = 1, $bString = false)
    {
        $aData = static::_callStack($nOffset + 1, 1);
        if (count($aData)) {
            if ($bString) {
                return static::_callerToString(reset($aData));
            }
            return reset($aData);
        }
        return null;
    }

    /**
     * Format caller to string
     *
     * @param array $aCaller
     *
     * @return string
     */
    protected static function _callerToString($aCaller)
    {
        $sResult = 'undefined';
        if ($aCaller && is_array($aCaller)) {
            $sCallerStr = '';
            $sPosition = '';
            if (isset($aCaller['class'], $aCaller['function'], $aCaller['type'])) {
                $sCallerStr = $aCaller['class'] . $aCaller['type'] . $aCaller['function'] . '()';
            } elseif (isset($aCaller['function'])) {
                $sCallerStr = $aCaller['function'] . '()';
            }
            if (isset($aCaller['file'], $aCaller['line'])) {
                $sPosition = ' in ' . $aCaller['file'] . ' on line ' . $aCaller['line'];
            }
            $sResult = $sCallerStr . $sPosition;
        } elseif ($aCaller) {
            $sResult = (string)$aCaller;
        }
        return $sResult;
    }

    /**
     * Returns call stack or part of them
     *
     * @param int  $nOffset
     * @param int  $nLength
     * @param bool $bCheckException
     *
     * @return array
     */
    protected static function _callStack($nOffset = 1, $nLength = null, $bCheckException = true)
    {
        if (function_exists('xdebug_get_function_stack')) {
            $aStack = array_reverse(xdebug_get_function_stack());
        } else {
            $aStack = debug_backtrace(false);
        }

        //$aStack = array_slice(debug_backtrace(false), $nOffset, $nLength);
        $aStack = array_slice($aStack, $nOffset, $nLength);
        // if exception then gets trace from it
        if ($bCheckException) {
            $aLastCaller = end($aStack);
            if (isset($aLastCaller['args'][0]) && is_object($aLastCaller['args'][0]) && $aLastCaller['args'][0] instanceof Exception) {
                $aStack = $aLastCaller['args'][0]->getTrace();
            }
        }
        return $aStack;
    }

    /**
     * Returns real call stack in error point
     *
     * @return array
     */
    protected static function _callStackError()
    {
        $aStack = static::_callStack();
        $aLastError = static::_getLastError();
        if ($aLastError && ($aLastError['err_no'] & ~static::$nFatalErrors)) {
            foreach ($aStack as $nI => $aCaller) {
                // find point of error
                if ((isset($aCaller['args'][0]) && is_numeric($aCaller['args'][0]) && $aCaller['args'][0] == $aLastError['err_no'])
                    && (isset($aCaller['args'][1]) && $aCaller['args'][1] == $aLastError['err_msg'])
                    && (isset($aCaller['args'][2]) && $aCaller['args'][2] == $aLastError['err_file'])
                    && (isset($aCaller['args'][3]) && $aCaller['args'][3] == $aLastError['err_line'])
                ) {
                    if (count($aStack) > $nI + 1) {
                        $aStack = array_slice($aStack, $nI + 1);
                        if (!isset($aStack[0]['file'])) {
                            $aStack[0]['file'] = $aLastError['err_file'];
                        }
                        if (!isset($aStack[0]['line'])) {
                            $aStack[0]['line'] = $aLastError['err_line'];
                        }
                    } else {
                        $aStack = [];
                    }
                    break;
                }
            }
        }
        return $aStack;
    }

    /**
     * @param string $sParam
     * @param mixed  $xDefault
     *
     * @return mixed
     */
    public static function _getConfig($sParam, $xDefault = null)
    {
        if (class_exists('Config', false)) {
            $xResult = Config::get($sParam);
        } else {
            $xResult = $xDefault;
        }
        return $xResult;
    }

    /**
     * Set error types for handler function
     *
     * @param int|null $nErrorTypes
     * @param bool     $bSystem
     *
     * @return int
     */
    public static function errorReporting($nErrorTypes = null, $bSystem = false)
    {
        if (is_bool($nErrorTypes) && func_num_args() === 1) {
            $bSystem  = $nErrorTypes;
            $nErrorTypes = null;
        }
        if ($bSystem) {
            if (is_int($nErrorTypes)) {
                $nResult = error_reporting($nErrorTypes);
                self::$nErrorTypes = $nErrorTypes;
            } else {
                $nResult = error_reporting();
            }
        } else {
            $nResult = self::$nErrorTypes;
            if (is_int($nErrorTypes)) {
                self::$nErrorTypes = $nErrorTypes;
            }
        }
        return $nResult;
    }

    /**
     * Set ignored error types for handler function
     *
     * @param int       $nErrorTypes
     * @param bool|null $bSystem
     *
     * @return int
     */
    public static function errorIgnored($nErrorTypes, $bSystem = false)
    {
        $nOldErrorTypes = static::errorReporting(null, $bSystem);
        static::errorReporting($nOldErrorTypes & ~$nErrorTypes, $bSystem);
        return $nOldErrorTypes;
    }

    /**
     * Sets error types for display
     *
     * @param int $nErrorTypes
     *
     * @return int
     */
    public static function setErrorDisplay($nErrorTypes)
    {
        $nOldErrorTypes = static::$nErrorDisplay;
        static::$nErrorDisplay = $nErrorTypes;
        return $nOldErrorTypes;
    }

    /**
     * Sets error types which can not be displayed
     *
     * @param int $nErrorTypes
     *
     * @return int
     */
    public static function setErrorNoDisplay($nErrorTypes)
    {
        $nOldErrorTypes = static::$nErrorDisplay;
        static::$nErrorDisplay &= ~$nErrorTypes;
        return $nOldErrorTypes;
    }

    /**
     * System warning message
     *
     * @param string $sMessage
     */
    public static function sysWarning($sMessage)
    {
        $aCaller = static::_getCaller();
        $nErrorReporting = F::setErrorNoDisplay(E_USER_WARNING);
        self::_errorHandler(
            E_USER_WARNING,
            $sMessage,
            isset($aCaller['file']) ? $aCaller['file'] : 'Unknown',
            isset($aCaller['line']) ? $aCaller['line'] : 0
        );
        F::errorReporting($nErrorReporting);
    }

    /**
     * @return bool
     */
    public static function isDebug()
    {
        return defined('ALTO_DEBUG') && ALTO_DEBUG;
    }

    /**
     * @param string $sMsg
     *
     * @return bool
     */
    public static function logError($sMsg)
    {
        return static::_log($sMsg, static::_errorLogFile(), 'ERROR');
    }

    /**
     * Includes PHP-file with statistics
     *
     * @param string $sFile - file name and path
     * @param bool $bOnce - once include
     * @param bool $bConfig - include as config-file
     *
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function includeFile($sFile, $bOnce = true, $bConfig = false)
    {
        $sDir = dirname($sFile);
        $sRealPath = null;
        if ($sDir === '.' || $sDir === '..' || 0 === strpos($sDir, './') || 0 === strpos($sDir, '../')) {
            $aCaller = static::_getCaller();
            if (isset($aCaller['file'])) {
                $sRealPath = realpath(dirname($aCaller['file']) . '/' . $sFile);
                if (!$sRealPath) {
                    throw new \RuntimeException('Real path for "' . $sFile . '" not found');
                }
            }
        }
        if (!$sRealPath) {
            $sRealPath = realpath($sFile);
        }
        if ($sRealPath) {
            $sFile = $sRealPath;
        }
        if (isset(static::$aExtensions['File']) && is_callable($sFunc = static::$aExtensions['File'] . '::IncludeFile')) {
            $sFuncClass = static::$aExtensions['File'];
            return $sFuncClass::IncludeFile($sFile, $bOnce, $bConfig);
        }

        if ($bOnce) {
            return include_once($sFile);
        }
        return include($sFile);
    }

    /**
     * Includes PHP-file from library dir
     *
     * @param string $sFile
     * @param bool   $bOnce
     *
     * @return  mixed
     */
    public static function includeLib($sFile, $bOnce = true)
    {
        if (class_exists('Config', false)) {
            return static::includeFile(Config::get('path.dir.libs') . '/' . $sFile, $bOnce);
        } else {
            return static::includeFile(dirname(__DIR__) . '/libs/' . $sFile, $bOnce);
        }
    }

    /**
     * Returns full dir path to plugins folder
     *
     * @param bool $bApplication
     *
     * @return string
     */
    public static function getPluginsDir($bApplication = false)
    {
        if (class_exists('Config', false)) {
            if ($bApplication) {
                $sPluginsDir = Config::get('path.dir.app') . 'plugins/';
            } else {
                $sPluginsDir = Config::get('path.dir.common') . 'plugins/';
            }
        } else {
            if ($bApplication) {
                $sPluginsDir = F::File_RootDir() . 'app/plugins/';
            } else {
                $sPluginsDir = F::File_RootDir() . 'common/plugins/';
            }
        }
        return $sPluginsDir;
    }

    /**
     * Returns full dir path to plugins dat file
     *
     * @return string
     */
    public static function getPluginsDatDir()
    {
        if (class_exists('Config', false)) {
            return Config::get('sys.plugins.activation_dir');
        } else {
            return F::File_RootDir() . 'app/plugins/';
        }
    }

    /**
     * Returns full dir path of plugins dat file
     *
     * @return string
     */
    public static function getPluginsDatFile()
    {
        if (class_exists('Config', false)) {
            return static::getPluginsDatDir() . Config::get('sys.plugins.activation_file');
        } else {
            return static::getPluginsDatDir() . 'plugins.dat';
        }
    }

    static protected $_aPluginList = [];

    /**
     * Проверяет плагины на соответствие маске разрешённых url и,
     * если нужно исключает из списка активных
     *
     * @param array $aPlugins
     *
     * @return array
     */
    protected static function excludeByEnabledMask($aPlugins)
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $aResult = [];
            //$sRequestUri = $_SERVER['REQUEST_URI'] == '/' ? '__MAIN_PAGE__' : $_SERVER['REQUEST_URI'];
            $sRequestUri = self::parseUrl(null, PHP_URL_PATH);
            if ($sRequestUri === '/') {
                $sRequestUri = '__MAIN_PAGE__';
            }
            foreach ($aPlugins as $sPluginName => $aPluginData) {
                $sXmlText = F::File_GetContents($aPluginData['manifest']);
                if (preg_match('~<enabled\>(.*)<\/enabled\>~', $sXmlText, $aMatches)) {
                    $sReq = preg_replace('/\/+/', '/', $sRequestUri);
                    $sReq = preg_replace('/^\/(.*)\/?$/U', '$1', $sReq);
                    $sReq = preg_replace('/^(.*)\?.*$/U', '$1', $sReq);
                    if (preg_match($aMatches[1], $sReq)) {
                        $aResult[$sPluginName] = $aPluginData;
                    }
                } else {
                    $aResult[$sPluginName] = $aPluginData;
                }
            }
        } else {
            $aResult = $aPlugins;
        }

        return $aResult;
    }

    /**
     * Получить список плагинов
     *
     * @param bool $bAll     - все плагины (иначе - только активные)
     * @param bool $bIdOnly  - только Id плагинов (иначе - вся строка с информацией о плагине)
     *
     * @return array
     */
    public static function getPluginsList($bAll = false, $bIdOnly = true)
    {
        $sPluginsDatFile = static::getPluginsDatFile();
        if (isset(self::$_aPluginList[$sPluginsDatFile][$bAll])) {
            $aPlugins = self::$_aPluginList[$sPluginsDatFile][$bAll];
        } else {
            $sCommonPluginsDir = static::getPluginsDir();
            $aPlugins = [];
            $aPluginsRaw = [];
            if ($bAll) {
                $aFiles = glob($sCommonPluginsDir . '{*,*/*}/plugin.xml', GLOB_BRACE);
                if ($aFiles)
                    foreach ($aFiles as $sXmlFile) {
                        $aPluginInfo = [];
                        $sXmlText = F::File_GetContents($sXmlFile);
                        $sDirName = dirname(\F::File_LocalPath($sXmlFile, $sCommonPluginsDir));
                        if (preg_match('/\<id\>([\w\.\/]+)\<\/id\>/', $sXmlText, $aMatches)) {
                            $aPluginInfo['id'] = $aMatches[1];
                        } else {
                            $aPluginInfo['id'] = $sDirName;
                        }
                        $aPluginInfo['dirname'] = $sDirName;
                        $aPluginInfo['path'] = dirname($sXmlFile) . '/';
                        $aPluginInfo['manifest'] = $sXmlFile;
                        $aPlugins[$aPluginInfo['id']] = $aPluginInfo;
                    }
            } else {
                if (is_file($sPluginsDatFile) && ($aPluginsRaw = @file($sPluginsDatFile))) {
                    $aPluginsRaw = array_map('trim', $aPluginsRaw);
                    $aPluginsRaw = array_unique($aPluginsRaw);
                }
                if ($aPluginsRaw) {
                    foreach ($aPluginsRaw as $sPluginStr) {
                        if (($n = strpos($sPluginStr, ';')) !== false) {
                            if ($n === 0) {
                                continue;
                            }
                            $sPluginStr = trim(substr($sPluginStr, 0, $n));
                        }
                        if ($sPluginStr) {
                            $aPluginInfo = str_word_count($sPluginStr, 1, '0..9/_');
                            $aPluginInfo['id'] = $aPluginInfo[0];
                            if (empty($aPluginInfo[1])) {
                                $aPluginInfo['dirname'] = $aPluginInfo[0];
                            } else {
                                $aPluginInfo['dirname'] = $aPluginInfo[1];
                            }
                            $sXmlFile = $sCommonPluginsDir . '/' . $aPluginInfo['dirname'] . '/plugin.xml';
                            if (is_file($sXmlFile)) {
                                $aPluginInfo['path'] = dirname($sXmlFile) . '/';
                                $aPluginInfo['manifest'] = $sXmlFile;
                                $aPlugins[$aPluginInfo['id']] = $aPluginInfo;
                            }
                        }
                    }
                }
            }
            if ($aPlugins) {
                $aPlugins = self::excludeByEnabledMask($aPlugins);
            }
            self::$_aPluginList[$sPluginsDatFile][$bAll] = $aPlugins;
        }
        if ($bIdOnly) {
            $aPlugins = array_keys($aPlugins);
        }
        return $aPlugins;
    }

    /**
     * @param string $sName
     * @param mixed  $xDefault
     * @param string $sType
     * @param bool   $bCaseInsensitive
     *
     * @return mixed
     */
    protected static function _getRequest($sName, $xDefault = null, $sType = null, $bCaseInsensitive = false)
    {
        // * Выбираем в каком из суперглобальных искать указанный ключ
        switch (strtolower($sType)) {
            case 'get':
                $aStorage = $_GET;
                break;
            case 'post':
                $aStorage = $_POST;
                break;
            default:
                $aStorage = $_REQUEST;
                break;
        }

        if ($bCaseInsensitive) {
            if (!empty($aStorage)) {
                $sName = strtolower($sName);
                foreach((array)$aStorage as $sKey => $xVal) {
                    if ($sName === strtolower($sKey)) {
                        return $xVal;
                    }
                }
            }
        } else {
            if (isset($aStorage[$sName])) {
                if (is_string($aStorage[$sName])) {
                    return trim($aStorage[$sName]);
                } else {
                    return $aStorage[$sName];
                }
            }
        }
        return $xDefault;
    }

    /**
     * @param string $sName
     * @param string $sDefault
     * @param string $sType
     * @param bool   $bCaseInsensitive
     *
     * @return string|null
     */
    protected static function _getRequestStr($sName, $sDefault = null, $sType = null, $bCaseInsensitive = false)
    {
        if (null !== $sDefault) {
            if (is_array($sDefault)) {
                $sDefault = '';
            } else {
                $sDefault = (string)$sDefault;
            }
        }
        $sResult = self::_getRequest($sName, $sDefault, $sType, $bCaseInsensitive);
        if (null !== $sResult) {
            return (is_array($sResult) ? '' : (string)$sResult);
        }
        return null;
    }

    /**
     * Функция доступа к REQUEST/GET/POST параметрам (name сase sensitive)
     *
     * @param string $sName
     * @param mixed  $xDefault
     * @param string $sType
     *
     * @return mixed
     */
    public static function getRequest($sName, $xDefault = null, $sType = null)
    {
        return self::_getRequest($sName, $xDefault, $sType, false);
    }

    /**
     * @param string $sName
     * @param string $sDefault
     * @param string $sType
     *
     * @return string|null
     */
    public static function getRequestStr($sName, $sDefault = null, $sType = null)
    {
        return self::_getRequestStr($sName, $sDefault, $sType, false);
    }

    /**
     * @param string $sName
     * @param int    $iDefault
     * @param string $sType
     *
     * @return string|null
     */
    public static function getRequestInt($sName, $iDefault = 0, $sType = null)
    {
        return (int)self::_getRequestStr($sName, $iDefault, $sType, false);
    }

    /**
     * Возвращает значение параметра, переданого методом POST
     *
     * @param string  $sName
     * @param mixed   $xDefault
     *
     * @return mixed
     */
    public static function getPost($sName, $xDefault = null)
    {
        return static::getRequest($sName, $xDefault, 'post');
    }

    /**
     * Возвращает значение параметра, переданого методом POST
     *
     * @param string  $sName
     * @param string  $sDefault
     *
     * @return string|null
     */
    public static function getPostStr($sName, $sDefault = null)
    {
        return static::getRequestStr($sName, $sDefault, 'post');
    }

    /**
     * Функция доступа к REQUEST/GET/POST параметрам (name сase insensitive)
     *
     * @param string $sName
     * @param mixed  $xDefault
     * @param string $sType
     *
     * @return mixed
     */
    public static function getIRequest($sName, $xDefault = null, $sType = null)
    {
        return self::_getRequest($sName, $xDefault, $sType, true);
    }

    /**
     * @param string $sName
     * @param mixed  $xDefault
     * @param string $sType
     *
     * @return string
     */
    public static function getIRequestStr($sName, $xDefault = null, $sType = null)
    {
        return self::_getRequestStr($sName, $xDefault, $sType, true);
    }

    /**
     * Возвращает значение параметра, переданого методом POST
     *
     * @param string  $sName
     * @param mixed   $xDefault
     *
     * @return bool
     */
    public static function getIPost($sName, $xDefault = null)
    {
        return static::getIRequest($sName, $xDefault, 'post');
    }

    /**
     * Возвращает значение параметра, переданого методом POST
     *
     * @param string  $sName
     * @param string  $sDefault
     *
     * @return  bool
     */
    public static function getIPostStr($sName, $sDefault = null)
    {
        return static::getIRequestStr($sName, $sDefault, 'post');
    }

    /**
     * Определяет, был ли передан указанный параметр методом POST
     *
     * @param string  $sName
     *
     * @return bool
     */
    public static function isPost($sName)
    {
        return (static::getPost($sName) !== null);
    }

    /**
     * Check if request is ajax
     *
     * @param bool $bPureAjax
     *
     * @return  bool
     */
    public static function ajaxRequest($bPureAjax = false)
    {
        if ($bPureAjax) {
            return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        } else {
            return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (!empty($_SERVER['HTTP_X_ALTO_AJAX_KEY']))
            || (!empty($_REQUEST['ALTO_AJAX']));
        }
    }

    /**
     * Аналог ф-ции stripslashes, умеющая обрабатывать массивы
     *
     * @param string|array $xData
     */
    public static function stripSlashes(&$xData)
    {
        if (is_array($xData)) {
            array_walk($xData, array(__CLASS__, 'StripSlashes'));
        } else {
            $xData = stripslashes($xData);
        }
    }

    /**
     * Аналог ф-ции htmlspecialchars, умеющая обрабатывать массивы
     *
     * @param mixed $xData
     */
    public static function HtmlSpecialChars(&$xData)
    {
        if (is_array($xData)) {
            array_walk($xData, array(__CLASS__, 'HtmlSpecialChars'));
        } else {
            $xData = htmlspecialchars($xData);
        }
    }

    /**
     * Приведение адреса к абсолютному виду
     *
     * Путь в $sLocation может быть как абсолютным, так и относительным.
     * Абсолютный путь определяется по наличию хоста
     *
     * Если задан относительный путь, то итоговый URL определяется в зависимости от второго парамтера.
     * Если $bRealHost == false (по умолчанию), то за основу берется root-адрес сайта, который задан в конфигурации.
     * В противном случае основа адреса - это реальный адрес хоста из $_SERVER['SERVER_NAME']
     *
     * @param string  $sLocation  - адрес перехода (напр., 'http://ya.ru/demo/', '/123.html', 'blog/add/')
     * @param bool    $bRealHost  - в случае относительной адресации брать адрес хоста из конфига или реальный
     *
     * @return string
     */
    public static function realUrl($sLocation, $bRealHost = false)
    {
        // если парсером хост не обнаружен, то задан относительный путь
        if (!parse_url($sLocation, PHP_URL_HOST)) {
            if (!$bRealHost) {
                $sUrl = F::File_RootUrl() . $sLocation;
            } else {
                $sUrl = static::urlBase() . '/' . $sLocation;
            }
        } else {
            $sUrl = $sLocation;
        }

        return F::File_NormPath($sUrl);
    }

    /**
     * $url = 'http://username:password@hostname.com/path?arg=value#anchor';
     *
     * @param string $sUrl
     * @param int    $iComponent
     *
     * @return array|string
     */
    public static function parseUrl($sUrl = null, $iComponent = -1)
    {
        if ((null === $sUrl) && isset($_SERVER['HTTP_HOST'])) {
            $sUrl = F::urlScheme(true) . $_SERVER['HTTP_HOST'];
            if (!empty($_SERVER['REQUEST_URI'])) {
                $sUrl .= $_SERVER['REQUEST_URI'];
            }
        }
        $xResult = array(
            'scheme' => null,
            'host' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => null,
            'fragment' => null,
            'base' => null,
        );
        if ($sUrl) {
            $xResult = array_merge($xResult, parse_url($sUrl));
            if ($xResult['host']) {
                $xResult['base'] = $xResult['host'];
                if ($xResult['scheme']) {
                    $xResult['base'] = $xResult['scheme'] . '://' . $xResult['base'];
                }
                if ($xResult['port']) {
                    $xResult['base'] .= ':' . $xResult['port'];
                }
            }
        }
        if ($iComponent !== -1) {
            if ($iComponent === PHP_URL_SCHEME) {
                $xResult = $xResult['scheme'];
            } elseif($iComponent === PHP_URL_HOST) {
                $xResult = $xResult['host'];
            } elseif($iComponent === PHP_URL_PORT) {
                $xResult = $xResult['port'];
            } elseif($iComponent === PHP_URL_USER) {
                $xResult = $xResult['user'];
            } elseif($iComponent === PHP_URL_PASS) {
                $xResult = $xResult['pass'];
            } elseif($iComponent === PHP_URL_PATH) {
                $xResult = $xResult['path'];
            } elseif($iComponent === PHP_URL_QUERY) {
                $xResult = $xResult['query'];
            } elseif($iComponent === PHP_URL_FRAGMENT) {
                $xResult = $xResult['fragment'];
            } if(isset($xResult[$iComponent])) {
                $xResult = $xResult[$iComponent];
            } else {
                $xResult = false;
            }
        }
        return $xResult;
    }

    /**
     * @param bool $bAddSlash
     *
     * @return string
     */
    public static function urlScheme($bAddSlash = false)
    {
        $sResult = 'http';
        if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $sResult = 'https';
        } elseif (isset($_SERVER['HTTP_SCHEME']) && strtolower($_SERVER['HTTP_SCHEME']) === 'https') {
            $sResult = 'https';
        } elseif(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
            $sResult = 'https';
        }
        if ($bAddSlash) {
            $sResult .= '://';
        }
        return $sResult;
    }

    /**
     * Returns base part of current URL request - scheme, host and port (if exists)
     *
     * @return string
     */
    public static function urlBase()
    {
        $aUrlParts = F::parseUrl();
        return !empty($aUrlParts['base']) ? $aUrlParts['base'] : null;
    }

    /**
     * Определение текущего HTTP-протокола для заголовка
     *
     * @return string
     */
    public static function httpProtocol()
    {
        $sProtocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
        return $sProtocol;
    }

    /**
     * Return request method
     *
     * @return string
     */
    public static function getRequestMethod()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
    }

    /**
     * Return all request headers
     *
     * @return array
     */
    public static function GetRequestHeaders()
    {
        return getallheaders();
    }

    static protected $_sRequestBody;

    /**
     * Return raw data from the request body
     *
     * @return string
     */
    public static function getRequestBody()
    {
        if (null === self::$_sRequestBody) {
            self::$_sRequestBody = file_get_contents('php://input');
        }
        return self::$_sRequestBody;
    }

    /**
     * Получает или устанавливает код ответа HTTP
     *
     * @param int|null $nResponseCode
     *
     * @return int
     */
    public static function httpResponseCode($nResponseCode = null)
    {
        if (null !== $nResponseCode) {
            $nResponseCode = (int)$nResponseCode;
        }
        // function http_response_code() added in PHP 5.4
        if (function_exists('http_response_code')) {
            return http_response_code($nResponseCode);
        } else {
            if (!empty($nResponseCode)) {
                switch ($nResponseCode) {
                    case 100: $sText = 'Continue'; break;
                    case 101: $sText = 'Switching Protocols'; break;
                    case 200: $sText = 'OK'; break;
                    case 201: $sText = 'Created'; break;
                    case 202: $sText = 'Accepted'; break;
                    case 203: $sText = 'Non-Authoritative Information'; break;
                    case 204: $sText = 'No Content'; break;
                    case 205: $sText = 'Reset Content'; break;
                    case 206: $sText = 'Partial Content'; break;
                    case 300: $sText = 'Multiple Choices'; break;
                    case 301: $sText = 'Moved Permanently'; break;
                    case 302: $sText = 'Moved Temporarily'; break;
                    case 303: $sText = 'See Other'; break;
                    case 304: $sText = 'Not Modified'; break;
                    case 305: $sText = 'Use Proxy'; break;
                    case 400: $sText = 'Bad Request'; break;
                    case 401: $sText = 'Unauthorized'; break;
                    case 402: $sText = 'Payment Required'; break;
                    case 403: $sText = 'Forbidden'; break;
                    case 404: $sText = 'Not Found'; break;
                    case 405: $sText = 'Method Not Allowed'; break;
                    case 406: $sText = 'Not Acceptable'; break;
                    case 407: $sText = 'Proxy Authentication Required'; break;
                    case 408: $sText = 'Request Time-out'; break;
                    case 409: $sText = 'Conflict'; break;
                    case 410: $sText = 'Gone'; break;
                    case 411: $sText = 'Length Required'; break;
                    case 412: $sText = 'Precondition Failed'; break;
                    case 413: $sText = 'Request Entity Too Large'; break;
                    case 414: $sText = 'Request-URI Too Large'; break;
                    case 415: $sText = 'Unsupported Media Type'; break;
                    case 500: $sText = 'Internal Server Error'; break;
                    case 501: $sText = 'Not Implemented'; break;
                    case 502: $sText = 'Bad Gateway'; break;
                    case 503: $sText = 'Service Unavailable'; break;
                    case 504: $sText = 'Gateway Time-out'; break;
                    case 505: $sText = 'HTTP Version not supported'; break;
                    default:
                        exit('Unknown http status code "' . $nResponseCode . '"');
                        break;
                }

                header(static::httpProtocol() . ' ' . $nResponseCode . ' ' . $sText);

                $GLOBALS['http_response_code'] = $nResponseCode;
            } else {
                $nResponseCode = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
            }

            return $nResponseCode;
        }
    }

    /**
     * @param string $sLocation
     * @param bool   $bRealHost
     */
    public static function headerLocation($sLocation, $bRealHost = false)
    {
        static::httpLocation($sLocation, $bRealHost);
    }

    /**
     * Переход по заданному адресу
     *
     * Путь в $sLocation может быть как абсолютным, так и относительным.
     * Абсолютный путь определяется по наличию хоста
     *
     * Если задан относительный путь, итоговый URL определяется в зависимости от второго парамтера.
     * Если $bRealHost == false (по умолчанию), то за основу берется root-адрес сайта, который задан в конфигурации.
     * В противном случае основа адреса - это реальный адрес хоста из $_SERVER['SERVER_NAME']
     *
     * @param string  $sLocation  - адрес перехода (напр., 'http://ya.ru/demo/', '/123.html', 'blog/add/')
     * @param bool    $bRealHost  - в случае относительной адресации брать адрес хоста из конфига или реальный
     */
    public static function httpLocation($sLocation, $bRealHost = false)
    {
        // Приведение адреса к абсолютному виду
        $sUrl = static::realUrl($sLocation, $bRealHost);

        $aHeaders = [
            'Expires: Fri, 01 Jan 2010 05:00:00 GMT',
            'Last-Modified: ' . gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control: no-cache, must-revalidate',
            ['Cache-Control: post-check=0,pre-check=0', false],
            ['Cache-Control: max-age=0', false],
            ['Pragma: no-cache'],
        ];

        static::httpHeader(303, $aHeaders, $sUrl);
    }

    /**
     * Постоянный редирект (с кодом 301)
     *
     * @param string $sLocation
     * @param bool   $bRealHost
     */
    public static function httpRedirect($sLocation, $bRealHost = false)
    {
        // Приведение адреса к абсолютному виду
        $sUrl = static::realUrl($sLocation, $bRealHost);

        static::httpHeader(301, null, $sUrl);
    }

    /**
     * Отправляет HTTP-заголовки и (опционально) адрес для редиректа
     *
     * @param int         $nHttpStatusCode
     * @param array|null  $aHeaders
     * @param string|null $sUrl
     */
    public static function httpHeader($nHttpStatusCode, $aHeaders = [], $sUrl = null)
    {
        // Можем работать с заголовком, только если он еще не отправлялся
        if (!headers_sent()) {
            session_commit();

            // Добавляем HTTP-заголовки
            if ($aHeaders && is_array($aHeaders)) {
                foreach ($aHeaders as $xHeaderParam) {
                    if (is_array($xHeaderParam)) {
                        if (count($xHeaderParam) > 1) {
                            header((string)$xHeaderParam[0], (bool)$xHeaderParam[1]);
                        } else {
                            header((string)$xHeaderParam[0]);
                        }
                    } else {
                        header((string)$xHeaderParam);
                    }
                }
            }
            // Устанавливаем HTTP-код
            F::httpResponseCode($nHttpStatusCode);
            if ($sUrl) {
                header('Location: ' . $sUrl, true);
            }
            exit;
        } elseif ($sUrl) {
            // Альтернативный редирект, если заголовок уже отправлен
            if (ob_get_level()) {
                @ob_end_clean();
            }
            echo '<!DOCTYPE HTML>
<html>
<head>
<script type="text/javascript">
<!--
location.replace("' . $sUrl . '");
//-->
</script>
<noscript>
<meta http-equiv="Refresh" content="0; URL=' . $sUrl . '">
</noscript>
</head>
<body>
Redirect to <a href="' . $sUrl . '">' . $sUrl . '</a>
</body>
</html>';
        }
        exit;
    }

    /**
     * @param       $xPatterns
     * @param       $sString
     * @param bool  $bCaseInsensitive
     * @param array $aMatches
     *
     * @return string|bool
     */
    public static function strMatch($xPatterns, $sString, $bCaseInsensitive = false, &$aMatches)
    {
        $sFuncClass = static::$aExtensions['Main'];
        return $sFuncClass::strMatch($xPatterns, $sString, $bCaseInsensitive, $aMatches);
    }



}

/***
 * Аналоги ф-ций preg_*, корректно обрабатывающие нелатиницу в UTF-8 при использовании флага PREG_OFFSET_CAPTURE
 */
if (!function_exists('mb_preg_match_all')) {

    function mb_preg_match_fix($bFuncAll, $sPattern, $sSubject, &$aMatches, $nFlags = PREG_OFFSET_CAPTURE, $nOffset = 0, $sEncoding = NULL)
    {
        if (null === $sEncoding) {
            $sEncoding = mb_internal_encoding();
        }

        $nOffset = strlen(mb_substr($sSubject, 0, $nOffset, $sEncoding));
        if ($bFuncAll) {
            $bResult = preg_match_all($sPattern, $sSubject, $aMatches, $nFlags, $nOffset);
            if ($bResult && ($nFlags & PREG_OFFSET_CAPTURE)) {
                foreach ((array)$aMatches as &$ha_match) {
                    foreach ($ha_match as &$ha_match) {
                        $ha_match[1] = mb_strlen(substr($sSubject, 0, $ha_match[1]), $sEncoding);
                    }
                }
            }
        } else {
            $bResult = preg_match($sPattern, $sSubject, $aMatches, $nFlags, $nOffset);
            if ($bResult && ($nFlags & PREG_OFFSET_CAPTURE)) {
                foreach ($aMatches as &$ha_match) {
                    $ha_match[1] = mb_strlen(substr($sSubject, 0, $ha_match[1]), $sEncoding);
                }
            }
        }

        return $bResult;
    }

    function mb_preg_match($sPattern, $sSubject, &$aMatches, $nFlags = PREG_OFFSET_CAPTURE, $nOffset = 0, $sEncoding = NULL)
    {
        return mb_preg_match_fix(0, $sPattern, $sSubject, $aMatches, $nFlags, $nOffset, $sEncoding);
    }

    function mb_preg_match_all($sPattern, $sSubject, &$aMatches, $nFlags = PREG_OFFSET_CAPTURE, $nOffset = 0, $sEncoding = NULL)
    {
        return mb_preg_match_fix(1, $sPattern, $sSubject, $aMatches, $nFlags, $nOffset, $sEncoding);
    }

}

if (!function_exists('getallheaders')) {

    function getallheaders()
    {
        $aHeaders = [];
        if (!empty($_SERVER)) {
            foreach ((array)$_SERVER as $sName => $sValue) {
                if (0 === strpos($sName, 'HTTP_')) {
                    $aHeaders[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($sName, 5)))))] = $sValue;
                }
            }
        }
        return $aHeaders;
    }
}

class_alias('Func', 'F');

F::init();

// EOF