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
 * Экшен обработки ajax запросов
 * Ответ отдает в JSON фомате
 *
 * @package actions
 * @since   1.0
 */
class ActionAjax extends Action
{
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    /**
     * Инициализация
     */
    public function init()
    {
        // * Устанавливаем формат ответа
        \E::Module('Viewer')->setResponseAjax('json');

        // * Получаем текущего пользователя
        $this->oUserCurrent = \E::User();
    }

    /**
     * Регистрация евентов
     */
    protected function registerEvent()
    {
        if (\C::get('rating.enabled')) {
            $this->addEventPreg('/^vote$/i', '/^comment$/', 'eventVoteComment');
            $this->addEventPreg('/^vote$/i', '/^topic$/', 'eventVoteTopic');
            $this->addEventPreg('/^vote$/i', '/^blog$/', 'eventVoteBlog');
            $this->addEventPreg('/^vote$/i', '/^user$/', 'eventVoteUser');
        }

        $this->addEventPreg('/^vote$/i', '/^poll$/', 'eventVotePoll');

        $this->addEventPreg('/^favourite$/i', '/^save-tags/', 'eventFavouriteSaveTags');
        $this->addEventPreg('/^favourite$/i', '/^topic$/', 'eventFavouriteTopic');
        $this->addEventPreg('/^favourite$/i', '/^comment$/', 'eventFavouriteComment');
        $this->addEventPreg('/^favourite$/i', '/^talk$/', 'eventFavouriteTalk');

        $this->addEventPreg('/^stream$/i', '/^comment$/', 'eventStreamComment');
        $this->addEventPreg('/^stream$/i', '/^topic$/', 'eventStreamTopic');
        $this->addEventPreg('/^stream$/i', '/^wall/', 'eventStreamWall');

        $this->addEventPreg('/^blogs$/i', '/^top$/', 'eventBlogsTop');
        $this->addEventPreg('/^blogs$/i', '/^self$/', 'eventBlogsSelf');
        $this->addEventPreg('/^blogs$/i', '/^join$/', 'eventBlogsJoin');

        $this->addEventPreg('/^preview$/i', '/^text$/', 'eventPreviewText');
        $this->addEventPreg('/^preview$/i', '/^topic/', 'eventPreviewTopic');

        $this->addEventPreg('/^upload$/i', '/^image$/', 'eventUploadImage');

        $this->addEventPreg('/^autocompleter$/i', '/^tag$/', 'eventAutocompleterTag');
        $this->addEventPreg('/^autocompleter$/i', '/^user$/', 'eventAutocompleterUser');

        $this->addEventPreg('/^comment$/i', '/^delete$/', 'eventCommentDelete');

        $this->addEventPreg('/^geo/i', '/^get/', '/^regions$/', 'eventGeoGetRegions');
        $this->addEventPreg('/^geo/i', '/^get/', '/^cities/', 'eventGeoGetCities');

        $this->addEventPreg('/^infobox/i', '/^info/', '/^blog/', 'eventInfoboxInfoBlog');

        $this->addEvent('fetch', 'eventFetch');

        // Менеджер изображений
        $this->addEvent('image-manager-load-tree', 'eventImageManagerLoadTree');
        $this->addEvent('image-manager-load-images', 'eventImageManagerLoadImages');

    }


    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    /**
     * Загрузка страницы картинок
     */
    public function eventImageManagerLoadImages(){

        \E::Module('Security')->validateSendForm();

        // Менеджер изображений может запускаться в том числе и из админки
        // Если передано название скина админки, то используем его, если же
        // нет, то ту тему, которая установлена для сайта
        if (($sAdminTheme = \F::getRequest('admin')) && E::isAdmin()) {
            \C::set('view.skin', $sAdminTheme);
        }

        // Получим идентификатор пользователя, изображения которого нужно загрузить
        $iUserId = (int)\F::getRequest('profile', FALSE);
        if ($iUserId && \E::Module('User')->getUserById($iUserId)) {
            \C::set('menu.data.profile_images.uid', $iUserId);
        } else {
            // Только пользователь может смотреть своё дерево изображений
            if (!\E::isUser()) {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
                return;
            }
            $iUserId = \E::userId();
        }

        $sCategory = F::getRequestStr('category', FALSE);
        $iPage = F::getRequestInt('page', '1');
        $sTopicId = \F::getRequestStr('topic_id', FALSE);
        $sTargetType = \F::getRequestStr('target');

        if (!$sCategory) {
            return;
        }

        $aTplVariables = [
            'sTargetType' => $sTargetType,
            'sTargetId' => $sTopicId,
        ];

        // Страница загрузки картинки с компьютера
        if ($sCategory === 'insert-from-pc') {
            $sImages = \E::Module('Viewer')->fetch('modals/insert_img/inject.pc.tpl', $aTplVariables);
            \E::Module('Viewer')->assignAjax('images', $sImages);
            return;
        }

        // Страница загрузки из интернета
        if ($sCategory === 'insert-from-link') {
            $sImages = \E::Module('Viewer')->fetch('modals/insert_img/inject.link.tpl', $aTplVariables);
            \E::Module('Viewer')->assignAjax('images', $sImages);
            return;
        }

        $sTemplateName = 'inject.images.tpl';

        $aResources = ['collection' => []];
        $iPagesCount = 0;
        if ($sCategory === 'user') {       //ок

            // * Аватар и фото пользователя

            $aResources = \E::Module('Media')->getMediaByFilter([
                'target_type' => [
                    'profile_avatar',
                    'profile_photo'
                ],
                'user_id'     => $iUserId,
            ], $iPage, \C::get('module.topic.images_per_page'));
            $sTemplateName = 'inject.images.user.tpl';
            $iPagesCount = 0;
        } elseif ($sCategory === '_topic') {

            // * Конкретный топик

            $oTopic = \E::Module('Topic')->getTopicById($sTopicId);
            if ($oTopic
                && ($oTopic->isPublished() || $oTopic->getUserId() == \E::userId())
                && \E::Module('ACL')->IsAllowShowBlog($oTopic->getBlog(), E::User())) {
                $aResourcesId = \E::Module('Media')->getCurrentTopicResourcesId($iUserId, $sTopicId);
                if ($aResourcesId) {
                    $aResources = \E::Module('Media')->getMediaByFilter([
                        'user_id' => $iUserId,
                        'media_id' => $aResourcesId,
                    ], $iPage, \C::get('module.topic.images_per_page'));
                    $aResources['count'] = count($aResourcesId);
                    $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.images_per_page'));

                    $aTplVariables['oTopic'] = $oTopic;
                }
            }

            $sTemplateName = 'inject.images.tpl';

        } elseif ($sCategory === 'talk') {

            // * Письмо

            /** @var ModuleTalk_EntityTalk $oTopic */
            $oTopic = \E::Module('Talk')->getTalkById($sTopicId);
            if ($oTopic && \E::Module('Talk')->getTalkUser($sTopicId, $iUserId)) {

                $aResources = \E::Module('Media')->getMediaByFilter([
                    'user_id' => $iUserId,
                    'target_type' => 'talk',
                    'target_id' => $sTopicId,
                ], $iPage, \C::get('module.topic.images_per_page'));
                $aResources['count'] = \E::Module('Media')->getMediaCountByTargetIdAndUserId('talk', $sTopicId, $iUserId);
                $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.images_per_page'));

                $aTplVariables['oTopic'] = $oTopic;
            }

            $sTemplateName = 'inject.images.tpl';

        } elseif ($sCategory === 'comments') {

            // * Комментарии

            $aResources = \E::Module('Media')->getMediaByFilter(array(
                'user_id'     => $iUserId,
                'target_type' => [
                    'talk_comment',
                    'topic_comment'
                ]
            ), $iPage, \C::get('module.topic.images_per_page'));
            $aResources['count'] = \E::Module('Media')->getMediaCountByTargetAndUserId(array(
                'talk_comment',
                'topic_comment'
            ), $iUserId);
            $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.images_per_page'));

            $sTemplateName = 'inject.images.tpl';

        } elseif ($sCategory === 'current') {       //ок

            // * Картинки текущего топика (текст, фотосет, одиночные картинки)

            $aResourcesId = \E::Module('Media')->getCurrentTopicResourcesId($iUserId, $sTopicId);
            if ($aResourcesId) {
                $aResources = \E::Module('Media')->getMediaByFilter([
                    'user_id' => $iUserId,
                    'media_id' => $aResourcesId,
                ], $iPage, \C::get('module.topic.images_per_page'));
                $aResources['count'] = count($aResourcesId);
                $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.images_per_page'));

            }

            $sTemplateName = 'inject.images.tpl';


        } elseif ($sCategory === 'blog_avatar') { // ок

            // * Аватары созданных блогов

            $aResources = \E::Module('Media')->getMediaByFilter([
                'target_type' => 'blog_avatar',
                'user_id' => $iUserId,
            ], $iPage, \C::get('module.topic.group_images_per_page'));
            $aResources['count'] = \E::Module('Media')->getMediaCountByTargetAndUserId('blog_avatar', $iUserId);

            // Получим блоги
            $aBlogsId = [];
            foreach ($aResources['collection'] as $oResource) {
                $aBlogsId[] = $oResource->getTargetId();
            }
            if ($aBlogsId) {
                $aBlogs = \E::Module('Blog')->getBlogsAdditionalData($aBlogsId);
                $aTplVariables['aBlogs'] = $aBlogs;
            }

            $sTemplateName = 'inject.images.blog.tpl';
            $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.group_images_per_page'));


        } elseif ($sCategory === 'topics') { // ок

            // * Страница топиков

            $aTopicsData = \E::Module('Media')->getTopicsPage($iUserId, $iPage, \C::get('module.topic.group_images_per_page'));

            $aTplVariables['aTopics'] = $aTopicsData['collection'];

            $sTemplateName = 'inject.images.topic.tpl';
            $iPagesCount = ceil($aTopicsData['count'] / \C::get('module.topic.group_images_per_page'));
            $aResources= ['collection'=> []];

        }  elseif (in_array($sCategory, \E::Module('Topic')->getTopicTypes())) { // ок

            // * Страница топиков

            $aTopicsData = \E::Module('Media')->getTopicsPageByType($iUserId, $sCategory, $iPage, \C::get('module.topic.group_images_per_page'));

            $aTplVariables['aTopics'] = $aTopicsData['collection'];

            $sTemplateName = 'inject.images.topic.tpl';
            $iPagesCount = ceil($aTopicsData['count'] / \C::get('module.topic.group_images_per_page'));
            $aResources= ['collection'=> []];

        } elseif ($sCategory === 'talks') { // ок

            // * Страница писем

            $aTalksData = \E::Module('Media')->getTalksPage($iUserId, $iPage, \C::get('module.topic.group_images_per_page'));

            $aTplVariables['aTalks'] = $aTalksData['collection'];
            $sTemplateName = 'inject.images.talk.tpl';
            $iPagesCount = ceil($aTalksData['count'] / \C::get('module.topic.group_images_per_page'));
            $aResources= ['collection' => []];

        } else {

            // * Прочие изображения

            $aResources = \E::Module('Media')->getMediaByFilter([
                'target_type' => $sCategory,
                'user_id' => $iUserId,
            ], $iPage, \C::get('module.topic.images_per_page'));
            $iPagesCount = ceil($aResources['count'] / \C::get('module.topic.images_per_page'));
        }

        $aTplVariables['aResources'] = $aResources['collection'];

        $sPath = \F::getRequest('profile', FALSE) ? 'actions/profile/created_photos/' : 'modals/insert_img/';
        $sImages = \E::Module('Viewer')->getLocalViewer()->fetch($sPath . $sTemplateName, $aTplVariables);

        \E::Module('Viewer')->assignAjax('images', $sImages);
        \E::Module('Viewer')->assignAjax('category', $sCategory);
        \E::Module('Viewer')->assignAjax('page', $iPage);
        \E::Module('Viewer')->assignAjax('pages', $iPagesCount);

    }


