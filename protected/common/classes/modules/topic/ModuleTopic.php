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
 * Модуль для работы с топиками
 *
 * @package modules.topic
 * @since   1.0
 */
class ModuleTopic extends Module
{
    const CONTENT_ACCESS_ALL = 1;           // Уровень доступа для всех зарегистрированных
    const CONTENT_ACCESS_ONLY_ADMIN = 2;    // Уровень доступа только для админов

    /**
     * Объект маппера
     *
     * @var ModuleTopic_MapperTopic
     */
    protected $oMapper;

    /**
     * Объект текущего пользователя
     *
     * @var ModuleUser_EntityUser
     */
    protected $oUserCurrent = null;

    /**
     * Список типов топика
     *
     * @var array
     */
    protected $aTopicTypes = []; //'topic','link','question','photoset'

    /**
     * Список полей
     *
     * @var array
     */
    protected $aFieldTypes = ['input', 'textarea', 'photoset', 'link', 'select', 'date', 'file'];

    /**
     * Массив объектов типов топика
     *
     * @var array
     */
    protected $aTopicTypesObjects = [];

    protected $aAdditionalData
        = [
            'user' => [], 'blog' => ['owner' => [], 'relation_user'],
            'vote', 'favourite', 'fields', 'comment_new',
        ];

    protected $aAdditionalDataContentType = ['fields' => []];

    protected $aTopicsFilter = ['topic_publish' => 1];

    /**
     * Инициализация
     *
     */
    public function init() 
    {
        $this->oMapper = \E::getMapper(__CLASS__);
        $this->oUserCurrent = \E::User();
        $this->aTopicTypesObjects = $this->getContentTypes(array('content_active' => 1));
        $this->aTopicTypes = array_keys($this->aTopicTypesObjects);
    }

