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
 * Обрабатывает пользовательские ленты контента
 *
 * @package actions
 * @since   1.0
 */
class ActionUserfeed extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent;

    protected $sMenuHeadItemSelect = 'blog';

    protected $sMenuSubItemSelect = 'feed';

    /**
     * Инициализация
     *
     */
    public function init() {

        // * Доступ только у авторизованных пользователей
        $this->oUserCurrent = \E::User();
        if (!$this->oUserCurrent) {
            parent::eventNotFound();
        }
        $this->setDefaultEvent('index');

        \E::Module('Viewer')->assign('sMenuItemSelect', 'feed');
    }

    /**
     * Регистрация евентов
     *
     */
    protected function registerEvent()
    {
        $this->addEvent('index', ['EventIndex', 'index']);
        $this->addEventPreg('/^track$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTrack');
        $this->addEventPreg('/^track$/i', '/^new$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventTrackNew');
        $this->addEvent('subscribe', 'eventSubscribe');
        $this->addEvent('subscribeByLogin', 'eventSubscribeByLogin');
        $this->addEvent('unsubscribe', 'eventUnSubscribe');
        $this->addEvent('get_more', 'eventGetMore');
    }

    /**
     * Выводит ленту контента(топики) для пользователя
     *
     */
    public function eventIndex() {

        // * Получаем топики
        $aTopics = \E::Module('Userfeed')->Read($this->oUserCurrent->getId());

        // * Вызов хуков
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        if (count($aTopics)) {
            \E::Module('Viewer')->assign('iUserfeedLastId', end($aTopics)->getId());
        }
        if (count($aTopics) < \C::get('module.userfeed.count_default')) {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', TRUE);
        } else {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', FALSE);
        }
        $this->setTemplateAction('list');
    }

    /**
     * Выводит ленту контента(топики) для пользователя
     *
     */
    public function eventTrack() {

        $this->sMenuSubItemSelect = 'track';

        // * Получаем топики
        $aResult = \E::Module('Userfeed')->Trackread($this->oUserCurrent->getId(), 1, \C::get('module.userfeed.count_default'));
        $aTopics = $aResult['collection'];

        // * Вызов хуков
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));

        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('sFeedType', 'track');
        if (count($aTopics)) {
            \E::Module('Viewer')->assign('iUserfeedLastId', 1);
        }
        if ($aResult['count'] < \C::get('module.userfeed.count_default')) {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', TRUE);
        } else {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', FALSE);
        }

        $this->setTemplateAction('track');
    }

    /**
     * Выводит ленту контента(только топики содержащие новые комментарии) для пользователя
     *
     */
    public function eventTrackNew() {

        $this->sMenuSubItemSelect = 'track_new';

        // * Получаем топики
        $aResult = \E::Module('Userfeed')->Trackread($this->oUserCurrent->getId(), 1, \C::get('module.userfeed.count_default'), TRUE);
        $aTopics = $aResult['collection'];

        // * Вызов хуков
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));

        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('sFeedType', 'track_new');
        if (count($aTopics)) {
            \E::Module('Viewer')->assign('iUserfeedLastId', 1);
        }
        if ($aResult['count'] < \C::get('module.userfeed.count_default')) {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', TRUE);
        } else {
            \E::Module('Viewer')->assign('bDisableGetMoreButton', FALSE);
        }

        $this->setTemplateAction('track');
    }

    /**
     * Подгрузка ленты топиков (замена постраничности)
     *
     */
    public function eventGetMore() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Проверяем последний просмотренный ID топика
        $iFromId = F::getRequestInt('last_id');
        if (!$iFromId) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));

            return;
        }

        // * Получаем топики
        $sTrackType = F::getRequestStr('type', FALSE);
        if ($sTrackType) {
            $aResult = \E::Module('Userfeed')->Trackread($this->oUserCurrent->getId(), ++$iFromId, \C::get('module.userfeed.count_default'), ($sTrackType == 'track_new' ? TRUE : FALSE));
            $aTopics = $aResult['collection'];
        } else {
            $aTopics = \E::Module('Userfeed')->Read($this->oUserCurrent->getId(), NULL, $iFromId);
        }

        // * Вызов хуков
        \HookManager::run('topics_list_show', array('aTopics' => $aTopics));

        // * Загружаем данные в ajax ответ
        $aVars = array(
            'aTopics' => $aTopics,
        );
        \E::Module('Viewer')->assignAjax('result', \E::Module('Viewer')->fetch('topics/topic.list.tpl', $aVars));
        \E::Module('Viewer')->assignAjax('topics_count', count($aTopics));

        if (count($aTopics)) {
            if ($sTrackType) {
                \E::Module('Viewer')->assignAjax('iUserfeedLastId', $iFromId);
            } else {
                \E::Module('Viewer')->assignAjax('iUserfeedLastId', end($aTopics)->getId());
            }
        }
    }

    /**
     * Подписка на контент блога или пользователя
     *
     */
    public function eventSubscribe() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Проверяем наличие ID блога или пользователя
        if (!F::getRequest('id')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
        $sType = F::getRequestStr('type');
        $iType = null;

        // * Определяем тип подписки
        switch ($sType) {
            case 'blog':
            case 'blogs':
                $iType = ModuleUserfeed::SUBSCRIBE_TYPE_BLOG;

                // * Проверяем существование блога
                if (!\E::Module('Blog')->getBlogById(\F::getRequestStr('id'))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                break;
            case 'user':
            case 'users':
                $iType = ModuleUserfeed::SUBSCRIBE_TYPE_USER;

                // * Проверяем существование пользователя
                if (!\E::Module('User')->getUserById(\F::getRequestStr('id'))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    return;
                }
                if ($this->oUserCurrent->getId() == F::getRequestStr('id')) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('userfeed_error_subscribe_to_yourself'), \E::Module('Lang')->get('error')
                    );
                    return;
                }
                break;
            default:
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
        }

        // * Подписываем
        \E::Module('Userfeed')->SubscribeUser($this->oUserCurrent->getId(), $iType, F::getRequestStr('id'));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('userfeed_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * Подписка на пользвователя по логину
     *
     */
    public function eventSubscribeByLogin() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Передан ли логин
        $sUserLogin = $this->getPost('login');
        if (!$sUserLogin) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем существование прользователя
        $oUser = \E::Module('User')->getUserByLogin($sUserLogin);
        if (!$oUser) {
            \E::Module('Message')->addError(
                \E::Module('Lang')->get('user_not_found', array('login' => htmlspecialchars(\F::getRequestStr('login')))),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Не даем подписаться на самого себя
        if ($this->oUserCurrent->getId() == $oUser->getId()) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('userfeed_error_subscribe_to_yourself'), \E::Module('Lang')->get('error'));
            return;
        }

        $aData = \E::Module('Userfeed')->getUserSubscribes($this->oUserCurrent->getId(), ModuleUserfeed::SUBSCRIBE_TYPE_USER, $oUser->getId());
        if (isset($aData['user'][$oUser->getId()])) {
            // Already subscribed
            \E::Module('Message')->addError(\E::Module('Lang')->get('userfeed_subscribes_already_subscribed'), \E::Module('Lang')->get('error'));
        } else {
            // * Подписываем
            \E::Module('Userfeed')->SubscribeUser($this->oUserCurrent->getId(), ModuleUserfeed::SUBSCRIBE_TYPE_USER, $oUser->getId());

            // * Загружаем данные ajax ответ
            \E::Module('Viewer')->assignAjax('uid', $oUser->getId());
            \E::Module('Viewer')->assignAjax('user_id', $oUser->getId());
            \E::Module('Viewer')->assignAjax('user_login', $oUser->getLogin());
            \E::Module('Viewer')->assignAjax('user_name', $oUser->getDisplayName());
            \E::Module('Viewer')->assignAjax('user_profile_url', $oUser->getProfileUrl());
            \E::Module('Viewer')->assignAjax('user_avatar', $oUser->getAvatarUrl(24));
            \E::Module('Viewer')->assignAjax('lang_error_msg', \E::Module('Lang')->get('userfeed_subscribes_already_subscribed'));
            \E::Module('Viewer')->assignAjax('lang_error_title', \E::Module('Lang')->get('error'));
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('userfeed_subscribes_updated'), \E::Module('Lang')->get('attention'));
        }

    }

    /**
     * Отписка от блога или пользователя
     *
     */
    public function eventUnsubscribe() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        if (!F::getRequest('id')) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $sType = F::getRequestStr('type');
        $iType = null;

        // * Определяем от чего отписываемся
        switch ($sType) {
            case 'blogs':
            case 'blog':
                $iType = ModuleUserfeed::SUBSCRIBE_TYPE_BLOG;
                break;
            case 'users':
            case 'user':
                $iType = ModuleUserfeed::SUBSCRIBE_TYPE_USER;
                break;
            default:
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
        }

        // * Отписываем пользователя
        \E::Module('Userfeed')->UnsubscribeUser($this->oUserCurrent->getId(), $iType, F::getRequestStr('id'));
        \E::Module('Message')->addNotice(\E::Module('Lang')->get('userfeed_subscribes_updated'), \E::Module('Lang')->get('attention'));
    }

    /**
     * При завершении экшена загружаем в шаблон необходимые переменные
     *
     */
    public function eventShutdown() {

        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
        /**
         * Подсчитываем новые топики
         */
        $iCountTopicsCollectiveNew=E::Module('Topic')->getCountTopicsCollectiveNew();
        $iCountTopicsPersonalNew=E::Module('Topic')->getCountTopicsPersonalNew();
        $iCountTopicsNew=$iCountTopicsCollectiveNew+$iCountTopicsPersonalNew;
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('iCountTopicsCollectiveNew',$iCountTopicsCollectiveNew);
        \E::Module('Viewer')->assign('iCountTopicsPersonalNew',$iCountTopicsPersonalNew);
        \E::Module('Viewer')->assign('iCountTopicsNew',$iCountTopicsNew);
    }
}

// EOF