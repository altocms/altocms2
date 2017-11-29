<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Экшен обработки УРЛа вида /content/ - управление своими топиками
 *
 * @package actions
 * @since 1.0
 */
class ActionTopic extends Action
{
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuHeadItemSelect = 'blog';

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
    protected $sMenuSubItemSelect = 'topic';

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
    protected $oContentType = null;

    protected $aBlogTypes = [];

    protected $bPersonalBlogEnabled = false;

    /**
     * Инициализация
     *
     */
    public function init() 
    {
        // * Проверяем авторизован ли юзер
        if (!\E::isUser() && (R::getControllerAction() !== 'go') && (R::getControllerAction() !== 'photo')) {
            return parent::eventNotFound();
        }
        $this->oUserCurrent = \E::User();

        // * Устанавливаем дефолтный эвент
        $this->setDefaultEvent('add');

        // * Загружаем в шаблон JS текстовки
        \E::Module('Lang')->addLangJs(
            ['topic_photoset_photo_delete',
                  'topic_photoset_mark_as_preview',
                  'topic_photoset_photo_delete_confirm',
                  'topic_photoset_is_preview',
                  'topic_photoset_upload_choose',
            ]
        );
        $this->aBlogTypes = $this->_getAllowBlogTypes();
    }

    /**
     * Регистрируем евенты
     *
     */
    protected function registerEvent()
    {
        $this->addEventPreg('/^published$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowTopics');
        $this->addEventPreg('/^saved$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowTopics');
        $this->addEventPreg('/^drafts$/i', '/^(page([1-9]\d{0,5}))?$/i', 'eventShowTopics');
        $this->addEvent('edit', ['EventEdit', 'edit']);
        $this->addEvent('delete', 'eventDelete');

        // Photosets
        if (\E::isUser()) {
            $this->addEventPreg('/^photo$/i', '/^upload$/i', 'eventAjaxPhotoUpload'); // Uploads image to photoset
            $this->addEventPreg('/^photo$/i', '/^description$/i', 'eventAjaxPhotoDescription'); // Sets description to image of photoset
            $this->addEventPreg('/^photo$/i', '/^delete$/i', 'eventPhotoDelete'); // Deletes image from photoset
        }
        $this->addEventPreg('/^photo$/i', '/^getmore$/i', 'eventAjaxPhotoGetMore'); // Gets more images from photosets to showed topic

        // Переход для топика с оригиналом
        $this->addEvent('go', 'eventGo');

        $this->addEventPreg('/^add$/i', ['EventAdd', 'add']);
        $this->addEventPreg('/^[\w\-\_]+$/i', '/^add$/i', ['EventAdd', 'add']);
    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Returns array of allowed blogs
     *
     * @param array $aFilter
     *
     * @return array
     */
    protected function _getAllowBlogs($aFilter = array()) {

        $oUser = (isset($aFilter['user']) ? $aFilter['user'] : null);
        $sContentTypeName = (isset($aFilter['content_type']) ? $aFilter['content_type'] : null);

        $aBlogs = \E::Module('Blog')->getBlogsAllowByUser($oUser);

        $aAllowBlogs = [];
        // Добавим персональный блог пользователю
        // Если персональные блоги отключены, то $oPersonalBlog будет равно null и добавлять
        // его в список догступных блогов не стоит, иначе будет ошибка при итерации по
        // массиву $aAllowBlogs.
        if ($oUser && $oPersonalBlog = \E::Module('Blog')->getPersonalBlogByUserId($oUser->getId())) {
            $aAllowBlogs[] = $oPersonalBlog;
        }

        /** @var ModuleBlog_EntityBlog $oBlog */
        foreach($aBlogs as $oBlog) {
            if (\E::Module('ACL')->canAddTopic($oUser, $oBlog) && $oBlog->IsContentTypeAllow($sContentTypeName)) {
                $aAllowBlogs[$oBlog->getId()] = $oBlog;
            }
        }
        return $aAllowBlogs;
    }

    /**
     * Returns of allowed blog types
     *
     * @return array
     */
    protected function _getAllowBlogTypes()
    {
        $aBlogTypes = \E::Module('Blog')->getAllowBlogTypes($this->oUserCurrent, 'write', true);
        $this->bPersonalBlogEnabled = in_array('personal', $aBlogTypes);

        return $aBlogTypes;
    }

    /**
     * Добавление топика
     *
     * @return mixed
     */
    public function eventAdd() {

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('add');
        \E::Module('Viewer')->assign('sMode', 'add');

        // * Вызов хуков
        \HookManager::run('topic_add_show');

        // * Получаем тип контента
        if (!$this->oContentType = \E::Module('Topic')->getContentTypeByUrl($this->sCurrentEvent)) {
            if (!($this->oContentType = \E::Module('Topic')->getContentTypeDefault())) {
                return parent::eventNotFound();
            }
        }

        \E::Module('Viewer')->assign('oContentType', $this->oContentType);
        $this->sMenuSubItemSelect = $this->oContentType->getContentUrl();

        // * Если тип контента не доступен текущему юзеру
        if (!$this->oContentType->isAccessible()) {
            return parent::eventNotFound();
        }

        $aBlogFilter = array(
            'user' => $this->oUserCurrent,
            'content_type' => $this->oContentType,
        );
        $aBlogsAllow = $this->_getAllowBlogs($aBlogFilter);

        // Такой тип контента не разрешен для пользователя ни в одном из типов блогов
        if (!$aBlogsAllow) {
            return parent::eventNotFound();
        }

        // Проверим можно ли писать в персональный блог такой тип контента
        /** @var ModuleBlog_EntityBlog $oAllowedBlog */
        $this->bPersonalBlogEnabled = FALSE;
        foreach ($aBlogsAllow as $oAllowedBlog) {
            // Нашли среди разрешенных персональный блог
            if ($oAllowedBlog->getType() == 'personal') {
                if (!$oAllowedBlog->getBlogType()->getContentTypes()) {
                    // типы контента не определены, значит, разрешен любой
                    $this->bPersonalBlogEnabled = TRUE;
                } else {
                    foreach ($oAllowedBlog->getBlogType()->getContentTypes() as $oContentType) {
                        if ($oContentType->getId() == $this->oContentType->getId()) {
                            $this->bPersonalBlogEnabled = TRUE;
                            break;
                        }
                    }
                }
                break;
            }
        }

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('bPersonalBlog', $this->bPersonalBlogEnabled);
        \E::Module('Viewer')->assign('aBlogsAllow', $aBlogsAllow);
        \E::Module('Viewer')->assign('bEditDisabled', false);
        \E::Module('Viewer')->addHtmlTitle(
            \E::Module('Lang')->get('topic_topic_create') . ' ' . mb_strtolower($this->oContentType->getContentTitle(), 'UTF-8')
        );
        if (!is_numeric(\F::getRequest('topic_id'))) {
            $_REQUEST['topic_id'] = '';
        }

        $_REQUEST['topic_show_photoset'] = 1;

        // * Если нет временного ключа для нового топика, то генерируем; если есть, то загружаем фото по этому ключу
        if ($sTargetTmp = \E::Module('Session')->getCookie('ls_photoset_target_tmp')) {
            \E::Module('Session')->setCookie('ls_photoset_target_tmp', $sTargetTmp, 'P1D', false);
            \E::Module('Viewer')->assign('aPhotos', \E::Module('Topic')->getPhotosByTargetTmp($sTargetTmp));
        } else {
            \E::Module('Session')->setCookie('ls_photoset_target_tmp', F::RandomStr(), 'P1D', false);
        }

        // Если POST-запрос, то обрабатываем отправку формы
        if ($this->isPost()) {
            return $this->submitAdd();
        }

        return null;
    }

    /**
     * Обработка добавления топика
     *
     * @return bool|string
     */
    protected function submitAdd()
    {
        // * Проверяем отправлена ли форма с данными (хотяб одна кнопка)
        if (!F::isPost('submit_topic_publish') && !F::isPost('submit_topic_draft') && !F::isPost('submit_topic_save')) {
            return false;
        }
        /** @var ModuleTopic_EntityTopic $oTopic */
        $oTopic = \E::getEntity('Topic');
        $oTopic->_setValidateScenario('topic');

        // * Заполняем поля для валидации
        $oTopic->setBlogId(\F::getRequestStr('blog_id'));

        // issue 151 (https://github.com/altocms/altocms/issues/151)
        // Некорректная обработка названия блога
        // $oTopic->setTitle(strip_tags(\F::getRequestStr('topic_title')));
        $oTopic->setTitle(\E::Module('Text')->removeAllTags(\F::getRequestStr('topic_title')));

        $oTopic->setTextSource(\F::getRequestStr('topic_text'));
        $oTopic->setUserId($this->oUserCurrent->getId());
        $oTopic->setType($this->oContentType->getContentUrl());

        if ($this->oContentType->isAllow('link')) {
            $oTopic->setSourceLink(\F::getRequestStr('topic_field_link'));
        }
        $oTopic->setTags(\F::getRequestStr('topic_field_tags'));

        $oTopic->setDateAdd(\F::Now());
        $oTopic->setUserIp(\F::GetUserIp());

        $sTopicUrl = \E::Module('Topic')->CorrectTopicUrl($oTopic->MakeTopicUrl());
        $oTopic->setTopicUrl($sTopicUrl);

        // * Проверка корректности полей формы
        if (!$this->checkTopicFields($oTopic)) {
            return false;
        }

        // * Определяем в какой блог делаем запись
        $nBlogId = $oTopic->getBlogId();
        if ($nBlogId == 0) {
            $oBlog = \E::Module('Blog')->getPersonalBlogByUserId($this->oUserCurrent->getId());
        } else {
            $oBlog = \E::Module('Blog')->getBlogById($nBlogId);
        }

        // * Если блог не определен, то выдаем предупреждение
        if (!$oBlog) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_create_blog_error_unknown'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем права на постинг в блог
        if (!\E::Module('ACL')->isAllowBlog($oBlog, $this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_create_blog_error_noallow'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем разрешено ли постить топик по времени
        if (\F::isPost('submit_topic_publish') && !\E::Module('ACL')->canPostTopicTime($this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_time_limit'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Теперь можно смело добавлять топик к блогу
        $oTopic->setBlogId($oBlog->getId());

        // * Получаемый и устанавливаем разрезанный текст по тегу <cut>
        list($sTextShort, $sTextNew, $sTextCut) = \E::Module('Text')->Cut($oTopic->getTextSource());

        $oTopic->setCutText($sTextCut);
        $oTopic->setText(\E::Module('Text')->Parse($sTextNew));

        // Получаем ссылки, полученные при парсинге текста
        $oTopic->setTextLinks(\E::Module('Text')->getLinks());
        $oTopic->setTextShort(\E::Module('Text')->Parse($sTextShort));

        // * Варианты ответов
        if ($this->oContentType->isAllow('poll') && F::getRequestStr('topic_field_question') && F::getRequest('topic_field_answers', array())) {
            $oTopic->setQuestionTitle(strip_tags(\F::getRequestStr('topic_field_question')));
            $oTopic->clearQuestionAnswer();
            $aAnswers = F::getRequest('topic_field_answers', array());
            foreach ($aAnswers as $sAnswer) {
                $sAnswer = trim((string)$sAnswer);
                if ($sAnswer) {
                    $oTopic->addQuestionAnswer($sAnswer);
                }
            }
        }

        $aPhotoSetData = \E::Module('Media')->getPhotosetData('photoset', 0);
        $oTopic->setPhotosetCount($aPhotoSetData['count']);
        if ($aPhotoSetData['cover']) {
            $oTopic->setPhotosetMainPhotoId($aPhotoSetData['cover']);
        }

        // * Публикуем или сохраняем
        if (isset($_REQUEST['submit_topic_publish'])) {
            $oTopic->setPublish(1);
            $oTopic->setPublishDraft(1);
            if (!$oTopic->getDateShow()) {
                $oTopic->setDateShow(\F::Now());
            }
        } else {
            $oTopic->setPublish(0);
            $oTopic->setPublishDraft(0);
        }

        // * Принудительный вывод на главную
        $oTopic->setPublishIndex(0);
        if (\E::Module('ACL')->isAllowPublishIndex($this->oUserCurrent)) {
            if (\F::getRequest('topic_publish_index')) {
                $oTopic->setPublishIndex(1);
            }
        }

        // * Запрет на комментарии к топику
        $oTopic->setForbidComment(\F::getRequest('topic_forbid_comment', 0));

        // Разрешение/запрет индексации контента топика изначально - как у блога
        if ($oBlogType = $oBlog->GetBlogType()) {
            // Если тип блога определен, то берем из типа блога...
            $oTopic->setTopicIndexIgnore($oBlogType->GetIndexIgnore());
        } else {
            // ...если нет, то индексацию разрешаем
            $oTopic->setTopicIndexIgnore(false);
        }

        $oTopic->setShowPhotoset(\F::getRequest('topic_show_photoset', 0));

        // * Запускаем выполнение хуков
        \HookManager::run('topic_add_before', array('oTopic' => $oTopic, 'oBlog' => $oBlog));

        // * Добавляем топик
        if ($this->_addTopic($oTopic)) {
            \HookManager::run('topic_add_after', array('oTopic' => $oTopic, 'oBlog' => $oBlog));
            // * Получаем топик, чтоб подцепить связанные данные
            $oTopic = \E::Module('Topic')->getTopicById($oTopic->getId());

            // * Обновляем количество топиков в блоге
            \E::Module('Blog')->RecalculateCountTopicByBlogId($oTopic->getBlogId());

            // * Добавляем автора топика в подписчики на новые комментарии к этому топику
            \E::Module('Subscribe')->AddSubscribeSimple(
                'topic_new_comment', $oTopic->getId(), $this->oUserCurrent->getMail(), $this->oUserCurrent->getId()
            );

            // * Подписываем автора топика на обновления в трекере
            if ($oTrack = \E::Module('Subscribe')->AddTrackSimple(
                'topic_new_comment', $oTopic->getId(), $this->oUserCurrent->getId()
            )) {
                // Если пользователь не отписался от обновлений топика
                if (!$oTrack->getStatus()) {
                    $oTrack->setStatus(1);
                    \E::Module('Subscribe')->UpdateTrack($oTrack);
                }
            }

            // * Делаем рассылку всем, кто состоит в этом блоге
            if ($oTopic->getPublish() == 1 && $oBlog->getType() !== 'personal') {
                \E::Module('Topic')->SendNotifyTopicNew($oBlog, $oTopic, $this->oUserCurrent);
            }
            /**
             * Привязываем фото к ID топика
             */
            if (isset($aPhotos) && count($aPhotos)) {
                \E::Module('Topic')->attachTmpPhotoToTopic($oTopic);
            }

            // * Удаляем временную куку
            \E::Module('Session')->delCookie('ls_photoset_target_tmp');

            // Обработаем фотосет
            if ($this->oContentType->isAllow('photoset') && ($sTargetTmp = \E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP))) {
                // Уберем у ресурса флаг временного размещения и удалим из куки target_tmp
                \E::Module('Session')->delCookie(ModuleUploader::COOKIE_TARGET_TMP);
            }

            // * Добавляем событие в ленту
            \E::Module('Stream')->Write(
                $oTopic->getUserId(), 'add_topic', $oTopic->getId(),
                $oTopic->getPublish() && (!$oBlog->getBlogType() || !$oBlog->getBlogType()->IsPrivate())
            );
            R::Location($oTopic->getUrl());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            F::SysWarning('System Error');
            return R::redirect('error');
        }
    }

    /**
     * Adds new topic
     *
     * @param $oTopic
     *
     * @return bool|ModuleTopic_EntityTopic
     */
    protected function _addTopic($oTopic)
    {
        return \E::Module('Topic')->addTopic($oTopic);
    }

    /**
     * Редактирование топика
     *
     */
    public function eventEdit()
    {
        // * Получаем номер топика из URL и проверяем существует ли он
        $iTopicId = (int)$this->getParam(0);
        if (!$iTopicId || !($oTopic = \E::Module('Topic')->getTopicById($iTopicId))) {
            return parent::eventNotFound();
        }

        // * Получаем тип контента
        if (!$this->oContentType = \E::Module('Topic')->getContentTypeByUrl($oTopic->getType())) {
            return parent::eventNotFound();
        }

        \E::Module('Viewer')->assign('oContentType', $this->oContentType);
        $this->sMenuSubItemSelect = $this->oContentType->getContentUrl();

        // * Есть права на редактирование
        if (!\E::Module('ACL')->isAllowEditTopic($oTopic, $this->oUserCurrent)) {
            return parent::eventNotFound();
        }

        $aBlogFilter = array(
            'user' => $this->oUserCurrent,
            'content_type' => $this->oContentType,
        );
        $aBlogsAllow = $this->_getAllowBlogs($aBlogFilter);

        // Такой тип контента не разрешен для пользователя ни в одном из типов блогов
        if (!$aBlogsAllow) {
            return parent::eventNotFound();
        }

        // * Вызов хука
        \HookManager::run('topic_edit_show', array('oTopic' => $oTopic));

        // * Загружаем переменные в шаблон
        \E::Module('Viewer')->assign('bPersonalBlog', $this->bPersonalBlogEnabled);
        \E::Module('Viewer')->assign('aBlogsAllow', $aBlogsAllow);
        \E::Module('Viewer')->assign('bEditDisabled', $oTopic->getQuestionCountVote() == 0 ? false : true);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('topic_topic_edit'));

        // * Устанавливаем шаблон вывода
        $this->setTemplateAction('add');
        \E::Module('Viewer')->assign('sMode', 'edit');

        // * Проверяем, отправлена ли форма с данными
        if ($this->isPost()) {
            // * Обрабатываем отправку формы
            $xResult = $this->SubmitEdit($oTopic);
            if ($xResult !== false) {
                return $xResult;
            }
        } else {
            /**
             * Заполняем поля формы для редактирования
             * Только перед отправкой формы!
             */
            $_REQUEST['topic_title'] = $oTopic->getTitle();
            $_REQUEST['topic_text'] = $oTopic->getTextSource();
            $_REQUEST['blog_id'] = $oTopic->getBlogId();
            $_REQUEST['topic_id'] = $oTopic->getId();
            $_REQUEST['topic_publish_index'] = $oTopic->getPublishIndex();
            $_REQUEST['topic_forbid_comment'] = $oTopic->getForbidComment();
            $_REQUEST['topic_main_photo'] = $oTopic->getPhotosetMainPhotoId();

            $_REQUEST['topic_field_link'] = $oTopic->getSourceLink();
            $_REQUEST['topic_field_tags'] = $oTopic->getTags();

            $_REQUEST['topic_field_question'] = $oTopic->getQuestionTitle();
            $_REQUEST['topic_field_answers'] = [];
            $_REQUEST['topic_show_photoset'] = $oTopic->getShowPhotoset();
            $aAnswers = $oTopic->getQuestionAnswers();
            foreach ($aAnswers as $aAnswer) {
                $_REQUEST['topic_field_answers'][] = $aAnswer['text'];
            }

            foreach ($this->oContentType->getFields() as $oField) {
                if ($oTopic->getField($oField->getFieldId())) {
                    $sValue = $oTopic->getField($oField->getFieldId())->getValueSource();
                    if ($oField->getFieldType() == 'file') {
                        $sValue = unserialize($sValue);
                    }
                    $_REQUEST['fields'][$oField->getFieldId()] = $sValue;
                }
            }
        }

        $sUrlMask = R::GetTopicUrlMask();
        if (strpos($sUrlMask, '%topic_url%') === false) {
            // Нет в маске URL
            $aEditTopicUrl = array(
                'before' => $oTopic->getLink($sUrlMask),
                'input' => '',
                'after' => '',
            );
        } else {
            // В маске есть URL, вместо него нужно вставить <input>
            $aUrlMaskParts = explode('%topic_url%', $sUrlMask);
            $aEditTopicUrl = array(
                'before' => $aUrlMaskParts[0] ? $oTopic->getLink($aUrlMaskParts[0]) : F::File_RootUrl(),
                'input' => $oTopic->getTopicUrl() ? $oTopic->getTopicUrl() : $oTopic->MakeTopicUrl(),
                'after' => (isset($aUrlMaskParts[1]) && $aUrlMaskParts[1]) ? $oTopic->getLink($aUrlMaskParts[1], false) : '',
            );
        }
        if (!isset($_REQUEST['topic_url_input'])) {
            $_REQUEST['topic_url_input'] = $aEditTopicUrl['input'];
        } else {
            $aEditTopicUrl['input'] = $_REQUEST['topic_url_input'];
        }
        if (!isset($_REQUEST['topic_url_short'])) {
            $_REQUEST['topic_url_short'] = $oTopic->getUrlShort();
        }
        \E::Module('Viewer')->assign('aEditTopicUrl', $aEditTopicUrl);

        // Old style frontend compatibility
        $_REQUEST['topic_url_before'] = $aEditTopicUrl['before'];
        $_REQUEST['topic_url'] = $aEditTopicUrl['input'];
        $_REQUEST['topic_url_after'] = $aEditTopicUrl['after'];

        \E::Module('Viewer')->assign('oTopic', $oTopic);

        // Добавим картинки фотосета для вывода
        \E::Module('Viewer')->assign(
            'aPhotos',
            \E::Module('Media')->getMresourcesRelByTarget('photoset', $oTopic->getId())
        );
    }

    /**
     * Обработка редактирования топика
     *
     * @param ModuleTopic_EntityTopic $oTopic
     *
     * @return mixed
     */
    protected function submitEdit($oTopic)
    {
        $oTopic->_setValidateScenario('topic');

        // * Сохраняем старое значение идентификатора блога
        $iBlogIdOld = $oTopic->getBlogId();

        // * Заполняем поля для валидации
        $iBlogId = F::getRequestStr('blog_id');
        // if blog_id is empty then save blog not changed
        if (is_numeric($iBlogId)) {
            $oTopic->setBlogId($iBlogId);
        }

        // issue 151 (https://github.com/altocms/altocms/issues/151)
        // Некорректная обработка названия блога
        // $oTopic->setTitle(strip_tags(\F::getRequestStr('topic_title')));
        $oTopic->setTitle(\E::Module('Text')->removeAllTags(\F::getRequestStr('topic_title')));

        $oTopic->setTextSource(\F::getRequestStr('topic_text'));

        if ($this->oContentType->isAllow('link')) {
            $oTopic->setSourceLink(\F::getRequestStr('topic_field_link'));
        }
        $oTopic->setTags(\F::getRequestStr('topic_field_tags'));

        $oTopic->setUserIp(\F::GetUserIp());

        if ($this->oUserCurrent && ($this->oUserCurrent->isAdministrator() || $this->oUserCurrent->isModerator())) {
            if (\F::getRequestStr('topic_url') && $oTopic->getTopicUrl() != F::getRequestStr('topic_url')) {
                $sTopicUrl = \E::Module('Topic')->CorrectTopicUrl(\F::TranslitUrl(\F::getRequestStr('topic_url')));
                $oTopic->setTopicUrl($sTopicUrl);
            }
        }

        // * Проверка корректности полей формы
        if (!$this->checkTopicFields($oTopic)) {
            return false;
        }

        // * Определяем в какой блог делаем запись
        $nBlogId = $oTopic->getBlogId();
        if ($nBlogId == 0) {
            $oBlog = \E::Module('Blog')->getPersonalBlogByUserId($oTopic->getUserId());
        } else {
            $oBlog = \E::Module('Blog')->getBlogById($nBlogId);
        }

        // * Если блог не определен выдаем предупреждение
        if (!$oBlog) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_create_blog_error_unknown'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем права на постинг в блог
        if (!\E::Module('ACL')->isAllowBlog($oBlog, $this->oUserCurrent)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_create_blog_error_noallow'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Проверяем разрешено ли постить топик по времени
        if (isPost('submit_topic_publish') && !$oTopic->getPublishDraft()
            && !\E::Module('ACL')->canPostTopicTime($this->oUserCurrent)
        ) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_time_limit'), \E::Module('Lang')->get('error'));
            return;
        }
        $oTopic->setBlogId($oBlog->getId());

        // * Получаемый и устанавливаем разрезанный текст по тегу <cut>
        list($sTextShort, $sTextNew, $sTextCut) = \E::Module('Text')->cut($oTopic->getTextSource());

        $oTopic->setCutText($sTextCut);
        $oTopic->setText(\E::Module('Text')->parse($sTextNew));

        // Получаем ссылки, полученные при парсинге текста
        $oTopic->setTextLinks(\E::Module('Text')->getLinks());
        $oTopic->setTextShort(\E::Module('Text')->parse($sTextShort));

        // * Изменяем вопрос/ответы, только если еще никто не голосовал
        if ($this->oContentType->isAllow('poll') && F::getRequestStr('topic_field_question')
            && F::getRequest('topic_field_answers', array()) && ($oTopic->getQuestionCountVote() == 0)
        ) {
            $oTopic->setQuestionTitle(strip_tags(\F::getRequestStr('topic_field_question')));
            $oTopic->clearQuestionAnswer();
            $aAnswers = F::getRequest('topic_field_answers', array());
            foreach ($aAnswers as $sAnswer) {
                $sAnswer = trim((string)$sAnswer);
                if ($sAnswer) {
                    $oTopic->addQuestionAnswer($sAnswer);
                }
            }
        }

        $aPhotoSetData = \E::Module('Media')->getPhotosetData('photoset', $oTopic->getId());
        $oTopic->setPhotosetCount($aPhotoSetData['count']);
        $oTopic->setPhotosetMainPhotoId($aPhotoSetData['cover']);

        // * Publish or save as a draft
        $bSendNotify = false;
        if (isset($_REQUEST['submit_topic_publish'])) {
            // If the topic has not been published then sets date of show (publication date)
            if (!$oTopic->getPublish() && !$oTopic->getDateShow()) {
                $oTopic->setDateShow(\F::Now());
            }
            $oTopic->setPublish(1);
            if ($oTopic->getPublishDraft() == 0) {
                $oTopic->setPublishDraft(1);
                $oTopic->setDateAdd(\F::Now());
                $bSendNotify = true;
            }
        } else {
            $oTopic->setPublish(0);
        }

        // * Принудительный вывод на главную
        if (\E::Module('ACL')->isAllowPublishIndex($this->oUserCurrent)) {
            if (\F::getRequest('topic_publish_index')) {
                $oTopic->setPublishIndex(1);
            } else {
                $oTopic->setPublishIndex(0);
            }
        }

        // * Запрет на комментарии к топику
        $oTopic->setForbidComment(\F::getRequest('topic_forbid_comment', 0));

        // Если запрет на индексацию не устанавливался вручную, то задаем, как у блога
        $oBlogType = $oBlog->getBlogType();
        if ($oBlogType && !$oTopic->getIndexIgnoreLock()) {
            $oTopic->setTopicIndexIgnore($oBlogType->getIndexIgnore());
        } else {
            $oTopic->setTopicIndexIgnore(false);
        }

        $oTopic->setShowPhotoset(\F::getRequest('topic_show_photoset', 0));

        \HookManager::run('topic_edit_before', array('oTopic' => $oTopic, 'oBlog' => $oBlog));

        // * Сохраняем топик
        if ($this->_updateTopic($oTopic)) {
            \HookManager::run(
                'topic_edit_after', array('oTopic' => $oTopic, 'oBlog' => $oBlog, 'bSendNotify' => &$bSendNotify)
            );

            // * Обновляем данные в комментариях, если топик был перенесен в новый блог
            if ($iBlogIdOld != $oTopic->getBlogId()) {
                \E::Module('Comment')->updateTargetParentByTargetId($oTopic->getBlogId(), 'topic', $oTopic->getId());
                \E::Module('Comment')->updateTargetParentByTargetIdOnline($oTopic->getBlogId(), 'topic', $oTopic->getId());
            }

            // * Обновляем количество топиков в блоге
            if ($iBlogIdOld != $oTopic->getBlogId()) {
                \E::Module('Blog')->RecalculateCountTopicByBlogId($iBlogIdOld);
            }
            \E::Module('Blog')->RecalculateCountTopicByBlogId($oTopic->getBlogId());

            // * Добавляем событие в ленту
            \E::Module('Stream')->Write(
                $oTopic->getUserId(), 'add_topic', $oTopic->getId(),
                $oTopic->getPublish() && (!$oBlogType || !$oBlog->getBlogType()->IsPrivate())
            );

            // * Рассылаем о новом топике подписчикам блога
            if ($bSendNotify) {
                \E::Module('Topic')->SendNotifyTopicNew($oBlog, $oTopic, $oTopic->getUser());
            }
            if (!$oTopic->getPublish()
                && !$this->oUserCurrent->isAdministrator()
                && !$this->oUserCurrent->isModerator()
                && $this->oUserCurrent->getId() != $oTopic->getUserId()
            ) {
                R::Location($oBlog->getUrlFull());
            }
            R::Location($oTopic->getUrl());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            \F::SysWarning('System Error');

            return R::redirect('error');
        }
    }

    /**
     * Updates topic
     *
     * @param $oTopic
     *
     * @return bool
     */
    protected function _updateTopic($oTopic)
    {
        return \E::Module('Topic')->updateTopic($oTopic);
    }

    /**
     * Удаление топика
     *
     */
    public function eventDelete()
    {
        \E::Module('Security')->validateSendForm();

        // * Получаем номер топика из УРЛ и проверяем существует ли он
        $sTopicId = $this->getParam(0);
        if (!($oTopic = \E::Module('Topic')->getTopicById($sTopicId))) {
            return parent::eventNotFound();
        }

        // * проверяем есть ли право на удаление топика
        if (!\E::Module('ACL')->isAllowDeleteTopic($oTopic, $this->oUserCurrent)) {
            return parent::eventNotFound();
        }

        // * Удаляем топик
        \HookManager::run('topic_delete_before', array('oTopic' => $oTopic));
        if ($this->_deleteTopic($oTopic)) {
            \HookManager::run('topic_delete_after', array('oTopic' => $oTopic));

            // * Перенаправляем на страницу со списком топиков из блога этого топика
            R::Location($oTopic->getBlog()->getUrlFull());
        } else {
            R::Location($oTopic->getUrl());
        }
    }

    /**
     * Deletes the topic
     *
     * @param $oTopic
     *
     * @return bool
     */
    protected function _deleteTopic($oTopic)
    {
        return \E::Module('Topic')->deleteTopic($oTopic);
    }

    /**
     * Выводит список топиков
     *
     */
    public function eventShowTopics()
    {
        /**
         * Меню
         */
        $this->sMenuSubItemSelect = $this->sCurrentEvent;
        /**
         * Передан ли номер страницы
         */
        $iPage = $this->getParamEventMatch(0, 2) ? $this->getParamEventMatch(0, 2) : 1;
        /**
         * Получаем список топиков
         */
        $aResult = \E::Module('Topic')->getTopicsPersonalByUser(
            $this->oUserCurrent->getId(), $this->sCurrentEvent == 'published' ? 1 : 0, $iPage,
            \C::get('module.topic.per_page')
        );
        $aTopics = $aResult['collection'];
        /**
         * Формируем постраничность
         */
        $aPaging = \E::Module('Viewer')->makePaging(
            $aResult['count'], $iPage, \C::get('module.topic.per_page'), \C::get('pagination.pages.count'),
            R::getLink('content') . $this->sCurrentEvent
        );
        /**
         * Загружаем переменные в шаблон
         */
        \E::Module('Viewer')->assign('aPaging', $aPaging);
        \E::Module('Viewer')->assign('aTopics', $aTopics);
        \E::Module('Viewer')->addHtmlTitle(\E::Module('Lang')->get('topic_menu_' . $this->sCurrentEvent));
    }

    /**
     * AJAX загрузка изображения в фотосет
     *
     * @return bool
     */
    public function eventAjaxPhotoUpload() {

        // Устанавливаем формат Ajax ответа. Здесь всегда json, поскольку грузится
        // картинка с помощью flash
        \E::Module('Viewer')->setResponseAjax('json', false);

        // * Проверяем авторизован ли юзер
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Файл был загружен?
        $aUploadedFile = $this->getUploadedFile('Filedata');
        if (!$aUploadedFile) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            F::SysWarning('System Error');
            return false;
        }

        $iTopicId = F::getRequestInt('topic_id');
        $sTargetId = null;

        // Если от сервера не пришёл ID топика, то пытаемся определить временный код для нового топика.
        // Если и его нет, то это ошибка
        if (!$iTopicId) {
            $sTargetId = \E::Module('Session')->getCookie('ls_photoset_target_tmp');
            if (!$sTargetId) {
                $sTargetId = F::getRequestStr('ls_photoset_target_tmp');
            }
            if (!$sTargetId) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                F::SysWarning('System Error');
                return false;
            }
            $iCountPhotos = \E::Module('Topic')->getCountPhotosByTargetTmp($sTargetId);
        } else {
            // * Загрузка фото к уже существующему топику
            $oTopic = \E::Module('Topic')->getTopicById($iTopicId);
            if (!$oTopic || !\E::Module('ACL')->isAllowEditTopic($oTopic, $this->oUserCurrent)) {
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                F::SysWarning('System Error');
                return false;
            }
            $iCountPhotos = \E::Module('Topic')->getCountPhotosByTopicId($iTopicId);
        }

        // * Максимальное количество фото в топике
        if (\C::get('module.topic.photoset.count_photos_max') && $iCountPhotos >= \C::get('module.topic.photoset.count_photos_max')) {
            \E::Module('Message')->addError(
                \E::Module('Lang')->get(
                    'topic_photoset_error_too_much_photos',
                    array('MAX' => \C::get('module.topic.photoset.count_photos_max'))
                ), \E::Module('Lang')->get('error')
            );
            return false;
        }

        // * Максимальный размер фото
        if (filesize($aUploadedFile['tmp_name']) > \C::get('module.topic.photoset.photo_max_size') * 1024) {
            \E::Module('Message')->addError(
                \E::Module('Lang')->get(
                    'topic_photoset_error_bad_filesize',
                    array('MAX' => \C::get('module.topic.photoset.photo_max_size'))
                ), \E::Module('Lang')->get('error')
            );
            return false;
        }

        // * Загружаем файл
        $sFile = \E::Module('Topic')->uploadTopicPhoto($aUploadedFile);
        if ($sFile) {
            // * Создаем фото
            $oPhoto = \E::getEntity('Topic_TopicPhoto');
            $oPhoto->setPath($sFile);
            if ($iTopicId) {
                $oPhoto->setTopicId($iTopicId);
            } else {
                $oPhoto->setTargetTmp($sTargetId);
            }
            if ($oPhoto = \E::Module('Topic')->addTopicPhoto($oPhoto)) {
                // * Если топик уже существует (редактирование), то обновляем число фотографий в нём
                if (isset($oTopic)) {
                    $oTopic->setPhotosetCount($oTopic->getPhotosetCount() + 1);
                    \E::Module('Topic')->updateTopic($oTopic);
                }

                \E::Module('Viewer')->assignAjax('file', $oPhoto->getWebPath('100crop'));
                \E::Module('Viewer')->assignAjax('id', $oPhoto->getId());
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('topic_photoset_photo_added'), \E::Module('Lang')->get('attention'));

                return true;
            } else {
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                F::SysWarning('System Error');
            }
        } else {
            $sMsg = \E::Module('Topic')->uploadPhotoError();
            if (!$sMsg) {
                $sMsg = \E::Module('Lang')->get('system_error');
            }
            \E::Module('Message')->addError($sMsg, \E::Module('Lang')->get('error'));
        }
        return false;
    }

