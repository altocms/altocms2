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
 * Абстракция хука, от которой наследуются все хуки
 * Дает возможность создавать обработчики хуков в каталоге /hooks/
 *
 * @package engine
 */
abstract class Hook extends Component
{
    /**
     * @param string   $sHookName Name of hook
     * @param callback $xCallBack Handler of hook
     * @param int      $iPriority Priority
     * @param array    $aOptions  Options to be passed to the handler
     *
     * @return bool
     */
    protected function addHandler($sHookName, $xCallBack, $iPriority = 0, $aOptions = [])
    {
        if (is_array($iPriority) && func_num_args() === 3) {
            $aOptions = $iPriority;
            $iPriority = null;
        }
        return \HookManager::addHandler($sHookName, $xCallBack, $iPriority, $aOptions);
    }

    /**
     * Adds template hook
     *
     * @param string          $sName
     * @param string|callable $sCallBack
     * @param int             $iPriority
     * @param array           $aOptions
     */
    protected function addHookTemplate($sName, $sCallBack, $iPriority = 0, $aOptions = [])
    {
        if (strpos($sName, 'template_') !== 0) {
            $sName = 'template_' . $sName;
        }
        if (is_string($sCallBack) && substr($sCallBack, -4) === '.tpl') {
            $aOptions['template'] = $sCallBack;
            \HookManager::addHandler($sName, [$this, 'fetchTemplate'], $iPriority, $aOptions);
            return;
        }
        if (is_string($sCallBack) && strpos($sCallBack, '::') === false && !function_exists($sCallBack)) {
            $sCallBack = [$this, $sCallBack];
        }
        \HookManager::addHandler($sName, $sCallBack, $iPriority, $aOptions);
    }

    /**
     * Old style compatibility
     *
     * @param string $sName
     * @param string $sMethod
     * @param string $sClass
     * @param int    $iPriority
     */
    protected function addHook($sName, $sMethod, $sClass = null, $iPriority = 0)
    {
        if (is_int($sClass) && func_num_args() === 3) {
            $iPriority = $sClass;
        }
        \HookManager::addHandler($sName, [$this, $sMethod], $iPriority);
    }

    /**
     * Обязательный метод в хуке - в нем происходит регистрация обработчиков хуков
     *
     * @abstract
     */
    abstract public function registerHook();

    /**
     * Метод для обработки хуков шаблнов
     *
     * @param $aParams
     *
     * @return string
     */
    public function fetchTemplate($aParams)
    {
        if (isset($aParams['template'])) {
            return \E::Module('Viewer')->fetch($aParams['template']);
        }
        return '';
    }

    /**
     * Sets stop handle flag
     *
     * @since   1.1
     */
    public function stopHookHandle()
    {
        $oHookEvent = \HookManager::getHookEvent();
        $oHookEvent->setStopHandle(true);
    }

    /**
     * Returns current hook name
     *
     * @return string
     *
     * @since   1.1
     */
    public function getHookName()
    {
        $oHookEvent = \HookManager::getHookEvent();
        return $oHookEvent->getName();
    }

    /**
     * Returns parameters of current hook handler
     *
     * @param mixed $xParam
     *
     * @return array
     */
    public function getHookOptions($xParam = null)
    {
        $oHookEvent = \HookManager::getHookEvent();
        $aOptions = (array)$oHookEvent->getOptions();
        if (null === $xParam) {
            return $aOptions;
        } elseif (is_scalar($xParam) && isset($aOptions[$xParam])) {
            return $aOptions[$xParam];
        }
        return null;
    }

    /**
     * Returns arguments of current hook handler
     *
     * @return array
     */
    public function getHookArguments()
    {
        $oHookEvent = \HookManager::getHookEvent();
        return $oHookEvent->getArguments();
    }

    /**
     * Returns the argument of current hook handler
     *
     * @param $xArgument
     *
     * @return mixed|null
     */
    public function getHookArgument($xArgument)
    {
        $oHookEvent = \HookManager::getHookEvent();
        $aArguments = (array)$oHookEvent->getArguments();
        if (null === $xArgument) {
            return $aArguments;
        } elseif (is_scalar($xArgument) && isset($aArguments[$xArgument])) {
            return $aArguments[$xArgument];
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        $oHookEvent = \HookManager::getHookEvent();
        return $oHookEvent->getResult();
    }

}

// EOF