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
 * Экшен обработки поиска по тегам
 *
 * @package actions
 * @since   1.0
 */
class ActionTag extends Action {
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blog';

    /**
     * Инициализация
     *
     */
    public function init() {
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() {

        $this->addEventPreg('/^.+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTags');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Отображение топиков
     *
     */
    public function eventTags() {

        // * Gets tag from URL
        $sTag = F::UrlDecode(R::url('event'), true);

        // * Check page number
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;

        // * Gets topics list
        $aResult = \E::Module('Topic')->getTopicsByTag($sTag, $iPage, \C::get('module.topic.per_page'));
        $aTopics = $aResult['collection'];

        // * Calls hooks
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));

        // * Makes pages
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('tag') . htmlspecialchars($sTag)
        );

        // * Loads variables to template
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('sTag', $sTag);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('tag_title'));
        \E::Module('Viewer')->addHtmlTitle($sTag);
        \E::Module('Viewer')->setHtmlRssAlternate(R::getLink('rss') . 'tag/' . $sTag . '/', $sTag);

        // * Sets template for display
        $this->setTemplateAction('index');
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() {
        /**
         * Загружаем в шаблон необходимые переменные
         */
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
    }
}

// EOF