    public function eventPhotoUpload()
    {
        return $this->eventAjaxPhotoUpload();
    }

    /**
     * AJAX установка описания фото
     *
     */
    public function eventAjaxPhotoDescription()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Проверяем авторизован ли юзер
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return R::redirect('error');
        }

        // * Поиск фото по id
        $oPhoto = \E::Module('Topic')->getTopicPhotoById(\F::getRequestStr('id'));
        if ($oPhoto) {
            $sDescription = htmlspecialchars(strip_tags(\F::getRequestStr('text')));
            if ($sDescription != $oPhoto->getDescription()) {
                if ($oPhoto->getTopicId()) {
                    // проверяем права на топик
                    $oTopic = \E::Module('Topic')->getTopicById($oPhoto->getTopicId());
                    if ($oTopic && \E::Module('ACL')->isAllowEditTopic($oTopic, $this->oUserCurrent)) {
                        $oPhoto->setDescription(htmlspecialchars(strip_tags(\F::getRequestStr('text'))));
                        \E::Module('Topic')->updateTopicPhoto($oPhoto);
                    }
                } else {
                    $oPhoto->setDescription(htmlspecialchars(strip_tags(\F::getRequestStr('text'))));
                    \E::Module('Topic')->updateTopicPhoto($oPhoto);
                }
                \E::Module('Message')->addNotice(\E::Module('Lang')->get('topic_photoset_description_done'));
            }
        }
    }

    /**
     * @return string
     */
    public function eventPhotoDescription()
    {
        return $this->eventAjaxPhotoDescription();
    }

    /**
     * AJAX подгрузка следующих фото
     *
     */
    public function eventAjaxPhotoGetMore()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Существует ли топик
        $iTopicId = F::getRequestStr('topic_id');
        $iLastId = F::getRequest('last_id');
        $sThumbSize = F::getRequest('thumb_size');
        if (!$sThumbSize) {
            $sThumbSize = '50crop';
        }
        if (!$iTopicId || !($oTopic = \E::Module('Topic')->getTopicById($iTopicId)) || !$iLastId) {
            \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            F::SysWarning('System Error');
            return;
        }

        // * Получаем список фото
        /** @var ModuleMedia_EntityMediaRel[] $aPhotos */
        $aPhotos = $oTopic->getPhotosetPhotos($iLastId, \C::get('module.topic.photoset.per_page'));
        $aResult = [];
        if (count($aPhotos)) {
            // * Формируем данные для ajax ответа
            foreach ($aPhotos as $oPhoto) {
                $aResult[] = array(
                    'id'          => $oPhoto->getMresourceId(),
                    //'path_thumb'  => $oPhoto->getLink($sThumbSize),
                    //'path'        => $oPhoto->getLink(),
                    'path_thumb' => $oPhoto->getWebPath($sThumbSize),
                    'path' => $oPhoto->getWebPath(),
                    'description' => $oPhoto->getDescription(),
                );
            }
            \E::Module('Viewer')->assignAjax('photos', $aResult);
        }
        \E::Module('Viewer')->assignAjax('bHaveNext', count($aPhotos) == \C::get('module.topic.photoset.per_page'));
    }

    /**
     * DEPRECATED
     */
    public function eventPhotoGetMore()
    {
        return $this->eventAjaxPhotoGetMore();
    }

    /**
     * AJAX удаление фото
     *
     */
    public function eventAjaxPhotoDelete()
    {
        // * Устанавливаем формат Ajax ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Проверяем авторизован ли юзер
        if (!\E::isAuth()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return false;
        }

        // * Поиск фото по id
        $oPhoto = \E::Module('Topic')->getTopicPhotoById($this->getPost('id'));
        if ($oPhoto) {
            if ($oPhoto->getTopicId()) {

                // * Проверяем права на топик
                $oTopic = \E::Module('Topic')->getTopicById($oPhoto->getTopicId());
                if ($oTopic && \E::Module('ACL')->isAllowEditTopic($oTopic, $this->oUserCurrent)) {
                    \E::Module('Topic')->deleteTopicPhoto($oPhoto);

                    // * Если удаляем главную фотографию. топика, то её необходимо сменить
                    if ($oPhoto->getId() == $oTopic->getPhotosetMainPhotoId() && $oTopic->getPhotosetCount() > 1) {
                        $aPhotos = $oTopic->getPhotosetPhotos(0, 1);
                        $oTopic->setPhotosetMainPhotoId($aPhotos[0]->getMresourceId());
                    } elseif ($oTopic->getPhotosetCount() == 1) {
                        $oTopic->setPhotosetMainPhotoId(null);
                    }
                    $oTopic->setPhotosetCount($oTopic->getPhotosetCount() - 1);
                    \E::Module('Topic')->updateTopic($oTopic);
                    \E::Module('Message')->addNotice(
                        \E::Module('Lang')->get('topic_photoset_photo_deleted'), \E::Module('Lang')->get('attention')
                    );
                    return;
                }
                \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
            \E::Module('Topic')->deleteTopicPhoto($oPhoto);
            \E::Module('Message')->addNotice(\E::Module('Lang')->get('topic_photoset_photo_deleted'), \E::Module('Lang')->get('attention'));
            return;
        }
        \E::Module('Message')->addError(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
        return;
    }

    public function eventPhotoDelete() {

        return $this->eventAjaxPhotoDelete();
    }

    /**
     * Переход по ссылке с подсчетом количества переходов
     *
     */
    public function eventGo() {

        // * Получаем номер топика из УРЛ и проверяем существует ли он
        $iTopicId = (int)$this->getParam(0);
        if (!$iTopicId || !($oTopic = \E::Module('Topic')->getTopicById($iTopicId)) || !$oTopic->getPublish()) {
            return parent::eventNotFound();
        }

        // * проверяем есть ли ссылка на источник
        if (!$oTopic->getSourceLink()) {
            return parent::eventNotFound();
        }

        // * увелививаем число переходов по ссылке
        $oTopic->setSourceLinkCountJump($oTopic->getSourceLinkCountJump() + 1);
        \E::Module('Topic')->updateTopic($oTopic);

        // * собственно сам переход по ссылке
        R::Location($oTopic->getSourceLink());
    }

    /*
     * Обработка дополнительных полей
     */
    public function processFields($oTopic) {
    }

    /**
     * Проверка полей формы
     *
     * @param $oTopic
     *
     * @return bool
     */
    protected function checkTopicFields($oTopic) {

        \E::Module('Security')->validateSendForm();

        $bOk = true;
        /**
         * Валидируем топик
         */
        if (!$oTopic->_Validate()) {
            \E::Module('Message')->addError($oTopic->_getValidateError(), \E::Module('Lang')->get('error'));
            $bOk = false;
        }
        /**
         * Выполнение хуков
         */
        \HookManager::run('check_topic_fields', array('bOk' => &$bOk));

        return $bOk;
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
