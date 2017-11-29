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
class HookManager extends Singleton
{
    static protected $aHookTypes = ['callback', 'module', 'hook', 'function', 'template'];

    static protected $aCallHookEvents = [];

    /**
     * Содержит список обработчиков хуков
     *
     * @var [ 'name' => [
     *        [
     *            'type'     => 'callback' | 'module' | 'hook' | 'function',
     *            'callback' => 'callback_name',
     *            'priority' => 1,
     *            'options'  => []
     *        ],
     *    ],
     * ]
     */
    protected $aHandlers = [];

    protected $aObservers = [];

    protected $aHandlerOrders = [];

    /**
     * Список объектов обработки хуков, для их кешировани
     *
     * @var array
     */
    protected $aHooksObject = [];

    /**
     * @param mixed  $xCallback
     * @param string $sClass
     *
     * @return array
     */
    protected function _parseCallback($xCallback, $sClass = null)
    {
        $aResult = [
            'function' => $xCallback,
            'class' => null,
            'object' => null,
            'method' => null,
        ];
        if (is_array($xCallback) && count($xCallback) === 2) {
            list($oObject, $sMethod) = $xCallback;
            if (is_string($sMethod)) {
                if (is_object($oObject)) {
                    $aResult['function'] = null;
                    $aResult['class'] = null;
                    $aResult['object'] = $oObject;
                    $aResult['method'] = $sMethod;
                } elseif (is_string($oObject)) {
                    $aResult['function'] = null;
                    $aResult['class'] = $oObject;
                    $aResult['object'] = null;
                    $aResult['method'] = $sMethod;
                }
            }
        }
        return $aResult;
    }

    /**
     * Notifies observers about hooks
     *
     * @param array $aHooks
     * @param array $aObservers
     */
    protected function _notifyObserver($aHooks, $aObservers)
    {
        foreach ($aObservers as $aObserver) {
            foreach ($aHooks as $sHookName => $aHookData) {
                if ($aObserver['strict']) {
                    $bNotify = ($sHookName === $aObserver['hook']);
                } else {
                    $bNotify = (strpos($sHookName, $aObserver['hook']) === 0);
                }
                if ($bNotify) {
                    call_user_func($aObserver['callback'], $sHookName);
                }
            }
        }
    }

    /**
     * Возвращает информацию о том, включен хук или нет
     *
     * @param string $sHookName
     *
     * @return bool
     */
    public function isEnabled($sHookName)
    {
        return isset($this->aHandlers[$sHookName]);
    }

    /**
     * Adds handler for hook
     * Call format:
     *   addHandler($sHookName, $xCallBack [, $iPriority] [, $aParams])
     *
     * @param string $sHookName Hook name
     * @param string|array $xCallBack Callback function to run for the hook
     * @param int    $iPriority
     * @param array  $aOptions
     *
     * @return bool
     *
     * @since 1.1
     */
    public static function addHandler($sHookName, $xCallBack, $iPriority = 0, $aOptions = [])
    {
        return self::getInstance()->add($sHookName, 'callback', $xCallBack, $iPriority, $aOptions);
    }

    /**
     * Adds observer to be notified of new handlers
     *
     * @param string   $sHookName
     * @param callable $xCallBack
     * @param bool     $bStrict
     *
     * @return bool
     */
    public static function addObserver($sHookName, $xCallBack, $bStrict = false)
    {
        return self::getInstance()->observer($sHookName, $xCallBack, $bStrict);
    }

    /**
     * @param       $sName
     * @param array $aVars
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public static function run($sName, $aVars = [])
    {
        return self::getInstance()->callHook($sName, $aVars);
    }

    /**
     * @param $sName
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public static function call($sName)
    {
        $aVars = func_get_args();
        return self::getInstance()->callHook($sName, $aVars);
    }

    /**
     * @return \alto\engine\core\HookEvent
     */
    public static function getHookEvent()
    {
        return end(self::$aCallHookEvents);
    }

    /**
     * Adds observer to be notified of new handlers
     *
     * @param string   $sHookName
     * @param callable $xCallBack
     * @param bool     $bStrict
     *
     * @return bool
     */
    public function observer($sHookName, $xCallBack, $bStrict = false)
    {
        $this->aObservers[] = [
            'hook'      => $sHookName,
            'callback'  => $xCallBack,
            'strict'    => $bStrict,
        ];
        $aObserver = end($this->aObservers);
        if ($this->aHandlers) {
            $this->_notifyObserver($this->aHandlers, [$aObserver]);
        }
        return true;
    }

    /**
     * Добавление обработчика на хук
     *
     * @param string $sHookName Имя хука
     * @param string $sType     Тип хука, возможны: module, function, hook
     * @param string $xCallback Функция/метод обработки хука
     * @param int    $iPriority Приоритер обработки, чем выше, тем раньше сработает хук относительно других
     * @param array  $aOptions  Список дополнительных опций, передаваемых при вызове обработчика хука
     *
     * @return bool
     */
    public function add($sHookName, $sType, $xCallback, $iPriority = 0, $aOptions = [])
    {
        $sHookName = strtolower($sHookName);

        $sType = strtolower($sType);
        if (!in_array($sType, self::$aHookTypes, true)) {
            return false;
        }

        $aHookData = [
            'type'     => $sType,
            'callback' => $xCallback,
            'priority' => (int)$iPriority,
            'options'  => $aOptions,
        ];
        $this->aHandlers[$sHookName][] = $aHookData;

        if (!empty($this->aHandlerOrders)) {
            $this->aHandlerOrders = [];
        }

        if ($this->aObservers) {
            $this->_notifyObserver([$aHookData], $this->aObservers);
        }

        return true;
    }

