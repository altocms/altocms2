<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

class ActionFilter extends Action {
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'filter';
    /**
     * Меню
     *
     * @var string
     */
    protected $sMenuItemSelect = 'topic';
    /**
     * СубМеню
     *
     * @var string
     */
    protected $sMenuSubItemSelect = '';
    /**
     * Текущий юзер
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;
    /**
     * Текущий тип контента
     *
     * @var ModuleTopic_EntityContentType|null
     */
    protected $oType = null;

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->oUserCurrent = \E::User();


    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent() {

        $this->addEventPreg('/^[\w\-\_]+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowTopics');

    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */


    /**
     * Выводит список топиков
     *
     */
    public function eventShowTopics() {
        /**
         * Меню
         */
        $this->sMenuSubItemSelect = $this->sCurrentEvent;

        /*
         * Получаем тип контента
         */
        if (!$this->oType = \E::Module('Topic')->getContentType($this->sCurrentEvent)) {
            return parent::eventNotFound();
        }

        /**
         * Устанавливаем title страницы
         */
        \E::Module('Viewer')->addHtmlTitle($this->oType->getContentTitleDecl());
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsByType(
            $iPage, \C::get('module.topic.per_page'), $this->oType->getContentUrl()
        );
        $aTopics = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('filter') . $this->sCurrentEvent
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        $this->setTemplateAction('index');
    }

    /**
     * При завершении экшена загружаем необходимые переменные
     *
     */
    public function eventShutdown() {
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
    }
}

// EOF