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
 * @package actions
 * @since   0.9.7
 */
class ActionT extends Action
{
    /**
     * Инициализация
     */
    public function init()
    {
        $this->setDefaultEvent('index');
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent()
    {
        $this->addEventPreg('/^\d+$/i', ['EventIndex', 'index']);
    }

    public function eventIndex()
    {
        if ($nTopicId = (int)$this->sCurrentEvent) {
            $oTopic = \E::Module('Topic')->getTopicById($nTopicId);
            if ($oTopic) {
                \F::httpRedirect($oTopic->getUrl());
                exit;
            }
        }
        return $this->eventNotFound();
    }
}

// EOF