<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Модуль для работы с топиками
 *
 * @package modules.tag
 * @since   2.0
 */
class ModuleTag extends Module
{
    /** @var  ModuleTag_MapperTag */
    protected $oMapper;

    /**
     * Инициализация
     *
     */
    public function init()
    {
        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * @param $aFilter
     * @param $iLimit
     *
     * @return array
     */
    public function getTags($aFilter, $iLimit)
    {
        return $this->oMapper->getTags($aFilter, $iLimit);
    }
}

// EOF