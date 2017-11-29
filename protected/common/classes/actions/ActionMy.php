<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Экшен обработки УРЛа вида /my/
 * Оставлен только для редиректов со старых УРЛ на новые
 *
 * @package actions
 * @since   1.0
 */
class ActionMy extends Action {
    /**
     * Объект юзера чей профиль мы смотрим
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserProfile = null;

    /**
     * Инициализация
     */
    public function init() {
    }

    /**
     * Регистрируем евенты
     */
    protected function registerEvent() {
        $this->addEventPreg('/^.+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTopics');
        $this->addEventPreg('/^.+$/i', '/^blog$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTopics');
        $this->addEventPreg('/^.+$/i', '/^comment$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventComments');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Выводит список топиков которые написал юзер
     * Перенаправляет на профиль пользователя
     *
     */
    public function eventTopics()
    {
        /**
         * Получаем логин из УРЛа
         */
        $sUserLogin = $this->sCurrentEvent;
        /**
         * Проверяем есть ли такой юзер
         */
        if (!($this->oUserProfile = \E::Module('User')->getUserByLogin($sUserLogin))) {
            return parent::eventNotFound();
        }
        /**
         * Передан ли номер страницы
         */
        if ($this->getParamEventMatch(0, 0) === 'blog') {
            $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        } else {
            $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        }
        /**
         * Выполняем редирект на новый URL, в новых версиях LS экшен "my" будет удален
         */
        $sPage = $iPage == 1 ? '' : "page{$iPage}/";
        R::Location($this->oUserProfile->getProfileUrl() . 'created/topics/' . $sPage);
    }

    /**
     * Выводит список комментариев которые написал юзер
     * Перенаправляет на профиль пользователя
     *
     */
    public function eventComments() {
        /**
         * Получаем логин из УРЛа
         */
        $sUserLogin = $this->sCurrentEvent;
        /**
         * Проверяем есть ли такой юзер
         */
        if (!($this->oUserProfile = \E::Module('User')->getUserByLogin($sUserLogin))) {
            return parent::eventNotFound();
        }
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        /**
         * Выполняем редирект на новый URL, в новых версиях LS экшен "my" будет удален
         */
        $sPage = $iPage == 1 ? '' : "page{$iPage}/";
        R::Location($this->oUserProfile->getProfileUrl() . 'created/comments/' . $sPage);
    }
}

// EOF