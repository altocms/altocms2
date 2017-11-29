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
 * Обработка главной страницы, т.е. УРЛа вида /index/
 *
 * @package actions
 * @since   1.0
 */
class ActionIndex extends Action {
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'index';
    /**
     * Меню
     *
     * @var string
     */
    protected $sMenuItemSelect = 'index';
    /**
     * Субменю
     *
     * @var string
     */
    protected $sMenuSubItemSelect = 'good';
    /**
     * Число новых топиков
     *
     * @var int
     */
    protected $iCountTopicsNew = 0;
    /**
     * Число новых топиков в коллективных блогах
     *
     * @var int
     */
    protected $iCountTopicsCollectiveNew = 0;
    /**
     * Число новых топиков в персональных блогах
     *
     * @var int
     */
    protected $iCountTopicsPersonalNew = 0;

    /**
     * Named filter for topic list
     *
     * @var string
     */
    protected $sTopicFilter = '';

    protected $sTopicFilterPeriod;

    /**
     * Инициализация
     *
     */
    public function init() {
        /**
         * Подсчитываем новые топики
         */
        $this->iCountTopicsCollectiveNew=E::Module('Topic')->getCountTopicsCollectiveNew();
        $this->iCountTopicsPersonalNew=E::Module('Topic')->getCountTopicsPersonalNew();
        $this->iCountTopicsNew=$this->iCountTopicsCollectiveNew+$this->iCountTopicsPersonalNew;
    }

    /**
     * Регистрация евентов
     *
     */
    protected function registerEvent() {
        $this->addEventPreg('/^(page([1-9]\d{0,5}))?$/i', 'eventIndex');
        $this->addEventPreg('/^new$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventNew');
        $this->addEventPreg('/^all$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventAll');
        $this->addEventPreg('/^discussed/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventDiscussed');
        if (C::get('rating.enabled')) {
            $this->addEventPreg('/^top/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTop');
        }
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Вывод рейтинговых топиков
     */
    public function eventTop() {
        $this->sTopicFilterPeriod = 1; // по дефолту 1 день
        if (in_array(\F::getRequestStr('period'), array(1, 7, 30, 'all'))) {
            $this->sTopicFilterPeriod = F::getRequestStr('period');
        }
        /**
         * Меню
         */
        $this->sTopicFilter = $this->sMenuSubItemSelect = 'top';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        if ($iPage == 1 && !F::getRequest('period')) {
            \E::Module('Viewer')->setHtmlCanonical(R::getLink('index') . 'top/');
        }
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsTop(
            $iPage, \C::get('module.topic.per_page'), $this->sTopicFilterPeriod == 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
        );
        /**
         * Если нет топиков за 1 день, то показываем за неделю (7)
         */
        if (!$aResult['count'] && $iPage == 1 && !F::getRequest('period')) {
            $this->sTopicFilterPeriod = 7;
            $aResult = \E::Module('Topic')->getTopicsTop(
                $iPage, \C::get('module.topic.per_page'), $this->sTopicFilterPeriod == 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
            );
        }
        $aTopics = $aResult['collection'];
        /**
         * Вызов хуков
         */
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('index') . 'top', array('period' => $this->sTopicFilterPeriod)
        );

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_menu_all_top') . ($iPage>1 ? (' (' . $iPage . ')') : ''));
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('sPeriodSelectCurrent', $this->sTopicFilterPeriod);
        \E::Module('Viewer')->assign('sPeriodSelectRoot', R::getLink('index') . 'top/');
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('index');
    }

