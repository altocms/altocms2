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
 * Экшен обработки ленты активности
 *
 * @package actions
 * @since   1.0
 */
class ActionStream extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent;
    /**
     * Какое меню активно
     *
     * @var string
     */
    protected $sMenuItemSelect = 'follow';

    /**
     * Инициализация
     *
     */
    public function init() {
        /**
         * Личная лента доступна только для авторизованных, для гостей показываем общую ленту
         */
        $this->oUserCurrent = \E::User();
        if ($this->oUserCurrent) {
            $this->setDefaultEvent('follow');
        } else {
            $this->setDefaultEvent('all');
        }
        \E::Module('Viewer')->assign('aStreamEventTypes', \E::Module('Stream')->getEventTypes());

        \E::Module('Viewer')->assign('sMenuHeadItemSelect', 'stream');
        /**
         * Загружаем в шаблон JS текстовки
         */
        \E::Module('Lang')->addLangJs(
            array(
                 'stream_subscribes_already_subscribed', 'error'
            )
        );
    }

    /**
     * Регистрация евентов
     *
     */
    protected function registerEvent() {

        $this->addEvent('follow', 'eventFollow');
        $this->addEvent('all', 'eventAll');
        $this->addEvent('subscribe', 'eventSubscribe');
        $this->addEvent('subscribeByLogin', 'eventSubscribeByLogin');
        $this->addEvent('unsubscribe', 'eventUnSubscribe');
        $this->addEvent('switchEventType', 'eventSwitchEventType');

        $this->addEvent('get_more', 'eventGetMoreFollow');
        $this->addEvent('get_more_follow', 'eventGetMoreFollow');
        $this->addEvent('get_more_user', 'eventGetMoreUser');
        $this->addEvent('get_more_all', 'eventGetMoreAll');
    }

    /**
     * Список событий в ленте активности пользователя
     *
     */
    public function eventFollow() {
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            return parent::eventNotFound();
        }
        $this->sMenuItemSelect = 'follow';

        $oSkin = \E::Module('Skin')->getSkin(\E::Module('Viewer')->getConfigSkin());
        if ($oSkin && $oSkin->GetCompatible() == 'alto') {
            \E::Module('Viewer')->addWidget('right', 'activitySettings');
            \E::Module('Viewer')->addWidget('right', 'activityFriends');
            \E::Module('Viewer')->addWidget('right', 'activityUsers');
        } else {
            \E::Module('Viewer')->addWidget('right', 'streamConfig');
        }

        /**
         * Читаем события
         */
        $aEvents = \E::Module('Stream')->Read();
        \E::Module('Viewer')->assign(
            'bDisableGetMoreButton',
            \E::Module('Stream')->getCountByReaderId($this->oUserCurrent->getId()) < \C::get('module.stream.count_default')
        );
        \E::Module('Viewer')->assign('aStreamEvents', $aEvents);
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assign('iStreamLastId', $oEvenLast->getId());
        }
        return null;
    }

    /**
     * Список событий в общей ленте активности сайта
     *
     */
    public function eventAll() {

        $this->sMenuItemSelect = 'all';
        /**
         * Читаем события
         */
        $aEvents = \E::Module('Stream')->ReadAll();
        \E::Module('Viewer')->assign(
            'bDisableGetMoreButton', \E::Module('Stream')->getCountAll() < \C::get('module.stream.count_default')
        );
        \E::Module('Viewer')->assign('aStreamEvents', $aEvents);
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assign('iStreamLastId', $oEvenLast->getId());
        }
    }

    /**
     * Активаци/деактивация типа события
     *
     */
    public function eventSwitchEventType() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        if (!F::getRequest('type')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
        /**
         * Активируем/деактивируем тип
         */
        \E::Module('Stream')->SwitchUserEventType($this->oUserCurrent->getId(), F::getRequestStr('type'));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('stream_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * Погрузка событий (замена постраничности)
     *
     */
    public function eventGetMoreFollow() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        /**
         * Необходимо передать последний просмотренный ID событий
         */
        $iFromId = F::getRequestStr('iLastId');
        if (!$iFromId) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Получаем события
         */
        $aEvents = \E::Module('Stream')->Read(null, $iFromId);

        $aVars = [];

        $aVars['aStreamEvents'] = $aEvents;
        $aVars['sDateLast'] = F::getRequestStr('sDateLast');
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assignAjax('iStreamLastId', $oEvenLast->getId());
        }
        /**
         * Возвращаем данные в ajax ответе
         */
        \E::Module('Viewer')->assignAjax('result', \E::Module('Viewer')->fetch('actions/stream/action.stream.events.tpl', $aVars));
        \E::Module('Viewer')->assignAjax('events_count', count($aEvents));
    }

    /**
     * Погрузка событий для всего сайта
     *
     */
    public function eventGetMoreAll() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        /**
         * Необходимо передать последний просмотренный ID событий
         */
        $iFromId = F::getRequestStr('iLastId');
        if (!$iFromId) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Получаем события
         */
        $aEvents = \E::Module('Stream')->ReadAll(null, $iFromId);

        $aVars = array(
            'aStreamEvents' => $aEvents,
            'sDateLast'     => F::getRequestStr('sDateLast'),
        );
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assignAjax('iStreamLastId', $oEvenLast->getId());
        }
        /**
         * Возвращаем данные в ajax ответе
         */
        \E::Module('Viewer')->assignAjax('result', \E::Module('Viewer')->fetch('actions/stream/action.stream.events.tpl', $aVars));
        \E::Module('Viewer')->assignAjax('events_count', count($aEvents));
    }

    /**
     * Подгрузка событий для пользователя
     *
     */
    public function eventGetMoreUser()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }

        // * Необходимо передать последний просмотренный ID событий
        $iFromId = F::getRequestStr('iLastId');
        if (!$iFromId) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /** @var ModuleUser_EntityUser $oUser */
        if (!($oUser = \E::Module('User')->getUserById(\F::getRequestStr('iUserId')))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Получаем события
        $aEvents = \E::Module('Stream')->ReadByUserId($oUser->getId(), null, $iFromId);

        $aVars = array(
            'aStreamEvents' => $aEvents,
            'sDateLast'     => F::getRequestStr('sDateLast'),
        );
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assignAjax('iStreamLastId', $oEvenLast->getId());
        }

        // * Возвращаем данные в ajax ответе
        \E::Module('Viewer')->assignAjax('result', \E::Module('Viewer')->fetch('actions/stream/action.stream.events.tpl', $aVars));
        \E::Module('Viewer')->assignAjax('events_count', count($aEvents));
    }

    /**
     * Подписка на пользователя по ID
     *
     */
    public function eventSubscribe()
    {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        /**
         * Проверяем существование пользователя
         */
        if (!\E::Module('User')->getUserById(\F::getRequestStr('id'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
        if ($this->oUserCurrent->getId() == F::getRequestStr('id')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('stream_error_subscribe_to_yourself'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Подписываем на пользователя
         */
        \E::Module('Stream')->SubscribeUser($this->oUserCurrent->getId(), F::getRequestStr('id'));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('stream_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * Подписка на пользователя по логину
     *
     */
    public function eventSubscribeByLogin() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        $sUserLogin = $this->getPost('login');
        if (!$sUserLogin) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Проверяем существование пользователя
         */
        $oUser = \E::Module('User')->getUserByLogin($sUserLogin);
        if (!$oUser) {
            \E::Module('Message')->addError(
                \E::Module('Lang')->get('user_not_found', array('login' => htmlspecialchars(\F::getRequestStr('login')))),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        if ($this->oUserCurrent->getId() == $oUser->getId()) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('stream_error_subscribe_to_yourself'), \E::Module('Lang')->get('error'));
            return;
        }
        /**
         * Подписываем на пользователя
         */
        \E::Module('Stream')->SubscribeUser($this->oUserCurrent->getId(), $oUser->getId());
        \E::Module('Viewer')->assignAjax('uid', $oUser->getId());
        \E::Module('Viewer')->assignAjax('user_login', $oUser->getLogin());
        \E::Module('Viewer')->assignAjax('user_web_path', $oUser->getProfileUrl());
        \E::Module('Viewer')->assignAjax('user_avatar_48', $oUser->getAvatarUrl(48));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('userfeed_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * Отписка от пользователя
     *
     */
    public function eventUnsubscribe() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        /**
         * Пользователь с таким ID существует?
         */
        if (!\E::Module('User')->getUserById(\F::getRequestStr('id'))) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
        /**
         * Отписываем
         */
        \E::Module('Stream')->UnsubscribeUser($this->oUserCurrent->getId(), F::getRequestStr('id'));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('stream_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() {
        /**
         * Загружаем в шаблон необходимые переменные
         */
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
    }

}

// EOF