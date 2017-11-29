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
 *
 * @package actions
 * @since   1.0
 */
class ActionPeople extends Action {
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'people';
    /**
     * Меню
     *
     * @var string
     */
    protected $sMenuItemSelect = 'all';

    /**
     * Инициализация
     *
     */
    public function init() {

        // Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('people'));

        if (!\E::Module('Session')->getCookie('view') && F::getRequestStr('view')) {
            \E::Module('Session')->delCookie('view');
        }
        \E::Module('Session')->setCookie('view', F::getRequestStr('view', '2'), 60 * 60 * 24 * 365);

    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent() {

        $this->addEvent('online', 'eventOnline');
        $this->addEvent('new', 'eventNew');
        $this->addEventPreg('/^(index)?$/i', '/^(page([1-9]\d{0,5}))?$/i', '/^$/i', 'eventIndex');
        $this->addEventPreg('/^ajax-search$/i', 'eventAjaxSearch');

        $this->addEventPreg('/^country$/i', '/^\d+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCountry');
        $this->addEventPreg('/^city$/i', '/^\d+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCity');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Поиск пользователей по логину
     *
     */
    public function eventAjaxSearch() {

        // Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        // Получаем из реквеста первые быквы для поиска пользователей по логину
        $sTitle = F::getRequest('user_login');
        if (is_string($sTitle) && mb_strlen($sTitle, 'utf-8')) {
            $sTitle = str_replace(array('_', '%'), array('\_', '\%'), $sTitle);
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        // Как именно искать: совпадение в любой части логина, или только начало или конец логина
        if (\F::getRequest('isPrefix')) {
            $sTitle .= '%';
        } elseif (\F::getRequest('isPostfix')) {
            $sTitle = '%' . $sTitle;
        } else {
            $sTitle = '%' . $sTitle . '%';
        }
        $aFilter = array('activate' => 1, 'login' => $sTitle);
        // Ищем пользователей
        $aResult = \E::Module('User')->getUsersByFilter($aFilter, array('user_rating' => 'desc'), 1, 50);

        // Формируем ответ
        $aVars = array(
            'aUsersList'     => $aResult['collection'],
            'oUserCurrent'   =>   \E::User(),
            'sUserListEmpty' => \E::Module('Lang')->get('user_search_empty'),
        );
        \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('commons/common.user_list.tpl', $aVars));
    }

    /**
     * Показывает юзеров по стране
     *
     */
    public function eventCountry() {

        $this->sMenuItemSelect = 'country';

        // Страна существует?
        if (!($oCountry = \E::Module('Geo')->getCountryById($this->getParam(0)))) {
            return parent::eventNotFound();
        }
        // Получаем статистику
        // Old skin compatibility
        $this->GetStats();

        // Передан ли номер страницы
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;

        // Получаем список связей пользователей со страной
        $aResult = \E::Module('Geo')->getTargets(
            array('country_id' => $oCountry->getId(), 'target_type' => 'user'), $iPage,
            \C::get('module.user.per_page')
        );
        $aUsersId = [];
        foreach ($aResult['collection'] as $oTarget) {
            $aUsersId[] = $oTarget->getTargetId();
        }
        $aUsersCountry = \E::Module('User')->getUsersAdditionalData($aUsersId);

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.user.per_page'), \C::get('pagination.pages.count'),
            R::getLink('people') . $this->sCurrentEvent . '/' . $oCountry->getId()
        );
        // Загружаем переменные в шаблон
        if ($aUsersCountry) {
            \E::Module('Viewer')->assign('aPaging', $aPaging);
        }
        \E::Module('Viewer')->assign('oCountry', $oCountry);
        \E::Module('Viewer')->assign('aUsersCountry', $aUsersCountry);
    }

    /**
     * Показывает юзеров по городу
     *
     */
    public function eventCity() {

        $this->sMenuItemSelect = 'city';
        // Город существует?
        if (!($oCity = \E::Module('Geo')->getCityById($this->getParam(0)))) {
            return parent::eventNotFound();
        }
        // Получаем статистику
        $this->GetStats();

        // Передан ли номер страницы
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;

        // Получаем список юзеров
        $aResult = \E::Module('Geo')->getTargets(
            array('city_id' => $oCity->getId(), 'target_type' => 'user'), $iPage, \C::get('module.user.per_page')
        );
        $aUsersId = [];
        foreach ($aResult['collection'] as $oTarget) {
            $aUsersId[] = $oTarget->getTargetId();
        }
        $aUsersCity = \E::Module('User')->getUsersAdditionalData($aUsersId);

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.user.per_page'), \C::get('pagination.pages.count'),
            R::getLink('people') . $this->sCurrentEvent . '/' . $oCity->getId()
        );

        // Загружаем переменные в шаблон
        if ($aUsersCity) {
            \E::Module('Viewer')->assign('aPaging', $aPaging);
        }
        \E::Module('Viewer')->assign('oCity', $oCity);
        \E::Module('Viewer')->assign('aUsersCity', $aUsersCity);
    }