    /**
     * Вывод обсуждаемых топиков
     */
    public function eventDiscussed() {
        $this->sTopicFilterPeriod = 1; // по дефолту 1 день
        if (in_array(\F::getRequestStr('period'), array(1, 7, 30, 'all'))) {
            $this->sTopicFilterPeriod = F::getRequestStr('period');
        }
        /**
         * Меню
         */
        $this->sTopicFilter = $this->sMenuSubItemSelect = 'discussed';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        if ($iPage == 1 && !F::getRequest('period')) {
            \E::Module('Viewer')->setHtmlCanonical(R::getLink('index') . 'discussed/');
        }
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsDiscussed(
            $iPage, \C::get('module.topic.per_page'), $this->sTopicFilterPeriod == 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
        );
        /**
         * Если нет топиков за 1 день, то показываем за неделю (7)
         */
        if (!$aResult['count'] && $iPage == 1 && !F::getRequest('period')) {
            $this->sTopicFilterPeriod = 7;
            $aResult = \E::Module('Topic')->getTopicsDiscussed(
                $iPage, \C::get('module.topic.per_page'), $this->sTopicFilterPeriod == 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
            );
        }
        $aTopics = $aResult['collection'];
        /**
         * Вызов хуков
         */
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('index') . 'discussed', array('period' => $this->sTopicFilterPeriod)
        );

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_menu_collective_discussed') . ($iPage>1 ? (' (' . $iPage . ')') : ''));
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('sPeriodSelectCurrent', $this->sTopicFilterPeriod);
        \E::Module('Viewer')->assign('sPeriodSelectRoot', R::getLink('index') . 'discussed/');
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('index');
    }

    /**
     * Вывод новых топиков
     */
    public function eventNew() {

        \E::Module('Viewer')->setHtmlRssAlternate(R::getLink('rss') . 'index/new/', \C::get('view.name'));
        /**
         * Меню
         */
        $this->sTopicFilter = $this->sMenuSubItemSelect = 'new';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsNew($iPage, \C::get('module.topic.per_page'));
        $aTopics = $aResult['collection'];
        /**
         * Вызов хуков
         */
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('index') . 'new'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('index');
    }

    /**
     * Вывод ВСЕХ новых топиков
     */
    public function eventNewAll() {

        $this->eventAll();
    }

    /**
     * Вывод ВСЕХ топиков
     * @throws \RuntimeException
     */
    public function eventAll() {

        \E::Module('Viewer')->setHtmlRssAlternate(R::getLink('rss') . 'index/all/', \C::get('view.name'));

        // * Меню
        $this->sTopicFilter = $this->sMenuSubItemSelect = 'new';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsNewAll($iPage, \C::get('module.topic.per_page'));
        $aTopics = $aResult['collection'];
        /**
         * Вызов хуков
         */
        \HookManager::run('topics_list_show', ['aTopics' => $aTopics]);
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('index') . 'all'
        );

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_menu_all_new')  . ($iPage>1 ? (' (' . $iPage . ')') : ''));

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    /**
     * Вывод интересных на главную
     *
     */
    public function eventIndex() {

        \E::Module('Viewer')->setHtmlRssAlternate(R::getLink('rss') . 'index/', \C::get('view.name'));
        /**
         * Меню
         */
        $this->sTopicFilter = $this->sMenuSubItemSelect = 'good';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getEventMatch(2) ? $this->getEventMatch(2) : 1;
        /**
         * Устанавливаем основной URL для поисковиков
         */
        if ($iPage == 1) {
            \E::Module('Viewer')->setHtmlCanonical(trim(\C::get('path.root.url'), '/') . '/');
        }
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsGood($iPage, \C::get('module.topic.per_page'));
        $aTopics = $aResult['collection'];
        /**
         * Вызов хуков
         */
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('index')
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('index');
    }

    /**
     * При завершении экшена загружаем переменные в шаблон
     *
     */
    public function eventShutdown() {
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
        \E::Module('Viewer')->assign('sTopicFilter', $this->sTopicFilter);
        \E::Module('Viewer')->assign('sTopicFilterPeriod', $this->sTopicFilterPeriod);
        \E::Module('Viewer')->assign('iCountTopicsNew', $this->iCountTopicsNew);
        \E::Module('Viewer')->assign('iCountTopicsCollectiveNew', $this->iCountTopicsCollectiveNew);
        \E::Module('Viewer')->assign('iCountTopicsPersonalNew', $this->iCountTopicsPersonalNew);
    }
}

// EOF