    /**
     * Добавляет обработчик хука с типом "module"
     * Позволяет в качестве обработчика использовать метод модуля
     *
     * @see add
     *
     * @param string       $sName     Имя хука
     * @param string|array $xCallBack Полное имя метода обработки хука в LS формате ("Module_Method") или в виде массива ['Module', 'Method']
     * @param int          $iPriority Приоритер обработки, чем выше, тем раньше сработает хук относительно других
     * @param array        $aOptions  Список дополнительных опций, передаваемых при вызове обработчика хука
     *
     * @return bool
     */
    public function addExecModule($sName, $xCallBack, $iPriority = 1, $aOptions = [])
    {
        if (is_string($xCallBack) && strpos($xCallBack, '_')) {
            $xCallBack = explode('_', $xCallBack, 2);
        }
        return $this->add($sName, 'module', $xCallBack, $iPriority, $aOptions);
    }

    /**
     * Добавляет обработчик хука с типом "function"
     * Позволяет в качестве обработчика использовать функцию
     *
     * @see add
     *
     * @param string $sName     Имя хука
     * @param string $sCallBack Функция обработки хука, например, "var_dump"
     * @param int    $iPriority Приоритер обработки, чем выше, тем раньше сработает хук относительно других
     * @param array  $aOptions  Список дополнительных опций, передаваемых при вызове обработчика хука
     *
     * @return bool
     */
    public function addExecFunction($sName, $sCallBack, $iPriority = 1, $aOptions = [])
    {
        return $this->add($sName, 'function', $sCallBack, $iPriority, $aOptions);
    }

    /**
     * @param string $sName
     *
     * @return HookEvent
     *
     * @throws \RuntimeException
     */
    protected function _newHookEvent($sName, $aData)
    {
        if (isset(self::$aCallHookEvents[$sName])) {
            // Вызов хука внутри обработки этого хука - такого быть не должно
            throw new \RuntimeException('Call the hook "' . $sName . '" inside the handler of this hook');
        }
        $oHookEvent = new HookEvent($aData);
        $oHookEvent->setStopHandle(false);
        self::$aCallHookEvents[$sName] = $oHookEvent;

        return $oHookEvent;
    }

    /**
     * @param string $sName
     */
    protected function _delHookEvent($sName)
    {
        self::$aCallHookEvents[$sName] = null;
        unset(self::$aCallHookEvents[$sName]);
    }

    /**
     * Запускает обаботку хуков
     *
     * @param string $sName Имя хука
     * @param array $aVars Список параметров хука, передаются в обработчик
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function callHook($sName, $aVars = [])
    {
        $aResults = [];
        $sName = strtolower($sName);

        if (isset($this->aHandlers[$sName])) {
            // сортировка обработчиков по приоритету
            if (empty($this->aHandlerOrders[ $sName])) {
                $iCount = count($this->aHandlers[ $sName]);
                if ($iCount > 1) {
                    for ($iHandlerNum = 0; $iHandlerNum < $iCount; $iHandlerNum++) {
                        $iPriority = $this->aHandlers[ $sName][$iHandlerNum]['priority'];
                        $this->aHandlerOrders[ $sName][$iHandlerNum] = $iPriority;
                    }
                    $this->aHandlerOrders[ $sName] = array_reverse($this->aHandlerOrders[ $sName], true);
                    arsort($this->aHandlerOrders[ $sName], SORT_NUMERIC);
                } else {
                    $this->aHandlerOrders[ $sName][0] = $this->aHandlers[ $sName][0]['priority'];
                }
            }

            // Runs hooks in priority order
            foreach ((array)$this->aHandlerOrders[ $sName] as $iHandlerNum => $iPriority) {
                $oHookEvent = $this->_newHookEvent($sName, $this->aHandlers[$sName][$iHandlerNum]);
                $oHookEvent->setArguments($aVars);
                $oHookEvent->setResults($aResults);
                $xCallback = $oHookEvent->getCallback();
                $xHookResult = call_user_func_array($xCallback, $aVars);
                $aResults[] = $xHookResult;

                if ($oHookEvent->getStopHandle()) {
                    break;
                }
                $this->_delHookEvent($sName);
            }
        }
        // Для шаблонного хука возвращаем все значения
        // для прочих - последнее
        if (0 === strpos($sName, 'template_')) {
            return $aResults;
        }
        return end($aResults);
    }

    /**
     * @param string $sElement
     * @param mixed  $xDefault
     *
     * @return mixed
     */
    protected function _currentHookElement($sElement, $xDefault = null)
    {
        $aHook = end(self::$aCallHookEvents);
        return $aHook[$sElement] ?: $xDefault;
    }
    /**
     * Returns current hook name
     *
     * @return string
     *
     * @since 1.1
     */
    public function getHookName()
    {
        return $this->_currentHookElement('name');
    }

    /**
     * Returns parameters of current hook handler
     *
     * @return array
     *
     * @since   1.1
     */
    public function getHookOptions()
    {
        return $this->_currentHookElement('name', []);
    }

    /**
     * Returns call arguments of current hook handler
     *
     * @return mixed
     *
     * @since   1.1.9
     */
    public function getHookArguments()
    {
        return $this->_currentHookElement('arguments');
    }

    /**
     * Returns result of hook source method
     *
     * @return mixed
     */
    public function getSourceResult()
    {
        return $this->_currentHookElement('result');
    }

    /**
     * Sets stop handle flag
     *
     * @since   1.1
     */
    public function stopHookHandle()
    {
        $this->bStopHandle = true;
    }

}

// EOF