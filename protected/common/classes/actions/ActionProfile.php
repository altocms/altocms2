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
 * Экшен обработки профайла юзера, т.е. УРЛ вида /profile/login/
 *
 * @package actions
 * @since   1.0
 */
class ActionProfile extends Action {
    /**
     * Объект юзера чей профиль мы смотрим
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserProfile;
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'people';
    /**
     * Субменю
     *
     * @var string
     */
    protected $sMenuSubItemSelect = '';
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent;

    /**
     * Инициализация
     */
    public function init() {

        $this->oUserCurrent = \E::User();
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() {

        $this->addEvent('friendoffer', 'eventFriendOffer');
        $this->addEvent('ajaxfriendadd', 'eventAjaxFriendAdd');
        $this->addEvent('ajaxfrienddelete', 'eventAjaxFriendDelete');
        $this->addEvent('ajaxfriendaccept', 'eventAjaxFriendAccept');
        $this->addEvent('ajax-note-save', 'eventAjaxNoteSave');
        $this->addEvent('ajax-note-remove', 'eventAjaxNoteRemove');

        $this->addEventPreg('/^.+$/i', '/^(whois)?$/i', 'eventWhois');
        $this->addEventPreg('/^.+$/i', '/^(info)?$/i', 'eventInfo');

        $this->addEventPreg('/^.+$/i', '/^wall$/i', '/^$/i', 'eventWall');
        $this->addEventPreg('/^.+$/i', '/^wall$/i', '/^add$/i', 'eventWallAdd');
        $this->addEventPreg('/^.+$/i', '/^wall$/i', '/^load$/i', 'eventWallLoad');
        $this->addEventPreg('/^.+$/i', '/^wall$/i', '/^load-reply$/i', 'eventWallLoadReply');
        $this->addEventPreg('/^.+$/i', '/^wall$/i', '/^remove$/i', 'eventWallRemove');

        $this->addEventPreg('/^.+$/i', '/^favourites$/i', '/^comments$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventFavouriteComments');
        $this->addEventPreg('/^.+$/i', '/^favourites$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventFavourite');
        $this->addEventPreg('/^.+$/i', '/^favourites$/i', '/^topics/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventFavourite');
        $this->addEventPreg('/^.+$/i', '/^favourites$/i', '/^topics/i', '/^tag/i', '/^.+/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventFavouriteTopicsTag');

        $this->addEventPreg('/^.+$/i', '/^created/i', '/^notes/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCreatedNotes');
        $this->addEventPreg('/^.+$/i', '/^created/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCreatedTopics');
        $this->addEventPreg('/^.+$/i', '/^created/i', '/^topics/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCreatedTopics');
        $this->addEventPreg('/^.+$/i', '/^created/i', '/^comments$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCreatedComments');
        $this->addEventPreg('/^.+$/i', '/^created/i', '/^photos$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventCreatedPhotos');

        $this->addEventPreg('/^.+$/i', '/^friends/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventFriends');
        $this->addEventPreg('/^.+$/i', '/^stream/i', '/^$/i', 'eventStream');

        $this->addEventPreg('/^changemail$/i', '/^confirm-from/i', '/^\w{32}$/i', 'eventChangemailConfirmFrom');
        $this->addEventPreg('/^changemail$/i', '/^confirm-to/i', '/^\w{32}$/i', 'eventChangemailConfirmTo');
    }

    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Проверка корректности профиля
     */
    protected function CheckUserProfile() {

        // * Проверяем есть ли такой юзер
        if (preg_match('/^(id|login)\-(.+)$/i', $this->sCurrentEvent, $aMatches)) {
            if ($aMatches[1] == 'id') {
                $this->oUserProfile = \E::Module('User')->getUserById($aMatches[2]);
            } else {
                $this->oUserProfile = \E::Module('User')->getUserByLogin($aMatches[2]);
            }
        } else {
            $this->oUserProfile = \E::Module('User')->getUserByLogin($this->sCurrentEvent);
        }

        return $this->oUserProfile;
    }

    /**
     * Чтение активности пользователя (stream)
     */
    public function eventStream() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        /**
         * Читаем события
         */
        $aEvents = \E::Module('Stream')->ReadByUserId($this->oUserProfile->getId());
        \E::Module('Viewer')->assign(
            'bDisableGetMoreButton',
            \E::Module('Stream')->getCountByUserId($this->oUserProfile->getId()) < \C::get('module.stream.count_default')
        );
        \E::Module('Viewer')->assign('aStreamEvents', $aEvents);
        if (count($aEvents)) {
            $oEvenLast = end($aEvents);
            \E::Module('Viewer')->assign('iStreamLastId', $oEvenLast->getId());
        }
        $this->setTemplateAction('stream');
    }

    /**
     * Список друзей пользователей
     */
    public function eventFriends() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        /**
         * Получаем список комментов
         */
        $aResult = \E::Module('User')->getUsersFriend(
            $this->oUserProfile->getId(), $iPage, \C::get('module.user.per_page')
        );
        $aFriends = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.user.per_page'), \C::get('pagination.pages.count'),
            $this->oUserProfile->getUserUrl() . 'friends'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aFriends', $aFriends);
        \E::Module('Viewer')->addHtmlTitle(
            \E::Module('Lang')->get('user_menu_profile_friends') . ' ' . $this->oUserProfile->getLogin()
        );

