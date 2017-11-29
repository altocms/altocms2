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
 * Экшен обработки URL'ов вида /blog/
 *
 * @package actions
 * @since   1.0
 */
class ActionBlog extends Action 
{
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blog';

    /**
     * Какое меню активно
     *
     * @var string
     */
    protected $sMenuItemSelect = 'blog';

    /**
     * Какое подменю активно
     *
     * @var string
     */
    protected $sMenuSubItemSelect = 'good';

    /**
     * УРЛ блога который подставляется в меню
     *
     * @var string
     */
    protected $sMenuSubBlogUrl;

    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent;

    /**
     * Текущий блог
     *
     * @var ModuleBlog_EntityBlog|null
     */
    protected $oCurrentBlog;

    /**
     * Current topic
     *
     * @var ModuleTopic_EntityTopic
     */
    protected $oCurrentTopic;

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
     * Число новых топиков в конкретном блоге
     *
     * @var int
     */
    protected $iCountTopicsBlogNew = 0;

    /**
     * Число новых топиков
     *
     * @var int
     */
    protected $iCountTopicsNew = 0;

    /**
     * Named filter for topic list
     *
     * @var string
     */
    protected $sTopicFilter = '';

    protected $sTopicFilterPeriod;

    /**
     * Список URL с котрыми запрещено создавать блог
     *
     * @var array
     */
    protected $aBadBlogUrl
        = [
            'new', 'good', 'bad', 'discussed', 'top', 'edit', 'add', 'admin', 'delete', 'invite',
            'ajaxaddcomment', 'ajaxresponsecomment', 'ajaxgetcomment', 'ajaxupdatecomment',
            'ajaxaddbloginvite', 'ajaxrebloginvite', 'ajaxremovebloginvite',
            'ajaxbloginfo', 'ajaxblogjoin', 'request',
        ];

    /**
     * Типы блогов, доступные для создания
     *
     * @var
     */
    protected $aBlogTypes;

    protected $aMenuFilters = ['bad', 'new', 'all', 'discussed', 'top'];

    protected $sMenuDefault = 'good';

    /**
     * Инизиализация экшена
     *
     */
    public function init() 
    {
        //  Устанавливаем евент по дефолту, т.е. будем показывать хорошие топики из коллективных блогов
        $this->setDefaultEvent('good');
        $this->sMenuSubBlogUrl = R::getLink('blog');

        //  Достаём текущего пользователя
        $this->oUserCurrent = \E::User();

        //  Загружаем в шаблон JS текстовки
        \E::Module('Lang')->addLangJs(['blog_join', 'blog_leave']);

        $this->aBlogTypes = \E::Module('Blog')->getAllowBlogTypes($this->oUserCurrent, 'add');
    }

    /**
     * Регистрируем евенты, по сути определяем УРЛы вида /blog/.../
     *
     */
    protected function registerEvent() 
    {
        $this->addEvent('add', 'eventAddBlog');
        $this->addEvent('edit', 'eventEditBlog');
        $this->addEvent('delete', 'eventDeleteBlog');
        $this->addEventPreg('/^admin$/i', '/^\d+$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventAdminBlog');
        $this->addEvent('invite', 'eventInviteBlog');
        $this->addEvent('request', 'eventRequestBlog');

        $this->addEvent('ajaxaddcomment', 'AjaxAddComment');
        $this->addEvent('ajaxresponsecomment', 'AjaxResponseComment');
        $this->addEvent('ajaxgetcomment', 'AjaxGetComment');
        $this->addEvent('ajaxupdatecomment', 'AjaxUpdateComment');

        $this->addEvent('ajaxaddbloginvite', 'AjaxAddBlogInvite');
        $this->addEvent('ajaxrebloginvite', 'AjaxReBlogInvite');
        $this->addEvent('ajaxremovebloginvite', 'AjaxRemoveBlogInvite');

        $this->addEvent('ajaxbloginfo', 'AjaxBlogInfo');
        $this->addEvent('ajaxblogjoin', 'AjaxBlogJoin');

        $this->addEventPreg('/^(\d+)\.html$/i', ['EventShowTopic', 'topic']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^(\d+)\.html$/i', ['EventShowTopic', 'topic']);

        // в URL должен быть хоть один нецифровой символ
        $this->addEventPreg('/^([\w\-\_]*[a-z\-\_][\w\-\_]*)\.html$/i', ['EventShowTopicByUrl', 'topic']);

        $this->addEventPreg('/^[\w\-\_]+$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^bad$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^new$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^all$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^discussed$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        if (C::get('rating.enabled')) {
            $this->addEventPreg('/^[\w\-\_]+$/i', '/^top$/i', '/^(page([1-9]\d{0,5}))?$/i', ['EventShowBlog', 'blog']);
        }

        $this->addEventPreg('/^[\w\-\_]+$/i', '/^users$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowUsers');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Добавление нового блога
     *
     */
    public function eventAddBlog() 
    {
        //  Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_create'));

        //  Меню
        $this->sMenuSubItemSelect = 'add';
        $this->sMenuItemSelect = 'blog';

        //  Проверяем авторизован ли пользователь
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        //  check whether the user can create a blog?
        if (!\E::Module('ACL')->canCreateBlog($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_create_acl'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }
        \HookManager::run('blog_add_show');

        \E::Module('Viewer')->assign('aBlogTypes', $this->aBlogTypes);

        if (\F::isPost('submit_blog_add')) {
            // Запускаем проверку корректности ввода полей при добалении блога.
            if (!$this->checkBlogFields()) {
                return false;
            }

            //  Если всё ок то пытаемся создать блог
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = \E::getEntity('Blog');
            $oBlog->setOwnerId($this->oUserCurrent->getId());

            // issue 151 (https://github.com/altocms/altocms/issues/151)
            // Некорректная обработка названия блога
            // $oBlog->setTitle(strip_tags(\F::getRequestStr('blog_title')));
            $oBlog->setTitle(\E::Module('Text')->removeAllTags(\F::getRequestStr('blog_title')));

            // * Парсим текст на предмет разных HTML-тегов
            $sText = \E::Module('Text')->parse(\F::getRequestStr('blog_description'));
            $oBlog->setDescription($sText);
            $oBlog->setType(\F::getRequestStr('blog_type'));
            $oBlog->setDateAdd(\F::Now());
            $oBlog->setLimitRatingTopic((float)F::getRequestStr('blog_limit_rating_topic'));
            $oBlog->setUrl(\F::getRequestStr('blog_url'));
            $oBlog->setAvatar(null);

            // * Создаём блог
            \HookManager::run('blog_add_before', ['oBlog' => $oBlog]);
            if ($this->_addBlog($oBlog)) {
                \HookManager::run('blog_add_after', ['oBlog' => $oBlog]);

                // Читаем блог - это для получения полного пути блога,
                // если он в будущем будет зависит от других сущностей (компании, юзер и т.п.)
                $this->oCurrentBlog = $oBlog = \E::Module('Blog')->getBlogById($oBlog->getId());

                // Добавляем событие в ленту
                \E::Module('Stream')->write($oBlog->getOwnerId(), 'add_blog', $oBlog->getId());

                // Подписываем владельца блога на свой блог
                \E::Module('Userfeed')->SubscribeUser($oBlog->getOwnerId(), ModuleUserfeed::SUBSCRIBE_TYPE_BLOG, $oBlog->getId());

                R::Location($oBlog->getUrlFull());
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            }
        }
        return true;
    }

    /**
     * Добавдение блога
     *
     * @param $oBlog
     *
     * @return bool|ModuleBlog_EntityBlog
     */
    protected function _addBlog($oBlog) 
    {
        return \E::Module('Blog')->AddBlog($oBlog);
    }

    /**
     * Редактирование блога
     *
     */
    public function eventEditBlog() 
    {
        // Меню
        $this->sMenuSubItemSelect = '';
        $this->sMenuItemSelect = 'profile';

        // Передан ли в URL номер блога
        $sBlogId = $this->getParam(0);
        if (!$oBlog = \E::Module('Blog')->getBlogById($sBlogId)) {
            return parent::eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

        // Проверяем тип блога
        if ($oBlog->getType() === 'personal') {
            return parent::eventNotFound();
        }

        // Проверям, авторизован ли пользователь
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        // Проверка на право редактировать блог
        if (!\E::Module('ACL')->isAllowEditBlog($oBlog, $this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('not_access'));
            return R::redirect('error');
        }

        \HookManager::run('blog_edit_show', ['oBlog' => $oBlog]);

        // * Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle($oBlog->getTitle());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_edit'));

        \E::Module('Viewer')->assign('oBlogEdit', $oBlog);

        if (!isset($this->aBlogTypes[$oBlog->getType()])) {
            $this->aBlogTypes[$oBlog->getType()] = $oBlog->getBlogType();
        }
        \E::Module('Viewer')->assign('aBlogTypes', $this->aBlogTypes);

        // Устанавливаем шаблон для вывода
        $this->setTemplateAction('add');

        // Если нажали кнопку "Сохранить"
        if (\F::isPost('submit_blog_add')) {

            // Запускаем проверку корректности ввода полей при редактировании блога
            if (!$this->checkBlogFields($oBlog)) {
                return false;
            }

            // issue 151 (https://github.com/altocms/altocms/issues/151)
            // Некорректная обработка названия блога
            // $oBlog->setTitle(strip_tags(\F::getRequestStr('blog_title')));
            $oBlog->setTitle(\E::Module('Text')->removeAllTags(\F::getRequestStr('blog_title')));

            // Парсим описание блога
            $sText = \E::Module('Text')->parse(\F::getRequestStr('blog_description'));
            $oBlog->setDescription($sText);

            // Если меняется тип блога, фиксируем это
            if ($oBlog->getType() !== F::getRequestStr('blog_type')) {
                $oBlog->setOldType($oBlog->getType());
            }
            $oBlog->setType(\F::getRequestStr('blog_type'));
            $oBlog->setLimitRatingTopic((float)F::getRequestStr('blog_limit_rating_topic'));

            if ($this->oUserCurrent->isAdministrator() || $this->oUserCurrent->isModerator()) {
                $oBlog->setUrl(\F::getRequestStr('blog_url')); // разрешаем смену URL блога только админу
            }

            // Обновляем блог
            \HookManager::run('blog_edit_before', ['oBlog' => $oBlog]);
            if ($this->_updateBlog($oBlog)) {
                \HookManager::run('blog_edit_after', ['oBlog' => $oBlog]);
                R::Location($oBlog->getUrlFull());
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return R::redirect('error');
            }
        } else {

            // Загружаем данные в форму редактирования блога
            $_REQUEST['blog_title'] = $oBlog->getTitle();
            $_REQUEST['blog_url'] = $oBlog->getUrl();
            $_REQUEST['blog_type'] = $oBlog->getType();
            $_REQUEST['blog_description'] = $oBlog->getDescription();
            $_REQUEST['blog_limit_rating_topic'] = $oBlog->getLimitRatingTopic();
            $_REQUEST['blog_id'] = $oBlog->getId();
        }
        return true;
    }

    /**
     * Обновление блога
     *
     * @param ModuleBlog_EntityBlog $oBlog
     *
     * @return bool
     */
    protected function _updateBlog($oBlog) 
    {
        // Удалить аватар (для старых шаблонов)
        if (isset($_REQUEST['avatar_delete'])) {
            \E::Module('Blog')->DeleteBlogAvatar($oBlog);
            $oBlog->setAvatar(null);
        }

        $bResult = \E::Module('Blog')->UpdateBlog($oBlog);

        // Загрузка аватара (для старых шаблонов)
        if ($bResult && ($aUploadedFile = $this->getUploadedFile('avatar'))) {
            if ($sUrl = \E::Module('Blog')->UploadBlogAvatar($aUploadedFile, $oBlog)) {
                $oBlog->setAvatar($sUrl);
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_avatar_error'), \E::Module('Lang')->get('error'));
                return false;
            }
        }

        return $bResult;
    }

    /**
     * Management of blog's users
     *
     * @return string|null
     */
    public function eventAdminBlog() 
    {
        //  Меню
        $this->sMenuItemSelect = 'admin';
        $this->sMenuSubItemSelect = '';

        //  Проверяем передан ли в УРЛе номер блога
        $sBlogId = $this->getParam(0);
        if (!$oBlog = \E::Module('Blog')->getBlogById($sBlogId)) {
            return parent::eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

        //  Проверям авторизован ли пользователь
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        //  Проверка на право управлением пользователями блога
        if (!\E::Module('ACL')->IsAllowAdminBlog($oBlog, $this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('not_access'));
            return R::redirect('error');
        }

        //  Обрабатываем сохранение формы
        if (\F::isPost('submit_blog_admin')) {
            \E::Module('Security')->validateSendForm();

            $aUserRank = (array)F::getRequest('user_rank', []);
            foreach ($aUserRank as $sUserId => $sRank) {
                $sRank = (string)$sRank;
                $iUserId = (int)$sUserId;
                if (!$iUserId) {
                    continue;
                }
                if (!($oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $iUserId))) {
                    \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                    break;
                }

                //  Увеличиваем число читателей блога
                if (in_array($sRank, ['administrator', 'moderator', 'reader'])
                    && $oBlogUser->getUserRole() === ModuleBlog::BLOG_USER_ROLE_BAN
                ) {
                    $oBlog->setCountUser($oBlog->getCountUser() + 1);
                }

                switch ($sRank) {
                    case 'administrator':
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
                        break;
                    case 'moderator':
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_MODERATOR);
                        break;
                    case 'reader':
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_MEMBER);
                        break;
                    case 'ban_for_comment':
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_BAN_FOR_COMMENT);
                        break;
                    case 'ban':
                        if ($oBlogUser->getUserRole() !== ModuleBlog::BLOG_USER_ROLE_BAN) {
                            $oBlog->setCountUser($oBlog->getCountUser() - 1);
                        }
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_BAN);
                        break;
                    default:
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_GUEST);
                }
                \E::Module('Blog')->UpdateRelationBlogUser($oBlogUser);
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_admin_users_submit_ok'));
            }
            \E::Module('Blog')->UpdateBlog($oBlog);
        }

        //  Текущая страница
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;

        //  Получаем список подписчиков блога
        $aResult = \E::Module('Blog')->getBlogUsersByBlogId(
            $oBlog->getId(),
            [
                ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR,
                ModuleBlog::BLOG_USER_ROLE_MODERATOR,
                ModuleBlog::BLOG_USER_ROLE_MEMBER,
                ModuleBlog::BLOG_USER_ROLE_BAN_FOR_COMMENT,
                ModuleBlog::BLOG_USER_ROLE_BAN,
            ],
            $iPage, C::get('module.blog.users_per_page'));
        $aBlogUsers = $aResult['collection'];

        //  Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, C::get('module.blog.users_per_page'), C::get('pagination.pages.count'),
            R::getLink('blog/admin') . $oBlog->getId()
        );
        \E::Module('Viewer')->assign('aPaging', $aPaging);

