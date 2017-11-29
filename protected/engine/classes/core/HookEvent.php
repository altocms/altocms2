<?php

namespace alto\engine\core;

use alto\engine\generic\Entity;

/**
 * Класс роутинга
 * Инициализирует ядро, определяет какой экшен запустить согласно URL'у и запускает его
 *
 * @method setStopHandle(bool $bParam)
 * @method setArguments(array $aParam)
 *
 * @method bool     getStopHandle()
 * @method array    getArguments()
 * @method string   getName()
 * @method string   getType()
 * @method array    getOptions()
 * @method callable getCallback()
 *
 * @package engine
 * @since 2.0
 */
class HookEvent extends Entity
{
    /**
     * @return string|null
     */
    public function getCallTemplate()
    {
        $aArguments = $this->getArguments();
        if (isset($aArguments['template'])) {
            return $aArguments['template'];
        }
        $aOptions = $this->getProp('options');
        if (isset($aOptions['template'])) {
            return $aOptions['template'];
        }
        return null;
    }

    public function getResult()
    {
        $aResults = (array)$this->getProp('results');
        return end($aResults);
    }
}

// EOF