    /**
     * Загрузка дерева изображений пользователя
     */
    public function eventImageManagerLoadTree(){

        // Менеджер изображений может запускаться в том числе и из админки
        // Если передано название скина админки, то используем его, если же
        // нет, то ту тему, которая установлена для сайта
        if (($sAdminTheme = \F::getRequest('admin')) && E::isAdmin()) {
            \C::set('view.skin', $sAdminTheme);
        }

        $sPath = ($iUserId = (int)\F::getRequest('profile', FALSE)) ? 'actions/profile/created_photos/' : 'modals/insert_img/';
        if ($iUserId && \E::Module('User')->getUserById($iUserId)) {
            \C::set('menu.data.profile_images.uid', $iUserId);
        } else {
            $iUserId = false;
        }

        if ($iUserId) {
            $aVars = array('iUserId' => $iUserId);
            $sCategories = \E::Module('Viewer')->getLocalViewer()->fetch("{$sPath}inject.categories.tpl", $aVars);
        } else {
            $sCategories = \E::Module('Viewer')->getLocalViewer()->fetch( "{$sPath}inject.categories.tpl");
        }

        \E::Module('Viewer')->assignAjax('categories', $sCategories);

        return FALSE;
    }


    /**
     * Вывод информации о блоге
     */
    public function eventInfoboxInfoBlog() {

        // * Если блог существует и он не персональный
        if (!is_string(\F::getRequest('iBlogId'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        if (!($oBlog = \E::Module('Blog')->getBlogById(\F::getRequest('iBlogId'))) /* || $oBlog->getType()=='personal'*/) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        $aVars = array('oBlog' => $oBlog);

        // Тип блога может быть не определен
        if (!$oBlog->getBlogType() || !$oBlog->getBlogType()->IsPrivate() || $oBlog->getUserIsJoin()) {
            // * Получаем последний топик
            $aResult = \E::Module('Topic')->getTopicsByFilter(['blog_id' => $oBlog->getId(), 'topic_publish' => 1], 1, 1);
            $aVars['oTopicLast'] = reset($aResult['collection']);
        }

        // * Устанавливаем переменные для ajax ответа
        \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('commons/common.infobox_blog.tpl', $aVars));
    }

    /**
     * Получение списка регионов по стране
     */
    public function eventGeoGetRegions() {

        $iCountryId = \F::getRequestStr('country');
        $iLimit = 200;
        if (is_numeric(\F::getRequest('limit')) && \F::getRequest('limit') > 0) {
            $iLimit = \F::getRequest('limit');
        }

        // * Находим страну
        if (!($oCountry = \E::Module('Geo')->getGeoObject('country', $iCountryId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        // * Получаем список регионов
        $aResult = \E::Module('Geo')->getRegions(['country_id' => $oCountry->getId()], ['sort' => 'asc'], 1, $iLimit);
        $aRegions = [];
        foreach ($aResult['collection'] as $oObject) {
            $aRegions[] = [
                'id'   => $oObject->getId(),
                'name' => $oObject->getName(),
            ];
        }

        // * Устанавливаем переменные для ajax ответа
        \E::Module('Viewer')->assignAjax('aRegions', $aRegions);
    }

    /**
     * Получение списка городов по региону
     */
    public function eventGeoGetCities() {

        $iRegionId = \F::getRequestStr('region');
        $iLimit = 500;
        if (is_numeric(\F::getRequest('limit')) && \F::getRequest('limit') > 0) {
            $iLimit = \F::getRequest('limit');
        }

        // * Находим регион
        if (!($oRegion = \E::Module('Geo')->getGeoObject('region', $iRegionId))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'));
            return;
        }

        // * Получаем города
        $aResult = \E::Module('Geo')->getCities(['region_id' => $oRegion->getId()], ['sort' => 'asc'], 1, $iLimit);
        $aCities = [];
        foreach ($aResult['collection'] as $oObject) {
            $aCities[] = [
                'id'   => $oObject->getId(),
                'name' => $oObject->getName(),
            ];
        }

        // * Устанавливаем переменные для ajax ответа
        \E::Module('Viewer')->assignAjax('aCities', $aCities);
    }

    /**
     * Голосование за комментарий
     *
     */
    public function eventVoteComment() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Комментарий существует?
        if (!($oComment = \E::Module('Comment')->getCommentById(\F::getRequestStr('idComment', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_noexists'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Голосует автор комментария?
        if ($oComment->getUserId() == $this->oUserCurrent->getId()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_self'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Пользователь уже голосовал?
        if ($oTopicCommentVote = \E::Module('Vote')->getVote($oComment->getId(), 'comment', $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_already'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Время голосования истекло?
        if (strtotime($oComment->getDate()) <= time() - \C::get('acl.vote.comment.limit_time')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_time'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Пользователь имеет право голоса?
        switch (\E::Module('ACL')->CanVoteComment($this->oUserCurrent, $oComment)) {
            case ModuleACL::CAN_VOTE_COMMENT_ERROR_BAN:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_banned'), \E::Module('Lang')->get('attention'));
                return;
                break;

            case ModuleACL::CAN_VOTE_COMMENT_FALSE:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_acl'), \E::Module('Lang')->get('attention'));
                return;
                break;
        }

        // * Как именно голосует пользователь
        $iValue = \F::getRequestStr('value', null, 'post');
        if (!in_array($iValue, ['1', '-1'])) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error_value'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Голосуем

        /** @var ModuleVote_EntityVote $oTopicCommentVote */
        $oTopicCommentVote = \E::getEntity('Vote');
        $oTopicCommentVote->setTarget($oComment);
        $oTopicCommentVote->setTargetId($oComment->getId());
        $oTopicCommentVote->setTargetType('comment');
        $oTopicCommentVote->setVoter($this->oUserCurrent);
        $oTopicCommentVote->setVoterId($this->oUserCurrent->getId());
        $oTopicCommentVote->setDirection($iValue);
        $oTopicCommentVote->setDate(\F::Now());

        if ($iValue) {
            $nDeltaRating = (float)\E::Module('Rating')->VoteComment($this->oUserCurrent, $oComment, $iValue);
        } else {
            $nDeltaRating = 0.0;
        }

        $oTopicCommentVote->setValue($nDeltaRating);
        $oComment->setCountVote($oComment->getCountVote() + 1);

        if (\E::Module('Vote')->addVote($oTopicCommentVote) && \E::Module('Comment')->updateComment($oComment)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('comment_vote_ok'), \E::Module('Lang')->get('attention'));
            \E::Module('Viewer')->assignAjax('iRating', $oComment->getRating());

            // * Добавляем событие в ленту
            \E::Module('Stream')->write($oTopicCommentVote->getVoterId(), 'vote_comment', $oComment->getId());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_vote_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Голосование за топик
     *
     */
    public function eventVoteTopic() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Топик существует?
        if (!($oTopic = \E::Module('Topic')->getTopicById(\F::getRequestStr('idTopic', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Голосует автор топика?
        if ($oTopic->getUserId() == $this->oUserCurrent->getId()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_self'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Пользователь уже голосовал?
        if ($oTopicVote = \E::Module('Vote')->getVote($oTopic->getId(), 'topic', $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_already'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Время голосования истекло?
        if (strtotime($oTopic->getDateAdd()) <= time() - \C::get('acl.vote.topic.limit_time')) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_time'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Как проголосовал пользователь
        $iValue = (float)\F::getRequestStr('value', null, 'post');
        if (!in_array($iValue, array('1', '-1', '0'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Права на голосование
        switch (\E::Module('ACL')->CanVoteTopic($this->oUserCurrent, $oTopic)) {
            case ModuleACL::CAN_VOTE_TOPIC_ERROR_BAN:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_banned'), \E::Module('Lang')->get('attention'));
                return;
                break;

            case ModuleACL::CAN_VOTE_TOPIC_FALSE:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_acl'), \E::Module('Lang')->get('attention'));
                return;
                break;

            case ModuleACL::CAN_VOTE_TOPIC_NOT_IS_PUBLISHED:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_vote_error_is_not_published'), \E::Module('Lang')->get('attention'));
                return;
                break;
        }

        // * Голосуем

        /** @var ModuleVote_EntityVote $oTopicVote */
        $oTopicVote = \E::getEntity('Vote');
        $oTopicVote->setTarget($oTopic);
        $oTopicVote->setTargetId($oTopic->getId());
        $oTopicVote->setTargetType('topic');
        $oTopicVote->setVoter($this->oUserCurrent);
        $oTopicVote->setVoterId($this->oUserCurrent->getId());
        $oTopicVote->setDirection($iValue);
        $oTopicVote->setDate(\F::Now());

        if ($iValue != 0) {
            $nDeltaRating = (float)\E::Module('Rating')->VoteTopic($this->oUserCurrent, $oTopic, $iValue);
        } else {
            $nDeltaRating = 0.0;
        }
        $oTopicVote->setValue($nDeltaRating);
        $oTopic->setCountVote($oTopic->getCountVote() + 1);

        if ($iValue == 1) {
            $oTopic->setCountVoteUp($oTopic->getCountVoteUp() + 1);
        } elseif ($iValue == -1) {
            $oTopic->setCountVoteDown($oTopic->getCountVoteDown() + 1);
        } elseif ($iValue == 0) {
            $oTopic->setCountVoteAbstain($oTopic->getCountVoteAbstain() + 1);
        }
        if (\E::Module('Vote')->addVote($oTopicVote) && \E::Module('Topic')->UpdateTopic($oTopic)) {
            if ($iValue) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_vote_ok'), \E::Module('Lang')->get('attention'));
            } else {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_vote_ok_abstain'), \E::Module('Lang')->get('attention'));
            }
            \E::Module('Viewer')->assignAjax('iRating', $oTopic->getRating());

            // * Добавляем событие в ленту
            \E::Module('Stream')->write($oTopicVote->getVoterId(), 'vote_topic', $oTopic->getId());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Голосование за блог
     *
     */
    public function eventVoteBlog() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Блог существует?
        if (!($oBlog = \E::Module('Blog')->getBlogById(\F::getRequestStr('idBlog', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Голосует за свой блог?
        if ($oBlog->getOwnerId() == $this->oUserCurrent->getId()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_vote_error_self'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Уже голосовал?
        if ($oBlogVote = \E::Module('Vote')->getVote($oBlog->getId(), 'blog', $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_vote_error_already'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Имеет право на голосование?
        switch (\E::Module('ACL')->CanVoteBlog($this->oUserCurrent, $oBlog)) {
            case ModuleACL::CAN_VOTE_BLOG_TRUE:
                $iValue = \F::getRequestStr('value', null, 'post');
                if (in_array($iValue, array('1', '-1'))) {

                    /** @var ModuleVote_EntityVote $oBlogVote */
                    $oBlogVote = \E::getEntity('Vote');
                    $oBlogVote->setTarget($oBlog);
                    $oBlogVote->setTargetId($oBlog->getId());
                    $oBlogVote->setTargetType('blog');
                    $oBlogVote->setVoter($this->oUserCurrent);
                    $oBlogVote->setVoterId($this->oUserCurrent->getId());
                    $oBlogVote->setDirection($iValue);
                    $oBlogVote->setDate(\F::Now());

                    if ($iValue != 0) {
                        $nDeltaRating = (float)\E::Module('Rating')->VoteBlog($this->oUserCurrent, $oBlog, $iValue);
                    } else {
                        $nDeltaRating = 0.0;
                    }
                    $oBlogVote->setValue($nDeltaRating);
                    $oBlog->setCountVote($oBlog->getCountVote() + 1);

                    if (\E::Module('Vote')->addVote($oBlogVote) && \E::Module('Blog')->UpdateBlog($oBlog)) {
                        \E::Module('Viewer')->assignAjax('iCountVote', $oBlog->getCountVote());
                        \E::Module('Viewer')->assignAjax('iRating', $oBlog->getRating());
                        \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('blog_vote_ok'), \E::Module('Lang')->get('attention'));

                        // * Добавляем событие в ленту
                        \E::Module('Stream')->write($oBlogVote->getVoterId(), 'vote_blog', $oBlog->getId());
                    } else {
                        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('attention'));
                        return;
                    }
                } else {
                    \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('attention'));
                    return;
                }
                break;
            case ModuleACL::CAN_VOTE_BLOG_ERROR_CLOSE:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_vote_error_close'), \E::Module('Lang')->get('attention'));
                return;
                break;
            case ModuleACL::CAN_VOTE_BLOG_ERROR_BAN:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_vote_error_banned'), \E::Module('Lang')->get('attention'));
                return;
                break;

            default:
            case ModuleACL::CAN_VOTE_BLOG_FALSE:
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('blog_vote_error_acl'), \E::Module('Lang')->get('attention'));
                return;
                break;
        }
    }

    /**
     * Голосование за пользователя
     *
     */
    public function eventVoteUser() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Пользователь существует?
        if (!($oUser = \E::Module('User')->getUserById(\F::getRequestStr('idUser', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Голосует за себя?
        if ($oUser->getId() == $this->oUserCurrent->getId()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_vote_error_self'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Уже голосовал?
        if ($oUserVote = \E::Module('Vote')->getVote($oUser->getId(), 'user', $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_vote_error_already'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Имеет право на голосование?
        if (!\E::Module('ACL')->CanVoteUser($this->oUserCurrent, $oUser)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('user_vote_error_acl'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Как проголосовал
        $iValue = \F::getRequestStr('value', null, 'post');
        if (!in_array($iValue, array('1', '-1'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('attention'));
            return;
        }

        // * Голосуем

        /** @var ModuleVote_EntityVote $oUserVote */
        $oUserVote = \E::getEntity('Vote');
        $oUserVote->setTarget($oUser);
        $oUserVote->setTargetId($oUser->getId());
        $oUserVote->setTargetType('user');
        $oUserVote->setVoter($this->oUserCurrent);
        $oUserVote->setVoterId($this->oUserCurrent->getId());
        $oUserVote->setDirection($iValue);
        $oUserVote->setDate(\F::Now());

        if ($iValue != 0) {
            $nDeltaRating = (float)\E::Module('Rating')->VoteUser($this->oUserCurrent, $oUser, $iValue);
        } else {
            $nDeltaRating = 0.0;
        }
        $oUserVote->setValue($nDeltaRating);
        $oUser->setCountVote($oUser->getCountVote() + 1);

        if (\E::Module('Vote')->addVote($oUserVote) && \E::Module('User')->Update($oUser)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('user_vote_ok'), \E::Module('Lang')->get('attention'));
            \E::Module('Viewer')->assignAjax('iRating', number_format($oUser->getRating(), \C::get('view.skill_length')));
            \E::Module('Viewer')->assignAjax('iSkill', number_format($oUser->getSkill(), \C::get('view.rating_length')));
            \E::Module('Viewer')->assignAjax('iCountVote', $oUser->getCountVote());

            // * Добавляем событие в ленту
            \E::Module('Stream')->write($oUserVote->getVoterId(), 'vote_user', $oUser->getId());
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Голосование за вариант ответа в опросе
     *
     */
    public function eventVotePoll() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Параметры голосования
        $idAnswer = \F::getRequestStr('idAnswer', null, 'post');
        $idTopic = \F::getRequestStr('idTopic', null, 'post');

        // * Топик существует?
        if (!($oTopic = \E::Module('Topic')->getTopicById($idTopic))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // *  У топика существует опрос?
        if (!$oTopic->getQuestionAnswers()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Уже голосовал?
        if ($oTopicQuestionVote = \E::Module('Topic')->getTopicQuestionVote($oTopic->getId(), $this->oUserCurrent->getId())) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_question_vote_already'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Вариант ответа
        $aAnswer = $oTopic->getQuestionAnswers();
        if (!isset($aAnswer[$idAnswer]) && $idAnswer != -1) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        if ($idAnswer == -1) {
            $oTopic->setQuestionCountVoteAbstain($oTopic->getQuestionCountVoteAbstain() + 1);
        } else {
            $oTopic->increaseQuestionAnswerVote($idAnswer);
        }
        $oTopic->setQuestionCountVote($oTopic->getQuestionCountVote() + 1);
        $oTopic->setUserQuestionIsVote(true);

        // * Голосуем(отвечаем на опрос)

        /** @var ModuleTopic_EntityTopicQuestionVote $oTopicQuestionVote */
        $oTopicQuestionVote = \E::getEntity('Topic_TopicQuestionVote');
        $oTopicQuestionVote->setTopicId($oTopic->getId());
        $oTopicQuestionVote->setVoterId($this->oUserCurrent->getId());
        $oTopicQuestionVote->setAnswer($idAnswer);

        if (\E::Module('Topic')->AddTopicQuestionVote($oTopicQuestionVote) && \E::Module('Topic')->UpdateTopic($oTopic)) {
            \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_question_vote_ok'), \E::Module('Lang')->get('attention'));
            $aVars = array('oTopic' => $oTopic);
            \E::Module('Viewer')->assignAjax('sText', \E::Module('Viewer')->fetch('fields/field.poll-show.tpl', $aVars));
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Сохраняет теги для избранного
     *
     */
    public function eventFavouriteSaveTags()
    {
        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Объект уже должен быть в избранном
        if ($oFavourite = \E::Module('Favourite')->getFavourite(\F::getRequestStr('target_id'), \F::getRequestStr('target_type'), $this->oUserCurrent->getId())) {
            // * Обрабатываем теги
            $aTags = explode(',', trim(\F::getRequestStr('tags'), "\r\n\t\0\x0B ."));
            $aTagsNew = [];
            $aTagsNewLow = [];
            $aTagsReturn = [];
            foreach ($aTags as $sTag) {
                $sTag = trim($sTag);
                if (\F::CheckVal($sTag, 'text', 2, 50) && !in_array(mb_strtolower($sTag, 'UTF-8'), $aTagsNewLow)) {
                    $sTagEsc = htmlspecialchars($sTag);
                    $aTagsNew[] = $sTagEsc;
                    $aTagsReturn[] = array(
                        'tag' => $sTagEsc,
                        'url' =>
                        $this->oUserCurrent->getProfileUrl() . 'favourites/' . $oFavourite->getTargetType() . 's/tag/'
                            . $sTagEsc . '/', // костыль для URL с множественным числом
                    );
                    $aTagsNewLow[] = mb_strtolower($sTag, 'UTF-8');
                }
            }
            if (!count($aTagsNew)) {
                $oFavourite->setTags('');
            } else {
                $oFavourite->setTags(implode(',', $aTagsNew));
            }
            \E::Module('Viewer')->assignAjax('aTags', $aTagsReturn);
            \E::Module('Favourite')->updateFavourite($oFavourite);
            return;
        }
        \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
    }

    /**
     * Обработка избранного - топик
     *
     */
    public function eventFavouriteTopic() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Можно только добавить или удалить из избранного
        $iType = \F::getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Топик существует?
        if (!($oTopic = \E::Module('Topic')->getTopicById(\F::getRequestStr('idTopic', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Пропускаем топик из черновиков
        if (!$oTopic->getPublish()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('error_favorite_topic_is_draft'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Топик уже в избранном?
        $oFavouriteTopic = \E::Module('Topic')->getFavouriteTopic($oTopic->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteTopic && $iType) {
            $oFavouriteTopicNew = \E::getEntity(
                'Favourite',
                array(
                     'target_id'      => $oTopic->getId(),
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_type'    => 'topic',
                     'target_publish' => $oTopic->getPublish()
                )
            );
            $oTopic->setCountFavourite($oTopic->getCountFavourite() + 1);
            if (\E::Module('Topic')->AddFavouriteTopic($oFavouriteTopicNew) && \E::Module('Topic')->UpdateTopic($oTopic)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_favourite_add_ok'), \E::Module('Lang')->get('attention'));
                \E::Module('Viewer')->assignAjax('bState', true);
                \E::Module('Viewer')->assignAjax('iCount', $oTopic->getCountFavourite());
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
        if (!$oFavouriteTopic && !$iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_favourite_add_no'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteTopic && $iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_favourite_add_already'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteTopic && !$iType) {
            $oTopic->setCountFavourite($oTopic->getCountFavourite() - 1);
            if (\E::Module('Topic')->DeleteFavouriteTopic($oFavouriteTopic) && \E::Module('Topic')->UpdateTopic($oTopic)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('topic_favourite_del_ok'), \E::Module('Lang')->get('attention'));
                \E::Module('Viewer')->assignAjax('bState', false);
                \E::Module('Viewer')->assignAjax('iCount', $oTopic->getCountFavourite());
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
    }

    /**
     * Обработка избранного - комментарий
     *
     */
    public function eventFavouriteComment() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Можно только добавить или удалить из избранного
        $iType = \F::getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Комментарий существует?
        if (!($oComment = \E::Module('Comment')->getCommentById(\F::getRequestStr('idComment', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Комментарий уже в избранном?
        $oFavouriteComment = \E::Module('Comment')->getFavouriteComment($oComment->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteComment && $iType) {
            $oFavouriteCommentNew = \E::getEntity(
                'Favourite',
                array(
                     'target_id'      => $oComment->getId(),
                     'target_type'    => 'comment',
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_publish' => $oComment->getPublish()
                )
            );
            $oComment->setCountFavourite($oComment->getCountFavourite() + 1);
            if (\E::Module('Comment')->AddFavouriteComment($oFavouriteCommentNew) && \E::Module('Comment')->updateComment($oComment)) {
                \E::Module('Message')->addNoticeSingle(
                    \E::Module('Lang')->get('comment_favourite_add_ok'), \E::Module('Lang')->get('attention')
                );
                \E::Module('Viewer')->assignAjax('bState', true);
                \E::Module('Viewer')->assignAjax('iCount', $oComment->getCountFavourite());
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
        if (!$oFavouriteComment && !$iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_favourite_add_no'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteComment && $iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('comment_favourite_add_already'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteComment && !$iType) {
            $oComment->setCountFavourite($oComment->getCountFavourite() - 1);
            if (\E::Module('Comment')->DeleteFavouriteComment($oFavouriteComment) && \E::Module('Comment')->updateComment($oComment)) {
                \E::Module('Message')->addNoticeSingle(
                    \E::Module('Lang')->get('comment_favourite_del_ok'), \E::Module('Lang')->get('attention')
                );
                \E::Module('Viewer')->assignAjax('bState', false);
                \E::Module('Viewer')->assignAjax('iCount', $oComment->getCountFavourite());
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
    }

    /**
     * Обработка избранного - личное сообщение
     *
     */
    public function eventFavouriteTalk() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Можно только добавить или удалить из избранного
        $iType = \F::getRequestStr('type', null, 'post');
        if (!in_array($iType, array('1', '0'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        // *    Сообщение существует?
        if (!($oTalk = \E::Module('Talk')->getTalkById(\F::getRequestStr('idTalk', null, 'post')))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Сообщение уже в избранном?
        $oFavouriteTalk = \E::Module('Talk')->getFavouriteTalk($oTalk->getId(), $this->oUserCurrent->getId());
        if (!$oFavouriteTalk && $iType) {
            $oFavouriteTalkNew = \E::getEntity(
                'Favourite',
                array(
                     'target_id'      => $oTalk->getId(),
                     'target_type'    => 'talk',
                     'user_id'        => $this->oUserCurrent->getId(),
                     'target_publish' => '1'
                )
            );
            if (\E::Module('Talk')->AddFavouriteTalk($oFavouriteTalkNew)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('talk_favourite_add_ok'), \E::Module('Lang')->get('attention'));
                \E::Module('Viewer')->assignAjax('bState', true);
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }
        if (!$oFavouriteTalk && !$iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('talk_favourite_add_no'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteTalk && $iType) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('talk_favourite_add_already'), \E::Module('Lang')->get('error'));
            return;
        }
        if ($oFavouriteTalk && !$iType) {
            if (\E::Module('Talk')->DeleteFavouriteTalk($oFavouriteTalk)) {
                \E::Module('Message')->addNoticeSingle(\E::Module('Lang')->get('talk_favourite_del_ok'), \E::Module('Lang')->get('attention'));
                \E::Module('Viewer')->assignAjax('bState', false);
            } else {
                \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
                return;
            }
        }

    }

    /**
     * Обработка получения последних комментов
     * Используется в блоке "Прямой эфир"
     *
     */
    public function eventStreamComment()
    {
        $aVars = [];
        $iLimit = \C::get('widgets.stream.params.limit');
        if (empty($iLimit)) {
            $iLimit = 20;
        }
        if ($aComments = \E::Module('Comment')->getCommentsOnline('topic', $iLimit)) {
            $aVars['aComments'] = $aComments;
        }
        $sTextResult = \E::Module('Viewer')->fetchWidget('stream_comment.tpl', $aVars);
        \E::Module('Viewer')->assignAjax('sText', $sTextResult);
    }

    /**
     * Обработка получения последних топиков
     * Используется в блоке "Прямой эфир"
     *
     */
    public function eventStreamTopic() {

        $aVars = [];
        if ($aTopics = \E::Module('Topic')->getTopicsLast(\C::get('widgets.stream.params.limit'))) {
            $aVars['aTopics'] = $aTopics['collection'];
        }
        $sTextResult = \E::Module('Viewer')->fetchWidget('stream_topic.tpl', $aVars);
        \E::Module('Viewer')->assignAjax('sText', $sTextResult);
    }

    /**
     * Обработка получения последних записей стены
     * Используется в блоке "Прямой эфир"
     *
     */
    public function eventStreamWall() {

        $aVars = [];
        $aResult = \E::Module('Wall')->getWall(array(), array('date_add' => 'DESC'), 1, \C::get('widgets.stream.params.limit'));
        if ($aResult['count'] != 0) {
            $aVars['aWall'] = $aResult['collection'];
        }

        $sTextResult = \E::Module('Viewer')->fetchWidget('stream_wall.tpl', $aVars);
        \E::Module('Viewer')->assignAjax('sText', $sTextResult);
    }

    /**
     * Обработка получения TOP блогов
     * Используется в блоке "TOP блогов"
     *
     */
    public function eventBlogsTop() {

        // * Получаем список блогов и формируем ответ
        if ($aResult = \E::Module('Blog')->getBlogsRating(1, \C::get('widgets.blogs.params.limit'))) {
            $aVars = array('aBlogs' => $aResult['collection']);

            // Рендерим шаблон виджета
            $sTextResult = \E::Module('Viewer')->fetchWidget('blogs_top.tpl', $aVars);
            \E::Module('Viewer')->assignAjax('sText', $sTextResult);
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
    }

    /**
     * Обработка получения своих блогов
     * Используется в блоке "TOP блогов"
     *
     */
    public function eventBlogsSelf() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Получаем список блогов и формируем ответ
        if ($aBlogs = \E::Module('Blog')->getBlogsRatingSelf($this->oUserCurrent->getId(), \C::get('widgets.blogs.params.limit'))) {
            $aVars = array('aBlogs' => $aBlogs);

            $sTextResult = \E::Module('Viewer')->fetchWidget('blogs_top.tpl', $aVars);
            \E::Module('Viewer')->assignAjax('sText', $sTextResult);
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('widget_blogs_self_error'), \E::Module('Lang')->get('attention'));
            return;
        }
    }

    /**
     * Обработка получения подключенных блогов
     * Используется в блоке "TOP блогов"
     *
     */
    public function eventBlogsJoin() {

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Получаем список блогов и формируем ответ
        if ($aBlogs = \E::Module('Blog')->getBlogsRatingJoin($this->oUserCurrent->getId(), \C::get('widgets.blogs.params.limit'))) {
            $aVars = array('aBlogs' => $aBlogs);

            // Рендерим шаблон виджета
            $sTextResult = \E::Module('Viewer')->fetchWidget('blogs_top.tpl', $aVars);
            \E::Module('Viewer')->assignAjax('sText', $sTextResult);
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('widget_blogs_join_error'), \E::Module('Lang')->get('attention'));
            return;
        }
    }

    /**
     * Предпросмотр топика
     *
     */
    public function eventPreviewTopic() {
        /**
         * Т.к. используется обработка отправки формы, то устанавливаем тип ответа 'jsonIframe' (тот же JSON только обернутый в textarea)
         * Это позволяет избежать ошибок в некоторых браузерах, например, Opera
         */
        \E::Module('Viewer')->SetResponseAjax(\F::AjaxRequest(true)?'json':'jsonIframe', false);

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Допустимый тип топика?
        if (!\E::Module('Topic')->IsAllowTopicType($sType = \F::getRequestStr('topic_type'))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('topic_create_type_error'), \E::Module('Lang')->get('error'));
            return;
        }

        // * Создаем объект топика для валидации данных
        $oTopic = \E::getEntity('ModuleTopic_EntityTopic');
        $oTopic->_setValidateScenario($sType); // зависит от типа топика

        $oTopic->setTitle(strip_tags(\F::getRequestStr('topic_title')));
        $oTopic->setTextSource(\F::getRequestStr('topic_text'));

        $aTags = \F::getRequestStr('topic_field_tags');
        if (!$aTags) {
            // LS compatibility
            $aTags = \F::getRequestStr('topic_tags');
        }
        $oTopic->setTags($aTags);

        $oTopic->setDateAdd(\F::Now());
        $oTopic->setUserId($this->oUserCurrent->getId());
        $oTopic->setType($sType);
        $oTopic->setBlogId(\F::getRequestStr('blog_id'));
        $oTopic->setPublish(1);

        // * Валидируем необходимые поля топика
        $oTopic->_validate(array('topic_title', 'topic_text', 'topic_tags', 'topic_type'), false);
        if ($oTopic->_hasValidateErrors()) {
            \E::Module('Message')->addErrorSingle($oTopic->_getValidateError());
            return false;
        }

        // * Формируем текст топика
        list($sTextShort, $sTextNew, $sTextCut) = \E::Module('Text')->Cut($oTopic->getTextSource());
        $oTopic->setCutText($sTextCut);
        $oTopic->setText(\E::Module('Text')->parse($sTextNew));
        $oTopic->setTextShort(\E::Module('Text')->parse($sTextShort));

        // * Готовим дополнительные поля, кроме файлов
        if ($oType = $oTopic->getContentType()) {
            //получаем поля для данного типа
            if ($aFields = $oType->getFields()) {
                $aValues = [];

                // вставляем поля, если они прописаны для топика
                foreach ($aFields as $oField) {
                    if (isset($_REQUEST['fields'][$oField->getFieldId()])) {

                        $sText = null;

                        //текстовые поля
                        if (in_array($oField->getFieldType(), array('input', 'textarea', 'select'))) {
                            $sText = \E::Module('Text')->parse($_REQUEST['fields'][$oField->getFieldId()]);
                        }
                        //поле ссылки
                        if ($oField->getFieldType() === 'link') {
                            $sText = $_REQUEST['fields'][$oField->getFieldId()];
                        }

                        //поле даты
                        if ($oField->getFieldType() === 'date') {
                            if (isset($_REQUEST['fields'][$oField->getFieldId()])) {

                                if (\F::CheckVal($_REQUEST['fields'][$oField->getFieldId()], 'text', 6, 10)
                                    && substr_count($_REQUEST['fields'][$oField->getFieldId()], '.') == 2
                                ) {
                                    list($d, $m, $y) = explode('.', $_REQUEST['fields'][$oField->getFieldId()]);
                                    if (@checkdate($m, $d, $y)) {
                                        $sText = $_REQUEST['fields'][$oField->getFieldId()];
                                    }
                                }

                            }

                        }

                        if ($sText) {
                            $oValue = \E::getEntity('Topic_ContentValues');
                            $oValue->setFieldId($oField->getFieldId());
                            $oValue->setFieldType($oField->getFieldType());
                            $oValue->setValue($sText);
                            $oValue->setValueSource($_REQUEST['fields'][$oField->getFieldId()]);

                            $aValues[$oField->getFieldId()] = $oValue;
                        }
                    }
                }
                $oTopic->setTopicValues($aValues);
            }
        }

        // * Рендерим шаблон для предпросмотра топика
        $oViewer = \E::Module('Viewer')->getLocalViewer();
        $oViewer->assign('oTopic', $oTopic);
        $oViewer->assign('bPreview', true);

        // Alto-style template
        $sTemplate = 'topics/topic.show.tpl';
        if (!\E::Module('Viewer')->templateExists($sTemplate)) {
            // LS-style template
            $sTemplate = "topic_preview_{$oTopic->getType()}.tpl";
            if (!\E::Module('Viewer')->templateExists($sTemplate)) {
                $sTemplate = 'topic_preview_topic.tpl';
            }
        }
        $sTextResult = $oViewer->fetch($sTemplate);

        // * Передаем результат в ajax ответ
        \E::Module('Viewer')->assignAjax('sText', $sTextResult);
        return true;
    }

    /**
     * Предпросмотр текста
     *
     */
    public function eventPreviewText() {

        $sText = \F::getRequestStr('text', null, 'post');
        $bSave = \F::getRequest('save', null, 'post');

        // * Экранировать или нет HTML теги
        if ($bSave) {
            $sTextResult = htmlspecialchars($sText);
        } else {
            $sTextResult = \E::Module('Text')->parse($sText);
        }
        // * Передаем результат в ajax ответ
        \E::Module('Viewer')->assignAjax('sText', $sTextResult);
    }

    /**
     * Загрузка изображения
     *
     */
    public function eventUploadImage() {
        /*
         * Т.к. используется обработка отправки формы, то устанавливаем тип ответа 'jsonIframe'
         * (тот же JSON только обернутый в textarea)
         * Это позволяет избежать ошибок в некоторых браузерах, например, Opera
         */
        \E::Module('Viewer')->SetResponseAjax(\F::AjaxRequest(true)?'json':'jsonIframe', false);

        // * Пользователь авторизован?
        if (!$this->oUserCurrent) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('need_authorization'), \E::Module('Lang')->get('error'));
            return;
        }
        $sFile = null;
        // * Был выбран файл с компьютера и он успешно загрузился?
        if ($aUploadedFile = $this->getUploadedFile('img_file')) {
            $aOptions = [];
            // Check options of uploaded image
            if ($nWidth = $this->getPost('img_width')) {
                if ($this->getPost('img_width_unit') === 'percent') {
                    // Max width according width of text area
                    if ($this->getPost('img_width_ref') === 'text' && ($nWidthText = (int)$this->getPost('img_width_text'))) {
                        $nWidth = round($nWidthText * $nWidth / 100);
                        $aOptions['size']['width'] = $nWidth;
                    }
                }
            }
            $sFile = \E::Module('Topic')->UploadTopicImageFile($aUploadedFile, $this->oUserCurrent, $aOptions);
            if (!$sFile) {
                $sMessage = \E::Module('Lang')->get('uploadimg_file_error');
                if (\E::Module('Uploader')->getError()) {
                    $sMessage .= ' (' . \E::Module('Uploader')->getErrorMsg() . ')';
                }
                \E::Module('Message')->addErrorSingle($sMessage, \E::Module('Lang')->get('error'));
                return;
            }
        } elseif (($sUrl = $this->getPost('img_url')) && ($sUrl !== 'http://')) {
            // * Загрузка файла по URL
            if (preg_match('~(https?:\/\/)(\w([\w]+)?\.[\w\.\-\/]+.*)$~i', $sUrl, $aM)) {
                // Иногда перед нормальным адресом встречается лишний 'http://' и прочий "мусор"
                $sUrl = $aM[1] . $aM[2];
                $sFile = \E::Module('Topic')->UploadTopicImageUrl($sUrl, $this->oUserCurrent);
            }
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('uploadimg_file_error'));
            return;
        }
        // * Если файл успешно загружен, формируем HTML вставки и возвращаем в ajax ответе
        if ($sFile) {
            $sText = \E::Module('Img')->buildHTML($sFile, $_REQUEST);
            \E::Module('Viewer')->assignAjax('sText', $sText);
        } else {
            \E::Module('Message')->addErrorSingle(\E::Module('Uploader')->getErrorMsg(), \E::Module('Lang')->get('error'));
        }
    }

    /**
     * Автоподставновка тегов
     *
     */
    public function eventAutocompleterTag() {

        // * Первые буквы тега переданы?
        if (!($sValue = \F::getRequest('value', null, 'post')) || !is_string($sValue)) {
            return;
        }
        $aItems = [];

        // * Формируем список тегов
        $aTags = \E::Module('Topic')->getTopicTagsByLike($sValue, 10);
        foreach ($aTags as $oTag) {
            $aItems[] = $oTag->getText();
        }
        // * Передаем результат в ajax ответ
        \E::Module('Viewer')->assignAjax('aItems', $aItems);
    }

    /**
     * Автоподставновка пользователей
     *
     */
    public function eventAutocompleterUser() {

        // * Первые буквы логина переданы?
        if (!($sValue = \F::getRequest('value', null, 'post')) || !is_string($sValue)) {
            return;
        }
        $aItems = [];

        // * Формируем список пользователей
        /** @var ModuleUser_EntityUser[] $aUsers */
        $aUsers = \E::Module('User')->getUsersByLoginLike($sValue, 10);
        foreach ($aUsers as $oUser) {
            $aItems[] =
                (\C::get('autocomplete.user.show_avatar') ? '<img src="' . $oUser->getAvatarUrl(\C::get('autocomplete.user.avatar_size')) . '">' : '')
                . $oUser->getLogin();
        }
        // * Передаем результат в ajax ответ
        \E::Module('Viewer')->assignAjax('aItems', $aItems);
    }

    /**
     * Удаление/восстановление комментария
     *
     */
    public function eventCommentDelete() {

        // * Комментарий существует?
        $idComment = \F::getRequestStr('idComment', null, 'post');
        if (!($oComment = \E::Module('Comment')->getCommentById($idComment))) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Есть права на удаление комментария?
        if (!$oComment->isDeletable()) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('not_access'), \E::Module('Lang')->get('error'));
            return;
        }
        // * Устанавливаем пометку о том, что комментарий удален
        $oComment->setDelete(($oComment->getDelete() + 1) % 2);
        \HookManager::run('comment_delete_before', array('oComment' => $oComment));
        if (!\E::Module('Comment')->updateCommentStatus($oComment)) {
            \E::Module('Message')->addErrorSingle(\E::Module('Lang')->get('system_error'), \E::Module('Lang')->get('error'));
            return;
        }
        \HookManager::run('comment_delete_after', array('oComment' => $oComment));

        // * Формируем текст ответа
        if ($bState = (bool)$oComment->getDelete()) {
            $sMsg = \E::Module('Lang')->get('comment_delete_ok');
            $sTextToggle = \E::Module('Lang')->get('comment_repair');
        } else {
            $sMsg = \E::Module('Lang')->get('comment_repair_ok');
            $sTextToggle = \E::Module('Lang')->get('comment_delete');
        }
        // * Обновление события в ленте активности
        \E::Module('Stream')->write($oComment->getUserId(), 'add_comment', $oComment->getId(), !$oComment->getDelete());

        // * Показываем сообщение и передаем переменные в ajax ответ
        \E::Module('Message')->addNoticeSingle($sMsg, \E::Module('Lang')->get('attention'));
        \E::Module('Viewer')->assignAjax('bState', $bState);
        \E::Module('Viewer')->assignAjax('sTextToggle', $sTextToggle);
    }

    /**
     *
     */
    public function eventFetch() {

        $sHtml = '';
        $bState = false;
        if ($sTpl = $this->getParam(0)) {
            $sTpl = 'ajax.' . $sTpl . '.tpl';
            if (\E::Module('Viewer')->templateExists($sTpl)) {
                $sHtml = \E::Module('Viewer')->fetch($sTpl);
                $bState = true;
            }
        }
        \E::Module('Viewer')->assignAjax('sHtml', $sHtml);
        \E::Module('Viewer')->assignAjax('bState', $bState);
    }

}

// EOF
