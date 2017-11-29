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
 * Абстракция модуля, от которой наследуются все модули
 *
 * @package engine
 * @since 1.0
 *
 * @method bool MethodExists($sMethodName)
 */
abstract class Module extends Component
{
    const DEFAULT_ITEMS_PER_PAGE = 25;

    const STATUS_INIT_BEFORE = 1;
    const STATUS_INIT = 2;
    const STATUS_DONE_BEFORE = 3;
    const STATUS_DONE = 4;

    /** @var int Статус модуля */
    protected $nStatus = 0;

    /** @var bool Признак предзагрузки */
    protected $bPreloaded = false;

    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * Блокируем копирование/клонирование объекта
     *
     */
    protected function __clone()
    {
    }

    /**
     * Метод инициализации модуля (всегда вызывается после загрузки модуля)
     *
     */
    public function init()
    {
    }

    /**
     * Returns array if entity IDs
     *
     * @param mixed $aEntities
     * @param bool  $bUnique
     * @param bool  $bSkipZero
     *
     * @return array
     */
    protected function _entitiesId($aEntities, $bUnique = true, $bSkipZero = true)
    {
        $aIds = [];
        if (!is_array($aEntities)) {
            $aEntities = [$aEntities];
        }
        foreach ((array)$aEntities as $oEntity) {
            $iEntityId = (int)(is_object($oEntity) ? $oEntity->getId() : $oEntity);
            if ($iEntityId || !$bSkipZero) {
                $aIds[] = $iEntityId;
            }
        }
        if ($aIds && $bUnique) {
            $aIds = array_unique($aIds);
        }
        return $aIds;
    }

    /**
     * Возвращает ID сущности, если передан объект, либо просто ID
     *
     * @param $xEntity
     *
     * @return int|null
     */
    protected function _entityId($xEntity)
    {
        if (is_scalar($xEntity)) {
            return (int)$xEntity;
        }
        $aIds = $this->_entitiesId($xEntity);
        if ($aIds) {
            return (int)reset($aIds);
        }
        return null;
    }

    /**
     * Метод срабатывает при завершении работы ядра
     *
     * @param null $oResponse
     */
    public function shutdown()
    {
    }

    /**
     * Устанавливает статус модуля
     *
     * @param   int $nStatus
     */
    public function setStatus($nStatus)
    {
        $this->nStatus = $nStatus;
    }

    /**
     * Вовзращает статус модуля
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->nStatus;
    }

    /**
     * @param bool $bVal
     */
    public function setPreloaded($bVal)
    {
        $this->bPreloaded = (bool)$bVal;
    }

    /**
     * @return bool
     */
    public function getPreloaded()
    {
        return $this->bPreloaded;
    }

    /**
     * Устанавливает признак начала и завершения инициализации модуля
     *
     * @param bool $bBefore
     */
    public function setInit($bBefore = false)
    {
        if ($bBefore) {
            $this->setStatus(self::STATUS_INIT_BEFORE);
        } else {
            $this->setStatus(self::STATUS_INIT);
        }
    }

    /**
     * Устанавливает признак начала и завершения шатдауна модуля
     *
     * @param bool $bBefore
     */
    public function setDone($bBefore = false)
    {
        if ($bBefore) {
            $this->setStatus(self::STATUS_DONE_BEFORE);
        } else {
            $this->setStatus(self::STATUS_DONE);
        }
    }

    /**
     * Возвращает значение флага инициализации модуля
     *
     * @return bool
     */
    public function inInitProgress()
    {
        return $this->getStatus() === self::STATUS_INIT_BEFORE;
    }

    /**
     * Возвращает значение флага инициализации модуля
     *
     * @return bool
     */
    public function isInit()
    {
        return $this->getStatus() >= self::STATUS_INIT;
    }

    /**
     * Возвращает значение флага инициализации модуля
     *
     * @return bool
     */
    public function inShutdownProgress()
    {
        return $this->getStatus() === self::STATUS_DONE_BEFORE;
    }

    /**
     * Возвращает значение флага инициализации модуля
     *
     * @return bool
     */
    public function isDone()
    {
        return $this->getStatus() >= self::STATUS_DONE;
    }

    /**
     * @param string $sMsg
     *
     * @return bool
     */
    public function logError($sMsg)
    {
        return \F::logError(get_class($this) . ': ' . $sMsg);
    }

    /**
     * Структурирует массив сущностей - возвращает многомерный массив по заданным ключам
     * <pre>
     * Structurize($aEntities, key1, key2, ...);
     * Structurize($aEntities, [key1, key2, ...]);
     * </pre>
     *
     * @return array
     */
    public function structurize()
    {
        $iArgsNum = func_num_args();
        $aArgs = func_get_args();
        if ($iArgsNum === 0) {
            return [];
        }
        if ($iArgsNum === 1) {
            return $aArgs[0];
        }
        $aResult = [];
        $aEntities = $aArgs[0];
        $oEntity = reset($aEntities);
        unset($aArgs[0]);
        if (count($aArgs) === 1 && is_array($aArgs[1])) {
            $aArgs = $aArgs[1];
        }
        foreach($aArgs as $iIdx => $sPropKey) {
            if (!$oEntity->isProp($sPropKey)) {
                unset($aArgs[$iIdx]);
            }
        }
        if ($aArgs) {
            /** @var Entity $oEntity */
            foreach($aEntities as $oEntity) {
                $aItems =& $aResult;
                foreach($aArgs as $sPropKey) {
                    $xKey = $oEntity->getProp($sPropKey);
                    if (!isset($aItems[$xKey])) {
                        $aItems[$xKey] = [];
                    }
                    $aItems =& $aItems[$xKey];
                }
                $aItems[$oEntity->getId()] = $oEntity;
            }
        } else {
            $aResult = $aEntities;
        }
        return $aResult;
    }

    /**
     * @return bool|string
     */
    public function makeCacheKey()
    {
        $sCallerHash = md5(json_encode(debug_backtrace(false)));
        $sActivePlugins = \E::getActivePluginsHash();
        return \E::Module('Cache')->key($sActivePlugins . '|' . $sCallerHash, func_get_args());
    }

}

// EOF