    /**
     * Получает доступные типы контента
     *
     * @param      $aFilter
     * @param null $aAllowData
     *
     * @return array
     */
    public function getContentTypes($aFilter, $aAllowData = null)
    {

        if (is_null($aAllowData)) {
            $aAllowData = $this->aAdditionalDataContentType;
        }
        $sCacheKey = 'content_types_' . serialize(array($aFilter, $aAllowData)) ;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey, 'tmp,'))) {
            $data = $this->oMapper->getContentTypes($aFilter);
            $aTypesId = [];
            foreach ($data as $oType) {
                $aTypesId[] = $oType->getContentId();
            }
            if (isset($aAllowData['fields'])) {
                $aTopicFieldValues = $this->GetFieldsByArrayId($aTypesId);
            }

            foreach ($data as $oType) {
                if (isset($aTopicFieldValues[$oType->getContentId()])) {
                    $oType->setFields($aTopicFieldValues[$oType->getContentId()]);
                }
            }
            \E::Module('Cache')->set($data, $sCacheKey, array('content_update', 'content_new'), 'P1D', 'tmp,');
        }
        return $data;
    }

    /*
     * Возвращает доступные типы контента
     *
     * @return ModuleTopic_EntityContentType
     */
    public function getContentType($sType)
    {
        if (in_array($sType, $this->aTopicTypes)) {
            return $this->aTopicTypesObjects[$sType];
        }
        return null;
    }

    /**
     * Возвращает доступные для создания пользователем типы контента
     *
     * @param ModuleUser_EntityUser $oUser
     *
     * @return ModuleTopic_EntityContentType[]
     */
    public function getAllowContentTypeByUserId($oUser)
    {
        if ($oUser && ($oUser->isAdministrator() || $oUser->isModerator())) {
            // Для админа и модератора доступны все активные типы контента
            /** @var ModuleTopic_EntityContentType[] $aContentTypes */
            $aContentTypes = \E::Module('Topic')->getContentTypes(array('content_active' => 1));

            return $aContentTypes;
        }

        // Получим все блоги пользователя
        $aBlogs = \E::Module('Blog')->getBlogsAllowByUser($oUser, false);

        // Добавим персональный блог пользователю
        if ($oUser) {
            $aBlogs[] = \E::Module('Blog')->getPersonalBlogByUserId($oUser->getId());
        }

        // Получим типы контента
        /** @var ModuleTopic_EntityContentType[] $aContentTypes */
        $aContentTypes = \E::Module('Topic')->getContentTypes(array('content_active' => 1));

        $aAllowContentTypes = [];

        /** @var ModuleBlog_EntityBlog $oBlog */
        foreach($aBlogs as $oBlog) {
            // Пропускаем блог, если в него нельзя добавлять топики
            if (!\E::Module('ACL')->CanAddTopic($oUser, $oBlog)) {
                continue;
            }

            if ($aContentTypes) {
                foreach ($aContentTypes as $k=>$oContentType) {
                    if ($oBlog->IsContentTypeAllow($oContentType->getContentUrl())) {
                        $aAllowContentTypes[] = $oContentType;
                        // Удалим, что бы повторное не проверять, ведь в каком-то
                        // блоге пользвоателя этот тип контента уже разрешён
                        unset($aContentTypes[$k]);
                    }
                }
            }
        }

        return $aAllowContentTypes;
    }

    /**
     * Получить тип контента по id
     *
     * @param string $nId
     *
     * @return ModuleTopic_EntityContentType|null
     */
    public function getContentTypeById($nId)
    {
        if (false === ($data = \E::Module('Cache')->get("content_type_{$nId}"))) {
            $data = $this->oMapper->getContentTypeById($nId);
            \E::Module('Cache')->set($data, "content_type_{$nId}", ['content_update', 'content_new'], 'P1D');
        }
        return $data;
    }

    /**
     * Получить тип контента по url
     *
     * @param string $sUrl
     *
     * @return ModuleTopic_EntityContentType|null
     */
    public function getContentTypeByUrl($sUrl)
    {
        if (false === ($data = \E::Module('Cache')->get("content_type_{$sUrl}"))) {
            $data = $this->oMapper->getContentTypeByUrl($sUrl);
            \E::Module('Cache')->set($data, "content_type_{$sUrl}", ['content_update', 'content_new'], 'P1D');
        }
        return $data;
    }

    /**
     * TODO: Задание типа контента по умолчанию в админке
     *
     * @return mixed|null
     */
    public function getContentTypeDefault()
    {
        $aTypes = $this->getContentTypes(['content_active' => 1]);
        if ($aTypes) {
            return reset($aTypes);
        }
        return null;
    }

    /**
     * заменить системный тип контента у уже созданных топиков
     *
     * @param string $sTypeOld
     * @param string $sTypeNew
     *
     * @return bool
     */
    public function changeType($sTypeOld, $sTypeNew)
    {
        return $this->oMapper->changeType($sTypeOld, $sTypeNew);
    }

    /**
     * Добавляет тип контента
     *
     * @param ModuleTopic_EntityContentType $oType    Объект типа контента
     *
     * @return ModuleTopic_EntityContentType|bool
     */
    public function addContentType($oType) {

        if ($nId = $this->oMapper->addContentType($oType)) {
            $oType->setContentId($nId);
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('content_new', 'content_update'));
            return $oType;
        }
        return false;
    }

    /**
     * Обновляет топик
     *
     * @param ModuleTopic_EntityContentType $oType    Объект типа контента
     *
     * @return bool
     */
   public function updateContentType($oType) {

        if ($this->oMapper->updateContentType($oType)) {

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(['content_new', 'content_update', 'topic_update']);
            \E::Module('Cache')->delete("content_type_{$oType->getContentId()}");
            return true;
        }
        return false;
   }

    /**
     * Обновляет топик
     *
     * @param ModuleTopic_EntityContentType $oContentType    Объект типа контента
     *
     * @return bool
     */
    public function deleteContentType($oContentType) {

        $aFilter = array(
            'topic_type' => $oContentType->getContentUrl(),
        );
        $iCount = $this->GetCountTopicsByFilter($aFilter);
        if (!$iCount && $this->oMapper->deleteContentType($oContentType->getId())) {

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('content_new', 'content_update', 'topic_update'));
            \E::Module('Cache')->delete("content_type_{$oContentType->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Получает доступные поля для типа контента
     *
     * @param array $aFilter
     *
     * @return ModuleTopic_EntityField[]
     */
    public function getContentFields($aFilter) {

        $sCacheKey = serialize($aFilter);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getContentFields($aFilter);
            \E::Module('Cache')->set($data, $sCacheKey, array('content_update', 'content_new'), 'P1D');
        }
        return $data;
    }

    /**
     * Добавляет поле
     *
     * @param ModuleTopic_EntityField $oField    Объект поля
     *
     * @return ModuleTopic_EntityField|bool
     */
    public function addContentField($oField)
    {

        if ($nId = $this->oMapper->addContentField($oField)) {
            $oField->setFieldId($nId);
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('content_new', 'content_update', 'field_new', 'field_update'));
            return $oField;
        }
        return false;
    }

    /**
     * Обновляет топик
     *
     * @param ModuleTopic_EntityField $oField    Объект поля
     *
     * @return bool
     */
   public function updateContentField($oField)
    {
        if ($this->oMapper->updateContentField($oField)) {

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('content_new', 'content_update', 'field_new', 'field_update'));
            \E::Module('Cache')->delete("content_field_{$oField->getFieldId()}");
            return true;
        }
        return false;
    }

    /**
     * Получить поле контента по id
     *
     * @param string $nId
     *
     * @return ModuleTopic_EntityField
     */
    public function getContentFieldById($nId) {

        if (false === ($data = \E::Module('Cache')->get("content_field_{$nId}"))) {
            $data = $this->oMapper->getContentFieldById($nId);
            \E::Module('Cache')->set(
                $data, "content_field_{$nId}", array('content_new', 'content_update', 'field_new', 'field_update'),
                'P1D'
            );
        }
        return $data;
    }

    /**
     * Удаляет поле
     *
     * @param ModuleTopic_EntityField|int $xField
     *
     * @return bool
     */
    public function deleteField($xField) {

        if (is_object($xField)) {
            $iContentFieldId = $xField->getFieldId();
        } else {
            $iContentFieldId = (int)$xField;
        }
        // * Если топик успешно удален, удаляем связанные данные
        if ($bResult = $this->oMapper->deleteField($iContentFieldId)) {

            // * Чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('field_update', 'content_update'));
            \E::Module('Cache')->delete("content_field_{$iContentFieldId}");

            return true;
        }

        return false;
    }


    /**
     * Добавление поля к топику
     *
     * @param ModuleTopic_EntityContentValues $oValue    Объект поля топика
     *
     * @return int
     */
    public function addTopicValue($oValue) {

        return $this->oMapper->addTopicValue($oValue);
    }

    /**
     * Обновляет значение поля топика
     *
     * @param ModuleTopic_EntityContentValues $oValue    Объект поля
     *
     * @return bool
     */
    public function updateContentFieldValue($oValue)
    {
        if ($this->oMapper->updateContentFieldValue($oValue)) {
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(['topic_update']);
            return true;
        }
        return false;
    }

    /**
     * Получает количество значений у конкретного поля
     *
     * @param $sFieldId
     * @return int|bool
     */
    public function getFieldValuesCount($sFieldId)
    {
        return $this->oMapper->getFieldValuesCount($sFieldId);
    }

    /**
     * Возвращает список типов топика
     *
     * @return string[]
     */
    public function getTopicTypes() {

        return $this->aTopicTypes;
    }

    /**
     * Добавляет новый тип топика
     *
     * @param string $sType    Новый тип
     *
     * @return bool
     */
    public function addTopicType($sType) {

        if (!in_array($sType, $this->aTopicTypes)) {
            $this->aTopicTypes[] = $sType;
            return true;
        }
        return false;
    }

    /**
     * Проверяет разрешен ли данный тип топика
     *
     * @param string $sType    Тип
     *
     * @return bool
     */
    public function isAllowTopicType($sType) {

        return in_array($sType, $this->aTopicTypes);
    }

    /**
     * Возвращает список полей
     *
     * @return array
     */
    public function getAvailableFieldTypes() {

        return $this->aFieldTypes;
    }

    /**
     * Добавляет новый тип поля
     *
     * @param string $sType    Новый тип
     *
     * @return bool
     */
    public function addFieldType($sType)
    {
        if (!in_array($sType, $this->aFieldTypes, true)) {
            $this->aFieldTypes[] = $sType;
            return true;
        }
        return false;
    }

    /**
     * Получает дополнительные данные(объекты) для топиков по их ID
     *
     * @param array|int  $aTopicId    Список ID топиков
     * @param array|null $aAllowData  Список типов дополнительных данных, которые нужно подключать к топикам
     *
     * @return ModuleTopic_EntityTopic[]
     */
    public function getTopicsAdditionalData($aTopicId, $aAllowData = null) {

        if (!is_array($aTopicId)) {
            $aTopicId = array($aTopicId);
        }

        // * Получаем "голые" топики
        $aTopics = $this->getTopicsByArrayId($aTopicId);
        if (!$aTopics) {
            return [];
        }

        if (null === $aAllowData) {
            $aAllowData = $this->aAdditionalData;
        }
        $aAllowData = F::Array_FlipIntKeys($aAllowData);

        // * Формируем ID дополнительных данных, которые нужно получить
        $aUserId = [];
        $aBlogId = [];
        $aTopicId = [];
        $aPhotoMainId = [];

        /** @var ModuleTopic_EntityTopic $oTopic */
        foreach ($aTopics as $oTopic) {
            if (isset($aAllowData['user'])) {
                $aUserId[] = $oTopic->getUserId();
            }
            if (isset($aAllowData['blog'])) {
                $aBlogId[] = $oTopic->getBlogId();
            }

            $aTopicId[] = $oTopic->getId();
            if ($oTopic->getPhotosetMainPhotoId()) {
                $aPhotoMainId[] = $oTopic->getPhotosetMainPhotoId();
            }
        }
        if ($aUserId) {
            $aUserId = array_unique($aUserId);
        }
        if ($aBlogId) {
            $aBlogId = array_unique($aBlogId);
        }
        /**
         * Получаем дополнительные данные
         */
        $aTopicsVote = [];
        $aFavouriteTopics = [];
        $aTopicsQuestionVote = [];
        $aTopicsRead = [];

        $aUsers = isset($aAllowData['user']) && is_array($aAllowData['user'])
            ? \E::Module('User')->getUsersAdditionalData($aUserId, $aAllowData['user'])
            : \E::Module('User')->getUsersAdditionalData($aUserId);

        $aBlogs = isset($aAllowData['blog']) && is_array($aAllowData['blog'])
            ? \E::Module('Blog')->getBlogsAdditionalData($aBlogId, $aAllowData['blog'])
            : \E::Module('Blog')->getBlogsAdditionalData($aBlogId);

        if (isset($aAllowData['vote']) && $this->oUserCurrent) {
            $aTopicsVote = \E::Module('Vote')->getVoteByArray($aTopicId, 'topic', $this->oUserCurrent->getId());
            $aTopicsQuestionVote = $this->GetTopicsQuestionVoteByArray($aTopicId, $this->oUserCurrent->getId());
        }

        if (isset($aAllowData['favourite']) && $this->oUserCurrent) {
            $aFavouriteTopics = $this->GetFavouriteTopicsByArray($aTopicId, $this->oUserCurrent->getId());
        }

        if (isset($aAllowData['fields'])) {
            $aTopicFieldValues = $this->GetTopicValuesByArrayId($aTopicId);
        }

        if (isset($aAllowData['comment_new']) && $this->oUserCurrent) {
            $aTopicsRead = $this->GetTopicsReadByArray($aTopicId, $this->oUserCurrent->getId());
        }

        $aPhotosetMainPhotos = $this->GetTopicPhotosByArrayId($aPhotoMainId);

        // * Добавляем данные к результату - списку топиков
        /** @var ModuleTopic_EntityTopic $oTopic */
        foreach ($aTopics as $oTopic) {
            if (isset($aUsers[$oTopic->getUserId()])) {
                $oTopic->setUser($aUsers[$oTopic->getUserId()]);
            } else {
                $oTopic->setUser(null); // или $oTopic->setUser(new ModuleUser_EntityUser());
            }
            if (isset($aBlogs[$oTopic->getBlogId()])) {
                $oTopic->setBlog($aBlogs[$oTopic->getBlogId()]);
            } else {
                $oTopic->setBlog(null); // или $oTopic->setBlog(new ModuleBlog_EntityBlog());
            }
            if (isset($aTopicsVote[$oTopic->getId()])) {
                $oTopic->setVote($aTopicsVote[$oTopic->getId()]);
            } else {
                $oTopic->setVote(null);
            }
            if (isset($aFavouriteTopics[$oTopic->getId()])) {
                $oTopic->setFavourite($aFavouriteTopics[$oTopic->getId()]);
            } else {
                $oTopic->setFavourite(null);
            }
            if (isset($aTopicsQuestionVote[$oTopic->getId()])) {
                $oTopic->setUserQuestionIsVote(true);
            } else {
                $oTopic->setUserQuestionIsVote(false);
            }
            if (isset($aTopicFieldValues[$oTopic->getId()])) {
                $oTopic->setTopicValues($aTopicFieldValues[$oTopic->getId()]);
            } else {
                $oTopic->setTopicValues(false);
            }
            if (isset($aTopicsRead[$oTopic->getId()])) {
                $oTopic->setCountCommentNew(
                    $oTopic->getCountComment() - $aTopicsRead[$oTopic->getId()]->getCommentCountLast()
                );
                $oTopic->setDateRead($aTopicsRead[$oTopic->getId()]->getDateRead());
            } else {
                $oTopic->setCountCommentNew(0);
                $oTopic->setDateRead(\F::Now());
            }
            if (isset($aPhotosetMainPhotos[$oTopic->getPhotosetMainPhotoId()])) {
                $oTopic->setPhotosetMainPhoto($aPhotosetMainPhotos[$oTopic->getPhotosetMainPhotoId()]);
            } else {
                $oTopic->setPhotosetMainPhoto(null);
            }
        }
        return $aTopics;
    }

    /**
     * Добавляет топик
     *
     * @param ModuleTopic_EntityTopic $oTopic    Объект топика
     *
     * @return ModuleTopic_EntityTopic|bool
     */
    public function addTopic($oTopic) {

        if ($nId = $this->oMapper->addTopic($oTopic)) {
            $oTopic->setId($nId);
            if ($oTopic->getPublish() && $oTopic->getTags()) {
                $aTags = explode(',', $oTopic->getTags());
                foreach ($aTags as $sTag) {
                    /** @var ModuleTфп_EntityTag $oTag */
                    $oTag = \E::getEntity('Topic_TopicTag');
                    $oTag->setTopicId($oTopic->getId());
                    $oTag->setUserId($oTopic->getUserId());
                    $oTag->setBlogId($oTopic->getBlogId());
                    $oTag->setText($sTag);
                    $this->AddTopicTag($oTag);
                }
            }
            $this->processTopicFields($oTopic, 'add');

            $this->UpdateMresources($oTopic);

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(
                ['topic_new', "topic_update_user_{$oTopic->getUserId()}", "blog_update_{$oTopic->getBlogId()}"]
            );
            return $oTopic;
        }
        return false;
    }

    /**
     * Добавление тега к топику
     *
     * @param ModuleTфп_EntityTag $oTopicTag    Объект тега топика
     *
     * @return int
     */
    public function addTopicTag($oTopicTag) {

        return $this->oMapper->addTopicTag($oTopicTag);
    }

    /**
     * Удаляет теги у топика
     *
     * @param   int|array $aTopicsId  ID топика
     *
     * @return  bool
     */
    public function deleteTopicTagsByTopicId($aTopicsId) {

        return $this->oMapper->deleteTopicTagsByTopicId($aTopicsId);
    }

    /**
     * Удаляет значения полей у топика
     *
     * @param int|array $aTopicsId    ID топика
     *
     * @return bool
     */
    public function deleteTopicValuesByTopicId($aTopicsId) {

        return $this->oMapper->deleteTopicValuesByTopicId($aTopicsId);
    }

    /**
     * Удаляет топик.
     * Если тип таблиц в БД InnoDB, то удалятся всё связи по топику(комменты,голосования,избранное)
     *
     * @param ModuleTopic_EntityTopic|int $oTopicId Объект топика или ID
     *
     * @return bool
     */
    public function deleteTopic($oTopicId) {

        if ($oTopicId instanceof ModuleTopic_EntityTopic) {
            $oTopic = $oTopicId;
            $iTopicId = $oTopic->getId();
            $iUserId = $oTopic->getUserId();
        } else {
            $iTopicId = (int)$oTopicId;
            $oTopic = $this->GetTopicById($iTopicId);
            if (!$oTopic) {
                return false;
            }
            $iUserId = $oTopic->getUserId();
        }
        $oTopicId = null;

        $oBlog = $oTopic->GetBlog();
        // * Если топик успешно удален, удаляем связанные данные
        if ($bResult = $this->oMapper->deleteTopic($iTopicId)) {
            $bResult = $this->deleteTopicAdditionalData($iTopicId);
            $this->deleteTopicValuesByTopicId($iTopicId);
            $this->deleteTopicTagsByTopicId($iTopicId);
            $this->deleteMresources($oTopic);
            if ($oBlog) {
                // Блог может быть удален до удаления топика
                \E::Module('Blog')->RecalculateCountTopicByBlogId($oBlog->GetId());
            }
        }

        // * Чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(['topic_update', 'topic_update_user_' . $iUserId]);
        \E::Module('Cache')->delete("topic_{$iTopicId}");

        return $bResult;
    }

    /**
     * Delete array of topics
     *
     * @param ModuleTopic_EntityTopic|int|ModuleTopic_EntityTopic[]|int[] $xTopics
     *
     * @return bool
     */
    public function deleteTopics($xTopics) {

        if (is_int($xTopics) || is_object($xTopics)) {
            return $this->deleteTopic($xTopics);
        }

        if (is_array($xTopics)) {
            if (count($xTopics) == 1) {
                return $this->deleteTopic(reset($xTopics));
            }
            if (!is_object(reset($xTopics))) {
                // there are IDs in param
                $aTopics = $this->getTopicsAdditionalData($xTopics);
            } else {
                // there are topic objects in param
                $aTopics = $xTopics;
            }
            if ($aTopics) {
                $aTopicsId = [];
                $aBlogId = [];
                $aUserId = [];
                foreach ($aTopics as $oTopic) {
                    $aTopicsId[] = $oTopic->getId();
                    $aBlogId[] = $oTopic->getBlogId();
                    $aUserId[] = $oTopic->getUserId();
                }
                if ($bResult = $this->oMapper->deleteTopic($aTopicsId)) {
                    $bResult = $this->deleteTopicAdditionalData($aTopicsId);
                    $this->deleteTopicValuesByTopicId($aTopicsId);
                    $this->deleteTopicTagsByTopicId($aTopicsId);
                    $this->deleteMresources($aTopics);
                    \E::Module('Blog')->RecalculateCountTopicByBlogId($aBlogId);
                }

                // * Чистим зависимые кеши
                $aCacheTags = ['topic_update'];
                foreach($aUserId as $iUserId) {
                    $aCacheTags[] = 'topic_update_user_' . $iUserId;
                }
                \E::Module('Cache')->cleanByTags($aCacheTags);
                foreach($aTopicsId as $iTopicId) {
                    \E::Module('Cache')->delete('topic_' . $iTopicId);
                }

                return $bResult;
            }
        }
        return false;
    }

    /**
     * Удаление топиков по массиву ID пользователей
     *
     * @param int[] $aUsersId
     *
     * @return bool
     */
    public function deleteTopicsByUsersId($aUsersId) {

        $aFilter = [
            'user_id' => $aUsersId,
        ];
        $aTopicsId = $this->oMapper->getAllTopics($aFilter);

        if ($bResult = $this->oMapper->deleteTopic($aTopicsId)) {
            $bResult = $this->deleteTopicAdditionalData($aTopicsId);
        }

        // * Чистим зависимые кеши
        $aTags = ['topic_update'];
        foreach ($aUsersId as $nUserId) {
            $aTags[] = 'topic_update_user_' . $nUserId;
        }
        \E::Module('Cache')->cleanByTags($aTags);
        if ($aTopicsId) {
            // * Чистим зависимые кеши
            $aCacheTags = ['topic_update'];
            foreach($aUsersId as $iUserId) {
                $aCacheTags[] = 'topic_update_user_' . $iUserId;
            }
            \E::Module('Cache')->cleanByTags($aCacheTags);
            foreach($aTopicsId as $iTopicId) {
                \E::Module('Cache')->delete('topic_' . $iTopicId);
            }
        }

        return $bResult;
    }

    /**
     * Удаляет свзяанные с топиком данные
     *
     * @param   int|array $aTopicId   ID топика или массив ID
     *
     * @return  bool
     */
    public function deleteTopicAdditionalData($aTopicId) {

        if (!is_array($aTopicId)) {
            $aTopicId = [(int)$aTopicId];
        }

        // * Удаляем контент топика
        $this->deleteTopicContentByTopicId($aTopicId);
        /**
         * Удаляем комментарии к топику.
         * При удалении комментариев они удаляются из избранного,прямого эфира и голоса за них
         */
        \E::Module('Comment')->DeleteCommentByTargetId($aTopicId, 'topic');
        /**
         * Удаляем топик из избранного
         */
        $this->deleteFavouriteTopicByArrayId($aTopicId);
        /**
         * Удаляем топик из прочитанного
         */
        $this->deleteTopicReadByArrayId($aTopicId);
        /**
         * Удаляем голосование к топику
         */
        \E::Module('Vote')->DeleteVoteByTarget($aTopicId, 'topic');
        /**
         * Удаляем теги
         */
        $this->deleteTopicTagsByTopicId($aTopicId);
        /**
         * Удаляем фото у топика фотосета
         */
        if ($aPhotos = $this->getPhotosByTopicId($aTopicId)) {
            foreach ($aPhotos as $oPhoto) {
                $this->deleteTopicPhoto($oPhoto);
            }
        }
        /**
         * Чистим зависимые кеши
         */
        \E::Module('Cache')->cleanByTags(array('topic_update'));
        foreach ($aTopicId as $nTopicId) {
            \E::Module('Cache')->delete("topic_{$nTopicId}");
        }
        return true;
    }

    /**
     * Обновляет топик
     *
     * @param ModuleTopic_EntityTopic $oTopic    Объект топика
     *
     * @return bool
     */
    public function updateTopic($oTopic)
    {
        // * Получаем топик ДО изменения
        $oTopicOld = $this->getTopicById($oTopic->getId());
        $oTopic->setDateEdit(\F::Now());
        if ($this->oMapper->updateTopic($oTopic)) {
            // * Если топик изменил видимость (publish) или локацию (BlogId) или список тегов
            if ($oTopicOld && (($oTopic->getPublish() != $oTopicOld->getPublish())
                || ($oTopic->getBlogId() != $oTopicOld->getBlogId())
                || ($oTopic->getTags() != $oTopicOld->getTags())
            )) {
                // * Обновляем теги
                $this->deleteTopicTagsByTopicId($oTopic->getId());
                if ($oTopic->getPublish() && $oTopic->getTags()) {
                    $aTags = explode(',', $oTopic->getTags());
                    foreach ($aTags as $sTag) {
                        /** @var ModuleTфп_EntityTag $oTag */
                        $oTag = \E::getEntity('Topic_TopicTag');
                        $oTag->setTopicId($oTopic->getId());
                        $oTag->setUserId($oTopic->getUserId());
                        $oTag->setBlogId($oTopic->getBlogId());
                        $oTag->setText($sTag);
                        $this->AddTopicTag($oTag);
                    }
                }
            }
            if ($oTopicOld && ($oTopic->getPublish() != $oTopicOld->getPublish())) {
                // * Обновляем избранное
                $this->SetFavouriteTopicPublish($oTopic->getId(), $oTopic->getPublish());
                // * Удаляем комментарий топика из прямого эфира
                if ($oTopic->getPublish() == 0) {
                    \E::Module('Comment')->DeleteCommentOnlineByTargetId($oTopic->getId(), 'topic');
                }
                // * Изменяем видимость комментов
                \E::Module('Comment')->SetCommentsPublish($oTopic->getId(), 'topic', $oTopic->getPublish());
            }

            if (R::GetAction() === 'content') {
                $this->processTopicFields($oTopic, 'update');
            }

            $this->UpdateMresources($oTopic);

            // чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('topic_update', "topic_update_user_{$oTopic->getUserId()}"));
            \E::Module('Cache')->delete("topic_{$oTopic->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Удаление контента топика по его номеру
     *
     * @param   int|array $aTopicsId   - ID топика или массив ID
     *
     * @return  bool
     */
    public function deleteTopicContentByTopicId($aTopicsId) {

        return $this->oMapper->deleteTopicContentByTopicId($aTopicsId);
    }

    /**
     * Получить топик по ID
     *
     * @param int $iTopicId    ID топика
     *
     * @return ModuleTopic_EntityTopic|null
     */
    public function getTopicById($iTopicId)
    {
        if (!(int)$iTopicId) {
            return null;
        }
        $aTopics = $this->getTopicsAdditionalData($iTopicId);
        if (isset($aTopics[$iTopicId])) {
            return $aTopics[$iTopicId];
        }
        return null;
    }

    /**
     * Получить топик по URL
     *
     * @param string $sUrl
     *
     * @return ModuleTopic_EntityTopic|null
     */
    public function getTopicByUrl($sUrl) {

        $iTopicId = $this->GetTopicIdByUrl($sUrl);
        if ($iTopicId) {
            return $this->GetTopicById($iTopicId);
        }
        return null;
    }

    /**
     * Returns topic ID by URL if it exists
     *
     * @param string $sUrl
     *
     * @return int
     */
    public function getTopicIdByUrl($sUrl) {

        $sCacheKey = 'topic_url_' . $sUrl;
        if (false === ($iTopicId = \E::Module('Cache')->get($sCacheKey))) {
            $iTopicId = $this->oMapper->getTopicIdByUrl($sUrl);
            if ($iTopicId) {
                \E::Module('Cache')->set($iTopicId, $sCacheKey, array("topic_update_{$iTopicId}"), 'P30D');
            } else {
                \E::Module('Cache')->set(null, $sCacheKey, array('topic_update', 'topic_new'), 'P30D');
            }
        }

        return $iTopicId;
    }

    /**
     * Получить топики по похожим URL
     *
     * @param string $sUrl
     *
     * @return ModuleTopic_EntityTopic[]
     */
    public function getTopicsLikeUrl($sUrl) {

        $aTopicsId = $this->oMapper->getTopicsIdLikeUrl($sUrl);
        if ($aTopicsId) {
            return $this->GetTopicsByArrayId($aTopicsId);
        }
        return [];
    }

    /**
     * Проверяет URL топика на совпадения и, если нужно, делает его уникальным
     *
     * @param string $sUrl
     *
     * @return string
     */
    public function correctTopicUrl($sUrl) {

        $iOnDuplicateUrl = Config::val('module.topic.on_duplicate_url', 1);
        if ($iOnDuplicateUrl) {
            // Получаем список топиков с похожим URL
            $aTopics = $this->GetTopicsLikeUrl($sUrl);
            if ($aTopics) {
                $aExistUrls = [];
                foreach ($aTopics as $oTopic) {
                    $aExistUrls[] = $oTopic->GetTopicUrl();
                }
                $nNum = count($aTopics) + 1;
                $sNewUrl = $sUrl . '-' . $nNum;
                while (in_array($sNewUrl, $aExistUrls)) {
                    $sNewUrl = $sUrl . '-' . (++$nNum);
                }
                $sUrl = $sNewUrl;
            }
        }
        return $sUrl;
    }

    /**
     * Получить список топиков по списку ID
     *
     * @param array $aTopicsId    Список ID топиков
     *
     * @return int|array
     */
    public function getTopicsByArrayId($aTopicsId) {

        if (!$aTopicsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->GetTopicsByArrayIdSolid($aTopicsId);
        }

        if (!is_array($aTopicsId)) {
            $aTopicsId = array($aTopicsId);
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopics = [];
        $aTopicIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aTopicsId, 'topic_');
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {

            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                /** @var ModuleTopic_EntityTopic[] $data */
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aTopics[$data[$sKey]->getId()] = $data[$sKey];
                    } else {
                        $aTopicIdNotNeedQuery[] = $aTopicsId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких топиков не было в кеше и делаем запрос в БД
        $aTopicIdNeedQuery = array_diff($aTopicsId, array_keys($aTopics));
        $aTopicIdNeedQuery = array_diff($aTopicIdNeedQuery, $aTopicIdNotNeedQuery);
        $aTopicIdNeedStore = $aTopicIdNeedQuery;

        if ($aTopicIdNeedQuery) {
            if ($data = $this->oMapper->getTopicsByArrayId($aTopicIdNeedQuery)) {
                foreach ($data as $oTopic) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aTopics[$oTopic->getId()] = $oTopic;
                    $aCacheTags = array('topic_update');
                    if ($oTopic->getBlogId()) {
                        $aCacheTags[] = 'blog_update_' . $oTopic->getBlogId();
                    }
                    \E::Module('Cache')->set($oTopic, "topic_{$oTopic->getId()}", $aCacheTags, 'P4D');
                    $aTopicIdNeedStore = array_diff($aTopicIdNeedStore, array($oTopic->getId()));
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aTopicIdNeedStore as $nId) {
            \E::Module('Cache')->set(null, "topic_{$nId}", array('topic_update'), 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aTopics = F::Array_SortByKeysArray($aTopics, $aTopicsId);

        return $aTopics;
    }

    /**
     * Получить список топиков по списку ID, но используя единый кеш
     *
     * @param array $aTopicsId    Список ID топиков
     *
     * @return ModuleTopic_EntityTopic[]
     */
    public function getTopicsByArrayIdSolid($aTopicsId) {

        if (!is_array($aTopicsId)) {
            $aTopicsId = array($aTopicsId);
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopics = [];
        $s = join(',', $aTopicsId);
        if (false === ($data = \E::Module('Cache')->get("topic_id_{$s}"))) {
            $data = $this->oMapper->getTopicsByArrayId($aTopicsId);
            foreach ($data as $oTopic) {
                $aTopics[$oTopic->getId()] = $oTopic;
            }
            \E::Module('Cache')->set($aTopics, "topic_id_{$s}", array('topic_update'), 'P1D');
            return $aTopics;
        }
        return $data;
    }

    /**
     * Получает список топиков из избранного
     *
     * @param  int $nUserId      ID пользователя
     * @param  int $iCurrPage    Номер текущей страницы
     * @param  int $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getTopicsFavouriteByUserId($nUserId, $iCurrPage, $iPerPage) {

        $aCloseTopics = [];
        /**
         * Получаем список идентификаторов избранных записей
         */
        $data = ($this->oUserCurrent && $nUserId == $this->oUserCurrent->getId())
            ? \E::Module('Favourite')->getFavouritesByUserId($nUserId, 'topic', $iCurrPage, $iPerPage, $aCloseTopics)
            : \E::Module('Favourite')->getFavouriteOpenTopicsByUserId($nUserId, $iCurrPage, $iPerPage);

        // * Получаем записи по переданому массиву айдишников
        if ($data['collection']) {
            $data['collection'] = $this->getTopicsAdditionalData($data['collection']);
        }

        if ($data['collection'] && !\E::isAdmin()) {
            $aAllowBlogTypes = \E::Module('Blog')->getOpenBlogTypes();
            if ($this->oUserCurrent) {
                $aClosedBlogs = \E::Module('Blog')->getAccessibleBlogsByUser($this->oUserCurrent);
            } else {
                $aClosedBlogs = [];
            }
            foreach ($data['collection'] as $iId=>$oTopic) {
                $oBlog = $oTopic->getBlog();
                if ($oBlog) {
                    if (!in_array($oBlog->getType(), $aAllowBlogTypes) && !in_array($oBlog->getId(), $aClosedBlogs)) {
                        $oTopic->setTitle('...');
                        $oTopic->setText(\E::Module('Lang')->get('acl_cannot_show_content'));
                        $oTopic->setTextShort(\E::Module('Lang')->get('acl_cannot_show_content'));
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Возвращает число топиков в избранном
     *
     * @param  int $nUserId    ID пользователя
     *
     * @return int
     */
    public function getCountTopicsFavouriteByUserId($nUserId) {

        $aCloseTopics = [];
        return ((int)$nUserId === \E::userId())
            ? \E::Module('Favourite')->getCountFavouritesByUserId($nUserId, 'topic', $aCloseTopics)
            : \E::Module('Favourite')->getCountFavouriteOpenTopicsByUserId($nUserId);
    }

    /**
     * Список топиков по фильтру
     *
     * @param  array      $aFilter       Фильтр
     * @param  int        $iPage         Номер страницы
     * @param  int        $iPerPage      Количество элементов на страницу
     * @param  array|null $aAllowData    Список типов данных для подгрузки в топики
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getTopicsByFilter($aFilter, $iPage = 1, $iPerPage = 10, $aAllowData = null)
    {
        if (!is_numeric($iPage) || $iPage <= 0) {
            $iPage = 1;
        }

        $sCacheKey = 'topic_filter_' . serialize($aFilter) . "_{$iPage}_{$iPerPage}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = [
                'collection' => $this->oMapper->getTopics($aFilter, $iCount, $iPage, $iPerPage),
                'count'      => $iCount
            ];
            \E::Module('Cache')->set($data, $sCacheKey, ['topic_update', 'topic_new'], 'P1D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getTopicsAdditionalData($data['collection'], $aAllowData);
        }
        return $data;
    }

    /**
     * Количество топиков по фильтру
     *
     * @param array $aFilter    Фильтр
     *
     * @return int
     */
    public function getCountTopicsByFilter($aFilter) {

        $sTmpCacheKey = 'get_count_topics_by_' . serialize($aFilter) . '_' . E::userId();
        if (FALSE === ($iResult = \E::Module('Cache')->getTmp($sTmpCacheKey))) {

            $iResult = 0;
            if (isset($aFilter['blog_type'])) {
                $aBlogsType = (array)$aFilter['blog_type'];
                unset($aFilter['blog_type']);
                if (isset($aBlogsType['*'])) {
                    $aBlogsId = $aBlogsType['*'];
                    unset($aBlogsType['*']);
                } else {
                    $aBlogsId = [];
                }
                $sCacheKey = 'topic_count_by_blog_type_' . serialize($aFilter);
                if (false === ($aData = \E::Module('Cache')->get($sCacheKey))) {
                    $aData = $this->oMapper->getCountTopicsByBlogtype($aFilter);
                    \E::Module('Cache')->set($aData, $sCacheKey, array('topic_update', 'topic_new', 'blog_update', 'blog_new'), 'P1D');
                }

                if ($aData) {
                    foreach($aBlogsType as $sBlogType) {
                        if (isset($aData[$sBlogType])) {
                            $iResult += $aData[$sBlogType];
                        }
                    }
                }
                if ($aBlogsId) {
                    $aFilter['blog_id'] = $aBlogsId;
                    $aFilter['blog_type_exclude'] = $aBlogsType;
                    $iCount = $this->GetCountTopicsByFilter($aFilter);
                    $iResult += $iCount;
                }
                return $iResult;
            } else {
                $sCacheKey = 'topic_count_' . serialize($aFilter);
                if (false === ($iResult = \E::Module('Cache')->get($sCacheKey))) {
                    $iResult = $this->oMapper->getCountTopics($aFilter);
                    \E::Module('Cache')->set($iResult, $sCacheKey, array('topic_update', 'topic_new'), 'P1D');
                }
            }
            \E::Module('Cache')->setTmp($iResult, $sTmpCacheKey);
        }
        return $iResult ? $iResult : 0;
    }

    /**
     * Количество черновиков у пользователя
     *
     * @param int $nUserId    ID пользователя
     *
     * @return int
     */
    public function getCountDraftTopicsByUserId($nUserId) {

        return $this->GetCountTopicsByFilter(
            array(
                 'user_id'       => $nUserId,
                 'topic_publish' => 0
            )
        );
    }

    /**
     * @param array $aFilter
     */
    public function setTopicsFilter($aFilter) {

        $this->aTopicsFilter = $aFilter;
    }

    /**
     * @return array
     */
    public function getTopicsFilter() {

        return $this->aTopicsFilter;
    }

    /**
     * Return filter for topic list by name and params
     *
     * @param string $sFilterName
     * @param array  $aParams
     *
     * @return array
     */
    public function getNamedFilter($sFilterName, $aParams = [])
    {
        $aFilter = $this->getTopicsFilter();
        switch ($sFilterName) {
            case 'good': // Filter for good topics
                $aFilter['topic_rating']  = [
                        'value'         => empty($aParams['rating']) ? 0 : (int)$aParams['rating'],
                        'type'          => 'top',
                        'publish_index' => 1,
                ];
                break;
            case 'bad': // Filter for good topics
                $aFilter['topic_rating']  = [
                        'value'         => empty($aParams['rating']) ? 0 : (int)$aParams['rating'],
                        'type'          => 'down',
                        'publish_index' => 1,
                ];
                break;
            case 'new': // Filter for new topics
                $sDate = date('Y-m-d H:00:00', time() - \C::get('module.topic.new_time'));
                $aFilter['topic_new'] = $sDate;
                break;
            case 'all': // Filter for ALL topics
            case 'new_all': // Filter for ALL new topics
                // Nothing others
                break;
            case 'discussed': //
                if (!empty($aParams['period'])) {
                    if (is_numeric($aParams['period'])) {
                        // количество последних секунд
                        $sPeriod = date('Y-m-d H:00:00', time() - (int)$aParams['period']);
                    } else {
                        $sPeriod = $aParams['period'];
                    }
                    $aFilter['topic_date_more'] = $sPeriod;
                }
                if (!isset($aFilter['order'])) {
                    $aFilter['order'] = [];
                }
                $aFilter['order'][] = 't.topic_count_comment DESC';
                $aFilter['order'][] = 't.topic_date_show DESC';
                $aFilter['order'][] = 't.topic_id DESC';
                break;
            case 'top':
                if (!empty($aParams['period'])) {
                    if (is_numeric($aParams['period'])) {
                        // количество последних секунд
                        $sPeriod = date('Y-m-d H:00:00', time() - (int)$aParams['period']);
                    } else {
                        $sPeriod = $aParams['period'];
                    }
                    $aFilter['topic_date_more'] = $sPeriod;
                }
                if (!isset($aFilter['order'])) {
                    $aFilter['order'] = [];
                }
                $aFilter['order'][] = 't.topic_rating DESC';
                $aFilter['order'][] = 't.topic_date_show DESC';
                $aFilter['order'][] = 't.topic_id DESC';
                break;
            default:
                // Nothing others
        }

        if (!empty($aParams['blog_id'])) {
            $aFilter['blog_id'] = (int)$aParams['blog_id'];
        } else {
            $aFilter['blog_type'] = empty($aParams['personal']) ? \E::Module('Blog')->getOpenBlogTypes() : 'personal';

            // If a user is authorized then adds blogs on which it is subscribed
            if (\E::isUser() && (!isset($aParams['accessible']) || $aParams['accessible']) && empty($aParams['personal'])) {
                $aOpenBlogs = \E::Module('Blog')->getAccessibleBlogsByUser(\E::User());
                if (count($aOpenBlogs)) {
                    $aFilter['blog_type']['*'] = $aOpenBlogs;
                }
            }
        }
        if (isset($aParams['personal']) && $aParams['personal'] === false && $aFilter['blog_type'] && is_array($aFilter['blog_type'])) {
            if (false !== ($iKey = array_search('personal', $aFilter['blog_type']))) {
                unset($aFilter['blog_type'][$iKey]);
            }
        }
        if (!empty($aParams['topic_type'])) {
            $aFilter['topic_type'] = $aParams['topic_type'];
        }
        if (!empty($aParams['user_id'])) {
            $aFilter['user_id'] = $aParams['user_id'];
        }
        if (isset($aParams['topic_published'])) {
            $aFilter['topic_publish'] = ($aParams['topic_published'] ? 1 : 0);
        }

        return $aFilter;
    }

    /**
     * Получает список хороших топиков для вывода на главную страницу (из всех блогов, как коллективных так и персональных)
     *
     * @param  int  $iPage          Номер страницы
     * @param  int  $iPerPage       Количество элементов на страницу
     * @param  bool $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                              из блогов доступных пользователю. При указании false,
     *                              в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsGood($iPage, $iPerPage, $bAddAccessible = true)
    {
        $aFilter = $this->getNamedFilter('good', ['accessible' => $bAddAccessible, 'rating' => \C::get('module.blog.index_good')]);
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает список новых топиков, ограничение новизны по дате из конфига
     *
     * @param  int  $iPage          Номер страницы
     * @param  int  $iPerPage       Количество элементов на страницу
     * @param  bool $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                              из блогов доступных пользователю. При указании false,
     *                              в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsNew($iPage, $iPerPage, $bAddAccessible = true)
    {
        $aFilter = $this->getNamedFilter('new', ['accessible' => $bAddAccessible]);
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает список ВСЕХ новых топиков
     *
     * @param  int  $iPage          Номер страницы
     * @param  int  $iPerPage       Количество элементов на страницу
     * @param  bool $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                              из блогов доступных пользователю. При указании false,
     *                              в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsNewAll($iPage, $iPerPage, $bAddAccessible = true) {

        $aFilter = $this->getNamedFilter('all', array('accessible' => $bAddAccessible));
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает список ВСЕХ обсуждаемых топиков
     *
     * @param  int        $iPage          Номер страницы
     * @param  int        $iPerPage       Количество элементов на страницу
     * @param  int|string $sPeriod        Период в виде секунд или конкретной даты
     * @param  bool       $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                                    из блогов доступных пользователю. При указании false,
     *                                    в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsDiscussed($iPage, $iPerPage, $sPeriod = null, $bAddAccessible = true) {

        $aFilter = $this->getNamedFilter('discussed', array('period' => $sPeriod, 'accessible' => $bAddAccessible));
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает список ВСЕХ рейтинговых топиков
     *
     * @param  int        $iPage          Номер страницы
     * @param  int        $iPerPage       Количество элементов на страницу
     * @param  int|string $sPeriod        Период в виде секунд или конкретной даты
     * @param  bool       $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                                    из блогов доступных пользователю. При указании false,
     *                                    в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsTop($iPage, $iPerPage, $sPeriod = null, $bAddAccessible = true) {

        $aFilter = $this->getNamedFilter('top', array('period' => $sPeriod, 'accessible' => $bAddAccessible));
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает заданое число последних топиков
     *
     * @param int $nCount    Количество
     *
     * @return array
     */
    public function getTopicsLast($nCount) {

        $aFilter = $this->getNamedFilter('default', array('accessible' => true));
        return $this->getTopicsByFilter($aFilter, 1, $nCount);
    }

    /**
     * список топиков из персональных блогов
     *
     * @param int        $iPage        Номер страницы
     * @param int        $iPerPage     Количество элементов на страницу
     * @param string     $sShowType    Тип выборки топиков
     * @param string|int $sPeriod      Период в виде секунд или конкретной даты
     *
     * @return array
     */
    public function getTopicsPersonal($iPage, $iPerPage, $sShowType = 'good', $sPeriod = null) {

        switch ($sShowType) {
            case 'good':
                $aFilter = $this->getNamedFilter('good', array('personal' => true, 'rating' => \C::get('module.blog.personal_good'), 'period' => $sPeriod));
                break;
            case 'bad':
                $aFilter = $this->getNamedFilter('bad', array('personal' => true, 'rating' => \C::get('module.blog.personal_good'), 'period' => $sPeriod));
                break;
            case 'new':
                $aFilter = $this->getNamedFilter('new', array('personal' => true, 'period' => $sPeriod));
                break;
            case 'all':
                $aFilter = $this->getNamedFilter('all', array('personal' => true, 'period' => $sPeriod));
                break;
            case 'discussed':
                $aFilter = $this->getNamedFilter('discussed', array('personal' => true, 'period' => $sPeriod));
                break;
            case 'top':
                $aFilter = $this->getNamedFilter('top', array('personal' => true, 'period' => $sPeriod));
                break;
            default:
                $aFilter = $this->getNamedFilter('default', array('personal' => true, 'period' => $sPeriod));
                break;
        }
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает число новых топиков в персональных блогах
     *
     * @return int
     */
    public function getCountTopicsPersonalNew() {

        $aFilter = $this->getNamedFilter('new', array('personal' => true));
        return $this->GetCountTopicsByFilter($aFilter);
    }

    /**
     * Получает список топиков по юзеру
     *
     * @param int|object $xUser Пользователь
     * @param int $bPublished   Флаг публикации топика
     * @param int $iPage        Номер страницы
     * @param int $iPerPage     Количество элементов на страницу
     *
     * @return array
     */
    public function getTopicsPersonalByUser($xUser, $bPublished, $iPage, $iPerPage) {

        $iUserId = (int)(is_object($xUser) ? $xUser->getId() : $xUser);
        $aFilter = $this->getNamedFilter('default', [
            'user_id' => $iUserId,
            'topic_published' => $bPublished,
        ]);

        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Возвращает количество топиков которые создал юзер
     *
     * @param int|object $xUser Пользователь
     * @param bool $bPublished  Флаг публикации топика
     *
     * @return array
     */
    public function getCountTopicsPersonalByUser($xUser, $bPublished) {

        $iUserId = (int)(is_object($xUser) ? $xUser->getId() : $xUser);
        $aFilter = $this->getNamedFilter('default', [
            'user_id' => $iUserId,
            'topic_published' => $bPublished,
        ]);

        $sCacheKey = 'topic_count_user_' . serialize($aFilter);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getCountTopics($aFilter);
            \E::Module('Cache')->set($data, $sCacheKey, ["topic_update_user_{$iUserId}"], 'P1D');
        }
        return $data;
    }

    /**
     * Получает список топиков из указанного блога
     *
     * @param  int|array $nBlogId       - ID блога | массив ID блогов
     * @param  int       $iPage         - Номер страницы
     * @param  int       $iPerPage      - Количество элементов на страницу
     * @param  array     $aAllowData    - Список типов данных для подгрузки в топики
     * @param  bool      $bIdsOnly      - Возвращать только ID или список объектов
     *
     * @return array
     */
    public function getTopicsByBlogId($nBlogId, $iPage = 0, $iPerPage = 0, $aAllowData = array(), $bIdsOnly = true) {

        $aFilter = array('blog_id' => $nBlogId);

        if (!$aTopics = $this->getTopicsByFilter($aFilter, $iPage, $iPerPage, $aAllowData)) {
            return false;
        }

        return ($bIdsOnly)
            ? array_keys($aTopics['collection'])
            : $aTopics;
    }

    /**
     * Список топиков из коллективных блогов
     *
     * @param int    $iPage        Номер страницы
     * @param int    $iPerPage     Количество элементов на страницу
     * @param string $sShowType    Тип выборки топиков
     * @param string $sPeriod      Период в виде секунд или конкретной даты
     *
     * @return array
     */
    public function getTopicsCollective($iPage, $iPerPage, $sShowType = 'good', $sPeriod = null) {

        switch ($sShowType) {
            case 'good':
                $aFilter = $this->getNamedFilter('good', array('accessible' => true, 'personal' => false, 'rating' => \C::get('module.blog.collective_good'), 'period' => $sPeriod));
                break;
            case 'bad':
                $aFilter = $this->getNamedFilter('bad', array('accessible' => true, 'personal' => false, 'rating' => \C::get('module.blog.collective_good'), 'period' => $sPeriod));
                break;
            case 'new':
                $aFilter = $this->getNamedFilter('new', array('accessible' => true, 'personal' => false, 'period' => $sPeriod));
                break;
            case 'all':
            case 'newall':
                $aFilter = $this->getNamedFilter('all', array('accessible' => true, 'personal' => false, 'period' => $sPeriod));
                break;
            case 'discussed':
                $aFilter = $this->getNamedFilter('discussed', array('accessible' => true, 'personal' => false, 'period' => $sPeriod));
                break;
            case 'top':
                $aFilter = $this->getNamedFilter('top', array('accessible' => true, 'personal' => false, 'period' => $sPeriod));
                break;
            default:
                $aFilter = $this->getNamedFilter('default', array('accessible' => true, 'personal' => false, 'period' => $sPeriod));
                break;
        }

        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает число новых топиков в коллективных блогах
     *
     * @return int
     */
    public function getCountTopicsCollectiveNew() {

        $aFilter = $this->getNamedFilter('new', array('accessible' => true, 'personal' => false));
        return $this->GetCountTopicsByFilter($aFilter);
    }

    /**
     * Получает топики по рейтингу и дате
     *
     * @param string $sDate     Дата
     * @param int    $iLimit    Количество
     *
     * @return array
     */
    public function getTopicsRatingByDate($sDate, $iLimit = 20) {
        /**
         * Получаем список блогов, топики которых нужно исключить из выдачи
         */
        $aCloseBlogs = ($this->oUserCurrent)
            ? \E::Module('Blog')->getInaccessibleBlogsByUser($this->oUserCurrent)
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        $sCacheKey = "topic_rating_{$sDate}_{$iLimit}_" . serialize($aCloseBlogs);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getTopicsRatingByDate($sDate, $iLimit, $aCloseBlogs);
            \E::Module('Cache')->set($data, $sCacheKey, array('topic_update'), 'P3D');
        }
        if ($data) {
            $data = $this->getTopicsAdditionalData($data);
        }
        return $data;
    }

    /**
     * Список топиков из блога
     *
     * @param ModuleBlog_EntityBlog $oBlog        Объект блога
     * @param int                   $iPage        Номер страницы
     * @param int                   $iPerPage     Количество элементов на страницу
     * @param string                $sShowType    Тип выборки топиков
     * @param string                $sPeriod      Период в виде секунд или конкретной даты
     *
     * @return array
     */
    public function getTopicsByBlog($oBlog, $iPage, $iPerPage, $sShowType = 'good', $sPeriod = null) {

        $iBlogId = (int)(is_object($oBlog) ? $oBlog->getId() : $oBlog);
        switch ($sShowType) {
            case 'good':
                $aFilter = $this->getNamedFilter('good', ['blog_id' => $iBlogId, 'rating' => \C::get('module.blog.collective_good'), 'period' => $sPeriod]);
                break;
            case 'bad':
                $aFilter = $this->getNamedFilter('bad', ['blog_id' => $iBlogId, 'rating' => \C::get('module.blog.collective_good'), 'period' => $sPeriod]);
                break;
            case 'new':
                $aFilter = $this->getNamedFilter('new', ['blog_id' => $iBlogId, 'period' => $sPeriod]);
                break;
            case 'all':
            case 'newall':
                $aFilter = $this->getNamedFilter('all', ['blog_id' => $iBlogId, 'period' => $sPeriod]);
                break;
            case 'discussed':
                $aFilter = $this->getNamedFilter('discussed', ['blog_id' => $iBlogId, 'period' => $sPeriod]);
                break;
            case 'top':
                $aFilter = $this->getNamedFilter('top', ['blog_id' => $iBlogId, 'period' => $sPeriod]);
                break;
            default:
                $aFilter = $this->getNamedFilter('default', ['blog_id' => $iBlogId, 'period' => $sPeriod]);
                break;
        }
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает число новых топиков из блога
     *
     * @param ModuleBlog_EntityBlog $oBlog Объект блога
     *
     * @return int
     */
    public function getCountTopicsByBlogNew($oBlog) {

        $iBlogId = (int)(is_object($oBlog) ? $oBlog->getId() : $oBlog);
        $aFilter = $this->getNamedFilter('new', ['blog_id' => $iBlogId]);

        return $this->GetCountTopicsByFilter($aFilter);
    }

    /**
     * Получает список топиков по тегу
     *
     * @param  string $sTag           Тег
     * @param  int    $iPage          Номер страницы
     * @param  int    $iPerPage       Количество элементов на страницу
     * @param  bool   $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                                из блогов доступных пользователю. При указании false,
     *                                в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsByTag($sTag, $iPage, $iPerPage, $bAddAccessible = true) {

        $aCloseBlogs = ($this->oUserCurrent && $bAddAccessible)
            ? \E::Module('Blog')->getInaccessibleBlogsByUser($this->oUserCurrent)
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        $sCacheKey = "topic_tag_{$sTag}_{$iPage}_{$iPerPage}_" . serialize($aCloseBlogs);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getTopicsByTag($sTag, $aCloseBlogs, $iCount, $iPage, $iPerPage),
                'count'      => $iCount
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('topic_update', 'topic_new'), 'P1D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getTopicsAdditionalData($data['collection']);
        }
        return $data;
    }

    /**
     * Получает список топиков по типам
     *
     * @param  int    $iPage          Номер страницы
     * @param  int    $iPerPage       Количество элементов на страницу
     * @param  string $sType
     * @param  bool   $bAddAccessible Указывает на необходимость добавить в выдачу топики,
     *                                из блогов доступных пользователю. При указании false,
     *                                в выдачу будут переданы только топики из общедоступных блогов.
     *
     * @return array
     */
    public function getTopicsByType($iPage, $iPerPage, $sType, $bAddAccessible = true) {

        $aFilter = $this->getNamedFilter('default', array('accessible' => $bAddAccessible, 'topic_type' => $sType));
        return $this->getTopicsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Получает список тегов топиков
     *
     * @param int   $nLimit           Количество
     * @param array $aExcludeTopic    Список ID топиков для исключения
     *
     * @return array
     */
    public function getTopicTags($nLimit, $aExcludeTopic = array()) {

        $sCacheKey = "tag_{$nLimit}_" . serialize($aExcludeTopic);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getTopicTags($nLimit, $aExcludeTopic);
            \E::Module('Cache')->set($data, $sCacheKey, array('topic_update', 'topic_new'), 'P1D');
        }
        return $data;
    }

    /**
     * Получает список тегов из топиков открытых блогов (open,personal)
     *
     * @param  int      $nLimit     - Количество
     * @param  int|null $nUserId    - ID пользователя, чью теги получаем
     *
     * @return array
     */
    public function getOpenTopicTags($nLimit, $nUserId = null)
    {
        $sCacheKey = "tag_{$nLimit}_{$nUserId}_open";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getOpenTopicTags($nLimit, $nUserId);
            \E::Module('Cache')->set($data, $sCacheKey, ['topic_update', 'topic_new'], 'P1D');
        }
        return $data;
    }

    /**
     * Увеличивает у топика число комментов
     *
     * @param int $nTopicId    ID топика
     *
     * @return bool
     */
    public function increaseTopicCountComment($nTopicId)
    {
        $bResult = $this->oMapper->increaseTopicCountComment($nTopicId);
        if ($bResult) {
            \E::Module('Cache')->delete("topic_{$nTopicId}");
            \E::Module('Cache')->cleanByTags(['topic_update']);
        }
        return $bResult;
    }

    /**
     * @param $nTopicId
     *
     * @return bool
     */
    public function recalcCountOfComments($nTopicId)
    {
        $bResult = $this->oMapper->recalcCountOfComments($nTopicId);
        if ($bResult) {
            \E::Module('Cache')->delete("topic_{$nTopicId}");
            \E::Module('Cache')->cleanByTags(['topic_update']);
        }
        return $bResult;
    }

    /**
     * Получает привязку топика к ибранному (добавлен ли топик в избранное у юзера)
     *
     * @param int $nTopicId    ID топика
     * @param int $nUserId     ID пользователя
     *
     * @return ModuleFavourite_EntityFavourite
     */
    public function getFavouriteTopic($nTopicId, $nUserId)
    {
        return \E::Module('Favourite')->getFavourite($nTopicId, 'topic', $nUserId);
    }

    /**
     * Получить список избранного по списку айдишников
     *
     * @param array $aTopicsId    Список ID топиков
     * @param int   $nUserId      ID пользователя
     *
     * @return ModuleFavourite_EntityFavourite[]
     */
    public function getFavouriteTopicsByArray($aTopicsId, $nUserId)
    {
        return \E::Module('Favourite')->getFavouritesByArray($aTopicsId, 'topic', $nUserId);
    }

    /**
     * Получить список избранного по списку айдишников, но используя единый кеш
     *
     * @param array $aTopicsId    Список ID топиков
     * @param int   $nUserId      ID пользователя
     *
     * @return array
     */
    public function getFavouriteTopicsByArraySolid($aTopicsId, $nUserId)
    {
        return \E::Module('Favourite')->getFavouritesByArraySolid($aTopicsId, 'topic', $nUserId);
    }

    /**
     * Добавляет топик в избранное
     *
     * @param ModuleFavourite_EntityFavourite $oFavouriteTopic    Объект избранного
     *
     * @return bool
     */
    public function addFavouriteTopic($oFavouriteTopic)
    {
        return \E::Module('Favourite')->AddFavourite($oFavouriteTopic);
    }

    /**
     * Удаляет топик из избранного
     *
     * @param ModuleFavourite_EntityFavourite $oFavouriteTopic    Объект избранного
     *
     * @return bool
     */
    public function deleteFavouriteTopic($oFavouriteTopic)
    {
        return \E::Module('Favourite')->DeleteFavourite($oFavouriteTopic);
    }

    /**
     * Устанавливает переданный параметр публикации таргета (топика)
     *
     * @param  int  $nTopicId    - ID топика
     * @param  bool $bPublish    - Флаг публикации топика
     *
     * @return bool
     */
    public function setFavouriteTopicPublish($nTopicId, $bPublish)
    {
        return \E::Module('Favourite')->setFavouriteTargetPublish($nTopicId, 'topic', $bPublish);
    }

    /**
     * Удаляет топики из избранного по списку
     *
     * @param  array $aTopicsId    Список ID топиков
     *
     * @return bool
     */
    public function deleteFavouriteTopicByArrayId($aTopicsId)
    {
        return \E::Module('Favourite')->deleteFavouriteByTargetId($aTopicsId, 'topic');
    }

    /**
     * Получает список тегов по первым буквам тега
     *
     * @param string $sTag      - Тэг
     * @param int    $nLimit    - Количество
     *
     * @return array
     */
    public function getTopicTagsByLike($sTag, $nLimit)
    {
        $sCacheKey = "tag_like_{$sTag}_{$nLimit}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getTopicTagsByLike($sTag, $nLimit);
            \E::Module('Cache')->set($data, $sCacheKey, ["topic_update", "topic_new"], 60 * 60 * 24 * 3);
        }
        return $data;
    }

    /**
     * Обновляем/устанавливаем дату прочтения топика, если читаем его первый раз то добавляем
     *
     * @param ModuleTopic_EntityTopicRead $oTopicRead    Объект факта чтения топика
     *
     * @return bool
     */
    public function setTopicRead($oTopicRead)
    {
        if ($this->getTopicRead($oTopicRead->getTopicId(), $oTopicRead->getUserId())) {
            return $this->updateTopicRead($oTopicRead);
        }
        return $this->addTopicRead($oTopicRead);
    }

    /**
     * @param ModuleTopic_EntityTopicRead $oTopicRead
     *
     * @return bool
     */
    public function addTopicRead($oTopicRead)
    {
        $xResult = $this->oMapper->addTopicRead($oTopicRead);
        \E::Module('Cache')->delete("topic_read_{$oTopicRead->getTopicId()}_{$oTopicRead->getUserId()}");
        \E::Module('Cache')->cleanByTags(["topic_read_user_{$oTopicRead->getUserId()}"]);

        return $xResult;
    }

    /**
     * @param ModuleTopic_EntityTopicRead $oTopicRead
     *
     * @return int
     */
    public function updateTopicRead($oTopicRead)
    {
        $xResult = $this->oMapper->updateTopicRead($oTopicRead);
        \E::Module('Cache')->delete("topic_read_{$oTopicRead->getTopicId()}_{$oTopicRead->getUserId()}");
        \E::Module('Cache')->cleanByTags(["topic_read_user_{$oTopicRead->getUserId()}"]);

        return $xResult;
    }

    /**
     * Получаем дату прочтения топика юзером
     *
     * @param int $iTopicId    - ID топика
     * @param int $iUserId     - ID пользователя
     *
     * @return ModuleTopic_EntityTopicRead|null
     */
    public function getTopicRead($iTopicId, $iUserId)
    {
        $data = $this->getTopicsReadByArray([$iTopicId], $iUserId);
        if (isset($data[$iTopicId])) {
            return $data[$iTopicId];
        }
        return null;
    }

    /**
     * Удаляет записи о чтении записей по списку идентификаторов
     *
     * @param  array|int $aTopicsId    Список ID топиков
     *
     * @return bool
     */
    public function deleteTopicReadByArrayId($aTopicsId)
    {
        if (!is_array($aTopicsId)) {
            $aTopicsId = [$aTopicsId];
        }
        return $this->oMapper->deleteTopicReadByArrayId($aTopicsId);
    }

    /**
     * Получить список просмотром/чтения топиков по списку айдишников
     *
     * @param array $aTopicsId    - Список ID топиков
     * @param int   $iUserId      - ID пользователя
     *
     * @return ModuleTopic_EntityTopicRead[]
     */
    public function getTopicsReadByArray($aTopicsId, $iUserId)
    {
        if (!$aTopicsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->GetTopicsReadByArraySolid($aTopicsId, $iUserId);
        }
        if (!is_array($aTopicsId)) {
            $aTopicsId = [$aTopicsId];
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopicsRead = [];
        $aTopicIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aTopicsId, 'topic_read_', '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                /** @var ModuleTopic_EntityTopicRead[] $data */
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aTopicsRead[$data[$sKey]->getTopicId()] = $data[$sKey];
                    } else {
                        $aTopicIdNotNeedQuery[] = $aTopicsId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких топиков не было в кеше и делаем запрос в БД
        $aTopicIdNeedQuery = array_diff($aTopicsId, array_keys($aTopicsRead));
        $aTopicIdNeedQuery = array_diff($aTopicIdNeedQuery, $aTopicIdNotNeedQuery);
        $aTopicIdNeedStore = $aTopicIdNeedQuery;

        if ($aTopicIdNeedQuery) {
            if ($data = $this->oMapper->getTopicsReadByArray($aTopicIdNeedQuery, $iUserId)) {
                /** @var ModuleTopic_EntityTopicRead $oTopicRead */
                foreach ($data as $oTopicRead) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aTopicsRead[$oTopicRead->getTopicId()] = $oTopicRead;
                    \E::Module('Cache')->set($oTopicRead, "topic_read_{$oTopicRead->getTopicId()}_{$oTopicRead->getUserId()}", [], 'P4D');
                    $aTopicIdNeedStore = array_diff($aTopicIdNeedStore, array($oTopicRead->getTopicId()));
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aTopicIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "topic_read_{$sId}_{$iUserId}", [], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aTopicsRead = F::Array_SortByKeysArray($aTopicsRead, $aTopicsId);

        return $aTopicsRead;
    }

    /**
     * Получить список просмотров/чтения топиков по списку ID, но используя единый кеш
     *
     * @param array $aTopicsId    - Список ID топиков
     * @param int   $nUserId      - ID пользователя
     *
     * @return array
     */
    public function getTopicsReadByArraySolid($aTopicsId, $nUserId)
    {
        if (!is_array($aTopicsId)) {
            $aTopicsId = [$aTopicsId];
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopicsRead = [];

        $sCacheKey = "topic_read_{$nUserId}_id_" . implode(',', $aTopicsId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getTopicsReadByArray($aTopicsId, $nUserId);
            /** @var ModuleTopic_EntityTopicRead $oTopicRead */
            foreach ($data as $oTopicRead) {
                $aTopicsRead[$oTopicRead->getTopicId()] = $oTopicRead;
            }
            \E::Module('Cache')->set($aTopicsRead, $sCacheKey, ["topic_read_user_{$nUserId}"], 'P1D');
            return $aTopicsRead;
        }
        return $data;
    }

    /**
     * Возвращает список полей по списку ID топиков
     *
     * @param array $aTopicId    Список ID топиков
     *
     * @return array
     * @TODO рефакторинг + solid
     */
    public function getTopicValuesByArrayId($aTopicId)
    {
        if (!$aTopicId) {
            return [];
        }
        if (!is_array($aTopicId)) {
            $aTopicId = [$aTopicId];
        }
        $aTopicId = array_unique($aTopicId);
        $aValues = [];
        $s = implode(',', $aTopicId);
        if (false === ($data = \E::Module('Cache')->get("topic_values_{$s}"))) {
            $data = $this->oMapper->getTopicValuesByArrayId($aTopicId);
            foreach ($data as $oValue) {
                $aValues[$oValue->getTargetId()][$oValue->getFieldId()] = $oValue;
            }
            \E::Module('Cache')->set($aValues, "topic_values_{$s}", ['topic_new', 'topic_update'], 'P1D');
            return $aValues;
        }
        return $data;
    }

    /**
     * Возвращает список полей по списку id типов контента
     *
     * @param array $aTypesId    Список ID типов контента
     *
     * @return array
     * @TODO рефакторинг + solid
     */
    public function getFieldsByArrayId($aTypesId)
    {
        if (!$aTypesId) {
            return [];
        }
        if (!is_array($aTypesId)) {
            $aTypesId = [$aTypesId];
        }
        $aTypesId = array_unique($aTypesId);
        $aFields = [];
        $s = implode(',', $aTypesId);
        if (false === ($data = \E::Module('Cache')->get("topic_fields_{$s}"))) {
            $data = $this->oMapper->getFieldsByArrayId($aTypesId);
            foreach ($data as $oField) {
                $aFields[$oField->getContentId()][$oField->getFieldId()] = $oField;
            }
            \E::Module('Cache')->set($aFields, "topic_fields_{$s}", ["field_update"], 'P1D');
            return $aFields;
        }
        return $data;
    }

    /**
     * Проверяет голосовал ли юзер за топик-вопрос
     *
     * @param int $nTopicId    ID топика
     * @param int $nUserId     ID пользователя
     *
     * @return ModuleTopic_EntityTopicQuestionVote|null
     */
    public function getTopicQuestionVote($nTopicId, $nUserId)
    {
        $data = $this->getTopicsQuestionVoteByArray([$nTopicId], $nUserId);
        if (isset($data[$nTopicId])) {
            return $data[$nTopicId];
        }
        return null;
    }

    /**
     * Получить список голосований в топике-опросе по списку ID
     *
     * @param array $aTopicsId    - Список ID топиков
     * @param int   $iUserId      - ID пользователя
     *
     * @return array
     */
    public function getTopicsQuestionVoteByArray($aTopicsId, $iUserId)
    {
        if (!$aTopicsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->GetTopicsQuestionVoteByArraySolid($aTopicsId, $iUserId);
        }
        if (!is_array($aTopicsId)) {
            $aTopicsId = [$aTopicsId];
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopicsQuestionVote = [];
        $aTopicIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aTopicsId, 'topic_question_vote_', '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                /** @var ModuleTopic_EntityTopicQuestionVote[] $data */
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aTopicsQuestionVote[$data[$sKey]->getTopicId()] = $data[$sKey];
                    } else {
                        $aTopicIdNotNeedQuery[] = $aTopicsId[$iIndex];
                    }
                }
            }
        }

        // * Смотрим каких топиков не было в кеше и делаем запрос в БД
        $aTopicIdNeedQuery = array_diff($aTopicsId, array_keys($aTopicsQuestionVote));
        $aTopicIdNeedQuery = array_diff($aTopicIdNeedQuery, $aTopicIdNotNeedQuery);
        $aTopicIdNeedStore = $aTopicIdNeedQuery;

        if ($aTopicIdNeedQuery) {
            if ($data = $this->oMapper->getTopicsQuestionVoteByArray($aTopicIdNeedQuery, $iUserId)) {
                foreach ($data as $oTopicVote) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aTopicsQuestionVote[$oTopicVote->getTopicId()] = $oTopicVote;
                    \E::Module('Cache')->set(
                        $oTopicVote, "topic_question_vote_{$oTopicVote->getTopicId()}_{$oTopicVote->getVoterId()}", [],
                        'P4D'
                    );
                    $aTopicIdNeedStore = array_diff($aTopicIdNeedStore, [$oTopicVote->getTopicId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aTopicIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "topic_question_vote_{$sId}_{$iUserId}", [], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aTopicsQuestionVote = F::Array_SortByKeysArray($aTopicsQuestionVote, $aTopicsId);

        return $aTopicsQuestionVote;
    }

    /**
     * Получить список голосований в топике-опросе по списку ID, но используя единый кеш
     *
     * @param array $aTopicsId    - Список ID топиков
     * @param int   $nUserId      - ID пользователя
     *
     * @return array
     */
    public function getTopicsQuestionVoteByArraySolid($aTopicsId, $nUserId)
    {
        if (!is_array($aTopicsId)) {
            $aTopicsId = [$aTopicsId];
        }
        $aTopicsId = array_unique($aTopicsId);
        $aTopicsQuestionVote = [];

        $sCacheKey = "topic_question_vote_{$nUserId}_id_" . implode(',', $aTopicsId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getTopicsQuestionVoteByArray($aTopicsId, $nUserId);
            foreach ($data as $oTopicVote) {
                $aTopicsQuestionVote[$oTopicVote->getTopicId()] = $oTopicVote;
            }
            \E::Module('Cache')->set($aTopicsQuestionVote, $sCacheKey, ["topic_question_vote_user_{$nUserId}"], 'P1D');
            return $aTopicsQuestionVote;
        }
        return $data;
    }

    /**
     * Добавляет факт голосования за топик-вопрос
     *
     * @param ModuleTopic_EntityTopicQuestionVote $oTopicQuestionVote    Объект голосования в топике-опросе
     *
     * @return bool
     */
    public function addTopicQuestionVote($oTopicQuestionVote)
    {
        $xResult = $this->oMapper->addTopicQuestionVote($oTopicQuestionVote);
        \E::Module('Cache')->delete(
            "topic_question_vote_{$oTopicQuestionVote->getTopicId()}_{$oTopicQuestionVote->getVoterId()}"
        );
        \E::Module('Cache')->cleanByTags(["topic_question_vote_user_{$oTopicQuestionVote->getVoterId()}"]);

        return $xResult;
    }

    /**
     * Получает топик по уникальному хешу(текст топика)
     *
     * @param int    - $nUserId
     * @param string - $sHash
     *
     * @return ModuleTopic_EntityTopic|null
     */
    public function getTopicUnique($nUserId, $sHash)
    {
        $sId = $this->oMapper->getTopicUnique($nUserId, $sHash);

        return $this->getTopicById($sId);
    }

    /**
     * Рассылает уведомления о новом топике подписчикам блога
     *
     * @param ModuleBlog_EntityBlog   $oBlog         Объект блога
     * @param ModuleTopic_EntityTopic $oTopic        Объект топика
     * @param ModuleUser_EntityUser   $oUserTopic    Объект пользователя
     */
    public function sendNotifyTopicNew($oBlog, $oTopic, $oUserTopic)
    {
        $aBlogUsersResult = \E::Module('Blog')->getBlogUsersByBlogId($oBlog->getId(), null, null);
        // нужно постранично пробегаться по всем
        /** @var ModuleBlog_EntityBlogUser[] $aBlogUsers */
        $aBlogUsers = $aBlogUsersResult['collection'];
        foreach ($aBlogUsers as $oBlogUser) {
            if ($oBlogUser->getUserId() == $oUserTopic->getId()) {
                continue;
            }
            \E::Module('Notify')->sendTopicNewToSubscribeBlog($oBlogUser->getUser(), $oTopic, $oBlog, $oUserTopic);
        }
        //отправляем создателю блога
        if ($oBlog->getOwnerId() != $oUserTopic->getId()) {
            \E::Module('Notify')->sendTopicNewToSubscribeBlog($oBlog->getOwner(), $oTopic, $oBlog, $oUserTopic);
        }
    }

    /**
     * Возвращает список последних топиков пользователя, опубликованных не более чем $iTimeLimit секунд назад
     *
     * @param  int   $nUserId        ID пользователя
     * @param  int   $iTimeLimit     Число секунд
     * @param  int   $iCountLimit    Количество
     * @param  array $aAllowData     Список типов данных для подгрузки в топики
     *
     * @return array
     */
    public function getLastTopicsByUserId($nUserId, $iTimeLimit, $iCountLimit = 1, $aAllowData = [])
    {
        $aFilter = [
            'topic_publish' => 1,
            'user_id'       => $nUserId,
            'topic_new'     => date('Y-m-d H:i:s', time() - $iTimeLimit),
        ];
        $aTopics = $this->getTopicsByFilter($aFilter, 1, $iCountLimit, $aAllowData);

        return $aTopics;
    }

    /**
     * Перемещает топики в другой блог
     *
     * @param  array $aTopicsId - Список ID топиков
     * @param  int   $iBlogId   - ID блога
     *
     * @return bool
     */
    public function moveTopicsByArrayId($aTopicsId, $iBlogId)
    {
        if ($aResult = $this->oMapper->moveTopicsByArrayId($aTopicsId, $iBlogId)) {
            \E::Module('Cache')->cleanByTags(['topic_update', "blog_update_{$iBlogId}"]);
            // перемещаем теги
            $this->oMapper->moveTopicsTagsByArrayId($aTopicsId, $iBlogId);
            // меняем target parent у комментов
            \E::Module('Comment')->updateTargetParentByTargetId($iBlogId, 'topic', $aTopicsId);
            // меняем target parent у комментов в прямом эфире
            \E::Module('Comment')->updateTargetParentByTargetIdOnline($iBlogId, 'topic', $aTopicsId);
            return $aResult;
        }
        return false;
    }

    /**
     * Перемещает топики в другой блог
     *
     * @param  int $iOldBlogId ID старого блога
     * @param  int $iNewBlogId ID нового блога
     *
     * @return bool
     */
    public function moveTopics($iOldBlogId, $iNewBlogId)
    {
        if ($bResult = $this->oMapper->moveTopics($iOldBlogId, $iNewBlogId)) {
            // перемещаем теги
            $this->oMapper->moveTopicsTags($iOldBlogId, $iNewBlogId);
            // меняем target parent у комментов
            \E::Module('Comment')->moveTargetParent($iOldBlogId, 'topic', $iNewBlogId);
            // меняем target parent у комментов в прямом эфире
            \E::Module('Comment')->moveTargetParentOnline($iOldBlogId, 'topic', $iNewBlogId);
            return $bResult;
        }
        \E::Module('Cache')->cleanByTags(['topic_update', 'blog_update', "blog_update_{$iOldBlogId}", "blog_update_{$iNewBlogId}"]);
        \E::Module('Cache')->delete("blog_{$iOldBlogId}");
        \E::Module('Cache')->delete("blog_{$iNewBlogId}");

        return false;
    }

    /**
     * Save uploaded image into store
     *
     * @param string                $sImageFile
     * @param ModuleUser_EntityUser $oUser
     * @param string                $sType
     * @param array                 $aOptions
     *
     * @return bool
     */
    protected function _saveTopicImage($sImageFile, $oUser, $sType, $aOptions = [])
    {
        $sExtension = F::File_GetExtension($sImageFile, true);
        $aConfig = \E::Module('Uploader')->getConfig($sImageFile, 'images.' . $sType);
        if ($aOptions) {
            $aConfig['transform'] = F::Array_Merge($aConfig['transform'], $aOptions);
        }
        // Check whether to save the original
        if (isset($aConfig['original']['save']) && $aConfig['original']['save']) {
            $sSuffix = (isset($aConfig['original']['suffix']) ? $aConfig['original']['suffix'] : '-original');
            $sOriginalFile = F::File_Copy($sImageFile, $sImageFile . $sSuffix . '.' . $sExtension);
        } else {
            $sSuffix = '';
            $sOriginalFile = null;
        }
        // Transform image before saving
        $sFileTmp = \E::Module('Img')->transformFile($sImageFile, $aConfig['transform']);
        if ($sFileTmp) {
            $sDirUpload = \E::Module('Uploader')->getUserImageDir($oUser->getId(), true, $sType);
            $sFileImage = \E::Module('Uploader')->Uniqname($sDirUpload, $sExtension);
            if ($oStoredFile = \E::Module('Uploader')->Store($sFileTmp, $sFileImage)) {
                if ($sOriginalFile) {
                    \E::Module('Uploader')->move($sOriginalFile, $oStoredFile->GetFile() . $sSuffix . '.' . $sExtension);
                }
                return $oStoredFile->GetUrl();
            }
        }
        return false;
    }

    /**
     * @param array                 $aFile
     * @param ModuleUser_EntityUser $oUser
     * @param array                 $aOptions
     *
     * @return string|bool
     */
    public function uploadTopicImageFile($aFile, $oUser, $aOptions = [])
    {
        if ($sFileTmp = \E::Module('Uploader')->UploadLocal($aFile)) {
            return $this->_saveTopicImage($sFileTmp, $oUser, 'topic', $aOptions);
        }
        return false;
    }

    /**
     * Загрузка изображений по переданному URL
     *
     * @param  string                $sUrl    URL изображения
     * @param  ModuleUser_EntityUser $oUser
     * @param array                 $aOptions
     *
     * @return string|int
     */
    public function uploadTopicImageUrl($sUrl, $oUser, $aOptions = [])
    {
        if ($sFileTmp = \E::Module('Uploader')->uploadRemote($sUrl)) {
            return $this->_saveTopicImage($sFileTmp, $oUser, 'topic', $aOptions);
        }
        return false;
    }

    /**
     * Возвращает список фотографий к топику-фотосет по списку ID фоток
     *
     * @param array|int $aPhotosId    Список ID фото
     *
     * @return array
     */
    public function getTopicPhotosByArrayId($aPhotosId)
    {
        if (!$aPhotosId) {
            return [];
        }
        if (!is_array($aPhotosId)) {
            $aPhotosId = [$aPhotosId];
        }
        $aPhotosId = array_unique($aPhotosId);
        $aPhotos = [];
        $s = implode(',', $aPhotosId);
        if (false === ($data = \E::Module('Cache')->get("photoset_photo_id_{$s}"))) {
            $data = $this->oMapper->getTopicPhotosByArrayId($aPhotosId);
            foreach ($data as $oPhoto) {
                $aPhotos[$oPhoto->getId()] = $oPhoto;
            }
            \E::Module('Cache')->set($aPhotos, "photoset_photo_id_{$s}", ["photoset_photo_update"], 'P1D');
            return $aPhotos;
        }
        return $data;
    }

    /**
     * Добавить к топику изображение
     *
     * @param ModuleTopic_EntityTopicPhoto $oPhoto    Объект фото к топику-фотосету
     *
     * @return ModuleTopic_EntityTopicPhoto|bool
     */
    public function addTopicPhoto($oPhoto)
    {
        if ($nId = $this->oMapper->addTopicPhoto($oPhoto)) {
            $oPhoto->setId($nId);
            \E::Module('Cache')->cleanByTags(['photoset_photo_update']);
            return $oPhoto;
        }
        return false;
    }

    /**
     * Получить изображение из фотосета по его ID
     *
     * @param int $iPhotoId    ID фото
     *
     * @return ModuleTopic_EntityTopicPhoto|null
     */
    public function getTopicPhotoById($iPhotoId)
    {
        $aPhotos = $this->getTopicPhotosByArrayId($iPhotoId);
        if (isset($aPhotos[$iPhotoId])) {
            return $aPhotos[$iPhotoId];
        }
        return null;
    }

    /**
     * Получить список изображений из фотосета по ID топика
     *
     * @param int|array $aTopicId - ID топика
     * @param int       $iFromId  - ID с которого начинать выборку
     * @param int       $iCount   - Количество
     *
     * @return array
     */
    public function getPhotosByTopicId($aTopicId, $iFromId = null, $iCount = null)
    {
        return $this->oMapper->getPhotosByTopicId($aTopicId, $iFromId, $iCount);
    }

    /**
     * Получить список изображений из фотосета по временному коду
     *
     * @param string $sTargetTmp    Временный ключ
     *
     * @return array
     */
    public function getPhotosByTargetTmp($sTargetTmp)
    {
        return $this->oMapper->getPhotosByTargetTmp($sTargetTmp);
    }

    /**
     * Получить число изображений из фотосета по id топика
     *
     * @param int $nTopicId - ID топика
     *
     * @return int
     */
    public function getCountPhotosByTopicId($nTopicId)
    {
        return $this->oMapper->getCountPhotosByTopicId($nTopicId);
    }

    /**
     * Получить число изображений из фотосета по id топика
     *
     * @param string $sTargetTmp - Временный ключ
     *
     * @return int
     */
    public function getCountPhotosByTargetTmp($sTargetTmp)
    {
        return $this->oMapper->getCountPhotosByTargetTmp($sTargetTmp);
    }

    /**
     * Обновить данные по изображению
     *
     * @param ModuleTopic_EntityTopicPhoto $oPhoto Объект фото
     */
    public function updateTopicPhoto($oPhoto)
    {
        \E::Module('Cache')->cleanByTags(['photoset_photo_update']);
        $this->oMapper->updateTopicPhoto($oPhoto);
    }

    /**
     * Удалить изображение
     *
     * @param ModuleTopic_EntityTopicPhoto $oPhoto - Объект фото
     */
    public function deleteTopicPhoto($oPhoto)
    {
        $this->oMapper->deleteTopicPhoto($oPhoto->getId());

        $sFile = \E::Module('Uploader')->Url2Dir($oPhoto->getPath());
        \E::Module('Img')->delete($sFile);
        \E::Module('Cache')->cleanByTags(['photoset_photo_update']);
    }

    /**
     * Загрузить изображение
     *
     * @param array $aFile - Элемент массива $_FILES
     *
     * @return string|bool
     */
    public function uploadTopicPhoto($aFile)
    {
        if ($sFileTmp = \E::Module('Uploader')->uploadLocal($aFile)) {
            return $this->_saveTopicImage($sFileTmp, $this->oUserCurrent, 'photoset');
        }
        return false;
    }

    /**
     * Returns upload error
     *
     * @return mixed
     */
    public function uploadPhotoError()
    {
        return \E::Module('Uploader')->getErrorMsg();
    }

    /**
     * Пересчитывает счетчик избранных топиков
     *
     * @return bool
     */
    public function recalculateFavourite()
    {
        return $this->oMapper->recalculateFavourite();
    }

    /**
     * Пересчитывает счетчики голосований
     *
     * @return bool
     */
    public function recalculateVote()
    {
        return $this->oMapper->recalculateVote();
    }

    /**
     * Алиас для корректной работы ORM
     *
     * @param array $aTopicId - Список ID топиков
     *
     * @return array|int
     */
    public function getTopicItemsByArrayId($aTopicId)
    {
        return $this->getTopicsByArrayId($aTopicId);
    }

    /**
     * Порционная отдача файла
     *
     * @param $sFilename
     *
     * @return bool
     */
    public function readfileChunked($sFilename)
    {
        F::File_PrintChunked($sFilename);
    }

    /**
     * Обработка дополнительных полей топика
     *
     * @param ModuleTopic_EntityTopic $oTopic
     * @param string $sType
     *
     * @return bool
     */
    public function processTopicFields($oTopic, $sType = 'add')
    {
        /** @var ModuleTopic_EntityContentValues $aValues */
        $aValues = [];

        if ($sType === 'update') {
            // * Получаем существующие значения
            if ($aData = $this->GetTopicValuesByArrayId(array($oTopic->getId()))) {
                $aValues = $aData[$oTopic->getId()];
            }
            // * Чистим существующие значения
            \E::Module('Topic')->DeleteTopicValuesByTopicId($oTopic->getId());
        }

        if ($oType = \E::Module('Topic')->getContentTypeByUrl($oTopic->getType())) {

            //получаем поля для данного типа
            if ($aFields = $oType->getFields()) {
                foreach ($aFields as $oField) {
                    $sData = null;
                    if (isset($_REQUEST['fields'][$oField->getFieldId()]) || isset($_FILES['fields_' . $oField->getFieldId()]) || $oField->getFieldType() == 'single-image-uploader') {

                        //текстовые поля
                        if (in_array($oField->getFieldType(), array('input', 'textarea', 'select'))) {
                            $sData = \E::Module('Text')->parse($_REQUEST['fields'][$oField->getFieldId()]);
                        }
                        //поле ссылки
                        if ($oField->getFieldType() === 'link') {
                            $sData = $_REQUEST['fields'][$oField->getFieldId()];
                        }

                        //поле даты
                        if ($oField->getFieldType() === 'date') {
                            if (isset($_REQUEST['fields'][$oField->getFieldId()])) {

                                if (\F::CheckVal($_REQUEST['fields'][$oField->getFieldId()], 'text', 6, 10)
                                    && substr_count($_REQUEST['fields'][$oField->getFieldId()], '.') == 2
                                ) {
                                    list($d, $m, $y) = explode('.', $_REQUEST['fields'][$oField->getFieldId()]);
                                    if (@checkdate($m, $d, $y)) {
                                        $sData = $_REQUEST['fields'][$oField->getFieldId()];
                                    }
                                }
                            }
                        }

                        //поле с файлом
                        if ($oField->getFieldType() === 'file') {
                            //если указано удаление файла
                            if (\F::getRequest('topic_delete_file_' . $oField->getFieldId())) {
                                if ($oTopic->getFieldFile($oField->getFieldId())) {
                                    @unlink(\C::get('path.root.dir') . $oTopic->getFieldFile($oField->getFieldId())->getFileUrl());
                                    //$oTopic->setValueField($oField->getFieldId(),'');
                                    $sData = null;
                                }
                            } else {
                                //если удаление файла не указано, уже ранее залит файл^ и нового файла не загружалось
                                if ($sType === 'update' && isset($aValues[$oField->getFieldId()])) {
                                    $sData = $aValues[$oField->getFieldId()]->getValueSource();
                                }
                            }

                            if (isset($_FILES['fields_' . $oField->getFieldId()]) && is_uploaded_file( $_FILES['fields_' . $oField->getFieldId()]['tmp_name'])) {
                                $iMaxFileSize = F::MemSize2Int(\C::get('module.uploader.files.default.file_maxsize'));
                                $aFileExtensions = \C::get('module.uploader.files.default.file_extensions');
                                if (!$iMaxFileSize || filesize($_FILES['fields_' . $oField->getFieldId()]['tmp_name']) <= $iMaxFileSize) {
                                    $aPathInfo = pathinfo($_FILES['fields_' . $oField->getFieldId()]['name']);

                                    if (!$aFileExtensions || in_array(strtolower($aPathInfo['extension']), $aFileExtensions)) {
                                        $sFileTmp = $_FILES['fields_' . $oField->getFieldId()]['tmp_name'];
                                        $sDirSave = \C::get('path.uploads.root') . '/files/' . \E::User()->getId() . '/' . F::RandomStr(16);
                                        if (\F::File_CheckDir(\C::get('path.root.dir') . $sDirSave)) {

                                            $sFile = $sDirSave . '/' . F::RandomStr(10) . '.' . strtolower($aPathInfo['extension']);
                                            $sFileFullPath = \C::get('path.root.dir') . $sFile;
                                            if (copy($sFileTmp, $sFileFullPath)) {
                                                //удаляем старый файл
                                                if ($oTopic->getFieldFile($oField->getFieldId())) {
                                                    $sOldFile = \C::get('path.root.dir') . $oTopic->getFieldFile($oField->getFieldId())->getFileUrl();
                                                    F::File_Delete($sOldFile);
                                                }

                                                $aFileObj = [];
                                                $aFileObj['file_hash'] = F::RandomStr(32);
                                                $aFileObj['file_name'] = \E::Module('Text')->parse($_FILES['fields_' . $oField->getFieldId()]['name']);
                                                $aFileObj['file_url'] = $sFile;
                                                $aFileObj['file_size'] = $_FILES['fields_' . $oField->getFieldId()]['size'];
                                                $aFileObj['file_extension'] = $aPathInfo['extension'];
                                                $aFileObj['file_downloads'] = 0;
                                                $sData = serialize($aFileObj);

                                                F::File_Delete($sFileTmp);
                                            }
                                        }
                                    } else {
                                        $sTypes = implode(', ', $aFileExtensions);
                                        \E::Module('Message')->addError(\E::Module('Lang')->get('topic_field_file_upload_err_type', array('types' => $sTypes)), null, true);
                                    }
                                } else {
                                    \E::Module('Message')->addError(\E::Module('Lang')->get('topic_field_file_upload_err_size', array('size' => $iMaxFileSize)), null, true);
                                }
                                F::File_Delete($_FILES['fields_' . $oField->getFieldId()]['tmp_name']);
                            }
                        }

                        // Поле с изображением
                        if ($oField->getFieldType() === 'single-image-uploader') {
                            $sTargetType = $oField->getFieldType(). '-' . $oField->getFieldId();
                            $iTargetId = $oTopic->getId();

                            // 1. Удалить значение target_tmp
                            // Нужно затереть временный ключ в ресурсах, что бы в дальнейшем картнка не
                            // воспринималась как временная.
                            if ($sTargetTmp = \E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP)) {
                                // 2. Удалить куку.
                                // Если прозошло сохранение вновь созданного топика, то нужно
                                // удалить куку временной картинки. Если же сохранялся уже существующий топик,
                                // то удаление куки ни на что влиять не будет.
                                \E::Module('Session')->delCookie(ModuleUploader::COOKIE_TARGET_TMP);

                                // 3. Переместить фото

                                $sNewPath = \E::Module('Uploader')->getUserImageDir(\E::userId(), true, false);
                                $aMresourceRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, 0, E::userId());

                                if ($aMresourceRel) {
                                    $oResource = array_shift($aMresourceRel);
                                    $sOldPath = $oResource->GetFile();

                                    $oStoredFile = \E::Module('Uploader')->Store($sOldPath, $sNewPath);
                                    /** @var ModuleMedia_EntityMedia $oResource */
                                    $oResource = \E::Module('Media')->getMresourcesByUuid($oStoredFile->getUuid());
                                    if ($oResource) {
                                        $oResource->setUrl(\E::Module('Media')->normalizeUrl(\E::Module('Uploader')->getTargetUrl($sTargetType, $iTargetId)));
                                        $oResource->setType($sTargetType);
                                        $oResource->setUserId(\E::userId());
                                        // 4. В свойство поля записать адрес картинки
                                        $sData = $oResource->getMediaId();
                                        $oResource = array($oResource);
                                        \E::Module('Media')->UnlinkFile($sTargetType, 0, $oTopic->getUserId());
                                        \E::Module('Media')->AddTargetRel($oResource, $sTargetType, $iTargetId);
                                    }
                                }
                            } else {
                                // Топик редактируется, просто обновим поле
                                $aMresourceRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, $iTargetId, E::userId());
                                if ($aMresourceRel) {
                                    $oResource = array_shift($aMresourceRel);
                                    $sData = $oResource->getMresourceId();
                                } else {
                                    $sData = false;
//                                    $this->deleteField($oField);
                                }
                            }


                        }

                        \HookManager::run('content_field_proccess', array('sData' => &$sData, 'oField' => $oField, 'oTopic' => $oTopic, 'aValues' => $aValues, 'sType' => &$sType));

                        //Добавляем поле к топику.
                        if ($sData) {
                            /** @var ModuleTopic_EntityContentValues $oValue */
                            $oValue = \E::getEntity('Topic_ContentValues');
                            $oValue->setTargetId($oTopic->getId());
                            $oValue->setTargetType('topic');
                            $oValue->setFieldId($oField->getFieldId());
                            $oValue->setFieldType($oField->getFieldType());
                            $oValue->setValue($sData);
                            $oValue->setValueSource(in_array($oField->getFieldType(), array('file', 'single-image-uploader'))
                                ? $sData
                                : $_REQUEST['fields'][$oField->getFieldId()]);

                            $this->AddTopicValue($oValue);

                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param ModuleTopic_EntityTopic $oTopic
     *
     * @return bool
     */
    public function updateMresources($oTopic)
    {
        return $this->attachTmpPhotoToTopic($oTopic);
    }

    /**
     * Delete MResources associated with topic(s)
     *
     * @param ModuleTopic_EntityTopic[]|ModuleTopic_EntityTopic $aTopics
     */
    public function deleteMresources($aTopics)
    {
        if (!is_array($aTopics)) {
            $aTopics = [$aTopics];
        }
        /** @var ModuleTopic_EntityTopic $oTopic */
        foreach ($aTopics as $oTopic) {
            \E::Module('Media')->DeleteMresourcesRelByTarget('topic', $oTopic->GetId());
        }
    }

    /**
     * @param ModuleTopic_EntityTopic $oTopic
     * @param string $sTargetTmp
     *
     * @return bool
     */
    public function attachTmpPhotoToTopic($oTopic, $sTargetTmp = null)
    {
        if (null === $sTargetTmp) {
            $sTargetTmp = \E::Module('Session')->getCookie(ModuleUploader::COOKIE_TARGET_TMP);
        }

        \E::Module('Media')->resetTmpRelById($sTargetTmp, $oTopic->getId());
        return $this->oMapper->attachTmpPhotoToTopic($oTopic, $sTargetTmp);
    }

}

// EOF