        $this->setTemplateAction('friends');
    }

    /**
     * Список топиков пользователя
     */
    public function eventCreatedTopics() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'topics';
        /**
         * Передан ли номер страницы
         */
        if ($this->getParamEventMatch(1, 0) == 'topics') {
            $iPage = $this->getParamEventMatch(2, 2) ? $this->getParamEventMatch(2, 2) : 1;
        } else {
            $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        }
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsPersonalByUser(
            $this->oUserProfile->getId(), 1, $iPage, \C::get('module.topic.per_page')
        );
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
            $this->oUserProfile->getUserUrl() . 'created/topics'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_publication') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_publication_blog'));
        \E::Module('Viewer')->setHtmlRssAlternate(
            R::getLink('rss') . 'personal_blog/' . $this->oUserProfile->getLogin() . '/',
            $this->oUserProfile->getLogin()
        );
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('created_topics');
    }

    /**
     * Вывод комментариев пользователя
     */
    public function eventCreatedComments() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'comments';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(2, 2) ? $this->getParamEventMatch(2, 2) : 1;
        /**
         * Получаем список комментов
         */
        $aResult = \E::Module('Comment')->getCommentsByUserId(
            $this->oUserProfile->getId(), 'topic', $iPage, \C::get('module.comment.per_page')
        );
        $aComments = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.comment.per_page'), \C::get('pagination.pages.count'),
            $this->oUserProfile->getUserUrl() . 'created/comments'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aComments', $aComments);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_publication') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_publication_comment'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('created_comments');
    }


    /**
     * Вывод фотографий пользователя пользователя.
     * В шаблоне в переменной oUserImagesInfo уже есть группы фотографий
     */
    public function eventCreatedPhotos() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'photos';

        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_publication') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('insertimg_images'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('created_photos');
    }

    /**
     * Выводит список избранноего юзера
     *
     */
    public function eventFavourite() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'topics';
        /**
         * Передан ли номер страницы
         */
        if ($this->getParamEventMatch(1, 0) == 'topics') {
            $iPage = $this->getParamEventMatch(2, 2) ? $this->getParamEventMatch(2, 2) : 1;
        } else {
            $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        }
        /**
         * Получаем список избранных топиков
         */
        $aResult = \E::Module('Topic')->getTopicsFavouriteByUserId(
            $this->oUserProfile->getId(), $iPage, \C::get('module.topic.per_page')
        );
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
            $this->oUserProfile->getUserUrl() . 'favourites/topics'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile_favourites'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('favourite_topics');
    }

    /**
     * Список топиков из избранного по тегу
     */
    public function eventFavouriteTopicsTag() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }

        // * Пользователь авторизован и просматривает свой профиль?
        if (!$this->oUserCurrent || $this->oUserProfile->getId() != $this->oUserCurrent->getId()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'topics';
        $sTag = F::UrlDecode($this->getParamEventMatch(3, 0), true);

        // * Передан ли номер страницы
        $iPage = $this->getParamEventMatch(4, 2) ? $this->getParamEventMatch(4, 2) : 1;

        // * Получаем список избранных топиков
        $aResult = \E::Module('Favourite')->getTags(
            array('target_type' => 'topic', 'user_id' => $this->oUserProfile->getId(), 'text' => $sTag),
            array('target_id' => 'desc'), $iPage, \C::get('module.topic.per_page')
        );
        $aTopicId = [];
        foreach ($aResult['collection'] as $oTag) {
            $aTopicId[] = $oTag->getTargetId();
        }
        $aTopics = \E::Module('Topic')->getTopicsAdditionalData($aTopicId);

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            $this->oUserProfile->getUserUrl() . 'favourites/topics/tag/' . htmlspecialchars($sTag)
        );

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('sFavouriteTag', htmlspecialchars($sTag));
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile_favourites'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('favourite_topics');
    }

    /**
     * Выводит список избранноего юзера
     *
     */
    public function eventFavouriteComments() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'comments';
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(2, 2) ? $this->getParamEventMatch(2, 2) : 1;
        /**
         * Получаем список избранных комментариев
         */
        $aResult = \E::Module('Comment')->getCommentsFavouriteByUserId(
            $this->oUserProfile->getId(), $iPage, \C::get('module.comment.per_page')
        );
        $aComments = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.comment.per_page'), \C::get('pagination.pages.count'),
            $this->oUserProfile->getUserUrl() . 'favourites/comments'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aComments', $aComments);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile_favourites_comments'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('favourite_comments');
    }

    protected function _filterBlogs($aBlogs) {

        if (!$aBlogs || E::isAdmin() || E::userId() == $this->oUserProfile->getId()) {
            return $aBlogs;
        } else {
            // Blog types for guest and all users
            $aBlogTypes = \E::Module('Blog')->getOpenBlogTypes();
            foreach ($aBlogs as $iBlogId => $oBlog) {
                if (!in_array($oBlog->getType(), $aBlogTypes)) {
                    unset($aBlogs[$iBlogId]);
                }
            }
        }
        return $aBlogs;
    }

    protected function _filterBlogUsers($aBlogUsers) {

        if (!$aBlogUsers || E::isAdmin() || E::userId() == $this->oUserProfile->getId()) {
            return $aBlogUsers;
        } else {
            // Blog types for guest and all users
            $aBlogTypes = \E::Module('Blog')->getOpenBlogTypes();
            foreach ($aBlogUsers as $n => $oBlogUser) {
                if (!in_array($oBlogUser->getBlog()->getType(), $aBlogTypes)) {
                    unset($aBlogUsers[$n]);
                }
            }
        }
        return $aBlogUsers;
    }

    /**
     * Показывает инфу профиля
     *
     */
    public function eventInfo() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'main';

        // * Получаем список друзей
        $aUsersFriend = \E::Module('User')->getUsersFriend($this->oUserProfile->getId(), 1, \C::get('module.user.friend_on_profile'));

        // * Если активен режим инвайтов, то загружаем дополнительную информацию
        if (\C::get('general.reg.invite')) {
            // * Получаем список тех кого пригласил юзер
            $aUsersInvite = \E::Module('User')->getUsersInvite($this->oUserProfile->getId());
            \E::Module('Viewer')->assign('aUsersInvite', $aUsersInvite);

            // * Получаем того юзера, кто пригласил текущего
            $oUserInviteFrom = \E::Module('User')->getUserInviteFrom($this->oUserProfile->getId());
            \E::Module('Viewer')->assign('oUserInviteFrom', $oUserInviteFrom);
        }
        // * Получаем список юзеров блога
        $aBlogUsers = $this->_filterBlogUsers(
            \E::Module('Blog')->getBlogUsersByUserId($this->oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_MEMBER)
        );
        $aBlogModerators = $this->_filterBlogUsers(
            \E::Module('Blog')->getBlogUsersByUserId($this->oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_MODERATOR)
        );
        $aBlogAdministrators = $this->_filterBlogUsers(
            \E::Module('Blog')->getBlogUsersByUserId($this->oUserProfile->getId(), ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR)
        );

        // * Получаем список блогов которые создал юзер
        $aBlogsOwner = \E::Module('Blog')->getBlogsByOwnerId($this->oUserProfile->getId());
        $aBlogsOwner = $this->_filterBlogs($aBlogsOwner);

        // * Получаем список контактов
        $aUserFields = \E::Module('User')->getUserFieldsValues($this->oUserProfile->getId());

        // * Вызов хуков
        \HookManager::run('profile_whois_show', array("oUserProfile" => $this->oUserProfile));

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aBlogUsers', $aBlogUsers);
        \E::Module('Viewer')->assign('aBlogModerators', $aBlogModerators);
        \E::Module('Viewer')->assign('aBlogAdministrators', $aBlogAdministrators);
        \E::Module('Viewer')->assign('aBlogsOwner', $aBlogsOwner);
        \E::Module('Viewer')->assign('aUsersFriend', $aUsersFriend['collection']);
        \E::Module('Viewer')->assign('aUserFields', $aUserFields);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile_whois'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('info');
    }

    /**
     * LS-comatibility
     *
     * @return string
     */
    public function eventWhois() {

        return $this->eventInfo();
    }

    /**
     * Отображение стены пользователя
     */
    public function eventWall() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }

        // * Получаем записи стены
        $aWallItems = \E::Module('Wall')->getWall(
            array('wall_user_id' => $this->oUserProfile->getId(), 'pid' => 0), array('id' => 'desc'), 1,
            \C::get('module.wall.per_page')
        );
        \E::Module('Viewer')->assign('aWallItems', $aWallItems['collection']);
        \E::Module('Viewer')->assign('iCountWall', $aWallItems['count']);

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('wall');
    }

    /**
     * Добавление записи на стену
     */
    public function eventWallAdd() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            return parent::eventNotFound();
        }
        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }

        // * Создаем запись
        /** @var ModuleWall_EntityWall $oWall */
        $oWall = \E::getEntity('Wall');
        $oWall->_setValidateScenario('add');
        $oWall->setWallUserId($this->oUserProfile->getId());
        $oWall->setUserId($this->oUserCurrent->getId());
        $oWall->setText(\F::getRequestStr('sText'));
        $oWall->setPid(\F::getRequestStr('iPid'));

        \HookManager::run('wall_add_validate_before', array('oWall' => $oWall));
        if ($oWall->_validate()) {

            // * Экранируем текст и добавляем запись в БД
            $oWall->setText(\E::Module('Text')->Parse($oWall->getText()));
            \HookManager::run('wall_add_before', array('oWall' => $oWall));
            if ($this->AddWallMessage($oWall)) {
                \HookManager::run('wall_add_after', array('oWall' => $oWall));

                // * Отправляем уведомления
                if ($oWall->getWallUserId() != $oWall->getUserId()) {
                    \E::Module('Notify')->sendWallNew($oWall, $this->oUserCurrent);
                }
                if (($oWallParent = $oWall->GetPidWall()) && ($oWallParent->getUserId() != $oWall->getUserId())) {
                    \E::Module('Notify')->sendWallReply($oWallParent, $oWall, $this->oUserCurrent);
                }

                // * Добавляем событие в ленту
                \E::Module('Stream')->Write($oWall->getUserId(), 'add_wall', $oWall->getId());
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('wall_add_error'), \E::Module('Lang')->get('error'));
            }
        } else {
            \E::Module('Message')->addError($oWall->_getValidateError(), \E::Module('Lang')->get('error'));
        }
    }

    protected function AddWallMessage($oWall) {

        return \E::Module('Wall')->AddWall($oWall);
    }

    /**
     * Удаление записи со стены
     */
    public function eventWallRemove() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            return parent::eventNotFound();
        }
        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        /**
         * Получаем запись
         */
        if (!($oWall = \E::Module('Wall')->getWallById(\F::getRequestStr('iId')))) {
            return parent::eventNotFound();
        }
        /**
         * Если разрешено удаление - удаляем
         */
        if ($oWall->isAllowDelete()) {
            \E::Module('Wall')->DeleteWall($oWall);
            return;
        }
        return parent::eventNotFound();
    }

    /**
     * Ajax подгрузка сообщений стены
     */
    public function eventWallLoad() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }

        // * Формируем фильтр для запроса к БД
        $aFilter = array(
            'wall_user_id' => $this->oUserProfile->getId(),
            'pid'          => null
        );
        if (is_numeric(\F::getRequest('iIdLess'))) {
            $aFilter['id_less'] = F::getRequest('iIdLess');
        } elseif (is_numeric(\F::getRequest('iIdMore'))) {
            $aFilter['id_more'] = F::getRequest('iIdMore');
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('error'));
            return;
        }

        // * Получаем сообщения и формируем ответ
        $aWallItems = \E::Module('Wall')->getWall($aFilter, array('id' => 'desc'), 1, \C::get('module.wall.per_page'));
        \E::Module('Viewer')->assign('aWallItems', $aWallItems['collection']);

        \E::Module('Viewer')->assign(
            'oUserCurrent', $this->oUserCurrent
        ); // хак, т.к. к этому моменту текущий юзер не загружен в шаблон
        \E::Module('Viewer')->assign('aLang', \E::Module('Lang')->getLangMsg());

        \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('actions/profile/action.profile.wall_items.tpl'));
        \E::Module('Viewer')->assignAjax('iCountWall', $aWallItems['count']);
        \E::Module('Viewer')->assignAjax('iCountWallReturn', count($aWallItems['collection']));
    }

    /**
     * Подгрузка ответов на стене к сообщению
     */
    public function eventWallLoadReply() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        // пока оставлю здесь, логику не понял
        //if (!($oWall = \E::Module('Wall')->getWallById($this->GetPost('iPid'))) || $oWall->getPid()) {
        if (!($oWall = \E::Module('Wall')->getWallById($this->getPost('iPid')))) {
            return parent::eventNotFound();
        }

        // * Формируем фильтр для запроса к БД
        $aFilter = array(
            'wall_user_id' => $this->oUserProfile->getId(),
            'pid'          => $oWall->getId()
        );
        if (is_numeric(\F::getRequest('iIdLess'))) {
            $aFilter['id_less'] = F::getRequest('iIdLess');
        } elseif (is_numeric(\F::getRequest('iIdMore'))) {
            $aFilter['id_more'] = F::getRequest('iIdMore');
        } else {
            \E::Module('Message')->addError(\E::Module('Lang')->get('error'));
            return;
        }

        // * Получаем сообщения и формируем ответ. Необходимо вернуть все ответы, но ставим "разумное" ограничение
        $aWall = \E::Module('Wall')->getWall($aFilter, array('id' => 'asc'), 1, 300);
        \E::Module('Viewer')->assign('aLang', \E::Module('Lang')->getLangMsg());
        \E::Module('Viewer')->assign('aReplyWall', $aWall['collection']);
        \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('actions/profile/action.profile.wall_items_reply.tpl'));
        \E::Module('Viewer')->assignAjax('iCountWall', $aWall['count']);
        \E::Module('Viewer')->assignAjax('iCountWallReturn', count($aWall['collection']));
    }

    /**
     * Сохраняет заметку о пользователе
     */
    public function eventAjaxNoteSave() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        /**
         * Пользователь авторизован?
         */
        if (!$this->oUserCurrent) {
            return parent::eventNotFound();
        }

        // * Создаем заметку и проводим валидацию
        /** @var ModuleUser_EntityNote $oNote */
        $oNote = \E::getEntity('ModuleUser_EntityNote');
        $oNote->setTargetUserId(\F::getRequestStr('iUserId'));
        $oNote->setUserId($this->oUserCurrent->getId());
        $oNote->setText(\F::getRequestStr('text'));

        if ($oNote->_validate()) {
            /**
             * Экранируем текст и добавляем запись в БД
             */
            $oNote->setText(htmlspecialchars(strip_tags($oNote->getText())));
            if (\E::Module('User')->SaveNote($oNote)) {
                \E::Module('Viewer')->assignAjax('sText', nl2br($oNote->getText()));
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('user_note_save_error'), \E::Module('Lang')->get('error'));
            }
        } else {
            \E::Module('Message')->addError($oNote->_getValidateError(), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Удаляет заметку о пользователе
     */
    public function eventAjaxNoteRemove() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        if (!$this->oUserCurrent) {
            return parent::eventNotFound();
        }

        if (!($oUserTarget = \E::Module('User')->getUserById(\F::getRequestStr('iUserId')))) {
            return parent::eventNotFound();
        }
        if (!($oNote = \E::Module('User')->getUserNote($oUserTarget->getId(), $this->oUserCurrent->getId()))) {
            return parent::eventNotFound();
        }
        \E::Module('User')->DeleteUserNoteById($oNote->getId());
    }

    /**
     * Список созданных заметок
     */
    public function eventCreatedNotes() {

        if (!$this->CheckUserProfile()) {
            return parent::eventNotFound();
        }
        $this->sMenuSubItemSelect = 'notes';
        /**
         * Заметки может читать только сам пользователь
         */
        if (!$this->oUserCurrent || $this->oUserCurrent->getId() != $this->oUserProfile->getId()) {
            return parent::eventNotFound();
        }
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(2, 2) ? $this->getParamEventMatch(2, 2) : 1;
        /**
         * Получаем список заметок
         */
        $aResult = \E::Module('User')->getUserNotesByUserId(
            $this->oUserProfile->getId(), $iPage, \C::get('module.user.usernote_per_page')
        );
        $aNotes = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.user.usernote_per_page'),
            \C::get('pagination.pages.count'), $this->oUserProfile->getUserUrl() . 'created/notes'
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aNotes', $aNotes);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile') . ' ' . $this->oUserProfile->getLogin());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('user_menu_profile_notes'));
        /**
         * Устанавливаем шаблон вывода
         */
        $this->setTemplateAction('created_notes');
    }

    /**
     * Добавление пользователя в друзья, по отправленной заявке
     */
    public function eventFriendOffer() {

        /**
         * Из реквеста дешифруем ID пользователя
         */
        $sUserId = F::Xxtea_Decode(\F::getRequestStr('code'), \C::get('module.talk.encrypt'));
        if (!$sUserId) {
            return $this->eventNotFound();
        }
        list($sUserId,) = explode('_', $sUserId, 2);

        $sAction = $this->getParam(0);
        /**
         * Получаем текущего пользователя
         */
        if (!\E::isAuth()) {
            return $this->eventNotFound();
        }
        $this->oUserCurrent = \E::User();
        /**
         * Получаем объект пользователя приславшего заявку,
         * если пользователь не найден, переводим в раздел сообщений (Talk) -
         * так как пользователь мог перейти сюда либо из talk-сообщений,
         * либо из e-mail письма-уведомления
         */
        if (!$oUser = \E::Module('User')->getUserById($sUserId)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('user_not_found'), \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return;
        }
        /**
         * Получаем связь дружбы из базы данных.
         * Если связь не найдена либо статус отличен от OFFER,
         * переходим в раздел Talk и возвращаем сообщение об ошибке
         */
        $oFriend = \E::Module('User')->getFriend($this->oUserCurrent->getId(), $oUser->getId(), 0);
        if (!$oFriend || ModuleUser::USER_FRIEND_OFFER + ModuleUser::USER_FRIEND_NULL !== $oFriend->getFriendStatus()) {
            $sMessage = ($oFriend)
                ? \E::Module('Lang')->get('user_friend_offer_already_done')
                : \E::Module('Lang')->get('user_friend_offer_not_found');
            \E::Module('Message')->addError($sMessage, \E::Module('Lang')->get('error'), true);

            R::Location('talk');
            return;
        }
        /**
         * Устанавливаем новый статус связи
         */
        $oFriend->setStatusTo(
            ($sAction === 'accept')
                ? ModuleUser::USER_FRIEND_ACCEPT
                : ModuleUser::USER_FRIEND_REJECT
        );

        if (\E::Module('User')->UpdateFriend($oFriend)) {
            $sMessage = ($sAction == 'accept')
                ? \E::Module('Lang')->get('user_friend_add_ok')
                : \E::Module('Lang')->get('user_friend_offer_reject');

            \E::Module('Message')->addNoticeSingle($sMessage, \E::Module('Lang')->get('attention'), true);
            $this->NoticeFriendOffer($oUser, $sAction);
        } else {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('system_error'),
                \E::Module('Lang')->get('error'),
                true
            );
        }
        R::Location('talk');
    }

    /**
     * Подтверждение заявки на добавления в друзья
     */
    public function eventAjaxFriendAccept() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        $sUserId = F::getRequestStr('idUser', null, 'post');
        /**
         * Если пользователь не авторизирован, возвращаем ошибку
         */
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserCurrent = \E::User();
        /**
         * При попытке добавить в друзья себя, возвращаем ошибку
         */
        if ($this->oUserCurrent->getId() == $sUserId) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_add_self'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Если пользователь не найден, возвращаем ошибку
         */
        if (!$oUser = \E::Module('User')->getUserById($sUserId)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_not_found'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserProfile = $oUser;
        /**
         * Получаем статус дружбы между пользователями
         */
        $oFriend = \E::Module('User')->getFriend($oUser->getId(), $this->oUserCurrent->getId());
        /**
         * При попытке потдвердить ранее отклоненную заявку,
         * проверяем, чтобы изменяющий был принимающей стороной
         */
        if ($oFriend
            && ($oFriend->getStatusFrom() == ModuleUser::USER_FRIEND_OFFER
                || $oFriend->getStatusFrom() == ModuleUser::USER_FRIEND_ACCEPT)
            && ($oFriend->getStatusTo() == ModuleUser::USER_FRIEND_REJECT
                || $oFriend->getStatusTo() == ModuleUser::USER_FRIEND_NULL)
            && $oFriend->getUserTo() == $this->oUserCurrent->getId()
        ) {
            /**
             * Меняем статус с отвергнутое, на акцептованное
             */
            $oFriend->setStatusByUserId(ModuleUser::USER_FRIEND_ACCEPT, $this->oUserCurrent->getId());
            if (\E::Module('User')->UpdateFriend($oFriend)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_friend_add_ok'), \E::Module('Lang')->get('attention'));
                $this->NoticeFriendOffer($oUser, 'accept');
                /**
                 * Добавляем событие в ленту
                 */
                \E::Module('Stream')->Write($oFriend->getUserFrom(), 'add_friend', $oFriend->getUserTo());
                \E::Module('Stream')->Write($oFriend->getUserTo(), 'add_friend', $oFriend->getUserFrom());
                /**
                 * Добавляем пользователей к друг другу в ленту активности
                 */
                \E::Module('Stream')->SubscribeUser($oFriend->getUserFrom(), $oFriend->getUserTo());
                \E::Module('Stream')->SubscribeUser($oFriend->getUserTo(), $oFriend->getUserFrom());

                $oViewerLocal = $this->GetViewerLocal();
                $oViewerLocal->assign('oUserFriend', $oFriend);
                \E::Module('Viewer')->assignAjax('sToggleText', $oViewerLocal->fetch('actions/profile/action.profile.friend_item.tpl'));

            } else {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('system_error'),
                    \E::Module('Lang')->get('error')
                );
            }
            return;
        }

        \E::Module('Message')->addErrorSingle(
            \E::Module('Lang')->get('system_error'),
            \E::Module('Lang')->get('error')
        );
        return;
    }

    /**
     * Отправляет пользователю Talk уведомление о принятии или отклонении его заявки
     *
     * @param ModuleUser_EntityUser $oUser
     * @param string                $sAction
     *
     * @return bool
     */
    protected function NoticeFriendOffer($oUser, $sAction) {
        /**
         * Проверяем допустимость действия
         */
        if (!in_array($sAction, array('accept', 'reject'))) {
            return false;
        }
        /**
         * Проверяем настройки (нужно ли отправлять уведомление)
         */
        if (!Config::get("module.user.friend_notice.{$sAction}")) {
            return false;
        }

        $sTitle = \E::Module('Lang')->get("user_friend_{$sAction}_notice_title");
        $sText = \E::Module('Lang')->get(
            "user_friend_{$sAction}_notice_text",
            array(
                 'login' => $this->oUserCurrent->getLogin(),
            )
        );
        $oTalk = \E::Module('Talk')->SendTalk($sTitle, $sText, $this->oUserCurrent, array($oUser), false, false);
        \E::Module('Talk')->DeleteTalkUserByArray($oTalk->getId(), $this->oUserCurrent->getId());
    }

    /**
     * Обработка Ajax добавления в друзья
     */
    public function eventAjaxFriendAdd() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        $sUserId = F::getRequestStr('idUser');
        $sUserText = F::getRequestStr('userText', '');
        /**
         * Если пользователь не авторизирован, возвращаем ошибку
         */
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserCurrent = \E::User();
        /**
         * При попытке добавить в друзья себя, возвращаем ошибку
         */
        if ($this->oUserCurrent->getId() == $sUserId) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_add_self'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Если пользователь не найден, возвращаем ошибку
         */
        if (!$oUser = \E::Module('User')->getUserById($sUserId)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_not_found'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserProfile = $oUser;
        /**
         * Получаем статус дружбы между пользователями
         */
        $oFriend = \E::Module('User')->getFriend($oUser->getId(), $this->oUserCurrent->getId());
        /**
         * Если связи ранее не было в базе данных, добавляем новую
         */
        if (!$oFriend) {
            $this->SubmitAddFriend($oUser, $sUserText, $oFriend);
            return;
        }
        /**
         * Если статус связи соответствует статусам отправленной и акцептованной заявки,
         * то предупреждаем что этот пользователь уже является нашим другом
         */
        if ($oFriend->getFriendStatus() == ModuleUser::USER_FRIEND_OFFER + ModuleUser::USER_FRIEND_ACCEPT) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_already_exist'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Если пользователь ранее отклонил нашу заявку,
         * возвращаем сообщение об ошибке
         */
        if ($oFriend->getUserFrom() == $this->oUserCurrent->getId()
            && $oFriend->getStatusTo() == ModuleUser::USER_FRIEND_REJECT
        ) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_offer_reject'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Если дружба была удалена, то проверяем кто ее удалил
         * и разрешаем восстановить только удалившему
         */
        if ($oFriend->getFriendStatus() > ModuleUser::USER_FRIEND_CANCEL
            && $oFriend->getFriendStatus() < ModuleUser::USER_FRIEND_REJECT
        ) {
            /**
             * Определяем статус связи текущего пользователя
             */
            $iStatusCurrent = $oFriend->getStatusByUserId($this->oUserCurrent->getId());

            if ($iStatusCurrent == ModuleUser::USER_FRIEND_CANCEL) {
                /**
                 * Меняем статус с удаленного, на акцептованное
                 */
                $oFriend->setStatusByUserId(ModuleUser::USER_FRIEND_ACCEPT, $this->oUserCurrent->getId());
                if (\E::Module('User')->UpdateFriend($oFriend)) {
                    /**
                     * Добавляем событие в ленту
                     */
                    \E::Module('Stream')->Write($oFriend->getUserFrom(), 'add_friend', $oFriend->getUserTo());
                    \E::Module('Stream')->Write($oFriend->getUserTo(), 'add_friend', $oFriend->getUserFrom());
                    \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_friend_add_ok'), \E::Module('Lang')->get('attention'));

                    $oViewerLocal = $this->GetViewerLocal();
                    $oViewerLocal->assign('oUserFriend', $oFriend);
                    \E::Module('Viewer')->assignAjax(
                        'sToggleText', $oViewerLocal->fetch('actions/profile/action.profile.friend_item.tpl')
                    );

                } else {
                    \E::Module('Message')->addErrorSingle(
                        \E::Module('Lang')->get('system_error'),
                        \E::Module('Lang')->get('error')
                    );
                }
                return;
            } else {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('user_friend_add_deleted'),
                    \E::Module('Lang')->get('error')
                );
                return;
            }
        }
    }

    /**
     * Функция создает локальный объект вьювера для рендеринга html-объектов в ajax запросах
     *
     * @return ModuleViewer
     */
    protected function GetViewerLocal() {
        /**
         * Получаем HTML код inject-объекта
         */
        $oViewerLocal =  \E::Module('Viewer')->getLocalViewer();
        $oViewerLocal->assign('oUserCurrent', $this->oUserCurrent);
        $oViewerLocal->assign('oUserProfile', $this->oUserProfile);

        $oViewerLocal->assign('USER_FRIEND_NULL', ModuleUser::USER_FRIEND_NULL);
        $oViewerLocal->assign('USER_FRIEND_OFFER', ModuleUser::USER_FRIEND_OFFER);
        $oViewerLocal->assign('USER_FRIEND_ACCEPT', ModuleUser::USER_FRIEND_ACCEPT);
        $oViewerLocal->assign('USER_FRIEND_REJECT', ModuleUser::USER_FRIEND_REJECT);
        $oViewerLocal->assign('USER_FRIEND_DELETE', ModuleUser::USER_FRIEND_CANCEL);

        return $oViewerLocal;
    }

    /**
     * Обработка добавления в друзья
     *
     * @param ModuleUser_EntityUser $oUser
     * @param string                $sUserText
     * @param ModuleUser_EntityUser $oFriend
     *
     * @return bool
     */
    protected function submitAddFriend($oUser, $sUserText, $oFriend = null) {
        /**
         * Ограничения на добавления в друзья, т.к. приглашение отправляется в личку, то и ограничиваем по ней
         */
        if (!\E::Module('ACL')->canSendTalkTime($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_friend_add_time_limit'), \E::Module('Lang')->get('error'));
            return false;
        }
        /**
         * Обрабатываем текст заявки
         */
        $sUserText = \E::Module('Text')->Parse($sUserText);
        /**
         * Создаем связь с другом
         */
        /** @var ModuleUser_EntityFriend $oFriendNew */
        $oFriendNew = \E::getEntity('User_Friend');
        $oFriendNew->setUserTo($oUser->getId());
        $oFriendNew->setUserFrom($this->oUserCurrent->getId());

        // Добавляем заявку в друзья
        $oFriendNew->setStatusFrom(ModuleUser::USER_FRIEND_OFFER);
        $oFriendNew->setStatusTo(ModuleUser::USER_FRIEND_NULL);

        $bStateError = ($oFriend)
            ? !\E::Module('User')->UpdateFriend($oFriendNew)
            : !\E::Module('User')->AddFriend($oFriendNew);

        if (!$bStateError) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_friend_offer_send'), \E::Module('Lang')->get('attention'));

            $sTitle = \E::Module('Lang')->get(
                'user_friend_offer_title',
                array(
                     'login'  => $this->oUserCurrent->getLogin(),
                     'friend' => $oUser->getLogin()
                )
            );

            $sCode = $this->oUserCurrent->getId() . '_' . $oUser->getId();
            $sCode = F::Xxtea_Encode($sCode, \C::get('module.talk.encrypt'));

            $aPath = array(
                'accept' => R::getLink('profile') . 'friendoffer/accept/?code=' . $sCode,
                'reject' => R::getLink('profile') . 'friendoffer/reject/?code=' . $sCode
            );

            $sText = \E::Module('Lang')->get(
                'user_friend_offer_text',
                array(
                     'login'       => $this->oUserCurrent->getLogin(),
                     'accept_path' => $aPath['accept'],
                     'reject_path' => $aPath['reject'],
                     'user_text'   => $sUserText
                )
            );
            $oTalk = \E::Module('Talk')->SendTalk($sTitle, $sText, $this->oUserCurrent, array($oUser), false, false);
            /**
             * Отправляем пользователю заявку
             */
            \E::Module('Notify')->sendUserFriendNew(
                $oUser, $this->oUserCurrent, $sUserText,
                R::getLink('talk') . 'read/' . $oTalk->getId() . '/'
            );
            /**
             * Удаляем отправляющего юзера из переписки
             */
            \E::Module('Talk')->DeleteTalkUserByArray($oTalk->getId(), $this->oUserCurrent->getId());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }

        /**
         * Подписываем запрашивающего дружбу на
         */
        \E::Module('Stream')->SubscribeUser($this->oUserCurrent->getId(), $oUser->getId());

        $oViewerLocal = $this->GetViewerLocal();
        $oViewerLocal->assign('oUserFriend', $oFriendNew);
        \E::Module('Viewer')->assignAjax('sToggleText', $oViewerLocal->fetch('actions/profile/action.profile.friend_item.tpl'));
    }

    /**
     * Удаление пользователя из друзей
     */
    public function eventAjaxFriendDelete() {
        /**
         * Устанавливаем формат Ajax ответа
         */
        \E::Module('Viewer')->setResponseAjax('json');
        $sUserId = F::getRequestStr('idUser', null, 'post');
        /**
         * Если пользователь не авторизирован, возвращаем ошибку
         */
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserCurrent = \E::User();
        /**
         * При попытке добавить в друзья себя, возвращаем ошибку
         */
        if ($this->oUserCurrent->getId() == $sUserId) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_add_self'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Если пользователь не найден, возвращаем ошибку
         */
        if (!$oUser = \E::Module('User')->getUserById($sUserId)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_del_no'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        $this->oUserProfile = $oUser;
        /**
         * Получаем статус дружбы между пользователями.
         * Если статус не определен, или отличается от принятой заявки,
         * возвращаем ошибку
         */
        $oFriend = \E::Module('User')->getFriend($oUser->getId(), $this->oUserCurrent->getId());
        $aAllowedFriendStatus = array(ModuleUser::USER_FRIEND_ACCEPT + ModuleUser::USER_FRIEND_OFFER,
                                      ModuleUser::USER_FRIEND_ACCEPT + ModuleUser::USER_FRIEND_ACCEPT);
        if (!$oFriend || !in_array($oFriend->getFriendStatus(), $aAllowedFriendStatus)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_friend_del_no'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        /**
         * Удаляем из друзей
         */
        if (\E::Module('User')->DeleteFriend($oFriend)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_friend_del_ok'), \E::Module('Lang')->get('attention'));

            $oViewerLocal = $this->GetViewerLocal();
            $oViewerLocal->assign('oUserFriend', $oFriend);
            \E::Module('Viewer')->assignAjax('sToggleText', $oViewerLocal->fetch('actions/profile/action.profile.friend_item.tpl'));

            /**
             * Отправляем пользователю сообщение об удалении дружеской связи
             */
            if (\C::get('module.user.friend_notice.delete')) {
                $sText = \E::Module('Lang')->get(
                    'user_friend_del_notice_text',
                    array(
                         'login' => $this->oUserCurrent->getLogin(),
                    )
                );
                $oTalk = \E::Module('Talk')->SendTalk(
                    \E::Module('Lang')->get('user_friend_del_notice_title'),
                    $sText, $this->oUserCurrent,
                    array($oUser), false, false
                );
                \E::Module('Talk')->DeleteTalkUserByArray($oTalk->getId(), $this->oUserCurrent->getId());
            }
            return;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Обработка подтверждения старого емайла при его смене
     */
    public function eventChangemailConfirmFrom() {

        if (!($oChangemail = \E::Module('User')->getUserChangeMailByCodeFrom($this->getParamEventMatch(1, 0)))) {
            return parent::eventNotFound();
        }

        if ($oChangemail->getConfirmFrom() || strtotime($oChangemail->getDateExpired()) < time()) {
            return parent::eventNotFound();
        }

        $oChangemail->setConfirmFrom(1);
        \E::Module('User')->updateUserChangeMail($oChangemail);

        /**
         * Отправляем уведомление
         */
        $oUser = \E::Module('User')->getUserById($oChangemail->getUserId());
        \E::Module('Notify')->send(
            $oChangemail->getMailTo(),
            'user_changemail_to.tpl',
            \E::Module('Lang')->get('notify_subject_user_changemail'),
            array(
                 'oUser'       => $oUser,
                 'oChangemail' => $oChangemail,
            ),
            null,
            true
        );

        \E::Module('Viewer')->assign('sText', \E::Module('Lang')->get('settings_profile_mail_change_to_notice'));
        // Исправление ошибки смены email {@link https://github.com/altocms/altocms/issues/260}
        \E::Module('Viewer')->assign('oUserProfile', $oUser);
        $this->setTemplateAction('changemail_confirm');
    }

    /**
     * Обработка подтверждения нового емайла при смене старого
     */
    public function eventChangemailConfirmTo() {

        if (!($oChangemail = \E::Module('User')->getUserChangemailByCodeTo($this->getParamEventMatch(1, 0)))) {
            return parent::eventNotFound();
        }

        if (!$oChangemail->getConfirmFrom() || $oChangemail->getConfirmTo() || strtotime($oChangemail->getDateExpired()) < time()) {
            return parent::eventNotFound();
        }

        $oChangemail->setConfirmTo(1);
        $oChangemail->setDateUsed(\F::Now());
        \E::Module('User')->updateUserChangeMail($oChangemail);

        $oUser = \E::Module('User')->getUserById($oChangemail->getUserId());
        $oUser->setMail($oChangemail->getMailTo());
        \E::Module('User')->Update($oUser);

        /**
         * Меняем емайл в подписках
         */
        if ($oChangemail->getMailFrom()) {
            \E::Module('Subscribe')->ChangeSubscribeMail(
                $oChangemail->getMailFrom(), $oChangemail->getMailTo(), $oUser->getId()
            );
        }

        \E::Module('Viewer')->assign(
            'sText', \E::Module('Lang')->get(
                'settings_profile_mail_change_ok', array('mail' => htmlspecialchars($oChangemail->getMailTo()))
            )
        );
        // Исправление ошибки смены email {@link https://github.com/altocms/altocms/issues/260}
        \E::Module('Viewer')->assign('oUserProfile', $oUser);
        $this->setTemplateAction('changemail_confirm');
    }

    /**
     * Выполняется при завершении работы экшена
     */
    public function eventShutdown() {

        if (!$this->oUserProfile) {
            return;
        }
        $iProfileUserId = $this->oUserProfile->getId();

        // Get stats of various user publications topics, comments, images, etc. and stats of favourites
        $aProfileStats = \E::Module('User')->getUserProfileStats($iProfileUserId);

        // Получим информацию об изображениях пользователя
        /** @var ModuleMedia_EntityMediaCategory[] $aUserImagesInfo */
        //$aUserImagesInfo = \E::Module('Media')->getAllImageCategoriesByUserId($iProfileUserId);

        // * Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('oUserProfile', $this->oUserProfile);
        \E::Module('Viewer')->assign('aProfileStats', $aProfileStats);
        // unused
        //E::Module('Viewer')->Assign('aUserImagesInfo', $aUserImagesInfo);

        // * Заметка текущего пользователя о юзере
        if (\E::User()) {
            \E::Module('Viewer')->assign('oUserNote', $this->oUserProfile->getUserNote());
        }

        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);

        \E::Module('Viewer')->assign('USER_FRIEND_NULL', ModuleUser::USER_FRIEND_NULL);
        \E::Module('Viewer')->assign('USER_FRIEND_OFFER', ModuleUser::USER_FRIEND_OFFER);
        \E::Module('Viewer')->assign('USER_FRIEND_ACCEPT', ModuleUser::USER_FRIEND_ACCEPT);
        \E::Module('Viewer')->assign('USER_FRIEND_REJECT', ModuleUser::USER_FRIEND_REJECT);
        \E::Module('Viewer')->assign('USER_FRIEND_DELETE', ModuleUser::USER_FRIEND_CANCEL);

        // Old style skin compatibility
        \E::Module('Viewer')->assign('iCountTopicUser', $aProfileStats['count_topics']);
        \E::Module('Viewer')->assign('iCountCommentUser', $aProfileStats['count_comments']);
        \E::Module('Viewer')->assign('iCountTopicFavourite', $aProfileStats['favourite_topics']);
        \E::Module('Viewer')->assign('iCountCommentFavourite', $aProfileStats['favourite_comments']);
        \E::Module('Viewer')->assign('iCountNoteUser', $aProfileStats['count_usernotes']);
        \E::Module('Viewer')->assign('iCountWallUser', $aProfileStats['count_wallrecords']);

        \E::Module('Viewer')->assign('iPhotoCount', $aProfileStats['count_images']);
        \E::Module('Viewer')->assign('iCountCreated', $aProfileStats['count_created']);

        \E::Module('Viewer')->assign('iCountFavourite', $aProfileStats['count_favourites']);
        \E::Module('Viewer')->assign('iCountFriendsUser', $aProfileStats['count_friends']);
    }

}

// EOF