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

/**
 * Class Decorator
 *
 * @mixin \Module
 * @method Init
 *
 * @package engine
 * @since 1.1
 */
class Decorator extends Component
{
    protected $sType;
    protected $sName;
    protected $bHookEnable = true;
    protected $sHookPrefix;
    protected $oComponent;

    protected $aRegisteredHooks = [];

    protected $aStats = [];

    /**
     * Decorator constructor.
     *
     * @param $oComponent
     */
    public function __construct($oComponent)
    {
        parent::__construct();
        $this->oComponent = $oComponent;
        \HookManager::addObserver($this->sHookPrefix, [$this, 'HookObserver']);
    }

    /**
     * Decorator destructor.
     *
     */
    public function __destruct()
    {
        // Nothing
    }

    /**
     * Hook register which registers hooks
     *
     * @param $sHookName
     */
    public function hookObserver($sHookName)
    {
        $this->aRegisteredHooks[$sHookName] = true;
    }

    /**
     * Checks whether there is a method in decorated object
     *
     * @param string $sMethodName
     *
     * @return bool
     */
    public function methodExists($sMethodName)
    {
        return method_exists($this->oComponent, $sMethodName);
    }

    /**
     * Calls method of decorated object
     *
     * @param string $sMethod
     * @param array  $aArgs
     *
     * @return mixed
     */
    public function callMethod($sMethod, $aArgs)
    {
        $sHookMethod = $this->sHookPrefix . strtolower($sMethod);
        if ($this->bHookEnable) {
            switch ($this->sType) {
                case 'action':
                    $this->hookBeforeAction();
                    break;
                case 'module':
                    $sHookName = $sHookMethod . '_before';
                    if (isset($this->aRegisteredHooks[$sHookName])) {
                        \HookManager::run($sHookName, $aArgs);
                    }
                    break;
                default:
                    break;
            }
        }

        switch (count($aArgs)) {
            case 0:
                $xResult = $this->oComponent->$sMethod();
                break;
            case 1:
                $xResult = $this->oComponent->$sMethod($aArgs[0]);
                break;
            case 2:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1]);
                break;
            case 3:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2]);
                break;
            case 4:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3]);
                break;
            case 5:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4]);
                break;
            case 6:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5]);
                break;
            case 7:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6]);
                break;
            case 8:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6], $aArgs[7]);
                break;
            case 9:
                $xResult = $this->oComponent->$sMethod($aArgs[0], $aArgs[1], $aArgs[2], $aArgs[3], $aArgs[4], $aArgs[5], $aArgs[6], $aArgs[7], $aArgs[8]);
                break;
            default:
                $xResult = call_user_func_array([$this->oComponent, $sMethod], $aArgs);
        }

        if ($this->bHookEnable) {
            switch ($this->sType) {
                case 'action':
                    $this->hookAfterAction();
                    break;
                case 'module':
                    $sHookName = $sHookMethod . '_after';
                    if (isset($this->aRegisteredHooks[$sHookName])) {
                        $aHookParams = ['result' => &$xResult, 'params' => &$aArgs];
                        \HookManager::run($sHookName, $aHookParams);
                    }
                    break;
                default:
                    break;
            }
        }

        return $xResult;
    }

    /**
     * @param string $sMethod
     * @param array  $aArgs
     *
     * @return mixed
     */
    public function __call($sMethod, $aArgs)
    {
        $iTime = microtime(true);

        $xResult = $this->callMethod($sMethod, $aArgs);

        $this->aStats['calls'][] = [
            'time' => round(microtime(true) - $iTime, 6),
            'method' => $sMethod,
            'args' => $aArgs,
        ];

        return $xResult;
    }

    /*
    /**
     * @param string $sFieldName
     *
     * @return null
     * /
    public function __get($sFieldName)
    {
        return isset($this->oComponent->$sFieldName) ? $this->oComponent->$sFieldName : null;
    }
    */

    protected function hookBeforeAction()
    {
    }

    protected function hookAfterAction()
    {
    }

    /**
     * @param string $sType
     */
    public function setType($sType)
    {
        $this->sType = $sType;
    }

    /**
     * @param string $sName
     */
    public function setName($sName)
    {
        $this->sName = $sName;
        $this->sHookPrefix = $this->sType . '_' . strtolower($this->sName) . '_';
    }

    /**
     * Enables/disables hooks for methods of decorated hook
     *
     * @param bool $bHookEnable
     */
    public function setHookEnable($bHookEnable)
    {
        $this->bHookEnable = (bool)$bHookEnable;
    }

    /**
     * Creates decorator
     *
     * @param object $oComponent
     * @param bool   $bHookEnable
     *
     * @return Decorator|object
     */
    public static function createComponent($oComponent, $bHookEnable = true)
    {
        $sClassName = get_class($oComponent);
        if (ALTO_DEBUG) {
            $sDecoratorClassName = 'Decorator' . $sClassName;
            $sDecoratorClassCode = 'class ' . $sDecoratorClassName . ' extends Decorator { }';
            eval($sDecoratorClassCode);
            $oComponentDecorator = new $sDecoratorClassName($oComponent);
        } else {
            $oComponentDecorator = new static($oComponent);
        }
        $aClassInfo = \E::GetClassInfo($oComponent, Engine::CI_CONTROLLER | Engine::CI_MODULE);
        if ($aClassInfo[Engine::CI_CONTROLLER]) {
            $oComponentDecorator->setType('action');
            $oComponentDecorator->setName($aClassInfo[Engine::CI_CONTROLLER]);
            $oComponentDecorator->setHookEnable(true);
        } elseif($aClassInfo[Engine::CI_MODULE]) {
            $oComponentDecorator->setType('module');
            $oComponentDecorator->setName($aClassInfo[Engine::CI_MODULE]);
            $oComponentDecorator->setHookEnable(true);
        }
        return $oComponentDecorator;
    }

}

// EOF