        //  Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle($oBlog->getTitle());
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('blog_admin'));

        \E::Module('Viewer')->assign('oBlogEdit', $oBlog);
        \E::Module('Viewer')->assign('aBlogUsers', $aBlogUsers);

        //  Устанавливаем шалон для вывода
        $this->setTemplateAction('admin');

        // Если блог приватный или только для чтения, получаем приглашенных
        // и добавляем блок-форму для приглашения
        if ($oBlog->getBlogType() && ($oBlog->getBlogType()->IsPrivate() || $oBlog->getBlogType()->IsReadOnly())) {
            $aBlogUsersInvited = \E::Module('Blog')->getBlogUsersByBlogId(
                $oBlog->getId(), ModuleBlog::BLOG_USER_ROLE_INVITE, null
            );
            \E::Module('Viewer')->assign('aBlogUsersInvited', $aBlogUsersInvited['collection']);
            if (\E::Module('Viewer')->templateExists('widgets/widget.invite_to_blog.tpl')) {
                \E::Module('Viewer')->addWidget('right', 'widgets/widget.invite_to_blog.tpl');
            }
        }
        return null;
    }

    /**
     * Проверка полей блога
     *
     * @param ModuleBlog_EntityBlog|null $oBlog
     *
     * @return bool
     */
    protected function checkBlogFields($oBlog = null)
    {
        //  Проверяем только если была отправлена форма с данными (методом POST)
        if (!F::isPost('submit_blog_add')) {
            $_REQUEST['blog_limit_rating_topic'] = 0;
            return false;
        }
        \E::Module('Security')->validateSendForm();

        $bOk = true;

        //  Проверяем есть ли название блога
        if (!F::CheckVal( F::getRequestStr('blog_title'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_title_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        } else {
            //  Проверяем есть ли уже блог с таким названием
            if ($oBlogExists = \E::Module('Blog')->getBlogByTitle( F::getRequestStr('blog_title'))) {
                if (!$oBlog || $oBlog->getId() != $oBlogExists->getId()) {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('blog_create_title_error_unique'), \E::Module('Lang')->get('error')
                    );
                    $bOk = false;
                }
            }
        }

        //  Проверяем есть ли URL блога, с заменой всех пробельных символов на "_"
        if (!$oBlog || $this->oUserCurrent->isAdministrator()) {
            $blogUrl = preg_replace('/\s+/', '_',  F::getRequestStr('blog_url'));
            $_REQUEST['blog_url'] = $blogUrl;
            if (!F::CheckVal( F::getRequestStr('blog_url'), 'login', 2, 50)) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_url_error'), \E::Module('Lang')->get('error'));
                $bOk = false;
            }
        }
        //  Проверяем на счет плохих УРЛов
        if (in_array( F::getRequestStr('blog_url'), $this->aBadBlogUrl)) {
            \E::Module('Message')->addError(
                \E::Module('Lang')->get('blog_create_url_error_badword') . ' ' . implode(',', $this->aBadBlogUrl),
                \E::Module('Lang')->get('error')
            );
            $bOk = false;
        }
        //  Проверяем есть ли уже блог с таким URL
        if ($oBlogExists = \E::Module('Blog')->getBlogByUrl( F::getRequestStr('blog_url'))) {
            if (!$oBlog || $oBlog->getId() != $oBlogExists->getId()) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_url_error_unique'), \E::Module('Lang')->get('error'));
                $bOk = false;
            }
        }

        // * Проверяем доступные типы блога для создания
        $aBlogTypes = \E::Module('Blog')->getAllowBlogTypes($this->oUserCurrent, 'add');
        if (!array_key_exists(\F::getRequestStr('blog_type'), $aBlogTypes)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_type_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        //  Проверяем есть ли описание блога
        if (!F::CheckVal( F::getRequestStr('blog_description'), 'text', 10, 3000)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('blog_create_description_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        //  Выполнение хуков
        \HookManager::run('check_blog_fields', ['bOk' => &$bOk]);
        return $bOk;
    }

    /**
     * Показ всех топиков
     *
     */
    public function eventTopics() 
    {
        $this->sTopicFilter = $this->sCurrentEvent;
        $this->sTopicFilterPeriod = 1; // по дефолту 1 день
        if (in_array( F::getRequestStr('period'), [1, 7, 30, 'all'])) {
            $this->sTopicFilterPeriod =  F::getRequestStr('period');
        }
        if (!in_array($this->sTopicFilter, ['discussed', 'top'])) {
            $this->sTopicFilterPeriod = 'all';
        }

        //  Меню
        $this->sMenuSubItemSelect = $this->sTopicFilter === 'all' ? 'new' : $this->sTopicFilter;

        //  Передан ли номер страницы
        $iPage = (int)$this->getParamEventMatch(0, 2) ?: 1;
        if ($iPage === 1 && !F::getRequest('period')) {
            \E::Module('Viewer')->setHtmlCanonical(R::getLink('blog') . $this->sTopicFilter . '/');
        }
        //  Получаем список топиков
        $aResult = \E::Module('Topic')->getTopicsCollective(
            $iPage, C::get('module.topic.per_page'), $this->sTopicFilter, $this->sTopicFilterPeriod === 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
        );
        //  Если нет топиков за 1 день, то показываем за неделю (7)
        if (in_array($this->sTopicFilter, ['discussed', 'top']) && !$aResult['count'] && $iPage == 1 && !F::getRequest('period')) {
            $this->sTopicFilterPeriod = 7;
            $aResult = \E::Module('Topic')->getTopicsCollective(
                $iPage, C::get('module.topic.per_page'), $this->sTopicFilter,
                $this->sTopicFilterPeriod === 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
            );
        }
        $aTopics = $aResult['collection'];

        //  Вызов хуков
        \HookManager::run('topics_list_show', ['aTopics' => $aTopics]);

        //  Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, C::get('module.topic.per_page'), C::get('pagination.pages.count'),
            R::getLink('blog') . $this->sTopicFilter,
            in_array($this->sTopicFilter, ['discussed', 'top']) ? ['period' => $this->sTopicFilterPeriod] : []
        );

        //  Вызов хуков
        \HookManager::run('blog_show', ['sShowType' => $this->sTopicFilter]);

        //  Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        if (in_array($this->sTopicFilter, ['discussed', 'top'])) {
            \E::Module('Viewer')->assign('sPeriodSelectCurrent', $this->sTopicFilterPeriod);
            \E::Module('Viewer')->assign('sPeriodSelectRoot', R::getLink('blog') . $this->sTopicFilter . '/');
        }
        \E::Module('Viewer')->assign('sTopicFilter', $this->sTopicFilter);
        \E::Module('Viewer')->assign('sTopicFilterPeriod', $this->sTopicFilterPeriod);

        //  Устанавливаем шаблон вывода
        $this->setTemplateAction('index');
    }

    /**
     * Show topic by URL
     *
     * @return string|null
     */
    public function eventShowTopicByUrl()
    {
        $sTopicUrl = $this->getEventMatch(1);

        // Проверяем есть ли такой топик
        $this->oCurrentTopic = \E::Module('Topic')->getTopicByUrl($sTopicUrl);
        if (!$this->oCurrentTopic) {
            return parent::eventNotFound();
        }

        return $this->eventShowTopic();
    }

    /**
     * Show topic
     *
     * @return string|null
     */
    public function eventShowTopic()
    {
        $this->sMenuHeadItemSelect = 'index';

        $sBlogUrl = '';
        $sTopicUrlMask = R::GetTopicUrlMask();

        if ($this->oCurrentTopic) {
            $this->oCurrentBlog = $this->oCurrentTopic->getBlog();
            if ($this->oCurrentBlog) {
                $sBlogUrl = $this->oCurrentBlog->getUrl();
            }
            $this->sMenuItemSelect = 'blog';
        } else {
            if ($this->getParamEventMatch(0, 1)) {

                // из коллективного блога
                $sBlogUrl = $this->sCurrentEvent;
                $iTopicId = $this->getParamEventMatch(0, 1);
                $this->sMenuItemSelect = 'blog';
            } else {
                // из персонального блога
                $iTopicId = $this->getEventMatch(1);
                $this->sMenuItemSelect = 'log';
            }
            // * Проверяем есть ли такой топик
            if (!$iTopicId || !($this->oCurrentTopic = \E::Module('Topic')->getTopicById($iTopicId))) {
                return parent::eventNotFound();
            }
        }
        if (!$this->oCurrentTopic->getBlog()) {
            // Этого быть не должно, но если вдруг, то надо отработать
            return parent::eventNotFound();
        }

        $this->sMenuSubItemSelect = '';

        // Trusted user is admin or owner of topic
        if ($this->oUserCurrent && ($this->oUserCurrent->isAdministrator() || $this->oUserCurrent->isModerator() || ($this->oUserCurrent->getId() == $this->oCurrentTopic->getUserId()))) {
            $bTrustedUser = true;
        } else {
            $bTrustedUser = false;
        }

        if (!$bTrustedUser) {
            // Topic with future date
            if ($this->oCurrentTopic->getDate() > date('Y-m-d H:i:s')) {
                return parent::eventNotFound();
            }

            // * Проверяем права на просмотр топика-черновика
            if (!$this->oCurrentTopic->getPublish()) {
                if (!C::get('module.topic.draft_link')) {
                    return parent::eventNotFound();
                } else {
                    // Если режим просмотра по прямой ссылке включен, то проверяем параметры
                    $bOk = false;
                    if (($sDraftCode = F::getRequestStr('draft', null, 'get')) && strpos($sDraftCode, ':')) {
                        list($nUser, $sHash) = explode(':', $sDraftCode);
                        if ($this->oCurrentTopic->GetUserId() == $nUser && $this->oCurrentTopic->getTextHash() === $sHash) {
                            $bOk = true;
                        }
                    }
                    if (!$bOk) {
                        return parent::eventNotFound();
                    }
                }
            }
        }

        // Если номер топика правильный, но URL блога неверный, то корректируем его и перенаправляем на нужный адрес
        if ($sBlogUrl !== '' && $this->oCurrentTopic->getBlog()->getUrl() !== $sBlogUrl) {
            R::Location($this->oCurrentTopic->getUrl());
        }

        // Если запросили топик с определенной маской, не указаным названием блога,
        // но ссылка на топик и ЧПУ url разные, и это не запрос RSS
        // то перенаправляем на страницу для вывода топика (во избежание дублирования контента по разным URL)
        if ($sTopicUrlMask && $sBlogUrl === ''
            && $this->oCurrentTopic->getUrl() !== R::GetPathWebCurrent() . (substr($this->oCurrentTopic->getUrl(), -1) === '/' ? '/' : '')
            && substr(R::realUrl(true), 0, 4) !== 'rss/'
        ) {
            R::Location($this->oCurrentTopic->getUrl());
        }

        // Checks rights to show content from the blog
        if (!$this->oCurrentTopic->getBlog()->CanReadBy($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('acl_cannot_show_content'), \E::Module('Lang')->get('not_access'));
            return R::redirect('error');
        }

        // Обрабатываем добавление коммента
        if (isset($_REQUEST['submit_comment'])) {
            $this->submitComment();
        }

        // Достаём комменты к топику
        if (!C::get('module.comment.nested_page_reverse')
            && C::get('module.comment.use_nested')
            && C::get('module.comment.nested_per_page')
        ) {
            $iPageDef = ceil(
                \E::Module('Comment')->getCountCommentsRootByTargetId($this->oCurrentTopic->getId(), 'topic') / C::get('module.comment.nested_per_page')
            );
        } else {
            $iPageDef = 1;
        }
        $iPage = (int)F::getRequest('cmtpage', 0);
        if ($iPage < 1) {
            $iPage = $iPageDef;
        }

        $aReturn = \E::Module('Comment')->getCommentsByTargetId($this->oCurrentTopic, 'topic', $iPage, C::get('module.comment.nested_per_page'));
        $iMaxIdComment = $aReturn['iMaxIdComment'];
        /** @var ModuleComment_EntityComment[] $aComments */
        $aComments = $aReturn['comments'];

        if ($aComments && $iMaxIdComment && isset($aComments[$iMaxIdComment])) {
            $sLastCommentDate = $aComments[$iMaxIdComment]->getDate();
        } else {
            $sLastCommentDate = null;
        }
        // Если используется постраничность для комментариев - формируем ее
        if (C::get('module.comment.use_nested') && C::get('module.comment.nested_per_page')) {
            $aPaging = \E::Module('Viewer')->makePaging(
                $aReturn['count'], $iPage, C::get('module.comment.nested_per_page'),
                C::get('pagination.pages.count'), ''
            );
            if (!C::get('module.comment.nested_page_reverse') && $aPaging) {
                // переворачиваем страницы в обратном порядке
                $aPaging['aPagesLeft'] = array_reverse($aPaging['aPagesLeft']);
                $aPaging['aPagesRight'] = array_reverse($aPaging['aPagesRight']);
            }
            \E::Module('Viewer')->assign('aPagingCmt', $aPaging);
        }

//      issue 253 {@link https://github.com/altocms/altocms/issues/253}
//      Запрещаем оставлять комментарии к топику-черновику
//      if ($this->oUserCurrent) {
        if ($this->oUserCurrent && (int)$this->oCurrentTopic->getPublish()) {
            $bAllowToComment = \E::Module('Blog')->getBlogsAllowTo('comment', $this->oUserCurrent, $this->oCurrentTopic->getBlog()->GetId(), true);
        } else {
            $bAllowToComment = false;
        }

        // Отмечаем прочтение топика
        if ($this->oUserCurrent) {
            $oTopicRead = \E::Module('Topic')->getTopicRead($this->oCurrentTopic->getId(), $this->oUserCurrent->getid());
            if (!$oTopicRead) {
                /** @var ModuleTopic_EntityTopicRead $oTopicRead */
                $oTopicRead = \E::getEntity('Topic_TopicRead');
                $oTopicRead->setTopicId($this->oCurrentTopic->getId());
                $oTopicRead->setUserId($this->oUserCurrent->getId());
                $oTopicRead->setCommentCountLast($this->oCurrentTopic->getCountComment());
                $oTopicRead->setCommentIdLast($iMaxIdComment);
                $oTopicRead->setDateRead(\F::Now());
                \E::Module('Topic')->AddTopicRead($oTopicRead);
            } else {
                if (($oTopicRead->getCommentCountLast() != $this->oCurrentTopic->getCountComment())
                    || ($oTopicRead->getCommentIdLast() != $iMaxIdComment)
                    || ((null !== $sLastCommentDate) && $oTopicRead->getDateRead() <= $sLastCommentDate)
                ) {
                    $oTopicRead->setCommentCountLast($this->oCurrentTopic->getCountComment());
                    $oTopicRead->setCommentIdLast($iMaxIdComment);
                    $oTopicRead->setDateRead(\F::Now());
                    \E::Module('Topic')->UpdateTopicRead($oTopicRead);
                }
            }
        }

        // Выставляем SEO данные
        $sTextSeo = strip_tags($this->oCurrentTopic->getText());
        \E::Module('Viewer')->setHtmlDescription(\F::CutText($sTextSeo, C::get('view.html.description_max_words')));
        \E::Module('Viewer')->setHtmlKeywords($this->oCurrentTopic->getTags());
        \E::Module('Viewer')->setHtmlCanonical($this->oCurrentTopic->getUrl());

        // Вызов хуков
        \HookManager::run('topic_show', ['oTopic' => $this->oCurrentTopic]);

        // Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('oTopic', $this->oCurrentTopic);
        \E::Module('Viewer')->assign('aComments', $aComments);
        \E::Module('Viewer')->assign('iMaxIdComment', $iMaxIdComment);
        \E::Module('Viewer')->assign('bAllowToComment', $bAllowToComment);

        // Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle($this->oCurrentTopic->getBlog()->getTitle());
        \E::Module('Viewer')->addHtmlTitle($this->oCurrentTopic->getTitle());
        \E::Module('Viewer')->setHtmlRssAlternate(
            R::getLink('rss') . 'comments/' . $this->oCurrentTopic->getId() . '/', $this->oCurrentTopic->getTitle()
        );

        // Устанавливаем шаблон вывода
        $this->setTemplateAction('topic');

        // Additional tags for <head>
        $aHeadTags = $this->_getHeadTags($this->oCurrentTopic);
        if ($aHeadTags) {
            \E::Module('Viewer')->setHtmlHeadTags($aHeadTags);
        }

        return null;
    }

    /**
     * Additional tags for <head>
     *
     * @param object $oTopic
     *
     * @return array
     */
    protected function _getHeadTags($oTopic)
    {
        $aHeadTags = [];
        if (!$oTopic->getPublish()) {
            // Disable indexing of drafts
            $aHeadTags[] = [
                'meta',
                [
                    'name' => 'robots',
                    'content' => 'noindex,nofollow',
                ],
            ];
        } else {
            // Tags for social networks
            $aHeadTags[] = [
                'meta',
                [
                    'property' => 'og:title',
                    'content' => $oTopic->getTitle(),
                ],
            ];
            $aHeadTags[] = [
                'meta',
                [
                    'property' => 'og:url',
                    'content' => $oTopic->getUrl(),
                ],
            ];
            $aHeadTags[] = [
                'meta',
                [
                    'property' => 'og:description',
                    'content' => \E::Module('Viewer')->getHtmlDescription(),
                ],
            ];
            $aHeadTags[] = [
                'meta',
                [
                    'property' => 'og:site_name',
                    'content' => C::get('view.name'),
                ],
            ];
            $aHeadTags[] = [
                'meta',
                [
                    'property' => 'og:type',
                    'content' => 'article',
                ],
            ];
            $aHeadTags[] = [
                'meta',
                [
                    'name' => 'twitter:card',
                    'content' => 'summary',
                ],
            ];
            if ($oTopic->getPreviewImageUrl()) {
                $aHeadTags[] = [
                    'meta',
                    [
                        'name' => 'og:image',
                        'content' => $oTopic->getPreviewImageUrl('700crop'),
                    ],
                ];
            }

        }
        return $aHeadTags;
    }

    /**
     * Страница со списком читателей блога
     *
     */
    public function eventShowUsers()
    {
        $sBlogUrl = $this->sCurrentEvent;

        //  Проверяем есть ли блог с таким УРЛ
        if (!($oBlog = \E::Module('Blog')->getBlogByUrl($sBlogUrl))) {
            return parent::eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

            //  Меню
        $this->sMenuSubItemSelect = '';
        $this->sMenuSubBlogUrl = $oBlog->getUrlFull();

        //  Текущая страница
        $iPage = $this->getParamEventMatch(1, 2) ? $this->getParamEventMatch(1, 2) : 1;
        $aBlogUsersResult = \E::Module('Blog')->getBlogUsersByBlogId(
            $oBlog->getId(), ModuleBlog::BLOG_USER_ROLE_MEMBER, $iPage, C::get('module.blog.users_per_page')
        );
        $aBlogUsers = $aBlogUsersResult['collection'];

        //  Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aBlogUsersResult['count'], $iPage, C::get('module.blog.users_per_page'),
            C::get('pagination.pages.count'), $oBlog->getUrlFull() . 'users'
        );
        \E::Module('Viewer')->assign('aPaging', $aPaging);

        //  Вызов хуков
        \HookManager::run('blog_collective_show_users', ['oBlog' => $oBlog]);

        //  Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aBlogUsers', $aBlogUsers);
        \E::Module('Viewer')->assign('iCountBlogUsers', $aBlogUsersResult['count']);
        \E::Module('Viewer')->assign('oBlog', $oBlog);

        //  Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle($oBlog->getTitle());

        //  Устанавливаем шаблон вывода
        $this->setTemplateAction('users');

        return true;
    }

    /**
     * Вывод топиков из определенного блога
     *
     */
    public function eventShowBlog()
    {
        $this->sMenuHeadItemSelect = 'index';

        $this->sTopicFilterPeriod = 1; // по дефолту 1 день
        if (in_array( F::getRequestStr('period'), [1, 7, 30, 'all'])) {
            $this->sTopicFilterPeriod =  F::getRequestStr('period');
        }
        $sBlogUrl = $this->sCurrentEvent;
        $this->sTopicFilter = in_array($this->getParamEventMatch(0, 0), $this->aMenuFilters)
            ? $this->getParamEventMatch(0, 0)
            : $this->sMenuDefault;
        if (!in_array($this->sTopicFilter, ['discussed', 'top'])) {
            $this->sTopicFilterPeriod = 'all';
        }

        //  Try to get blog by URL
        $oBlog = \E::Module('Blog')->getBlogByUrl($sBlogUrl);
        if (!$oBlog && ((int)$sBlogUrl == $sBlogUrl)) {
            // Try to get blog by ID
            $oBlog = \E::Module('Blog')->getBlogById($sBlogUrl);
        }
        if (!$oBlog) {
            return parent::eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

            //  Определяем права на отображение закрытого блога
        $oBlogType = $oBlog->GetBlogType();
        if ($oBlogType) {
            $bCloseBlog = !$oBlog->CanReadBy($this->oUserCurrent);
        } else {
            // if blog type not defined then it' open blog
            $bCloseBlog = false;
        }

        // В скрытый блог посторонних совсем не пускам
        if ($bCloseBlog && $oBlog->getBlogType() && $oBlog->GetBlogType()->IsHidden()) {
            return parent::eventNotFound();
        }

        //  Меню
        $this->sMenuSubItemSelect = $this->sTopicFilter === 'all' ? 'new' : $this->sTopicFilter;
        $this->sMenuSubBlogUrl = $oBlog->getUrlFull();

        //  Передан ли номер страницы
        $iPage = $this->getParamEventMatch(($this->sTopicFilter === 'good') ? 0 : 1, 2)
            ? $this->getParamEventMatch(($this->sTopicFilter === 'good') ? 0 : 1, 2)
            : 1;
        if (($iPage == 1) && !F::getRequest('period') && in_array($this->sTopicFilter, ['discussed', 'top'])) {
            \E::Module('Viewer')->setHtmlCanonical($oBlog->getUrlFull() . $this->sTopicFilter . '/');
        }

        //  Получаем число новых топиков в текущем блоге (даже для закрытых блогов)
        $this->iCountTopicsBlogNew = \E::Module('Topic')->getCountTopicsByBlogNew($oBlog);

        if (!$bCloseBlog) {
            //  Получаем список топиков
            $aResult = \E::Module('Topic')->getTopicsByBlog(
                $oBlog, $iPage, C::get('module.topic.per_page'), $this->sTopicFilter,
                $this->sTopicFilterPeriod === 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
            );
            //  Если нет топиков за 1 день, то показываем за неделю (7)
            if (in_array($this->sTopicFilter, ['discussed', 'top']) && !$aResult['count'] && $iPage == 1 && !F::getRequest('period')) {
                $this->sTopicFilterPeriod = 7;
                $aResult = \E::Module('Topic')->getTopicsByBlog(
                    $oBlog, $iPage, C::get('module.topic.per_page'), $this->sTopicFilter,
                    $this->sTopicFilterPeriod === 'all' ? null : $this->sTopicFilterPeriod * 60 * 60 * 24
                );
            }
            $aTopics = $aResult['collection'];
            //  Формируем постраничность
            if (($this->sTopicFilter === 'good')) {
                $aPaging = \E::Module('Viewer')->makePaging(
                        $aResult['count'], $iPage, C::get('module.topic.per_page'),
                        C::get('pagination.pages.count'), rtrim($oBlog->getLink(), '/')
                    );
            } elseif ($this->sTopicFilter === 'all') {
                $aPaging = \E::Module('Viewer')->makePaging(
                    $aResult['count'], $iPage, C::get('module.topic.per_page'),
                    C::get('pagination.pages.count'), $oBlog->getLink() . $this->sTopicFilter
                );
            } else {
                $aPaging = \E::Module('Viewer')->makePaging(
                        $aResult['count'], $iPage, C::get('module.topic.per_page'),
                        C::get('pagination.pages.count'), $oBlog->getLink() . $this->sTopicFilter,
                        ['period' => $this->sTopicFilterPeriod]
                    );
            }

            \E::Module('Viewer')->assign('aPaging', $aPaging);
            \E::Module('Viewer')->assign('aTopics', $aTopics);
            \E::Module('Viewer')->assign('iTopicsTotal', $aResult['count']);
            if (in_array($this->sTopicFilter, ['discussed', 'top'], true)) {
                \E::Module('Viewer')->assign('sPeriodSelectCurrent', $this->sTopicFilterPeriod);
                \E::Module('Viewer')->assign('sPeriodSelectRoot', $oBlog->getLink() . $this->sTopicFilter . '/');
            }
        }
        //  Выставляем SEO данные
        $sTextSeo = strip_tags($oBlog->getDescription());
        \E::Module('Viewer')->setHtmlDescription(\F::CutText($sTextSeo, C::get('view.html.description_max_words')));

        //  Получаем список юзеров блога
        $aBlogUsersResult = \E::Module('Blog')->getBlogUsersByBlogId(
            $oBlog->getId(), ModuleBlog::BLOG_USER_ROLE_MEMBER, 1, C::get('module.blog.users_per_page')
        );
        $aBlogUsers = $aBlogUsersResult['collection'];
        $aBlogModeratorsResult = \E::Module('Blog')->getBlogUsersByBlogId(
            $oBlog->getId(), ModuleBlog::BLOG_USER_ROLE_MODERATOR
        );
        $aBlogModerators = $aBlogModeratorsResult['collection'];
        $aBlogAdministratorsResult = \E::Module('Blog')->getBlogUsersByBlogId(
            $oBlog->getId(), ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR
        );
        $aBlogAdministrators = $aBlogAdministratorsResult['collection'];

        //  Для админов проекта получаем список блогов и передаем их во вьювер
        if ($this->oUserCurrent && ($this->oUserCurrent->isAdministrator() || $this->oUserCurrent->isModerator())) {
            $aBlogs = \E::Module('Blog')->getBlogs();
            unset($aBlogs[$oBlog->getId()]);

            \E::Module('Viewer')->assign('aBlogs', $aBlogs);
        }
        //  Вызов хуков
        \HookManager::run('blog_collective_show', ['oBlog' => $oBlog, 'sShowType' => $this->sTopicFilter]);

        //  Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aBlogUsers', $aBlogUsers);
        \E::Module('Viewer')->assign('aBlogModerators', $aBlogModerators);
        \E::Module('Viewer')->assign('aBlogAdministrators', $aBlogAdministrators);
        \E::Module('Viewer')->assign('iCountBlogUsers', $aBlogUsersResult['count']);
        \E::Module('Viewer')->assign('iCountBlogModerators', $aBlogModeratorsResult['count']);
        \E::Module('Viewer')->assign('iCountBlogAdministrators', $aBlogAdministratorsResult['count'] + 1);
        \E::Module('Viewer')->assign('oBlog', $oBlog);
        \E::Module('Viewer')->assign('bCloseBlog', $bCloseBlog);
        \E::Module('Viewer')->assign('sTopicFilter', $this->sTopicFilter);
        \E::Module('Viewer')->assign('sTopicFilterPeriod', $this->sTopicFilterPeriod);

        //  Устанавливаем title страницы
        \E::Module('Viewer')->addHtmlTitle($oBlog->getTitle());
        \E::Module('Viewer')->setHtmlRssAlternate(
            R::getLink('rss') . 'blog/' . $oBlog->getUrl() . '/', $oBlog->getTitle()
        );
        //  Устанавливаем шаблон вывода
        $this->setTemplateAction('blog');

        return true;
    }

    /**
     * Обработка добавление комментария к топику через ajax
     *
     */
    protected function ajaxAddComment()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $this->submitComment();
    }

    /**
     * Обработка добавление комментария к топику
     *
     */
    protected function submitComment()
    {
        // * Проверям авторизован ли пользователь
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем топик
        if (!($oTopic = \E::Module('Topic')->getTopicById( F::getRequestStr('cmt_target_id')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Возможность постить коммент в топик в черновиках
        if (!$oTopic->getPublish()
//            issue 253 {@link https://github.com/altocms/altocms/issues/253}
//            Запрещаем оставлять комментарии к топику-черновику
//            && ($this->oUserCurrent->getId() != $oTopic->getUserId())
//            && !$this->oUserCurrent->isAdministrator()
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем разрешено ли постить комменты
        switch (\E::Module('ACL')->CanPostComment($this->oUserCurrent, $oTopic)) {
            case ModuleACL::CAN_TOPIC_COMMENT_ERROR_BAN:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_banned'), \E::Module('Lang')->get('attention'));
                return;
            case ModuleACL::CAN_TOPIC_COMMENT_FALSE:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_acl'), \E::Module('Lang')->get('error'));
                return;
            }

        // * Проверяем разрешено ли постить комменты по времени
        if (!\E::Module('ACL')->CanPostCommentTime($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_limit'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем запрет на добавления коммента автором топика
        if ($oTopic->getForbidComment()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_notallow'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем текст комментария
        $sText = \E::Module('Text')->parse(\F::getRequestStr('comment_text'));
        if (!F::CheckVal($sText, 'text', C::val('module.comment.min_length', 2), C::val('module.comment.max_length', 10000))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_text_len', [
                'min' => 2,
                'max' => C::val('module.comment.max_length', 10000)
            ]), \E::Module('Lang')->get('error'));
            return;
        }
        $iMin = C::val('module.comment.min_length', 2);
        $iMax = C::val('module.comment.max_length', 0);
        if (!F::CheckVal($sText, 'text', $iMin, $iMax)) {
            if ($iMax) {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('topic_comment_text_len', ['min' => $iMin, 'max' => $iMax]), \E::Module('Lang')->get('error')
                );
            } else {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('topic_comment_text_min', ['min' => $iMin]), \E::Module('Lang')->get('error')
                );
            }
            return;
        }

        // * Проверям на какой коммент отвечаем
        if (!$this->isPost('reply')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $oCommentParent = null;
        $iParentId = (int)F::getRequest('reply');
        if (!empty($iParentId)) {
            // * Проверяем существует ли комментарий на который отвечаем
            if (!($oCommentParent = \E::Module('Comment')->getCommentById($iParentId))) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
            // * Проверяем из одного топика ли новый коммент и тот на который отвечаем
            if ($oCommentParent->getTargetId() != $oTopic->getId()) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        } else {

            // * Корневой комментарий
            $iParentId = null;
        }

        // * Проверка на дублирующий коммент
        if (\E::Module('Comment')->getCommentUnique($oTopic->getId(), 'topic', $this->oUserCurrent->getId(), $iParentId, md5($sText))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_spam'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Создаём коммент
        /** @var ModuleComment_EntityComment $oCommentNew */
        $oCommentNew = \E::getEntity('Comment');
        $oCommentNew->setTargetId($oTopic->getId());
        $oCommentNew->setTargetType('topic');
        $oCommentNew->setTargetParentId($oTopic->getBlog()->getId());
        $oCommentNew->setUserId($this->oUserCurrent->getId());
        $oCommentNew->setText($sText);
        $oCommentNew->setDate(\F::Now());
        $oCommentNew->setUserIp(\F::getUserIp());
        $oCommentNew->setPid($iParentId);
        $oCommentNew->setTextHash(md5($sText));
        $oCommentNew->setPublish($oTopic->getPublish());

        // * Добавляем коммент
        \HookManager::run(
            'comment_add_before',
            ['oCommentNew' => $oCommentNew, 'oCommentParent' => $oCommentParent, 'oTopic' => $oTopic]
        );
        if (\E::Module('Comment')->AddComment($oCommentNew)) {
            \HookManager::run(
                'comment_add_after',
                ['oCommentNew' => $oCommentNew, 'oCommentParent' => $oCommentParent, 'oTopic' => $oTopic]
            );

            \E::Module('Viewer')->assignAjax('sCommentId', $oCommentNew->getId());
            if ($oTopic->getPublish()) {

                // * Добавляем коммент в прямой эфир если топик не в черновиках
                /** @var ModuleComment_EntityCommentOnline $oCommentOnline */
                $oCommentOnline = \E::getEntity('Comment_CommentOnline');
                $oCommentOnline->setTargetId($oCommentNew->getTargetId());
                $oCommentOnline->setTargetType($oCommentNew->getTargetType());
                $oCommentOnline->setTargetParentId($oCommentNew->getTargetParentId());
                $oCommentOnline->setCommentId($oCommentNew->getId());

                \E::Module('Comment')->AddCommentOnline($oCommentOnline);
            }

            // * Список емайлов на которые не нужно отправлять уведомление
            $aExcludeMail = [$this->oUserCurrent->getMail()];

            // * Отправляем уведомление тому на чей коммент ответили
            if ($oCommentParent && $oCommentParent->getUserId() != $oTopic->getUserId() && $oCommentNew->getUserId() != $oCommentParent->getUserId()) {
                $oUserAuthorComment = $oCommentParent->getUser();
                $aExcludeMail[] = $oUserAuthorComment->getMail();
                \E::Module('Notify')->SendCommentReplyToAuthorParentComment(
                    $oUserAuthorComment, $oTopic, $oCommentNew, $this->oUserCurrent
                );
            }

            // issue 131 (https://github.com/altocms/altocms/issues/131)
            // Не работает настройка уведомлений о комментариях к своим топикам

            // Уберём автора топика из рассылки
            /** @var ModuleTopic_EntityTopic $oTopic */
            $aExcludeMail[] = $oTopic->getUser()->getMail();
            // Отправим ему сообщение через отдельный метод, который проверяет эту настройку
            /** @var ModuleComment_EntityComment $oCommentNew */
            \E::Module('Notify')->SendCommentNewToAuthorTopic($oTopic->getUser(), $oTopic, $oCommentNew, $this->oUserCurrent);

            // * Отправка уведомления всем, кто подписан на топик кроме автора
            \E::Module('Subscribe')->Send(
                'topic_new_comment', $oTopic->getId(), 'comment_new.tpl',
                \E::Module('Lang')->get('notify_subject_comment_new'),
                ['oTopic' => $oTopic, 'oComment' => $oCommentNew, 'oUserComment' => $this->oUserCurrent,],
                $aExcludeMail
            );

            // * Подписываем автора коммента на обновления в трекере
            $oTrack = \E::Module('Subscribe')->AddTrackSimple(
                'topic_new_comment', $oTopic->getId(), $this->oUserCurrent->getId()
            );
            if ($oTrack) {
                //если пользователь не отписался от обновлений топика
                if (!$oTrack->getStatus()) {
                    $oTrack->setStatus(1);
                    \E::Module('Subscribe')->UpdateTrack($oTrack);
                }
            }

            // * Добавляем событие в ленту
            \E::Module('Stream')->write(
                $oCommentNew->getUserId(), 'add_comment', $oCommentNew->getId(),
                $oTopic->getPublish() && !$oTopic->getBlog()->IsPrivate()
            );
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Получение новых комментариев
     *
     */
    protected function ajaxResponseComment()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Топик существует?
        $iTopicId = F::getRequestInt('idTarget', null, 'post');
        if (!$iTopicId || !($oTopic = \E::Module('Topic')->getTopicById($iTopicId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Есть доступ к комментариям этого топика? Закрытый блог?
        if (!\E::Module('ACL')->IsAllowShowBlog($oTopic->getBlog(), $this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $iLastCommentId = F::getRequestStr('idCommentLast', null, 'post');
        $selfIdComment = F::getRequestStr('selfIdComment', null, 'post');
        $aComments = [];

        // * Если используется постраничность, возвращаем только добавленный комментарий
        if (\F::getRequest('bUsePaging', null, 'post') && $selfIdComment) {
            $oComment = \E::Module('Comment')->getCommentById($selfIdComment);
            if ($oComment && ($oComment->getTargetId() == $oTopic->getId()) && ($oComment->getTargetType() === 'topic')) {

                $aVars = [
                    'oUserCurrent' => $this->oUserCurrent,
                    'bOneComment' => true,
                    'oComment' => $oComment,
                ];
                $sText = \E::Module('Viewer')->fetch(\E::Module('Comment')->getTemplateCommentByTarget($oTopic->getId(), 'topic'));
                $aCmt = [];
                $aCmt[] = [
                    'html' => $sText,
                    'obj'  => $oComment,
                ];
            } else {
                $aCmt = [];
            }
            $aReturn['comments'] = $aCmt;
            $aReturn['iMaxIdComment'] = $selfIdComment;
        } else {
            $aReturn = \E::Module('Comment')->getCommentsNewByTargetId($oTopic->getId(), 'topic', $iLastCommentId);
        }
        $iMaxIdComment = $aReturn['iMaxIdComment'];

        /** @var ModuleTopic_EntityTopicRead $oTopicRead */
        $oTopicRead = \E::getEntity('Topic_TopicRead');
        $oTopicRead->setTopicId($oTopic->getId());
        $oTopicRead->setUserId($this->oUserCurrent->getId());
        $oTopicRead->setCommentCountLast($oTopic->getCountComment());
        $oTopicRead->setCommentIdLast($iMaxIdComment);
        $oTopicRead->setDateRead(\F::Now());
        \E::Module('Topic')->SetTopicRead($oTopicRead);

        $aCmts = $aReturn['comments'];
        if ($aCmts && is_array($aCmts)) {
            foreach ($aCmts as $aCmt) {
                $aComments[] = [
                    'html'     => $aCmt['html'],
                    'idParent' => $aCmt['obj']->getPid(),
                    'id'       => $aCmt['obj']->getId(),
                ];
            }
        }

        \E::Module('Viewer')->assignAjax('iMaxIdComment', $iMaxIdComment);
        \E::Module('Viewer')->assignAjax('aComments', $aComments);
    }

    /**
     * Returns text of comment
     *
     */
    protected function ajaxGetComment()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Топик существует?
        $nTopicId = (int)$this->getPost('targetId');
        if (!$nTopicId || !($oTopic = \E::Module('Topic')->getTopicById($nTopicId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $nCommentId = (int)$this->getPost('commentId');
        if (!$nCommentId || !($oComment = \E::Module('Comment')->getCommentById($nCommentId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!$this->getPost('submit')) {
            $sText = $oComment->getText();
            // restore <code></code>
            // see ModuleText::CodeSourceParser()
            $sText = str_replace('<pre class="prettyprint"><code>', '<code>', $sText);
            $sText = str_replace('</code></pre>', '</code>', $sText);

            \E::Module('Viewer')->assignAjax('sText', $sText);
            \E::Module('Viewer')->assignAjax('sDateEdit', $oComment->getCommentDateEdit());
        }
    }

    /**
     * Updates comment
     *
     */
    protected function ajaxUpdateComment()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!\E::Module('Security')->validateSendForm(false) || $this->getPost('comment_mode') !== 'edit') {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем текст комментария
        $sNewText = \E::Module('Text')->parse($this->getPost('comment_text'));
        $iMin = C::val('module.comment.min_length', 2);
        $iMax = C::val('module.comment.max_length', 0);
        if (!F::CheckVal($sNewText, 'text', $iMin, $iMax)) {
            if ($iMax) {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('topic_comment_text_len', ['min' => $iMin, 'max' => $iMax]), \E::Module('Lang')->get('error')
                );
            } else {
                \E::Module('Message')->addErrorSingle(
                    \E::Module('Lang')->get('topic_comment_text_min', ['min' => $iMin]), \E::Module('Lang')->get('error')
                );
            }
            return;
        }

        // * Получаем комментарий
        $iCommentId = (int)$this->getPost('comment_id');

        /** var ModuleComment_EntityComment $oComment */
        if (!$iCommentId || !($oComment = \E::Module('Comment')->getCommentById($iCommentId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!$oComment->isEditable()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_cannot_edit'), \E::Module('Lang')->get('error'));
            return;
        }

        if (!$oComment->getEditTime() && !$oComment->isEditable(false)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_edit_timeout'), \E::Module('Lang')->get('error'));
            return;
        }

        // Если все нормально, то обновляем текст
        $oComment->setText($sNewText);
        if (\E::Module('Comment')->updateComment($oComment)) {
            $oComment = \E::Module('Comment')->getCommentById($iCommentId);
            \E::Module('Viewer')->assignAjax('nCommentId', $oComment->getId());
            \E::Module('Viewer')->assignAjax('sText', $oComment->getText());
            \E::Module('Viewer')->assignAjax('sDateEdit', $oComment->getCommentDateEdit());
            \E::Module('Viewer')->assignAjax('sDateEditText', \E::Module('Lang')->get('date_now'));
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('comment_updated'));
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Обработка ajax запроса на отправку
     * пользователям приглашения вступить в приватный блог
     */
    protected function ajaxAddBlogInvite()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $sUsers = F::getRequest('users', null, 'post');
        $iBlogId = F::getRequestInt('idBlog', null, 'post');

        // * Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oUserCurrent = \E::User();

        // * Проверяем существование блога
        if (!$iBlogId || !($oBlog = \E::Module('Blog')->getBlogById($iBlogId)) || !is_string($sUsers)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oCurrentBlog = $oBlog;

            // * Проверяем, имеет ли право текущий пользователь добавлять invite в blog
        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $this->oUserCurrent->getId());
        $bBlogAdministrator = ($oBlogUser ? $oBlogUser->IsBlogAdministrator() : false);
        if ($oBlog->getOwnerId() != $this->oUserCurrent->getId() && !$this->oUserCurrent->isAdministrator() && !$this->oUserCurrent->isModerator() && !$bBlogAdministrator) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Получаем список пользователей блога (любого статуса)
        $aBlogUsersResult = \E::Module('Blog')->getBlogUsersByBlogId($oBlog->getId(), true, null);
        /** @var ModuleBlog_EntityBlogUser[] $aBlogUsers */
        $aBlogUsers = $aBlogUsersResult['collection'];
        $aUsers = explode(',', $sUsers);

        $aResult = [];

        // * Обрабатываем добавление по каждому из переданных логинов
        foreach ($aUsers as $sUser) {
            $sUser = trim($sUser);
            if ($sUser === '') {
                continue;
            }
            // * Если пользователь пытается добавить инвайт самому себе, возвращаем ошибку
            if (strtolower($sUser) === strtolower($this->oUserCurrent->getLogin())) {
                $aResult[] = [
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('blog_user_invite_add_self')
                ];
                continue;
            }

            // * Если пользователь не найден или неактивен, возвращаем ошибку
            $oUser = \E::Module('User')->getUserByLogin($sUser);
            if (!$oUser || $oUser->getActivate() != 1) {
                $aResult[] = [
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('user_not_found', ['login' => htmlspecialchars($sUser)]),
                    'sUserLogin'  => htmlspecialchars($sUser)
                ];
                continue;
            }

            if (!isset($aBlogUsers[$oUser->getId()])) {
                // * Создаем нового блог-пользователя со статусом INVITED
                /** @var ModuleBlog_EntityBlogUser $oBlogUserNew */
                $oBlogUserNew = \E::getEntity('Blog_BlogUser');
                $oBlogUserNew->setBlogId($oBlog->getId());
                $oBlogUserNew->setUserId($oUser->getId());
                $oBlogUserNew->setUserRole(ModuleBlog::BLOG_USER_ROLE_INVITE);

                if (\E::Module('Blog')->AddRelationBlogUser($oBlogUserNew)) {
                    $aResult[] = [
                        'bStateError'   => false,
                        'sMsgTitle'     => \E::Module('Lang')->get('attention'),
                        'sMsg'          => \E::Module('Lang')->get(
                            'blog_user_invite_add_ok',
                            ['login' => htmlspecialchars($sUser)]
                        ),
                        'sUserLogin'    => htmlspecialchars($sUser),
                        'sUserWebPath'  => $oUser->getProfileUrl(),
                        'sUserAvatar48' => $oUser->getAvatarUrl(48),
                    ];
                    $this->sendBlogInvite($oBlog, $oUser);
                } else {
                    $aResult[] = [
                        'bStateError' => true,
                        'sMsgTitle'   => \E::Module('Lang')->get('error'),
                        'sMsg'        => \E::Module('Lang')->get('system_error'),
                        'sUserLogin'  => htmlspecialchars($sUser)
                    ];
                }
            } elseif ($aBlogUsers[$oUser->getId()]->getUserRole() == ModuleBlog::BLOG_USER_ROLE_NOTMEMBER || $aBlogUsers[$oUser->getId()]->getUserRole() == ModuleBlog::BLOG_USER_ROLE_WISHES) {
                // * Change status of user to INVITED
                /** @var ModuleBlog_EntityBlogUser $oBlogUser */
                $oBlogUser = $aBlogUsers[$oUser->getId()];
                $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_INVITE);

                if (\E::Module('Blog')->UpdateRelationBlogUser($oBlogUser)) {
                    $aResult[] = [
                        'bStateError'   => false,
                        'sMsgTitle'     => \E::Module('Lang')->get('attention'),
                        'sMsg'          => \E::Module('Lang')->get(
                            'blog_user_invite_add_ok',
                            ['login' => htmlspecialchars($sUser)]
                        ),
                        'sUserLogin'    => htmlspecialchars($sUser),
                        'sUserWebPath'  => $oUser->getProfileUrl(),
                        'sUserAvatar48' => $oUser->getAvatarUrl(48),
                    ];
                    $this->sendBlogInvite($oBlog, $oUser);
                } else {
                    $aResult[] = [
                        'bStateError' => true,
                        'sMsgTitle'   => \E::Module('Lang')->get('error'),
                        'sMsg'        => \E::Module('Lang')->get('system_error'),
                        'sUserLogin'  => htmlspecialchars($sUser)
                    ];
                }
            } else {
                // Попытка добавить приглашение уже существующему пользователю,
                // возвращаем ошибку (сначала определяя ее точный текст)
                switch (true) {
                    case ($aBlogUsers[$oUser->getId()]->getUserRole() == ModuleBlog::BLOG_USER_ROLE_INVITE):
                        $sErrorMessage = \E::Module('Lang')->get(
                            'blog_user_already_invited', ['login' => htmlspecialchars($sUser)]
                        );
                        break;
                    case ($aBlogUsers[$oUser->getId()]->getUserRole() > ModuleBlog::BLOG_USER_ROLE_GUEST):
                        $sErrorMessage = \E::Module('Lang')->get(
                            'blog_user_already_exists', ['login' => htmlspecialchars($sUser)]
                        );
                        break;
                    case ($aBlogUsers[$oUser->getId()]->getUserRole() == ModuleBlog::BLOG_USER_ROLE_REJECT):
                        $sErrorMessage = \E::Module('Lang')->get(
                            'blog_user_already_reject', ['login' => htmlspecialchars($sUser)]
                        );
                        break;
                    default:
                        $sErrorMessage = \E::Module('Lang')->get('system_error');
                }
                $aResult[] = [
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => $sErrorMessage,
                    'sUserLogin'  => htmlspecialchars($sUser)
                ];
                continue;
            }
        }

        // * Передаем во вьевер массив с результатами обработки по каждому пользователю
        \E::Module('Viewer')->assignAjax('aUsers', $aResult);
    }

    /**
     * Обработка ajax запроса на отправку
     * повторного приглашения вступить в приватный блог
     */
    protected function ajaxReBlogInvite()
    {
        //  Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $sUserId = F::getRequestStr('idUser', null, 'post');
        $sBlogId = F::getRequestStr('idBlog', null, 'post');

        //  Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oUserCurrent = \E::User();

        //  Проверяем существование блога
        if (!$oBlog = \E::Module('Blog')->getBlogById($sBlogId)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oCurrentBlog = $oBlog;

        //  Пользователь существует и активен?
        $oUser = \E::Module('User')->getUserById($sUserId);
        if (!$oUser || !$oUser->getActivate()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        //  Проверяем, имеет ли право текущий пользователь добавлять invite в blog
        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $this->oUserCurrent->getId());
        $bBlogAdministrator = ($oBlogUser ? $oBlogUser->IsBlogAdministrator() : false);
        if ($oBlog->getOwnerId() != $this->oUserCurrent->getId() && !$this->oUserCurrent->isAdministrator() && !$this->oUserCurrent->isModerator() && !$bBlogAdministrator) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
        if ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_INVITE) {
            $this->sendBlogInvite($oBlog, $oUser);
            \E::Module('Message')->addNoticeSingle(
                \E::Module('Lang')->get('blog_user_invite_add_ok', ['login' => $oUser->getLogin()]),
                \E::Module('Lang')->get('attention')
            );
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Обработка ajax-запроса на удаление приглашения подписаться на приватный блог
     *
     */
    protected function ajaxRemoveBlogInvite()
    {
        //  Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $sUserId = F::getRequestStr('idUser', null, 'post');
        $sBlogId = F::getRequestStr('idBlog', null, 'post');

        //  Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oUserCurrent = \E::User();
        //  Проверяем существование блога
        if (!$oBlog = \E::Module('Blog')->getBlogById($sBlogId)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oCurrentBlog = $oBlog;

        //  Пользователь существует и активен?
        $oUser = \E::Module('User')->getUserById($sUserId);
        if (!$oUser || $oUser->getActivate() != 1) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        //  Проверяем, имеет ли право текущий пользователь добавлять invite в blog
        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $this->oUserCurrent->getId());
        $bBlogAdministrator = ($oBlogUser ? $oBlogUser->IsBlogAdministrator() : false);
        if ($oBlog->getOwnerId() != $this->oUserCurrent->getId() && !$this->oUserCurrent->isAdministrator() && !$this->oUserCurrent->isModerator() && !$bBlogAdministrator) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oUser->getId());
        if ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_INVITE) {
            //  Удаляем связь/приглашение
            \E::Module('Blog')->DeleteRelationBlogUser($oBlogUser);
            \E::Module('Message')->addNoticeSingle(
                \E::Module('Lang')->get('blog_user_invite_remove_ok', ['login' => $oUser->getLogin()]),
                \E::Module('Lang')->get('attention')
            );
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Выполняет отправку уведомления модераторам и администратору блога о том, что
     * конкретный пользователь запросил приглашение в блог
     *
     * @param ModuleBlog_EntityBlog   $oBlog           Блог, в который хочет вступить пользователь
     * @param ModuleUser_EntityUser[] $aBlogModerators Модератор/админ, которому отправляем письмо
     * @param ModuleUser_EntityUser   $oGuestUser      Пользователь, который хочет вступить в блог
     */
    protected function sendBlogRequest($oBlog, $aBlogModerators, $oGuestUser)
    {
        $sTitle = \E::Module('Lang')->get('blog_user_request_title', ['blog_title' => $oBlog->getTitle()]);

        //  Формируем код подтверждения в URL
        $sCode = $oBlog->getId() . '_' . $oGuestUser->getId();
        $sCode = F::Xxtea_Encode($sCode, C::get('module.blog.encrypt'));

        $aPath = [
            'accept' => R::getLink('blog') . 'request/accept/?code=' . $sCode,
            'reject' => R::getLink('blog') . 'request/reject/?code=' . $sCode
        ];

        $sText = \E::Module('Lang')->get(
            'blog_user_request_text',
            [
                'login'        => $oGuestUser->getLogin(),
                'user_profile' => $oGuestUser->getProfileUrl(),
                'accept_path'  => $aPath['accept'],
                'reject_path'  => $aPath['reject'],
                'blog_title'   => $oBlog->getTitle()
            ]
        );
        $oTalk = \E::Module('Talk')->SendTalk($sTitle, $sText, $oGuestUser, $aBlogModerators, FALSE, FALSE);

        foreach ($aBlogModerators as $oUserTo) {
            \E::Module('Notify')->SendBlogUserRequest(
                $oUserTo,
                $this->oUserCurrent,
                $oBlog,
                R::getLink('talk') . 'read/' . $oTalk->getId() . '/'
            );
        }
        //  Удаляем отправляющего юзера из переписки
        \E::Module('Talk')->DeleteTalkUserByArray($oTalk->getId(), $oGuestUser->getId());
    }

    /**
     * Выполняет отправку приглашения в блог
     * (по внутренней почте и на email)
     *
     * @param ModuleBlog_EntityBlog $oBlog
     * @param ModuleUser_EntityUser $oUser
     */
    protected function sendBlogInvite($oBlog, $oUser)
    {
        $sTitle = \E::Module('Lang')->get('blog_user_invite_title', ['blog_title' => $oBlog->getTitle()]);

        //  Формируем код подтверждения в URL
        $sCode = $oBlog->getId() . '_' . $oUser->getId();
        $sCode = F::Xxtea_Encode($sCode, C::get('module.blog.encrypt'));

        $aPath = [
            'accept' => R::getLink('blog') . 'invite/accept/?code=' . $sCode,
            'reject' => R::getLink('blog') . 'invite/reject/?code=' . $sCode
        ];

        // Сформируем название типа блога на языке приложения.
        // Это может быть либо название, либо текстовка.
        $sBlogType = mb_strtolower(
            preg_match('~^\{\{(.*)\}\}$~', $sBlogType = $oBlog->getBlogType()->getTypeName(), $aMatches)
                ? \E::Module('Lang')->get($aMatches[1])
                : $sBlogType, 'UTF-8'
        );


        $sText = \E::Module('Lang')->get(
            'blog_user_invite_text',
            [
                 'login'       => $this->oUserCurrent->getLogin(),
                 'accept_path' => $aPath['accept'],
                 'reject_path' => $aPath['reject'],
                 'blog_title'  => $oBlog->getTitle(),
                 'blog_type'   => $sBlogType,
            ]
        );
        $oTalk = \E::Module('Talk')->SendTalk($sTitle, $sText, $this->oUserCurrent, [$oUser], false, false);

        //  Отправляем пользователю заявку
        \E::Module('Notify')->SendBlogUserInvite(
            $oUser, $this->oUserCurrent, $oBlog,
            R::getLink('talk') . 'read/' . $oTalk->getId() . '/'
        );
        //  Удаляем отправляющего юзера из переписки
        \E::Module('Talk')->DeleteTalkUserByArray($oTalk->getId(), $this->oUserCurrent->getId());
    }

    /**
     * Обработка отправленого пользователю приглашения подписаться на блог
     *
     * @return string|null
     */
    public function eventInviteBlog() 
    {
        // * Получаем код подтверждения из ревеста и дешефруем его
        $sCode = F::Xxtea_Decode(\F::getRequestStr('code'), C::get('module.blog.encrypt'));
        if (!$sCode) {
            return $this->eventNotFound();
        }
        list($sBlogId, $sUserId) = explode('_', $sCode, 2);

        $sAction = $this->getParam(0);

        // * Получаем текущего пользователя
        if (!\E::isUser()) {
            return $this->eventNotFound();
        }
        $this->oUserCurrent = \E::User();

        // * Если приглашенный пользователь не является авторизированным
        if ($this->oUserCurrent->getId() != $sUserId) {
            return $this->eventNotFound();
        }

        // * Получаем указанный блог
        $oBlog = \E::Module('Blog')->getBlogById($sBlogId);
        if (!$oBlog || !$oBlog->getBlogType() || !($oBlog->getBlogType()->IsPrivate()||$oBlog->getBlogType()->IsReadOnly())) {
            return $this->eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

        // * Получаем связь "блог-пользователь" и проверяем, чтобы ее тип был INVITE или REJECT
        if (!$oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $this->oUserCurrent->getId())) {
            return $this->eventNotFound();
        }
        if ($oBlogUser->getUserRole() > ModuleBlog::BLOG_USER_ROLE_GUEST) {
            $sMessage = \E::Module('Lang')->get('blog_user_invite_already_done');
            \E::Module('Message')->addError($sMessage, \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return;
        }
        if (!in_array($oBlogUser->getUserRole(), [ModuleBlog::BLOG_USER_ROLE_INVITE, ModuleBlog::BLOG_USER_ROLE_REJECT])) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return;
        }

        // * Обновляем роль пользователя до читателя
        $oBlogUser->setUserRole(($sAction === 'accept') ? ModuleBlog::BLOG_USER_ROLE_MEMBER : ModuleBlog::BLOG_USER_ROLE_REJECT);
        if (!\E::Module('Blog')->UpdateRelationBlogUser($oBlogUser)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return;
        }
        if ($sAction === 'accept') {

            // * Увеличиваем число читателей блога
            $oBlog->setCountUser($oBlog->getCountUser() + 1);
            \E::Module('Blog')->UpdateBlog($oBlog);
            $sMessage = \E::Module('Lang')->get('blog_user_invite_accept');

            // * Добавляем событие в ленту
            \E::Module('Stream')->write($oBlogUser->getUserId(), 'join_blog', $oBlog->getId());
        } else {
            $sMessage = \E::Module('Lang')->get('blog_user_invite_reject');
        }
        \E::Module('Message')->addNotice($sMessage, \E::Module('Lang')->get('attention'), true);

        // * Перенаправляем на страницу личной почты
        R::Location(R::getLink('talk'));
    }

    /**
     * Обработка отправленого админу запроса на вступление в блог
     *
     * @return string|null
     */
    public function eventRequestBlog() 
    {
        // * Получаем код подтверждения из ревеста и дешефруем его
        $sCode = F::Xxtea_Decode(\F::getRequestStr('code'), C::get('module.blog.encrypt'));
        if (!$sCode) {
            return $this->eventNotFound();
        }
        list($sBlogId, $sUserId) = explode('_', $sCode, 2);

        $sAction = $this->getParam(0);

        // * Получаем текущего пользователя
        if (!\E::isUser()) {
            return $this->eventNotFound();
        }
        $this->oUserCurrent = \E::User();

        // Получаем блог
        /** @var ModuleBlog_EntityBlog $oBlog */
        $oBlog = \E::Module('Blog')->getBlogById($sBlogId);
        if (!$oBlog || !$oBlog->getBlogType() || !($oBlog->getBlogType()->IsPrivate()||$oBlog->getBlogType()->IsReadOnly())) {
            return $this->eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

        // Проверим, что текущий пользователь имеет право принимать решение
        if (!($oBlog->getUserIsAdministrator() || $oBlog->getUserIsModerator() || $oBlog->getOwnerId() == \E::userId())) {
            return $this->eventNotFound();
        }

        // Получим пользователя, который запрашивает приглашение
        if (!($oGuestUser = \E::Module('User')->getUserById($sUserId))) {
            return $this->eventNotFound();
        }

        // * Получаем связь "блог-пользователь" и проверяем, чтобы ее тип был REQUEST
        if (!$oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $oGuestUser->getId())) {
            return $this->eventNotFound();
        }

        // Пользователь уже принят в ряды
        if ($oBlogUser->getUserRole() >= ModuleBlog::BLOG_USER_ROLE_MEMBER) {
            $sMessage = \E::Module('Lang')->get('blog_user_request_already_done');
            \E::Module('Message')->addError($sMessage, \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return null;
        }

        // У пользователя непонятный флаг
        if ($oBlogUser->getUserRole() != ModuleBlog::BLOG_USER_ROLE_WISHES) {
            return $this->eventNotFound();
        }

        // * Обновляем роль пользователя до читателя
        $oBlogUser->setUserRole(($sAction === 'accept') ? ModuleBlog::BLOG_USER_ROLE_MEMBER : ModuleBlog::BLOG_USER_ROLE_NOTMEMBER);
        if (!\E::Module('Blog')->UpdateRelationBlogUser($oBlogUser)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'), true);
            R::Location(R::getLink('talk'));
            return null;
        }
        if ($sAction === 'accept') {

            // * Увеличиваем число читателей блога
            $oBlog->setCountUser($oBlog->getCountUser() + 1);
            \E::Module('Blog')->UpdateBlog($oBlog);
            $sMessage = \E::Module('Lang')->get('blog_user_request_accept');

            // * Добавляем событие в ленту
            \E::Module('Stream')->write($oBlogUser->getUserId(), 'join_blog', $oBlog->getId());
        } else {
            $sMessage = \E::Module('Lang')->get('blog_user_request_no_accept');
        }
        \E::Module('Message')->addNotice($sMessage, \E::Module('Lang')->get('attention'), true);

        // * Перенаправляем на страницу личной почты
        R::Location(R::getLink('talk'));

        return null;
    }

    /**
     * Удаление блога
     *
     */
    public function eventDeleteBlog() 
    {
        \E::Module('Security')->validateSendForm();

        // * Проверяем передан ли в УРЛе номер блога
        $nBlogId = (int)$this->getParam(0);
        if (!$nBlogId || (!$oBlog = \E::Module('Blog')->getBlogById($nBlogId))) {
            return parent::eventNotFound();
        }
        $this->oCurrentBlog = $oBlog;

        // * Проверям авторизован ли пользователь
        if (!\E::isUser()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        // * проверяем есть ли право на удаление блога
        if (!$nAccess = \E::Module('ACL')->IsAllowDeleteBlog($oBlog, $this->oUserCurrent)) {
            return parent::eventNotFound();
        }
        $aTopics = \E::Module('Topic')->getTopicsByBlogId($nBlogId);

        switch ($nAccess) {
            case ModuleACL::CAN_DELETE_BLOG_EMPTY_ONLY :
                if (is_array($aTopics) && count($aTopics)) {
                    \E::Module('Message')->addErrorSingle(
                        \E::Module('Lang')->get('blog_admin_delete_not_empty'), \E::Module('Lang')->get('error'), true
                    );
                    R::Location($oBlog->getUrlFull());
                }
                break;
            case ModuleACL::CAN_DELETE_BLOG_WITH_TOPICS :
                /*
                 * Если указан идентификатор блога для перемещения,
                 * то делаем попытку переместить топики.
                 *
                 * (-1) - выбран пункт меню "удалить топики".
                 */
                $nNewBlogId = F::getRequestInt('topic_move_to');
                if (($nNewBlogId > 0) && is_array($aTopics) && count($aTopics)) {
                    if (!$oBlogNew = \E::Module('Blog')->getBlogById($nNewBlogId)) {
                        \E::Module('Message')->addErrorSingle(
                            \E::Module('Lang')->get('blog_admin_delete_move_error'), \E::Module('Lang')->get('error'), true
                        );
                        R::Location($oBlog->getUrlFull());
                    }
                    // * Если выбранный блог является персональным, возвращаем ошибку
                    if ($oBlogNew->getType() === 'personal') {
                        \E::Module('Message')->addErrorSingle(
                            \E::Module('Lang')->get('blog_admin_delete_move_personal'), \E::Module('Lang')->get('error'), true
                        );
                        R::Location($oBlog->getUrlFull());
                    }
                    // * Перемещаем топики
                    \E::Module('Topic')->moveTopics($nBlogId, $nNewBlogId);
                }
                break;
            default:
                return parent::eventNotFound();
        }

        // * Удаляяем блог и перенаправляем пользователя к списку блогов
        \HookManager::run('blog_delete_before', ['sBlogId' => $nBlogId]);

        if ($this->_deleteBlog($oBlog)) {
            \HookManager::run('blog_delete_after', ['sBlogId' => $nBlogId]);
            \E::Module('Message')->addNoticeSingle(
                \E::Module('Lang')->get('blog_admin_delete_success'), \E::Module('Lang')->get('attention'), true
            );
            R::Location(R::getLink('blogs'));
        } else {
            R::Location($oBlog->getUrlFull());
        }
        return null;
    }

    /**
     * Удаление блога
     *
     * @param $oBlog
     *
     * @return bool
     */
    protected function _deleteBlog($oBlog)
    {
        return \E::Module('Blog')->DeleteBlog($oBlog);
    }

    /**
     * Получение описания блога
     *
     */
    protected function ajaxBlogInfo() 
    {
        //  Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $iBlogId = F::getRequestInt('idBlog', null, 'post');
        //  Определяем тип блога и получаем его
        if (($iBlogId == 0) && $this->oUserCurrent) {
            $oBlog = \E::Module('Blog')->getPersonalBlogByUserId($this->oUserCurrent->getId());
        } elseif ($iBlogId) {
            $this->oCurrentBlog = $oBlog = \E::Module('Blog')->getBlogById($iBlogId);
        } else {
            $oBlog = null;
        }

        //  если блог найден, то возвращаем описание
        if ($oBlog) {
            $sText = $oBlog->getDescription();
            \E::Module('Viewer')->assignAjax('sText', $sText);
        }
    }

    /**
     * Подключение/отключение к блогу
     *
     */
    protected function ajaxBlogJoin() 
    {
        //  Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        //  Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        //  Блог существует?
        $nBlogId = F::getRequestInt('idBlog', null, 'post');
        if (!$nBlogId || !($oBlog = \E::Module('Blog')->getBlogById($nBlogId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        $this->oCurrentBlog = $oBlog;

        // Type of the blog
        $oBlogType = $oBlog->getBlogType();

        // Current status of user in the blog
        /** @var ModuleBlog_EntityBlogUser $oBlogUser */
        $oBlogUser = \E::Module('Blog')->getBlogUserByBlogIdAndUserId($oBlog->getId(), $this->oUserCurrent->getId());

        if (!$oBlogUser || ($oBlogUser->getUserRole() < ModuleBlog::BLOG_USER_ROLE_GUEST && (!$oBlogType || $oBlogType->IsPrivate()))) {
            // * Проверяем тип блога на возможность свободного вступления или вступления по запросу
            if ($oBlogType && !$oBlogType->GetMembership(ModuleBlog::BLOG_USER_JOIN_FREE) && !$oBlogType->GetMembership(ModuleBlog::BLOG_USER_JOIN_REQUEST)) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_join_error_invite'), \E::Module('Lang')->get('error'));
                return;
            }
            if ($oBlog->getOwnerId() != $this->oUserCurrent->getId()) {
                // Subscribe user to the blog
                if ($oBlogType->GetMembership(ModuleBlog::BLOG_USER_JOIN_FREE)) {
                    $bResult = false;
                    if ($oBlogUser) {
                        $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_MEMBER);
                        $bResult = \E::Module('Blog')->UpdateRelationBlogUser($oBlogUser);
                    } else {
                        // User can free subscribe to blog
                        /** @var ModuleBlog_EntityBlogUser $oBlogUserNew */
                        $oBlogUserNew = \E::getEntity('Blog_BlogUser');
                        $oBlogUserNew->setBlogId($oBlog->getId());
                        $oBlogUserNew->setUserId($this->oUserCurrent->getId());
                        $oBlogUserNew->setUserRole(ModuleBlog::BLOG_USER_ROLE_MEMBER);
                        $bResult = \E::Module('Blog')->AddRelationBlogUser($oBlogUserNew);
                    }
                    if ($bResult) {
                        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_join_ok'), \E::Module('Lang')->get('attention'));
                        \E::Module('Viewer')->assignAjax('bState', true);
                        //  Увеличиваем число читателей блога
                        $oBlog->setCountUser($oBlog->getCountUser() + 1);
                        \E::Module('Blog')->UpdateBlog($oBlog);
                        \E::Module('Viewer')->assignAjax('iCountUser', $oBlog->getCountUser());
                        //  Добавляем событие в ленту
                        \E::Module('Stream')->write($this->oUserCurrent->getId(), 'join_blog', $oBlog->getId());
                        //  Добавляем подписку на этот блог в ленту пользователя
                        \E::Module('Userfeed')->SubscribeUser(
                            $this->oUserCurrent->getId(), ModuleUserfeed::SUBSCRIBE_TYPE_BLOG, $oBlog->getId()
                        );
                    } else {
                        $sMsg = $oBlogType->IsPrivate()
                            ? \E::Module('Lang')->get('blog_join_error_invite')
                            : \E::Module('Lang')->get('system_error');
                        \E::Module('Message')->addErrorSingle($sMsg, \E::Module('Lang')->get('error'));
                        return;
                    }
                }

                // Подписываем по запросу
                if ($oBlogType->GetMembership(ModuleBlog::BLOG_USER_JOIN_REQUEST)) {

                    // Подписка уже была запрошена, но результатов пока нет
                    if ($oBlogUser && $oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_WISHES) {
                        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_join_request_already'), \E::Module('Lang')->get('attention'));
                        \E::Module('Viewer')->assignAjax('bState', true);
                        return;
                    }

                    if ($oBlogUser) {
                        if (!in_array($oBlogUser->getUserRole(), [ModuleBlog::BLOG_USER_ROLE_REJECT, ModuleBlog::BLOG_USER_ROLE_WISHES])) {
                            $sMessage = \E::Module('Lang')->get('blog_user_status_is') . ' "' . \E::Module('Blog')->getBlogUserRoleName($oBlogUser->getUserRole()) . '"';
                            \E::Module('Message')->addNoticeSingle($sMessage, \E::Module('Lang')->get('attention'));
                            \E::Module('Viewer')->assignAjax('bState', true);
                            return;
                        } else {
                            $oBlogUser->setUserRole(ModuleBlog::BLOG_USER_ROLE_WISHES);
                            $bResult = \E::Module('Blog')->UpdateRelationBlogUser($oBlogUser);
                        }
                    } else {
                        // Подписки ещё не было - оформим ее
                        /** @var ModuleBlog_EntityBlogUser $oBlogUserNew */
                        $oBlogUserNew = \E::getEntity('Blog_BlogUser');
                        $oBlogUserNew->setBlogId($oBlog->getId());
                        $oBlogUserNew->setUserId($this->oUserCurrent->getId());
                        $oBlogUserNew->setUserRole(ModuleBlog::BLOG_USER_ROLE_WISHES);
                        $bResult = \E::Module('Blog')->AddRelationBlogUser($oBlogUserNew);
                    }

                    if ($bResult) {
                        // Отправим сообщение модераторам и администраторам блога о том, что
                        // этот пользоватлеь захотел присоединиться к нашему блогу
                        $aBlogUsersResult = \E::Module('Blog')->getBlogUsersByBlogId(
                            $oBlog->getId(),
                            [
                                ModuleBlog::BLOG_USER_ROLE_MODERATOR,
                                ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR
                            ], null
                        );
                        if ($aBlogUsersResult) {
                            /** @var ModuleUser_EntityUser[] $aBlogModerators */
                            $aBlogModerators = [];
                            /** @var ModuleBlog_EntityBlogUser $oCurrentBlogUser */
                            foreach ($aBlogUsersResult['collection'] as $oCurrentBlogUser) {
                                $aBlogModerators[] = $oCurrentBlogUser->getUser();
                            }
                            // Добавим владельца блога к списку
                            $aBlogModerators = array_merge($aBlogModerators, [$oBlog->getOwner()]);
                            $this->sendBlogRequest($oBlog, $aBlogModerators, $this->oUserCurrent);
                        }


                        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_join_request_send'), \E::Module('Lang')->get('attention'));
                        \E::Module('Viewer')->assignAjax('bState', true);
                        return;
                    }

                }

            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_join_error_self'), \E::Module('Lang')->get('attention'));
                return;
            }
        }
        if ($oBlogUser && ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_MEMBER)) {

            // Unsubscribe user from the blog
            if (\E::Module('Blog')->DeleteRelationBlogUser($oBlogUser)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_leave_ok'), \E::Module('Lang')->get('attention'));
                \E::Module('Viewer')->assignAjax('bState', false);

                //  Уменьшаем число читателей блога
                $oBlog->setCountUser($oBlog->getCountUser() - 1);
                \E::Module('Blog')->UpdateBlog($oBlog);
                \E::Module('Viewer')->assignAjax('iCountUser', $oBlog->getCountUser());

                //  Удаляем подписку на этот блог в ленте пользователя
                \E::Module('Userfeed')->UnsubscribeUser($this->oUserCurrent->getId(), ModuleUserfeed::SUBSCRIBE_TYPE_BLOG, $oBlog->getId());
                return;
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
        if ($oBlogUser && ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_NOTMEMBER)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_user_request_no_accept'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oBlogUser && ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_leave_error_banned'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oBlogUser && ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_BAN_FOR_COMMENT)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_leave_error_banned'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oBlogUser && ($oBlogUser->getUserRole() == ModuleBlog::BLOG_USER_ROLE_WISHES)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_join_request_leave'), \E::Module('Lang')->get('attention'));
            \E::Module('Viewer')->assignAjax('bState', true);
            return;
        }
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function eventShutdown() 
    {
        //  Загружаем в шаблон необходимые переменные
        \E::Module('Viewer')->assign('sMenuHeadItemSelect', $this->sMenuHeadItemSelect);
        \E::Module('Viewer')->assign('sMenuItemSelect', $this->sMenuItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
        \E::Module('Viewer')->assign('sMenuSubBlogUrl', $this->sMenuSubBlogUrl);
        \E::Module('Viewer')->assign('iCountTopicsCollectiveNew', $this->iCountTopicsCollectiveNew);
        \E::Module('Viewer')->assign('iCountTopicsPersonalNew', $this->iCountTopicsPersonalNew);
        \E::Module('Viewer')->assign('iCountTopicsBlogNew', $this->iCountTopicsBlogNew);
        \E::Module('Viewer')->assign('iCountTopicsNew', $this->iCountTopicsNew);

        \E::Module('Viewer')->assign('BLOG_USER_ROLE_GUEST', ModuleBlog::BLOG_USER_ROLE_GUEST);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_USER', ModuleBlog::BLOG_USER_ROLE_MEMBER);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_MODERATOR', ModuleBlog::BLOG_USER_ROLE_MODERATOR);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_ADMINISTRATOR', ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_INVITE', ModuleBlog::BLOG_USER_ROLE_INVITE);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_REJECT', ModuleBlog::BLOG_USER_ROLE_REJECT);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_BAN', ModuleBlog::BLOG_USER_ROLE_BAN);
        \E::Module('Viewer')->assign('BLOG_USER_ROLE_BAN_FOR_COMMENT', ModuleBlog::BLOG_USER_ROLE_BAN_FOR_COMMENT);
    }

}

// EOF