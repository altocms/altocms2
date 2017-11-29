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
 * Экшен обработки личной почты (сообщения /talk/)
 *
 * @package actions
 * @since   1.0
 */
class ActionTalk extends Action {
    /**
     * Текущий юзер
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;
    /**
     * Подменю
     *
     * @var string
     */
    protected $sMenuSubItemSelect = '';
    /**
     * Массив ID юзеров адресатов
     *
     * @var array
     */
    protected $aUsersId = [];

    /**
     * Инициализация
     *
     */
    public function init() {

        // * Проверяем авторизован ли юзер
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'));
            return R::redirect('error');
        }

        // * Получаем текущего юзера
        $this->oUserCurrent = \E::User();
        $this->setDefaultEvent('inbox');
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('talk_menu_inbox'));

        // * Загружаем в шаблон JS текстовки
        \E::Module('Lang')->addLangJs(
            array(
                 'delete',
                 'talk_inbox_delete_confirm'
            )
        );
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent() {

        $this->addEvent('inbox', 'eventInbox');
        $this->addEvent('add', 'eventAdd');
        $this->addEvent('read', 'eventRead');
        $this->addEvent('delete', 'eventDelete');
        $this->addEvent('ajaxaddcomment', 'AjaxAddComment');
        $this->addEvent('ajaxresponsecomment', 'AjaxResponseComment');
        $this->addEvent('favourites', 'eventFavourites');
        $this->addEvent('blacklist', 'eventBlacklist');
        $this->addEvent('ajaxaddtoblacklist', 'AjaxAddToBlacklist');
        $this->addEvent('ajaxdeletefromblacklist', 'AjaxDeleteFromBlacklist');
        $this->addEvent('ajaxdeletetalkuser', 'AjaxDeleteTalkUser');
        $this->addEvent('ajaxaddtalkuser', 'AjaxAddTalkUser');
        $this->addEvent('ajaxnewmessages', 'AjaxNewMessages');
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Удаление письма
     */
    public function eventDelete()
    {
        \E::Module('Security')->validateSendForm();

        // * Получаем номер сообщения из УРЛ и проверяем существует ли оно
        $sTalkId = $this->getParam(0);
        if (!($oTalk = \E::Module('Talk')->getTalkById($sTalkId))) {
            return parent::eventNotFound();
        }

        // * Пользователь входит в переписку?
        if (!($oTalkUser = \E::Module('Talk')->getTalkUser($oTalk->getId(), $this->oUserCurrent->getId()))) {
            return parent::eventNotFound();
        }

        // * Обработка удаления сообщения
        \E::Module('Talk')->DeleteTalkUserByArray($sTalkId, $this->oUserCurrent->getId());
        R::Location(R::getLink('talk'));
    }

    /**
     * Отображение списка сообщений
     */
    public function eventInbox() {

        // * Обработка удаления сообщений
        if (\F::getRequest('submit_talk_del')) {
            \E::Module('Security')->validateSendForm();

            $aTalksIdDel = F::getRequest('talk_select');
            if (is_array($aTalksIdDel)) {
                \E::Module('Talk')->DeleteTalkUserByArray(array_keys($aTalksIdDel), $this->oUserCurrent->getId());
            }
        }

        // * Обработка отметки о прочтении
        if (\F::getRequest('submit_talk_read')) {
            \E::Module('Security')->validateSendForm();

            $aTalksIdDel = F::getRequest('talk_select');
            if (is_array($aTalksIdDel)) {
                \E::Module('Talk')->MarkReadTalkUserByArray(array_keys($aTalksIdDel), $this->oUserCurrent->getId());
            }
        }

        // * Обработка отметки непрочтенных сообщений
        if (\F::getRequest('submit_talk_unread')) {
            \E::Module('Security')->validateSendForm();

            $aTalksIdDel = F::getRequest('talk_select');
            if (is_array($aTalksIdDel)) {
                \E::Module('Talk')->MarkUnreadTalkUserByArray(array_keys($aTalksIdDel), $this->oUserCurrent->getId());
            }
        }
        $this->sMenuSubItemSelect = 'inbox';

        // * Количество сообщений на страницу
        $iPerPage = \C::get('module.talk.per_page');

        // * Формируем фильтр для поиска сообщений
        $aFilter = $this->BuildFilter();

        // * Если только новые, то добавляем условие в фильтр
        if ($this->getParam(0) == 'new') {
            $this->sMenuSubItemSelect = 'new';
            $aFilter['only_new'] = true;
            $iPerPage = 50; // новых отображаем только последние 50 писем, без постраничности
        }

        // * Передан ли номер страницы
        $iPage = preg_match('/^page([1-9]\d{0,5})$/i', $this->getParam(0), $aMatch) ? $aMatch[1] : 1;

        // * Получаем список писем
        $aResult = \E::Module('Talk')->getTalksByFilter($aFilter, $iPage, $iPerPage);

        $aTalks = $aResult['collection'];

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, $iPerPage, \C::get('pagination.pages.count'),
            R::getLink('talk') . $this->sCurrentEvent,
            array_intersect_key(
                $_REQUEST,
                array_fill_keys(
                    array('start', 'end', 'keyword', 'sender', 'keyword_text', 'favourite'),
                    ''
                )
            )
        );

        // * Показываем сообщение, если происходит поиск по фильтру
        if (\F::getRequest('submit_talk_filter')) {
            \E::Module('Message')->addNotice(
                ($aResult['count'])
                    ? \E::Module('Lang')->get('talk_filter_result_count', array('count' => $aResult['count']))
                    : \E::Module('Lang')->get('talk_filter_result_empty')
            );
        }

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTalks', $aTalks);
    }

    /**
     * Формирует из REQUEST массива фильтр для отбора писем
     *
     * @return array
     */
    protected function BuildFilter() {

        // * Текущий пользователь
        $aFilter = array(
            'user_id' => $this->oUserCurrent->getId(),
        );

        // * Дата старта поиска
        if ($start = F::getRequestStr('start')) {
            if (\F::CheckVal($start, 'text', 6, 10) && substr_count($start, '.') == 2) {
                list($d, $m, $y) = explode('.', $start);
                if (@checkdate($m, $d, $y)) {
                    $aFilter['date_min'] = "{$y}-{$m}-{$d}";
                } else {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('talk_filter_error_date_format'),
                        \E::Module('Lang')->get('talk_filter_error')
                    );
                    unset($_REQUEST['start']);
                }
            } else {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get('talk_filter_error_date_format'),
                    \E::Module('Lang')->get('talk_filter_error')
                );
                unset($_REQUEST['start']);
            }
        }

        // * Дата окончания поиска
        if ($end = F::getRequestStr('end')) {
            if (\F::CheckVal($end, 'text', 6, 10) && substr_count($end, '.') == 2) {
                list($d, $m, $y) = explode('.', $end);
                if (@checkdate($m, $d, $y)) {
                    $aFilter['date_max'] = "{$y}-{$m}-{$d} 23:59:59";
                } else {
                    \E::Module('Message')->addError(
                        \E::Module('Lang')->get('talk_filter_error_date_format'),
                        \E::Module('Lang')->get('talk_filter_error')
                    );
                    unset($_REQUEST['end']);
                }
            } else {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get('talk_filter_error_date_format'),
                    \E::Module('Lang')->get('talk_filter_error')
                );
                unset($_REQUEST['end']);
            }
        }

        // * Ключевые слова в теме сообщения
        if (($sKeyRequest = F::getRequest('keyword')) && is_string($sKeyRequest)) {
            $sKeyRequest = urldecode($sKeyRequest);
            preg_match_all('~(\S+)~u', $sKeyRequest, $aWords);

            if (is_array($aWords[1]) && isset($aWords[1]) && count($aWords[1])) {
                $aFilter['keyword'] = '%' . implode('%', $aWords[1]) . '%';
            } else {
                unset($_REQUEST['keyword']);
            }
        }

        // * Ключевые слова в тексте сообщения
        if (($sKeyRequest = F::getRequest('keyword_text')) && is_string($sKeyRequest)) {
            $sKeyRequest = urldecode($sKeyRequest);
            preg_match_all('~(\S+)~u', $sKeyRequest, $aWords);

            if (is_array($aWords[1]) && isset($aWords[1]) && count($aWords[1])) {
                $aFilter['text_like'] = '%' . implode('%', $aWords[1]) . '%';
            } else {
                unset($_REQUEST['keyword_text']);
            }
        }

        // * Отправитель
        if (($sSender = F::getRequest('sender')) && is_string($sSender)) {
            $aFilter['user_login'] = F::Array_Str2Array(urldecode($sSender), ',', true);
        }
        // * Адресат
        if (($sAddressee = F::getRequest('addressee')) && is_string($sAddressee)) {
            $aFilter['user_login'] = F::Array_Str2Array(urldecode($sAddressee), ',', true);
        }

        // * Искать только в избранных письмах
        if (\F::getRequest('favourite')) {
            $aTalkIdResult = \E::Module('Favourite')->getFavouritesByUserId(
                $this->oUserCurrent->getId(), 'talk', 1, 500
            ); // ограничиваем
            $aFilter['id'] = $aTalkIdResult['collection'];
            $_REQUEST['favourite'] = 1;
        } else {
            unset($_REQUEST['favourite']);
        }
        return $aFilter;
    }

    /**
     * Отображение списка блэк-листа
     */
    public function eventBlacklist() {

        $this->sMenuSubItemSelect = 'blacklist';
        $aUsersBlacklist = \E::Module('Talk')->getBlacklistByUserId($this->oUserCurrent->getId());
        \E::Module('Viewer')->assign('aUsersBlacklist', $aUsersBlacklist);
    }

    /**
     * Отображение списка избранных писем
     */
    public function eventFavourites() {

        $this->sMenuSubItemSelect = 'favourites';

        // * Передан ли номер страницы
        $iPage = preg_match("/^page([1-9]\d{0,5})$/i", $this->getParam(0), $aMatch) ? $aMatch[1] : 1;

        // * Получаем список писем
        $aResult = \E::Module('Talk')->getTalksFavouriteByUserId(
            $this->oUserCurrent->getId(),
            $iPage, \C::get('module.talk.per_page')
        );
        $aTalks = $aResult['collection'];

        // * Формируем постраничность
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.talk.per_page'), \C::get('pagination.pages.count'),
            R::getLink('talk') . $this->sCurrentEvent
        );

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTalks', $aTalks);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('talk_favourite_inbox'));
    }

    /**
     * Страница создания письма
     */
    public function eventAdd() {

        $this->sMenuSubItemSelect = 'add';
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('talk_menu_inbox_create'));

        // * Получаем список друзей
        $aUsersFriend = \E::Module('User')->getUsersFriend($this->oUserCurrent->getId());
        if ($aUsersFriend['collection']) {
            \E::Module('Viewer')->assign('aUsersFriend', $aUsersFriend['collection']);
        }

        // * Проверяем отправлена ли форма с данными
        if (!F::isPost('submit_talk_add')) {
            return false;
        }

        // * Проверка корректности полей формы
        if (!$this->checkTalkFields()) {
            return false;
        }

        // * Проверяем разрешено ли отправлять инбокс по времени
        if (!\E::Module('ACL')->canSendTalkTime($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('talk_time_limit'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Отправляем письмо
        if ($oTalk = \E::Module('Talk')->SendTalk(
            \E::Module('Text')->Parse(strip_tags(\F::getRequestStr('talk_title'))), \E::Module('Text')->Parse(\F::getRequestStr('talk_text')),
            $this->oUserCurrent, $this->aUsersId
        )
        ) {

            \E::Module('Media')->CheckTargetTextForImages('talk', $oTalk->getId(), $oTalk->getText());

            R::Location(R::getLink('talk') . 'read/' . $oTalk->getId() . '/');
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return R::redirect('error');
        }
    }

    /**
     * Чтение письма
     */
    public function eventRead() {

        $this->sMenuSubItemSelect = 'read';

        // * Получаем номер сообщения из УРЛ и проверяем существует ли оно
        $sTalkId = $this->getParam(0);
        if (!($oTalk = \E::Module('Talk')->getTalkById($sTalkId))) {
            return parent::eventNotFound();
        }

        // * Пользователь есть в переписке?
        if (!($oTalkUser = \E::Module('Talk')->getTalkUser($oTalk->getId(), $this->oUserCurrent->getId()))) {
            return parent::eventNotFound();
        }

        // * Пользователь активен в переписке?
        if ($oTalkUser->getUserActive() != ModuleTalk::TALK_USER_ACTIVE) {
            return parent::eventNotFound();
        }

        // * Обрабатываем добавление коммента
        if (isset($_REQUEST['submit_comment'])) {
            $this->SubmitComment();
        }

        // * Достаём комменты к сообщению
        $aReturn = \E::Module('Comment')->getCommentsByTargetId($oTalk, 'talk');
        $iMaxIdComment = $aReturn['iMaxIdComment'];
        $aComments = $aReturn['comments'];

        // * Помечаем дату последнего просмотра
        $oTalkUser->setDateLast(\F::Now());
        $oTalkUser->setCommentIdLast($iMaxIdComment);
        $oTalkUser->setCommentCountNew(0);
        \E::Module('Talk')->UpdateTalkUser($oTalkUser);

        \E::Module('Viewer')->addHtmlTitle($oTalk->getTitle());
        \E::Module('Viewer')->assign('oTalk', $oTalk);
        \E::Module('Viewer')->assign('aComments', $aComments);
        \E::Module('Viewer')->assign('iMaxIdComment', $iMaxIdComment);
        /*
         * Подсчитываем нужно ли отображать комментарии.
         * Комментарии не отображаются, если у вестки только один читатель
         * и ранее созданных комментариев нет.
         */
        if (count($aComments) == 0) {
            $iActiveSpeakers = 0;
            foreach ((array)$oTalk->getTalkUsers() as $oTalkUser) {
                if (($oTalkUser->getUserId() != $this->oUserCurrent->getId())
                    && $oTalkUser->getUserActive() == ModuleTalk::TALK_USER_ACTIVE
                ) {
                    $iActiveSpeakers++;
                    break;
                }
            }
            if ($iActiveSpeakers == 0) {
                \E::Module('Viewer')->assign('bNoComments', true);
            }
        }

        \E::Module('Viewer')->assign('bAllowToComment', true);
        $this->setTemplateAction('message');
    }

    /**
     * Проверка полей при создании письма
     *
     * @return bool
     */
    protected function checkTalkFields() {
        \E::Module('Security')->validateSendForm();

        $bOk = true;

        // * Проверяем есть ли заголовок
        if (!F::CheckVal(\F::getRequestStr('talk_title'), 'text', 2, 200)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_title_error'), \E::Module('Lang')->get('error'));
            $bOk = false;
        }

        // * Проверяем есть ли содержание топика
        $iMin = (int)Config::get('module.talk.min_length');
        $iMax = (int)Config::get('module.talk.max_length');
        if (!F::CheckVal(\F::getRequestStr('talk_text'), 'text', $iMin, $iMax)) {
            if ($iMax) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_text_error_len', ['min'=>$iMin, 'max'=>$iMax]), \E::Module('Lang')->get('error'));
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_text_error_min', ['min'=>$iMin]), \E::Module('Lang')->get('error'));
            }
            $bOk = false;
        }

        // * Проверяем адресатов
        $sUsers = F::getRequest('talk_users');
        $aUsers = explode(',', (string)$sUsers);
        $aUsersNew = [];
        $aUserInBlacklist = \E::Module('Talk')->getBlacklistByTargetId($this->oUserCurrent->getId());

        $this->aUsersId = [];
        foreach ($aUsers as $sUser) {
            $sUser = trim($sUser);
            if ($sUser === '' || strtolower($sUser) === strtolower($this->oUserCurrent->getLogin())) {
                continue;
            }
            if (($oUser = \E::Module('User')->getUserByLogin($sUser)) && $oUser->getActivate() == 1) {
                // Проверяем, попал ли отправиль в блек лист
                if (!in_array($oUser->getId(), $aUserInBlacklist)) {
                    $this->aUsersId[] = $oUser->getId();
                } else {
                    \E::Module('Message')->addError(
                        str_replace(
                            'login',
                            $oUser->getLogin(),
                            \E::Module('Lang')->get(
                                'talk_user_in_blacklist', array('login' => htmlspecialchars($oUser->getLogin()))
                            )
                        ),
                        \E::Module('Lang')->get('error')
                    );
                    $bOk = false;
                    continue;
                }
            } else {
                \E::Module('Message')->addError(
                    \E::Module('Lang')->get('talk_create_users_error_not_found') . ' «' . htmlspecialchars($sUser) . '»',
                    \E::Module('Lang')->get('error')
                );
                $bOk = false;
            }
            $aUsersNew[] = $sUser;
        }
        if (!count($aUsersNew)) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_users_error'), \E::Module('Lang')->get('error'));
            $_REQUEST['talk_users'] = '';
            $bOk = false;
        } else {
            if (count($aUsersNew) > \C::get('module.talk.max_users') && !$this->oUserCurrent->isAdministrator()) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_users_error_many'), \E::Module('Lang')->get('error'));
                $bOk = false;
            }
            $_REQUEST['talk_users'] = implode(',', $aUsersNew);
        }

        // * Выполнение хуков
        \HookManager::run('check_talk_fields', array('bOk' => &$bOk));

        return $bOk;
    }

    /**
     * Получение новых комментариев
     *
     */
    protected function AjaxResponseComment() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $idCommentLast = F::getRequestStr('idCommentLast');

        // * Проверям авторизован ли пользователь
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Проверяем разговор
        if (!($oTalk = \E::Module('Talk')->getTalkById(\F::getRequestStr('idTarget')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        if (!($oTalkUser = \E::Module('Talk')->getTalkUser($oTalk->getId(), $this->oUserCurrent->getId()))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Получаем комментарии
        $aReturn = \E::Module('Comment')->getCommentsNewByTargetId($oTalk->getId(), 'talk', $idCommentLast);
        $iMaxIdComment = $aReturn['iMaxIdComment'];

        // * Отмечаем дату прочтения письма
        $oTalkUser->setDateLast(\F::Now());
        if ($iMaxIdComment != 0) {
            $oTalkUser->setCommentIdLast($iMaxIdComment);
        }
        $oTalkUser->setCommentCountNew(0);
        \E::Module('Talk')->UpdateTalkUser($oTalkUser);

        $aComments = [];
        $aCmts = $aReturn['comments'];
        if ($aCmts && is_array($aCmts)) {
            foreach ($aCmts as $aCmt) {
                $aComments[] = array(
                    'html'     => $aCmt['html'],
                    'idParent' => $aCmt['obj']->getPid(),
                    'id'       => $aCmt['obj']->getId(),
                );
            }
        }
        \E::Module('Viewer')->assignAjax('aComments', $aComments);
        \E::Module('Viewer')->assignAjax('iMaxIdComment', $iMaxIdComment);
    }

    /**
     * Обработка добавление комментария к письму через ajax
     *
     */
    protected function AjaxAddComment() {

        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $this->SubmitComment();
    }

    /**
     * Обработка добавление комментария к письму
     *
     */
    protected function submitComment() {

        // * Проверям авторизован ли пользователь
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем разговор
        if (!($oTalk = \E::Module('Talk')->getTalkById(\F::getRequestStr('cmt_target_id')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return false;
        }
        if (!($oTalkUser = \E::Module('Talk')->getTalkUser($oTalk->getId(), $this->oUserCurrent->getId()))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем разрешено ли отправлять инбокс по времени
        if (!\E::Module('ACL')->canPostTalkCommentTime($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('talk_time_limit'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем текст комментария
        $sText = \E::Module('Text')->Parse(\F::getRequestStr('comment_text'));
        $iMin = (int)Config::get('module.talk.min_length');
        $iMax = (int)Config::get('module.talk.max_length');
        if (!F::CheckVal($sText, 'text', $iMin, $iMax)) {
            if ($iMax) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_text_error_len', array('min'=>$iMin, 'max'=>$iMax)), \E::Module('Lang')->get('error'));
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_text_error_min', array('min'=>$iMin)), \E::Module('Lang')->get('error'));
            }
            return false;
        }

        // * Проверям на какой коммент отвечаем
        $sParentId = (int)F::getRequest('reply');
        if (!F::CheckVal($sParentId, 'id')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return false;
        }
        $oCommentParent = null;
        if ($sParentId != 0) {

            // * Проверяем существует ли комментарий на который отвечаем
            if (!($oCommentParent = \E::Module('Comment')->getCommentById($sParentId))) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return false;
            }

            // * Проверяем из одного топика ли новый коммент и тот на который отвечаем
            if ($oCommentParent->getTargetId() != $oTalk->getId()) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return false;
            }
        } else {

            // * Корневой комментарий
            $sParentId = null;
        }

        // * Проверка на дублирующий коммент
        if (\E::Module('Comment')->getCommentUnique($oTalk->getId(), 'talk', $this->oUserCurrent->getId(), $sParentId, md5($sText))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_comment_spam'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Создаём комментарий
        /** @var ModuleComment_EntityComment $oCommentNew */
        $oCommentNew = \E::getEntity('Comment');
        $oCommentNew->setTargetId($oTalk->getId());
        $oCommentNew->setTargetType('talk');
        $oCommentNew->setUserId($this->oUserCurrent->getId());
        $oCommentNew->setText($sText);
        $oCommentNew->setDate(\F::Now());
        $oCommentNew->setUserIp(\F::GetUserIp());
        $oCommentNew->setPid($sParentId);
        $oCommentNew->setTextHash(md5($sText));
        $oCommentNew->setPublish(1);

        // * Добавляем коммент
        \HookManager::run(
            'talk_comment_add_before',
            array('oCommentNew' => $oCommentNew, 'oCommentParent' => $oCommentParent, 'oTalk' => $oTalk)
        );
        if (\E::Module('Comment')->AddComment($oCommentNew)) {
            \HookManager::run(
                'talk_comment_add_after',
                array('oCommentNew' => $oCommentNew, 'oCommentParent' => $oCommentParent, 'oTalk' => $oTalk)
            );

            \E::Module('Viewer')->assignAjax('sCommentId', $oCommentNew->getId());
            $oTalk->setDateLast(\F::Now());
            $oTalk->setUserIdLast($oCommentNew->getUserId());
            $oTalk->setCommentIdLast($oCommentNew->getId());
            $oTalk->setCountComment($oTalk->getCountComment() + 1);
            \E::Module('Talk')->UpdateTalk($oTalk);

            // * Отсылаем уведомления всем адресатам
            $aUsersTalk = \E::Module('Talk')->getUsersTalk($oTalk->getId(), ModuleTalk::TALK_USER_ACTIVE);

            foreach ($aUsersTalk as $oUserTalk) {
                if ($oUserTalk->getId() != $oCommentNew->getUserId()) {
                    \E::Module('Notify')->sendTalkCommentNew($oUserTalk, $this->oUserCurrent, $oTalk, $oCommentNew);
                }
            }

            // * Увеличиваем число новых комментов
            \E::Module('Talk')->IncreaseCountCommentNew($oTalk->getId(), $oCommentNew->getUserId());
            return true;
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        }
        return false;
    }

    /**
     * Добавление нового пользователя(-лей) в блек лист (ajax)
     *
     */
    public function ajaxAddToBlacklist()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $sUsers = F::getRequestStr('users', null, 'post');

        // * Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $aUsers = explode(',', $sUsers);

        // * Получаем блекслист пользователя
        $aUserBlacklist = \E::Module('Talk')->getBlacklistByUserId($this->oUserCurrent->getId());

        $aResult = [];

        // * Обрабатываем добавление по каждому из переданных логинов
        foreach ($aUsers as $sUser) {
            $sUser = trim($sUser);
            if ($sUser == '') {
                continue;
            }

            // * Если пользователь пытается добавить в блеклист самого себя, возвращаем ошибку
            if (strtolower($sUser) == strtolower($this->oUserCurrent->getLogin())) {
                $aResult[] = array(
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('talk_blacklist_add_self')
                );
                continue;
            }

            // * Если пользователь не найден или неактивен, возвращаем ошибку
            if (($oUser = \E::Module('User')->getUserByLogin($sUser)) && $oUser->getActivate() == 1) {
                if (!isset($aUserBlacklist[$oUser->getId()])) {
                    if (\E::Module('Talk')->AddUserToBlackList($oUser->getId(), $this->oUserCurrent->getId())) {
                        $aResult[] = array(
                            'bStateError'   => false,
                            'sMsgTitle'     => \E::Module('Lang')->get('attention'),
                            'sMsg'          => \E::Module('Lang')->get(
                                'talk_blacklist_add_ok', array('login' => htmlspecialchars($sUser))
                            ),
                            'sUserId'       => $oUser->getId(),
                            'sUserLogin'    => htmlspecialchars($oUser->getDisplayName()),
                            'sUserWebPath'  => $oUser->getProfileUrl(),
                            'sUserAvatar48' => $oUser->getAvatarUrl(48),
                            'sUserName'     => $oUser->getDisplayName(),
                            'sUserUrl'      => $oUser->getProfileUrl(),
                            'sUserAvatar  ' => $oUser->getAvatarUrl(48),
                        );
                    } else {
                        $aResult[] = array(
                            'bStateError' => true,
                            'sMsgTitle'   => \E::Module('Lang')->get('error'),
                            'sMsg'        => \E::Module('Lang')->get('system_error'),
                            'sUserLogin'  => htmlspecialchars($sUser)
                        );
                    }
                } else {

                    // * Попытка добавить уже существующего в блеклисте пользователя, возвращаем ошибку
                    $aResult[] = array(
                        'bStateError' => true,
                        'sMsgTitle'   => \E::Module('Lang')->get('error'),
                        'sMsg'        => \E::Module('Lang')->get(
                            'talk_blacklist_user_already_have', array('login' => htmlspecialchars($sUser))
                        ),
                        'sUserLogin'  => htmlspecialchars($sUser)
                    );
                    continue;
                }
            } else {
                $aResult[] = array(
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('user_not_found', array('login' => htmlspecialchars($sUser))),
                    'sUserLogin'  => htmlspecialchars($sUser)
                );
            }
        }

        // * Передаем во вьевер массив с результатами обработки по каждому пользователю
        \E::Module('Viewer')->assignAjax('aUsers', $aResult);
    }

    /**
     * Удаление пользователя из блек листа (ajax)
     *
     */
    public function ajaxDeleteFromBlacklist()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $idTarget = F::getRequestStr('idTarget', null, 'post');

        // * Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Если пользователь не существуем, возращаем ошибку
        if (!$oUserTarget = \E::Module('User')->getUserById($idTarget)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_not_found_by_id', array('id' => htmlspecialchars($idTarget))),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Получаем блеклист пользователя
        $aBlacklist = \E::Module('Talk')->getBlacklistByUserId($this->oUserCurrent->getId());

        // * Если указанный пользователь не найден в блекслисте, возвращаем ошибку
        if (!isset($aBlacklist[$oUserTarget->getId()])) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get(
                    'talk_blacklist_user_not_found',
                    array('login' => $oUserTarget->getLogin())
                ),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Производим удаление пользователя из блекслиста
        if (!\E::Module('Talk')->DeleteUserFromBlacklist($idTarget, $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('system_error'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        \E::Module('Message')->addNoticeSingle(
            \E::Module('Lang')->get(
                'talk_blacklist_delete_ok',
                array('login' => $oUserTarget->getLogin())
            ),
            \E::Module('Lang')->get('attention')
        );
    }

    /**
     * Удаление участника разговора (ajax)
     *
     */
    public function ajaxDeleteTalkUser()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $idTarget = F::getRequestStr('idTarget', null, 'post');
        $idTalk = F::getRequestStr('idTalk', null, 'post');

        // * Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Если удаляемый участник не существует в базе данных, возвращаем ошибку
        if (!$oUserTarget = \E::Module('User')->getUserById($idTarget)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('user_not_found_by_id', array('id' => htmlspecialchars($idTarget))),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Если разговор не найден, или пользователь не является его автором (либо админом), возвращаем ошибку
        if ((!$oTalk = \E::Module('Talk')->getTalkById($idTalk))
            || (($oTalk->getUserId() != $this->oUserCurrent->getId()) && !$this->oUserCurrent->isAdministrator())
        ) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('talk_not_found'),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Получаем список всех участников разговора
        $aTalkUsers = $oTalk->getTalkUsers();

        // * Если пользователь не является участником разговора или удалил себя самостоятельно  возвращаем ошибку
        if (!isset($aTalkUsers[$idTarget])
            || $aTalkUsers[$idTarget]->getUserActive() == ModuleTalk::TALK_USER_DELETE_BY_SELF
        ) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get(
                    'talk_speaker_user_not_found',
                    array('login' => $oUserTarget->getLogin())
                ),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Удаляем пользователя из разговора,  если удаление прошло неудачно - возвращаем системную ошибку
        if (!\E::Module('Talk')->DeleteTalkUserByArray($idTalk, $idTarget, ModuleTalk::TALK_USER_DELETE_BY_AUTHOR)) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('system_error'),
                \E::Module('Lang')->get('error')
            );
            return;
        }
        \E::Module('Message')->addNoticeSingle(
            \E::Module('Lang')->get(
                'talk_speaker_delete_ok',
                array('login' => $oUserTarget->getLogin())
            ),
            \E::Module('Lang')->get('attention')
        );
    }

    /**
     * Добавление нового участника разговора (ajax)
     *
     */
    public function ajaxAddTalkUser()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');
        $sUsers = F::getRequestStr('users', null, 'post');
        $idTalk = F::getRequestStr('idTalk', null, 'post');

        // * Если пользователь не авторизирован, возвращаем ошибку
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('need_authorization'),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Если разговор не найден, или пользователь не является его автором (или админом), возвращаем ошибку
        if ((!$oTalk = \E::Module('Talk')->getTalkById($idTalk))
            || (($oTalk->getUserId() != $this->oUserCurrent->getId()) && !$this->oUserCurrent->isAdministrator())
        ) {
            \E::Module('Message')->addErrorSingle(
                \E::Module('Lang')->get('talk_not_found'),
                \E::Module('Lang')->get('error')
            );
            return;
        }

        // * Получаем список всех участников разговора
        $aTalkUsers = $oTalk->getTalkUsers();
        $aUsers = explode(',', $sUsers);

        // * Получаем список пользователей, которые не принимают письма
        $aUserInBlacklist = \E::Module('Talk')->getBlacklistByTargetId($this->oUserCurrent->getId());

        // * Ограничения на максимальное число участников разговора
        if (count($aTalkUsers) >= \C::get('module.talk.max_users') && !$this->oUserCurrent->isAdministrator()) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('talk_create_users_error_many'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Обрабатываем добавление по каждому переданному логину пользователя
        foreach ($aUsers as $sUser) {
            $sUser = trim($sUser);
            if ($sUser == '') {
                continue;
            }

            // * Попытка добавить себя
            if (strtolower($sUser) == strtolower($this->oUserCurrent->getLogin())) {
                $aResult[] = [
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('talk_speaker_add_self')
                ];
                continue;
            }
            if (($oUser = \E::Module('User')->getUserByLogin($sUser))
                && ($oUser->getActivate() == 1)
            ) {
                if (!in_array($oUser->getId(), $aUserInBlacklist)) {
                    if (array_key_exists($oUser->getId(), $aTalkUsers)) {
                        switch ($aTalkUsers[$oUser->getId()]->getUserActive()) {
                            // * Если пользователь ранее был удален админом разговора, то добавляем его снова
                            case ModuleTalk::TALK_USER_DELETE_BY_AUTHOR:
                                if (
                                    \E::Module('Talk')->AddTalkUser(
                                        E::getEntity(
                                            'Talk_TalkUser',
                                            [
                                                 'talk_id'          => $idTalk,
                                                 'user_id'          => $oUser->getId(),
                                                 'date_last'        => null,
                                                 'talk_user_active' => ModuleTalk::TALK_USER_ACTIVE
                                            ]
                                        )
                                    )
                                ) {
                                    \E::Module('Notify')->sendTalkNew($oUser, $this->oUserCurrent, $oTalk);
                                    $aResult[] = [
                                        'bStateError'   => false,
                                        'sMsgTitle'     => \E::Module('Lang')->get('attention'),
                                        'sMsg'          => \E::Module('Lang')->get(
                                            'talk_speaker_add_ok', ['login', htmlspecialchars($sUser)]
                                        ),
                                        'sUserId'       => $oUser->getId(),
                                        'sUserLogin'    => $oUser->getLogin(),
                                        'sUserLink'     => $oUser->getProfileUrl(),
                                        'sUserAvatar48' => $oUser->getAvatarUrl(48)
                                    ];
                                    $bState = true;
                                } else {
                                    $aResult[] = [
                                        'bStateError' => true,
                                        'sMsgTitle'   => \E::Module('Lang')->get('error'),
                                        'sMsg'        => \E::Module('Lang')->get('system_error')
                                    ];
                                }
                                break;

                            // * Если пользователь является активным участником разговора, возвращаем ошибку
                            case ModuleTalk::TALK_USER_ACTIVE:
                                $aResult[] = [
                                    'bStateError' => true,
                                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                                    'sMsg'        => \E::Module('Lang')->get(
                                        'talk_speaker_user_already_exist', ['login' => htmlspecialchars($sUser)]
                                    )
                                ];
                                break;

                            // * Если пользователь удалил себя из разговора самостоятельно, то блокируем повторное добавление
                            case ModuleTalk::TALK_USER_DELETE_BY_SELF:
                                $aResult[] = [
                                    'bStateError' => true,
                                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                                    'sMsg'        => \E::Module('Lang')->get(
                                        'talk_speaker_delete_by_self', ['login' => htmlspecialchars($sUser)]
                                    )
                                ];
                                break;

                            default:
                                $aResult[] = [
                                    'bStateError' => true,
                                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                                    'sMsg'        => \E::Module('Lang')->get('system_error')
                                ];
                        }
                    } elseif (
                        \E::Module('Talk')->addTalkUser(
                            E::getEntity(
                                'Talk_TalkUser',
                                [
                                     'talk_id'          => $idTalk,
                                     'user_id'          => $oUser->getId(),
                                     'date_last'        => null,
                                     'talk_user_active' => ModuleTalk::TALK_USER_ACTIVE
                                ]
                            )
                        )
                    ) {
                        \E::Module('Notify')->sendTalkNew($oUser, $this->oUserCurrent, $oTalk);
                        $aResult[] = [
                            'bStateError'   => false,
                            'sMsgTitle'     => \E::Module('Lang')->get('attention'),
                            'sMsg'          => \E::Module('Lang')->get(
                                'talk_speaker_add_ok', ['login', htmlspecialchars($sUser)]
                            ),
                            'sUserId'       => $oUser->getId(),
                            'sUserLogin'    => $oUser->getLogin(),
                            'sUserLink'     => $oUser->getProfileUrl(),
                            'sUserAvatar48' => $oUser->getAvatarUrl(48)
                        ];
                        $bState = true;
                    } else {
                        $aResult[] = [
                            'bStateError' => true,
                            'sMsgTitle'   => \E::Module('Lang')->get('error'),
                            'sMsg'        => \E::Module('Lang')->get('system_error')
                        ];
                    }
                } else {
                    // * Добавляем пользователь не принимает сообщения
                    $aResult[] = [
                        'bStateError' => true,
                        'sMsgTitle'   => \E::Module('Lang')->get('error'),
                        'sMsg'        => \E::Module('Lang')->get(
                            'talk_user_in_blacklist', ['login' => htmlspecialchars($sUser)]
                        )
                    ];
                }
            } else {
                // * Пользователь не найден в базе данных или не активен
                $aResult[] = [
                    'bStateError' => true,
                    'sMsgTitle'   => \E::Module('Lang')->get('error'),
                    'sMsg'        => \E::Module('Lang')->get('user_not_found', ['login' => htmlspecialchars($sUser)])
                ];
            }
        }

        // * Передаем во вьевер массив результатов обработки по каждому пользователю
        \E::Module('Viewer')->assignAjax('aUsers', $aResult);
    }

    /**
     * Возвращает количество новых сообщений
     */
    public function ajaxNewMessages()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $iCountTalkNew = \E::Module('Talk')->getCountTalkNew($this->oUserCurrent->getId());
        \E::Module('Viewer')->assignAjax('iCountTalkNew', $iCountTalkNew);
    }

    /**
     * Обработка завершения работу экшена
     */
    public function eventShutdown()
    {
        if (!\E::User()) {
            return;
        }
        $iCountTalkFavourite = \E::Module('Talk')->getCountTalksFavouriteByUserId($this->oUserCurrent->getId());
        \E::Module('Viewer')->assign('iCountTalkFavourite', $iCountTalkFavourite);

        $iUserId = \E::userId();

        // Get stats of various user publications topics, comments, images, etc. and stats of favourites
        $aProfileStats = \E::Module('User')->getUserProfileStats($iUserId);

        // Получим информацию об изображениях пользователя
        /** @var ModuleMedia_EntityMediaCategory[] $aUserImagesInfo */
        $aUserImagesInfo = \E::Module('Media')->getAllImageCategoriesByUserId($iUserId);

        \E::Module('Viewer')->assign('oUserProfile', E::User());
        \E::Module('Viewer')->assign('aProfileStats', $aProfileStats);
        \E::Module('Viewer')->assign('aUserImagesInfo', $aUserImagesInfo);

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

        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);
        \E::Module('Viewer')->assign('sMenuSubItemSelect', $this->sMenuSubItemSelect);

        // * Передаем константы состояний участников разговора
        \E::Module('Viewer')->assign('TALK_USER_ACTIVE', ModuleTalk::TALK_USER_ACTIVE);
        \E::Module('Viewer')->assign('TALK_USER_DELETE_BY_SELF', ModuleTalk::TALK_USER_DELETE_BY_SELF);
        \E::Module('Viewer')->assign('TALK_USER_DELETE_BY_AUTHOR', ModuleTalk::TALK_USER_DELETE_BY_AUTHOR);
    }
}

// EOF