    /**
     * Показываем последних на сайте
     *
     */
    public function eventOnline() {

        $this->sMenuItemSelect = 'online';

        // Последние по визиту на сайт
        $aUsersLast = \E::Module('User')->getUsersByDateLast(C::get('module.user.per_page'));
        \E::Module('Viewer')->assign('aUsersLast', $aUsersLast);

        // Получаем статистику
        $this->GetStats();
    }

    /**
     * Показываем новых на сайте
     *
     */
    public function eventNew() {

        $this->sMenuItemSelect = 'new';

        // Последние по регистрации
        $aUsersRegister = \E::Module('User')->getUsersByDateRegister(C::get('module.user.per_page'));
        \E::Module('Viewer')->assign('aUsersRegister', $aUsersRegister);

        // Получаем статистику
        $this->GetStats();
    }

    /**
     * Показываем юзеров
     *
     */
    public function eventIndex() {

        // Получаем статистику
        $this->GetStats();
        // По какому полю сортировать
        $sOrder = 'user_rating';
        if (\F::getRequest('order')) {
            $sOrder = F::getRequestStr('order');
        }
        // В каком направлении сортировать
        $sOrderWay = 'desc';
        if (\F::getRequest('order_way')) {
            $sOrderWay = F::getRequestStr('order_way');
        }
        $aFilter = array(
            'activate' => 1
        );

        // Передан ли номер страницы
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;

        // Получаем список юзеров
        $aResult = \E::Module('User')->getUsersByFilter(
            $aFilter, array($sOrder => $sOrderWay), $iPage, \C::get('module.user.per_page')
        );
        $aUsers = $aResult['collection'];

        // Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.user.per_page'), \C::get('pagination.pages.count'),
            R::getLink('people') . 'index', array('order' => $sOrder, 'order_way' => $sOrderWay)
        );

        // Получаем алфавитный указатель на список пользователей
        $aPrefixUser = \E::Module('User')->getGroupPrefixUser(1);

        // Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aUsersRating', $aUsers);
        \E::Module('Viewer')->assign('aPrefixUser', $aPrefixUser);
        \E::Module('Viewer')->assign("sUsersOrder", htmlspecialchars($sOrder));
        \E::Module('Viewer')->assign("sUsersOrderWay", htmlspecialchars($sOrderWay));
        \E::Module('Viewer')->assign("sUsersOrderWayNext", htmlspecialchars($sOrderWay == 'desc' ? 'asc' : 'desc'));

        // Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    /**
     * Получение статистики
     *
     */
    protected function GetStats() {

        // Статистика кто, где и т.п.
        $aStat = \E::Module('User')->getStatUsers();

        // Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aStat', $aStat);
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() {

        // Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
    }
}

// EOF