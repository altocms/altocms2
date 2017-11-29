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
 * Модуль для работы с блогами
 *
 * @package modules.blog
 * @since   1.0
 */
class ModuleBlog extends Module
{
    //  Возможные роли пользователя в блоге
    const BLOG_USER_ROLE_GUEST = 0;
    const BLOG_USER_ROLE_MEMBER = 1;
    const BLOG_USER_ROLE_MODERATOR = 2;
    const BLOG_USER_ROLE_ADMINISTRATOR = 4;
    const BLOG_USER_ROLE_OWNER = 8;
    const BLOG_USER_ROLE_NOTMEMBER = 16;
    const BLOG_USER_ROLE_BAN_FOR_COMMENT = 32;
    const BLOG_USER_ROLE_AUTHOR = 64;

    // BLOG_USER_ROLE_MEMBER | BLOG_USER_ROLE_MODERATOR | BLOG_USER_ROLE_ADMINISTRATOR | BLOG_USER_ROLE_OWNER | BLOG_USER_ROLE_AUTHOR
    const BLOG_USER_ROLE_SUBSCRIBER = 79;

    const BLOG_USER_JOIN_NONE = 0;
    const BLOG_USER_JOIN_FREE = 1;
    const BLOG_USER_JOIN_REQUEST = 2;
    const BLOG_USER_JOIN_INVITE = 4;

    const BLOG_USER_ACL_GUEST = 1;
    const BLOG_USER_ACL_USER = 2;
    const BLOG_USER_ACL_MEMBER = 4;

    //  Пользователь, приглашенный админом блога в блог
    const BLOG_USER_ROLE_INVITE = -1;

    //  Пользователь, отклонивший приглашение админа
    const BLOG_USER_ROLE_REJECT = -2;

    //  Забаненный в блоге пользователь
    const BLOG_USER_ROLE_BAN = -4;

    //  User sent request for subscribe to blog
    const BLOG_USER_ROLE_WISHES = -6;

    const BLOG_SORT_TITLE = 1;
    const BLOG_SORT_TITLE_PERSONAL = 2;

    /**
     * Объект маппера
     *
     * @var ModuleBlog_MapperBlog
     */
    protected $oMapper;

    /**
     * Объект текущего пользователя
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    protected $aAdditionalData = ['vote', 'owner' => [], 'relation_user', 'media'];

    protected $aBlogsFilter = ['exclude_type' => 'personal'];


    /**
     * Инициализация
     *
     */
    public function init()
    {
        $this->oMapper = \E::getMapper(__CLASS__);
        $this->oUserCurrent = \E::User();
    }

    /**
     * Спавнение по наименованию
     *
     * @param ModuleBlog_EntityBlog $oBlog1
     * @param ModuleBlog_EntityBlog $oBlog2
     *
     * @return int
     */
    public function _compareByTitle($oBlog1, $oBlog2)
    {
        return strcasecmp($oBlog1->getTitle(), $oBlog2->getTitle());
    }

    /**
     * Сортировка блогов
     *
     * @param array $aBlogList
     */
    protected function _sortByTitle(&$aBlogList)
    {
        uasort($aBlogList, [$this, '_compareByTitle']);
    }

    /**
     * @return array
     */
    public function getBlogUserRoleTextKeys() {

        $aResult = [
            self::BLOG_USER_ROLE_MEMBER          => 'blog_user_role_member',
            self::BLOG_USER_ROLE_MODERATOR       => 'blog_user_role_moderator',
            self::BLOG_USER_ROLE_ADMINISTRATOR   => 'blog_user_role_administrator',
            self::BLOG_USER_ROLE_OWNER           => 'blog_user_role_owner',
            self::BLOG_USER_ROLE_NOTMEMBER       => 'blog_user_role_notmember',
            self::BLOG_USER_ROLE_BAN_FOR_COMMENT => 'blog_user_role_banned_for_comment',
            self::BLOG_USER_ROLE_AUTHOR          => 'blog_user_role_author',
            self::BLOG_USER_ROLE_INVITE          => 'blog_user_role_invite',
            self::BLOG_USER_ROLE_REJECT          => 'blog_user_role_reject',
            self::BLOG_USER_ROLE_BAN             => 'blog_user_role_banned',
            self::BLOG_USER_ROLE_WISHES          => 'blog_user_role_request',
        ];

        return $aResult;
    }

    /**
     * @param int $iRole
     *
     * @return string
     */
    public function getBlogUserRoleName($iRole)
    {
        $aLangKeys = $this->getBlogUserRoleTextKeys();
        if (!empty($aLangKeys[$iRole])) {
            $sResult = \E::Module('Lang')->get($aLangKeys[$iRole]);
        } else {
            $sResult = \E::Module('Lang')->get('blog_user_role_other');
        }

        return $sResult;
    }

    /**
     * Получает дополнительные данные(объекты) для блогов по их ID
     *
     * @param array|int $aBlogsId   - Список ID блогов
     * @param array     $aAllowData - Список типов дополнительных данных, которые нужно получить для блогов
     * @param array     $aOrder     - Порядок сортировки
     *
     * @return array
     */
    public function getBlogsAdditionalData($aBlogsId, $aAllowData = null, $aOrder = null) 
    {
        if (!$aBlogsId) {
            return [];
        }
        if (null === $aAllowData) {
            $aAllowData = $this->aAdditionalData;
        }
        $aAllowData = \F::Array_FlipIntKeys($aAllowData);
        if (!is_array($aBlogsId)) {
            $aBlogsId = array($aBlogsId);
        }

        // * Получаем блоги
        $aBlogs = $this->getBlogsByArrayId($aBlogsId, $aOrder);
        if (!$aBlogs || (is_array($aAllowData) && empty($aAllowData))) {
            // additional data not required
            return $aBlogs;
        }

        $sCacheKey = 'Blog_GetBlogsAdditionalData_' . md5(serialize(array($aBlogsId, $aAllowData, $aOrder)));
        if (false !== ($data = \E::Module('Cache')->get($sCacheKey, 'tmp'))) {
            return $data;
        }

        // * Формируем ID дополнительных данных, которые нужно получить
        $aUserId = [];
        foreach ($aBlogs as $oBlog) {
            if (isset($aAllowData['owner'])) {
                $aUserId[] = $oBlog->getOwnerId();
            }
        }

        // * Получаем дополнительные данные
        $aBlogUsers = [];
        $aBlogVotes = [];
        $aUsers = (isset($aAllowData['owner']) && is_array($aAllowData['owner']))
            ?   \E::Module('User')->getUsersAdditionalData($aUserId, $aAllowData['owner'])
            :   \E::Module('User')->getUsersAdditionalData($aUserId);

        if ($this->oUserCurrent) {
            if (isset($aAllowData['relation_user'])) {
                $aBlogUsers = $this->getBlogUsersByArrayBlog($aBlogsId, $this->oUserCurrent->getId());
            }
            if (isset($aAllowData['vote'])) {
                $aBlogVotes = \E::Module('Vote')->getVoteByArray($aBlogsId, 'blog', $this->oUserCurrent->getId());
            }
        }

        $aBlogTypes = $this->getBlogTypes();

        if (isset($aAllowData['media'])) {
            $aAvatars = \E::Module('Uploader')->getMediaObjects('blog_avatar', $aBlogsId, null, ['target_id']);
        }

        // * Добавляем данные к результату - списку блогов
        /** @var ModuleBlog_EntityBlog $oBlog */
        foreach ($aBlogs as $oBlog) {
            if (isset($aUsers[$oBlog->getOwnerId()])) {
                $oBlog->setOwner($aUsers[$oBlog->getOwnerId()]);
            } else {
                $oBlog->setOwner(null); // или $oBlog->setOwner(new ModuleUser_EntityUser());
            }
            if (isset($aBlogUsers[$oBlog->getId()])) {
                $oBlog->setCurrentUserRole($aBlogUsers[$oBlog->getId()]->getUserRole());
            }
            if (isset($aBlogVotes[$oBlog->getId()])) {
                $oBlog->setVote($aBlogVotes[$oBlog->getId()]);
            } else {
                $oBlog->setVote(null);
            }
            if (isset($aBlogTypes[$oBlog->getType()])) {
                $oBlog->setBlogType($aBlogTypes[$oBlog->getType()]);
            }

            if (isset($aAllowData['media'])) {
                // Sets blogs avatars
                if (isset($aAvatars[$oBlog->getId()])) {
                    $oBlog->setMediaResources('blog_avatar', $aAvatars[$oBlog->getId()]);
                } else {
                    $oBlog->setMediaResources('blog_avatar', []);
                }
            }
        }
        // Saves only for executing session, so any additional tags no required
        \E::Module('Cache')->set($aBlogs, $sCacheKey, [], 'P1D', 'tmp');

        return $aBlogs;
    }

    /**
     * Возвращает список блогов по ID
     *
     * @param array      $aBlogsId    Список ID блогов
     * @param array|null $aOrder     Порядок сортировки
     *
     * @return ModuleBlog_EntityBlog[]
     */
    public function getBlogsByArrayId($aBlogsId, $aOrder = null) 
    {
        if (!$aBlogsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->getBlogsByArrayIdSolid($aBlogsId, $aOrder);
        }
        if (!is_array($aBlogsId)) {
            $aBlogsId = [$aBlogsId];
        }
        $aBlogsId = array_unique($aBlogsId);
        $aBlogs = [];
        $aBlogIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = (array)\F::Array_ChangeValues($aBlogsId, 'blog_');
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aBlogs[$data[$sKey]->getId()] = $data[$sKey];
                    } else {
                        $aBlogIdNotNeedQuery[] = $aBlogsId[$iIndex];
                    }
                }
            }
        }
        // * Смотрим каких блогов не было в кеше и делаем запрос в БД
        $aBlogIdNeedQuery = array_diff($aBlogsId, array_keys($aBlogs));
        $aBlogIdNeedQuery = array_diff($aBlogIdNeedQuery, $aBlogIdNotNeedQuery);
        $aBlogIdNeedStore = $aBlogIdNeedQuery;

        if ($aBlogIdNeedQuery) {
            if ($data = $this->oMapper->getBlogsByArrayId($aBlogIdNeedQuery)) {
                /** @var ModuleBlog_EntityBlog $oBlog */
                foreach ($data as $oBlog) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aBlogs[$oBlog->getId()] = $oBlog;
                    \E::Module('Cache')->set($oBlog, "blog_{$oBlog->getId()}", [], 'P4D');
                    $aBlogIdNeedStore = array_diff($aBlogIdNeedStore, [$oBlog->getId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aBlogIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "blog_{$sId}", [], 'P4D');
        }
        // * Сортируем результат согласно входящему массиву
        $aBlogs = \F::Array_SortByKeysArray($aBlogs, $aBlogsId);
        return $aBlogs;
    }

    /**
     * Возвращает список блогов по ID, но используя единый кеш
     *
     * @param array|int  $aBlogId    Список ID блогов
     * @param array|null $aOrder     Сортировка блогов
     *
     * @return array
     */
    public function getBlogsByArrayIdSolid($aBlogId, $aOrder = null) 
    {
        $aBlogId = array_unique((array)$aBlogId);
        $aBlogs = [];
        $sCacheKey = 'blog_id_' . implode(',', $aBlogId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogsByArrayId($aBlogId, $aOrder);
            /** @var ModuleBlog_EntityBlog $oBlog */
            foreach ($data as $oBlog) {
                $aBlogs[$oBlog->getId()] = $oBlog;
            }
            \E::Module('Cache')->set($aBlogs, $sCacheKey, ['blog_update'], 'P1D');
            return $aBlogs;
        }
        return $data;
    }

    /**
     * Получить персональный блог юзера
     *
     * @param int $iUserId    ID пользователя
     *
     * @return ModuleBlog_EntityBlog|null
     */
    public function getPersonalBlogByUserId($iUserId) 
    {
        $sCacheKey = 'blog_personal_' . $iUserId;
        if (false === ($iBlogId = \E::Module('Cache')->get($sCacheKey))) {
            $iBlogId = $this->oMapper->getPersonalBlogByUserId($iUserId);
            if ($iBlogId) {
                \E::Module('Cache')->set($iBlogId, $sCacheKey, ["blog_update_{$iBlogId}", "user_update_{$iUserId}"], 'P30D');
            } else {
                \E::Module('Cache')->set(null, $sCacheKey, ['blog_update', 'blog_new', "user_update_{$iUserId}"], 'P30D');
            }
        }

        if ($iBlogId) {
            return $this->getBlogById($iBlogId);
        }
        return null;
    }

    /**
     * Получить блог по айдишнику(номеру)
     *
     * @param int $iBlogId    ID блога
     *
     * @return ModuleBlog_EntityBlog|null
     */
    public function getBlogById($iBlogId)
    {
        if (!(int)$iBlogId) {
            return null;
        }
        $aBlogs = $this->getBlogsAdditionalData($iBlogId);
        if (isset($aBlogs[$iBlogId])) {
            return $aBlogs[$iBlogId];
        }
        return null;
    }

    /**
     * Получить блог по УРЛу
     *
     * @param   string $sBlogUrl    URL блога
     *
     * @return  ModuleBlog_EntityBlog|null
     */
    public function getBlogByUrl($sBlogUrl) 
    {
        $sCacheKey = 'blog_url_' . $sBlogUrl;
        if (false === ($iBlogId = \E::Module('Cache')->get($sCacheKey))) {
            if ($iBlogId = $this->oMapper->getBlogsIdByUrl($sBlogUrl)) {
                \E::Module('Cache')->set($iBlogId, $sCacheKey, ["blog_update_{$iBlogId}"], 'P30D');
            } else {
                \E::Module('Cache')->set(null, $sCacheKey, ['blog_update', 'blog_new'], 'P30D');
            }
        }
        if ($iBlogId) {
            return $this->getBlogById($iBlogId);
        }
        return null;
    }

    /**
     * Returns array of blogs by URLs
     *
     * @param array $aBlogsUrl
     *
     * @return array
     */
    public function getBlogsByUrl($aBlogsUrl) 
    {
        $sCacheKey = 'blogs_by_url_' . serialize($aBlogsUrl);
        if (false === ($aBlogs = \E::Module('Cache')->get($sCacheKey))) {
            if ($aBlogsId = $this->oMapper->getBlogsIdByUrl($aBlogsUrl)) {
                $aBlogs = $this->getBlogsAdditionalData($aBlogsId);
                $aOrders = array_flip($aBlogsUrl);
                foreach($aBlogs as $oBlog) {
                    $oBlog->setProp('_order', $aOrders[$oBlog->getUrl()]);
                }
                $aBlogs = \F::Array_SortEntities($aBlogs, '_order');
                $aAdditionalCacheKeys = \F::Array_ChangeValues($aBlogsUrl, 'blog_update_');
            } else {
                $aBlogs = [];
                $aAdditionalCacheKeys = [];
            }
            $aAdditionalCacheKeys[] = 'blog_update';
            $aAdditionalCacheKeys[] = 'blog_new';
            \E::Module('Cache')->set([], $sCacheKey, $aAdditionalCacheKeys, 'P30D');
        }
        return $aBlogs;
    }

    /**
     * Получить блог по названию
     *
     * @param string $sTitle    Название блога
     *
     * @return ModuleBlog_EntityBlog|null
     */
    public function getBlogByTitle($sTitle)
    {
        if (false === ($id = \E::Module('Cache')->get("blog_title_{$sTitle}"))) {
            if ($id = $this->oMapper->getBlogByTitle($sTitle)) {
                \E::Module('Cache')->set($id, "blog_title_{$sTitle}", ["blog_update_{$id}", 'blog_new'], 'P2D');
            } else {
                \E::Module('Cache')->set(null, "blog_title_{$sTitle}", ['blog_update', 'blog_new'], 60 * 60);
            }
        }
        return $this->getBlogById($id);
    }

    /**
     * Создаёт персональный блог
     *
     * @param ModuleUser_EntityUser $oUser    Пользователь
     *
     * @return ModuleBlog_EntityBlog|bool
     */
    public function createPersonalBlog($oUser)
    {
        $oBlogType = $this->getBlogTypeByCode('personal');

        // Создаем персональный блог, только если это разрешено
        if ($oBlogType && $oBlogType->isActive()) {
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = \E::getEntity('Blog');
            $oBlog->setOwnerId($oUser->getId());
            $oBlog->setOwner($oUser);
            $oBlog->setTitle(\E::Module('Lang')->get('blogs_personal_title') . ' ' . $oUser->getLogin());
            $oBlog->setType('personal');
            $oBlog->setDescription(\E::Module('Lang')->get('blogs_personal_description'));
            $oBlog->setDateAdd(\F::Now());
            $oBlog->setLimitRatingTopic(-1000);
            $oBlog->setUrl(null);
            $oBlog->setAvatar(null);
            return $this->addBlog($oBlog);
        }
        return false;
    }

    /**
     * Добавляет блог
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     *
     * @return ModuleBlog_EntityBlog|bool
     */
    public function addBlog($oBlog)
    {
        if ($sId = $this->oMapper->addBlog($oBlog)) {
            $oBlog->setId($sId);
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(['blog_new']);


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
                $sTargetType = 'blog_avatar';
                $sTargetId = $sId;

                $aMediaRel = \E::Module('Media')->getMediaRelByTargetAndUser($sTargetType, 0, E::userId());

                if ($aMediaRel) {
                    $oResource = array_shift($aMediaRel);
                    $sOldPath = $oResource->getFile();

                    //$oStoredFile = \E::Module('Uploader')->Store($sOldPath, $sNewPath);
                    $oStoredFile = \E::Module('Uploader')->storeImage($sOldPath, $sTargetType, $sTargetId);
                    /** @var ModuleMedia_EntityMedia $oResource */
                    $oResource = \E::Module('Media')->getMresourcesByUuid($oStoredFile->getUuid());
                    if ($oResource) {
                        $oResource->setUrl(\E::Module('Media')->normalizeUrl(\E::Module('Uploader')->getTargetUrl($sTargetType, $sTargetId)));
                        $oResource->setType($sTargetType);
                        $oResource->setUserId(\E::userId());

                        $oResource = [$oResource];
                        \E::Module('Media')->unlinkFile($sTargetType, 0, E::userId());
                        \E::Module('Media')->addTargetRel([$oResource], $sTargetType, $sTargetId);

                        // 4. Обновим сведения об аватаре у блога для обеспечения обратной
                        // совместимости. Могут быть плагины которые берут картинку непосредственно
                        // из свойства блога, а не через модуль uploader
                        $oBlog->setAvatar($oBlog->getAvatar());
                        $this->updateBlog($oBlog);
                    }
                }
            }

            return $oBlog;
        }
        return false;
    }

    /**
     * Обновляет блог
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     *
     * @return ModuleBlog_EntityBlog|bool
     */
   public function updateBlog($oBlog)
   {
       $oBlog->setDateEdit(\F::Now());
       $bResult = $this->oMapper->updateBlog($oBlog);
       if ($bResult) {
           $aTags = ['blog_update', "blog_update_{$oBlog->getId()}", 'topic_update'];
           if ($oBlog->getOldType() && $oBlog->getOldType() != $oBlog->getType()) {
               // Списк авторов блога
               $aUsersId = $this->getAuthorsIdByBlog($oBlog->GetId());
               foreach($aUsersId as $nUserId) {
                   $aTags[] = 'topic_update_user_' . $nUserId;
               }
           }
           //чистим зависимые кеши
           \E::Module('Cache')->cleanByTags($aTags);
           \E::Module('Cache')->delete("blog_{$oBlog->getId()}");
           return true;
       }
       return false;
   }

    /**
     * Добавляет отношение юзера к блогу, по сути присоединяет к блогу
     *
     * @param ModuleBlog_EntityBlogUser $oBlogUser    Объект связи(отношения) блога с пользователем
     *
     * @return bool
     */
    public function addRelationBlogUser($oBlogUser)
    {
        if ($this->oMapper->addRelationBlogUser($oBlogUser)) {
            \E::Module('Cache')->cleanByTags(["blog_relation_change_{$oBlogUser->getUserId()}", "blog_relation_change_blog_{$oBlogUser->getBlogId()}"]);
            \E::Module('Cache')->delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");
            return true;
        }
        return false;
    }

    /**
     * Обновляет отношения пользователя с блогом
     *
     * @param ModuleBlog_EntityBlogUser $oBlogUser    Объект отновшения
     *
     * @return bool
     */
    public function updateRelationBlogUser($oBlogUser)
    {
        $bResult = $this->oMapper->updateRelationBlogUser($oBlogUser);
        if ($bResult) {
            \E::Module('Cache')->cleanByTags(["blog_relation_change_{$oBlogUser->getUserId()}", "blog_relation_change_blog_{$oBlogUser->getBlogId()}"]);
            \E::Module('Cache')->delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");
            return $bResult;
        }
        return false;
    }

    /**
     * Удалет отношение юзера к блогу, по сути отключает от блога
     *
     * @param ModuleBlog_EntityBlogUser $oBlogUser    Объект связи(отношения) блога с пользователем
     *
     * @return bool
     */
    public function deleteRelationBlogUser($oBlogUser) 
    {
        if ($this->oMapper->deleteRelationBlogUser($oBlogUser)) {
            \E::Module('Cache')->cleanByTags(["blog_relation_change_{$oBlogUser->getUserId()}", "blog_relation_change_blog_{$oBlogUser->getBlogId()}"]);
            \E::Module('Cache')->delete("blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}");
            return true;
        }
        return false;
    }

    /**
     * Получает список блогов по хозяину
     *
     * @param int  $iUserId          ID пользователя
     * @param bool $bReturnIdOnly    Возвращать только ID блогов или полные объекты
     *
     * @return array
     */
    public function getBlogsByOwnerId($iUserId, $bReturnIdOnly = false)
    {
        $iUserId = (int)$iUserId;
        if (!$iUserId) {
            return [];
        }

        $sCacheKey = 'blogs_by_owner' . $iUserId;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogsIdByOwnerId($iUserId);
            \E::Module('Cache')->set($data, $sCacheKey, ['blog_update', 'blog_new', "user_update_{$iUserId}"], 'P30D');
        }

        // * Возвращаем только иденитификаторы
        if ($bReturnIdOnly) {
            return $data;
        }
        if ($data) {
            $data = $this->getBlogsAdditionalData($data);
        }
        return $data;
    }

    /**
     * Получает список всех НЕ персональных блогов
     *
     * @param bool|array $xReturnOptions  true - Возвращать только ID блогов, array - Доп.данные блога
     *
     * @return ModuleBlog_EntityBlog[]
     */
    public function getBlogs($xReturnOptions = null)
    {
        $sCacheKey = 'Blog_GetBlogsId';
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogsId();
            \E::Module('Cache')->set($data, $sCacheKey, ['blog_update', 'blog_new'], 'P1D');
        }

        // * Возвращаем только иденитификаторы
        if ($xReturnOptions === true) {
            return $data;
        }
        if ($data) {
            if (is_array($xReturnOptions)) {
                $aAdditionalData = $xReturnOptions;
            } else {
                $aAdditionalData = null;
            }
            $data = $this->getBlogsAdditionalData($data, $aAdditionalData);
        }
        return $data;
    }

    /**
     * Получает список пользователей блога.
     * Если роль не указана, то считаем что поиск производиться по положительным значениям (статусом выше GUEST).
     *
     * @param int            $iBlogId  ID блога
     * @param int|array|bool $xRole    Роль пользователей в блоге (null == subscriber only; true === all roles)
     * @param int            $iPage    Номер текущей страницы
     * @param int            $iPerPage Количество элементов на одну страницу
     *
     * @return array
     */
    public function getBlogUsersByBlogId($iBlogId, $xRole = null, $iPage = 1, $iPerPage = 100)
    {
        $aFilter = [
            'blog_id' => $iBlogId,
        ];
        if ($xRole === true) {
            $aFilter['user_all_role'] = true;
        } elseif (is_int($xRole) || is_array($xRole)) {
            $aFilter['user_role'] = $xRole;
        }
        if (null === $iPage) {
            $iPerPage = null;
        }
        $sCacheKey = 'blog_relation_user_by_filter_' . serialize($aFilter) . '_' . $iPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = [
                'collection' => $this->oMapper->getBlogUsers($aFilter, $iCount, $iPage, $iPerPage),
                'count'      => $iCount,
            ];
            \E::Module('Cache')->set($data, $sCacheKey, ["blog_relation_change_blog_{$iBlogId}"], 'P3D');
        }

        // * Достаем дополнительные данные, для этого формируем список юзеров и делаем мульти-запрос
        if ($data['collection']) {
            $aUserId = [];
            /** @var ModuleBlog_EntityBlogUser $oBlogUser */
            foreach ((array)$data['collection'] as $oBlogUser) {
                $aUserId[] = $oBlogUser->getUserId();
            }
            $aUsers = \E::Module('User')->getUsersAdditionalData($aUserId);
            $aBlogs = \E::Module('Blog')->getBlogsAdditionalData($iBlogId);

            $aResults = [];
            foreach ((array)$data['collection'] as $oBlogUser) {
                if (isset($aUsers[$oBlogUser->getUserId()])) {
                    $oBlogUser->setUser($aUsers[$oBlogUser->getUserId()]);
                } else {
                    $oBlogUser->setUser(null);
                }
                if (isset($aBlogs[$oBlogUser->getBlogId()])) {
                    $oBlogUser->setBlog($aBlogs[$oBlogUser->getBlogId()]);
                } else {
                    $oBlogUser->setBlog(null);
                }
                $aResults[$oBlogUser->getUserId()] = $oBlogUser;
            }
            $data['collection'] = $aResults;
        }
        return $data;
    }

    /**
     * @param $iUserId
     * @param null $xRole
     * @param bool $bReturnIdOnly
     *
     * @return int[]|ModuleBlog_EntityBlogUser[]
     */
    public function getBlogUsersByUserId($iUserId, $xRole = null, $bReturnIdOnly = false)
    {
        return $this->getBlogUserRelsByUserId($iUserId, $xRole, $bReturnIdOnly);
    }

    /**
     * Получает отношения юзера к блогам (подписан на блог или нет)
     *
     * @param int       $iUserId          ID пользователя
     * @param int|int[] $xRole            Роль пользователя в блоге
     * @param bool      $bReturnIdOnly    Возвращать только ID блогов или полные объекты
     *
     * @return int[]|ModuleBlog_EntityBlogUser[]
     */
    public function getBlogUserRelsByUserId($iUserId, $xRole = null, $bReturnIdOnly = false)
    {
        $aFilter = [
            'user_id' => $iUserId
        ];
        if ($xRole !== null) {
            $aFilter['user_role'] = $xRole;
        }
        $sCacheKey = 'blog_relation_user_by_filter_' . serialize($aFilter);
        if (false === ($aBlogUserRels = \E::Module('Cache')->get($sCacheKey, 'tmp,'))) {
            $aBlogUserRels = $this->oMapper->getBlogUsers($aFilter);
            \E::Module('Cache')->set(
                $aBlogUserRels, $sCacheKey, ['blog_update', "blog_relation_change_{$iUserId}"], 'P3D', ',tmp'
            );
        }
        //  Достаем дополнительные данные, для этого формируем список блогов и делаем мульти-запрос
        $aBlogId = [];
        $aResult = [];
        if ($aBlogUserRels) {
            foreach ($aBlogUserRels as $oBlogUser) {
                $aBlogId[] = $oBlogUser->getBlogId();
            }
            //  Если указано возвращать полные объекты
            if (!$bReturnIdOnly) {
                $aUsers = \E::Module('User')->getUsersAdditionalData($iUserId);
                $aBlogs = \E::Module('Blog')->getBlogsAdditionalData($aBlogId);
                foreach ($aBlogUserRels as $oBlogUser) {
                    if (isset($aUsers[$oBlogUser->getUserId()])) {
                        $oBlogUser->setUser($aUsers[$oBlogUser->getUserId()]);
                    } else {
                        $oBlogUser->setUser(null);
                    }
                    if (isset($aBlogs[$oBlogUser->getBlogId()])) {
                        $oBlogUser->setBlog($aBlogs[$oBlogUser->getBlogId()]);
                    } else {
                        $oBlogUser->setBlog(null);
                    }
                    $aResult[$oBlogUser->getBlogId()] = $oBlogUser;
                }
            }
        }
        return $bReturnIdOnly ? $aBlogId : $aResult;
    }

    /**
     * @param $iBlogId
     * @param $iUserId
     *
     * @return ModuleBlog_EntityBlogUser|null
     */
    public function getBlogUserByBlogIdAndUserId($iBlogId, $iUserId)
    {
        return $this->getBlogUserRelByBlogIdAndUserId($iBlogId, $iUserId);
    }

    /**
     * Состоит ли юзер в конкретном блоге
     *
     * @param int $iBlogId    ID блога
     * @param int $iUserId    ID пользователя
     *
     * @return ModuleBlog_EntityBlogUser|null
     */
    public function getBlogUserRelByBlogIdAndUserId($iBlogId, $iUserId)
    {
        $aBlogUser = $this->getBlogUsersByArrayBlog($iBlogId, $iUserId);
        if (isset($aBlogUser[$iBlogId])) {
            return $aBlogUser[$iBlogId];
        }
        return null;
    }

    /**
     * @param $aBlogId
     * @param $iUserId
     *
     * @return ModuleBlog_EntityBlogUser[]
     */
    public function getBlogUsersByArrayBlog($aBlogId, $iUserId)
    {
        return $this->getBlogUserRelsByArrayBlog($aBlogId, $iUserId);
    }

    /**
     * Получить список отношений блог-юзер по списку айдишников
     *
     * @param array|int $aBlogId Список ID блогов
     * @param int       $iUserId ID пользователя
     *
     * @return ModuleBlog_EntityBlogUser[]
     */
    public function getBlogUserRelsByArrayBlog($aBlogId, $iUserId)
    {
        if (!$aBlogId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->getBlogUsersByArrayBlogSolid($aBlogId, $iUserId);
        }
        if (!is_array($aBlogId)) {
            $aBlogId = [(int)$aBlogId];
        }
        $aBlogId = array_unique($aBlogId);
        $aBlogUsers = [];
        $aBlogIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = (array)\F::Array_ChangeValues($aBlogId, 'blog_relation_user_', '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aBlogUsers[$data[$sKey]->getBlogId()] = $data[$sKey];
                    } else {
                        $aBlogIdNotNeedQuery[] = $aBlogId[$iIndex];
                    }
                }
            }
        }
        // * Смотрим каких блогов не было в кеше и делаем запрос в БД
        $aBlogIdNeedQuery = array_diff($aBlogId, array_keys($aBlogUsers));
        $aBlogIdNeedQuery = array_diff($aBlogIdNeedQuery, $aBlogIdNotNeedQuery);
        $aBlogIdNeedStore = $aBlogIdNeedQuery;

        if ($aBlogIdNeedQuery) {
            if ($data = $this->oMapper->getBlogUsersByArrayBlog($aBlogIdNeedQuery, $iUserId)) {
                foreach ($data as $oBlogUser) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aBlogUsers[$oBlogUser->getBlogId()] = $oBlogUser;
                    \E::Module('Cache')->set(
                        $oBlogUser, "blog_relation_user_{$oBlogUser->getBlogId()}_{$oBlogUser->getUserId()}", [], 'P4D'
                    );
                    $aBlogIdNeedStore = array_diff($aBlogIdNeedStore, [$oBlogUser->getBlogId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aBlogIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "blog_relation_user_{$sId}_{$iUserId}", [], 'P4D');
        }

        // * Сортируем результат согласно входящему массиву
        $aBlogUsers = \F::Array_SortByKeysArray($aBlogUsers, $aBlogId);

        return $aBlogUsers;
    }

    /**
     * Получить список отношений блог-юзер по списку айдишников используя общий кеш
     *
     * @param array $aBlogId    Список ID блогов
     * @param int   $iUserId    ID пользователя
     *
     * @return array
     */
    public function getBlogUsersByArrayBlogSolid($aBlogId, $iUserId)
    {
        $aBlogId = array_unique((array)$aBlogId);
        $aBlogUsers = [];
        $sCacheKey = 'blog_relation_user_' . $iUserId . '_id_' . implode(',', $aBlogId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogUsersByArrayBlog($aBlogId, $iUserId);
            foreach ($data as $oBlogUser) {
                $aBlogUsers[$oBlogUser->getBlogId()] = $oBlogUser;
            }
            \E::Module('Cache')->set(
                $aBlogUsers, $sCacheKey,
                ['blog_update', "blog_relation_change_{$iUserId}"], 'P1D'
            );
            return $aBlogUsers;
        }
        return $data;
    }

    /**
     * Возвращает список ID пользователей, являющихся авторами в блоге
     *
     * @param $xBlogId
     *
     * @return array
     */
    public function getAuthorsIdByBlog($xBlogId)
    {
        $nBlogId = $this->_entityId($xBlogId);
        if ($nBlogId) {
            $sCacheKey = 'authors_id_by_blog_' . $nBlogId;
            if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
                $data = $this->oMapper->getAuthorsIdByBlogId($nBlogId);
                \E::Module('Cache')->set($data, $sCacheKey, ['blog_update', 'blog_new', 'topic_new', 'topic_update'], 'P1D');
            }
            return $data;
        }
        return [];
    }

    /**
     * @param array $aFilter
     */
    public function setBlogsFilter($aFilter) 
    {
        $this->aBlogsFilter = $aFilter;
    }

    /**
     * @return array
     */
    public function getBlogsFilter() 
    {
        return $this->aBlogsFilter;
    }

    /**
     * Возвращает список блогов по фильтру
     *
     * @param array $aFilter    Фильтр выборки блогов
     * @param int   $iPage      Номер текущей страницы
     * @param int   $iPerPage   Количество элементов на одну страницу
     * @param array $aAllowData Список типов данных, которые нужно подтянуть к списку блогов
     *
     * @return array('collection'=>array,'count'=>int)
     *
     * Old interface: GetBlogsByFilter($aFilter, $aOrder, $iPage, $iPerPage, $aAllowData = null)
     */
    public function getBlogsByFilter($aFilter, $iPage, $iPerPage, $aAllowData = null) {

        // Old interface compatibility
        if (!isset($aFilter['order']) && is_numeric($iPerPage) && is_numeric($aAllowData)) {
            $aOrder = $iPage;
            $iPage = $iPerPage;
            $iPerPage = $aAllowData;
            if (func_num_args() === 5) {
                $aAllowData = func_get_arg(4);
            } else {
                $aAllowData = null;
            }
        } else {
            $aOrder = (isset($aFilter['order']) ? (array)$aFilter['order'] : []);
        }
        if (null === $aAllowData) {
            $aAllowData = ['owner' => [], 'relation_user'];
        }
        $sCacheKey = 'blog_filter_' . serialize($aFilter) . serialize($aOrder) . "_{$iPage}_{$iPerPage}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = [
                'collection' => $this->oMapper->getBlogsIdByFilterPerPage($aFilter, $aOrder, $iCount, $iPage, $iPerPage),
                'count'      => $iCount
            ];
            \E::Module('Cache')->set($data, $sCacheKey, ['blog_update', 'blog_new'], 'P2D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getBlogsAdditionalData($data['collection'], $aAllowData);
        }
        return $data;
    }

    /**
     * Return filter for blog list by name and params
     *
     * @param string $sFilterName
     * @param array  $aParams
     *
     * @return array
     */
    public function getNamedFilter($sFilterName, $aParams = []) 
    {
        $aFilter = $this->getBlogsFilter();
        $aFilter['include_type'] = $this->getAllowBlogTypes(\E::User(), 'list', true);
        switch ($sFilterName) {
            case 'top':
                $aFilter['order'] = ['blog_rating' => 'desc'];
                break;
            default:
                break;
        }
        if (!empty($aParams['exclude_type'])) {
            $aFilter['exclude_type'] = $aParams['exclude_type'];
        }
        if (!empty($aParams['owner_id'])) {
            $aFilter['user_owner_id'] = $aParams['owner_id'];
        }

        return $aFilter;
    }

    /**
     * Получает список блогов по рейтингу
     *
     * @param int $iPage       Номер текущей страницы
     * @param int $iPerPage    Количество элементов на одну страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getBlogsRating($iPage, $iPerPage)
    {
        $aFilter = $this->getNamedFilter('top');
        return $this->getBlogsByFilter($aFilter, $iPage, $iPerPage);
    }

    /**
     * Список подключенных блогов по рейтингу
     *
     * @param int $iUserId    ID пользователя
     * @param int $iLimit     Ограничение на количество в ответе
     *
     * @return array
     */
    public function getBlogsRatingJoin($iUserId, $iLimit)
    {
        $sCacheKey = "blog_rating_join_{$iUserId}_{$iLimit}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogsRatingJoin($iUserId, $iLimit);
            \E::Module('Cache')->set($data, $sCacheKey, ['blog_update', "blog_relation_change_{$iUserId}"], 'P1D');
        }
        return $data;
    }

    /**
     * Список своих блогов по рейтингу
     *
     * @param int $iUserId    ID пользователя
     * @param int $iLimit     Ограничение на количество в ответе
     *
     * @return array
     */
    public function getBlogsRatingSelf($iUserId, $iLimit)
    {
        $aFilter = $this->getNamedFilter('top', ['owner_id' => $iUserId]);
        $aResult = $this->getBlogsByFilter($aFilter, 1, $iLimit);

        return $aResult['collection'];
    }

    /**
     * Получает список блогов в которые может постить юзер
     *
     * @param ModuleUser_EntityUser $oUser - Объект пользователя
     * @param bool                  $bSortByTitle
     *
     * @return array
     */
    public function getBlogsAllowByUser($oUser, $bSortByTitle = true)
    {
        return $this->getBlogsAllowTo('write', $oUser, null, false, $bSortByTitle);
    }

    /**
     * Получает список блогов, которые доступны пользователю для заданного действия.
     * Или проверяет на заданное действие конкретный блог
     *
     * @param string                    $sAllow
     * @param ModuleUser_EntityUser     $oUser
     * @param int|ModuleBlog_EntityBlog $xBlog
     * @param bool                      $bCheckOnly
     * @param bool                      $bSortByTitle
     *
     * @return array|bool
     */
    public function getBlogsAllowTo($sAllow, $oUser, $xBlog = null, $bCheckOnly = false, $bSortByTitle = true)
    {
        if (!$oUser) {
            return null;
        }

        if (is_object($xBlog)) {
            $iRequestBlogId = (int)$xBlog->getId();
        } else {
            $iRequestBlogId = (int)$xBlog;
        }
        if (!$iRequestBlogId && $bCheckOnly) {
            return false;
        }

        $sCacheKeyAll = \E::Module('Cache')->key('blogs_allow_to_', $sAllow, $oUser->getId(), $iRequestBlogId);
        $sCacheKeySorted = $sCacheKeyAll . '_sort';
        $sCacheKeyChecked = $sCacheKeyAll . '_check';
        if ($bCheckOnly) {
            // Если только проверка прав, то проверяем временный кеш
            if (is_int($xCacheResult = \E::Module('Cache')->get($sCacheKeyChecked, 'tmp'))) {
                return $xCacheResult;
            }
            if (($xCacheResult = \E::Module('Cache')->get($sCacheKeySorted, 'tmp,')) && ($xCacheResult !== false)) {
                // see sorted result in cache
                $xResult = !empty($xCacheResult[$iRequestBlogId]);
            } elseif (($xCacheResult = \E::Module('Cache')->get($sCacheKeyAll, 'tmp,')) && ($xCacheResult !== false)) {
                // see unsorted result in cache
                $xResult = !empty($xCacheResult[$iRequestBlogId]);
            } else {
                $xResult = $this->_getBlogsAllowTo($sAllow, $oUser, $xBlog, true);
            }
            // Чтоб не было ложных сробатываний, используем в этом кеше числовое значение
            \E::Module('Cache')->set(!empty($xResult) ? 1 : 0, $sCacheKeyChecked, ['blog_update', 'user_update'], 0, 'tmp');

            return $xResult;
        }

        if ($bSortByTitle) {
            // see sorted blogs in cache
            if (($xCacheResult = \E::Module('Cache')->get($sCacheKeySorted, 'tmp,')) && ($xCacheResult !== false)) {
                return $xCacheResult;
            }
        }

        // see unsorted blogs in cache
        $xCacheResult = \E::Module('Cache')->get($sCacheKeyAll, 'tmp,');
        if ($xCacheResult !== false) {
            if ($bSortByTitle) {
                $this->_sortByTitle($xCacheResult);
                \E::Module('Cache')->set($xCacheResult, $sCacheKeySorted, ['blog_update', 'user_update'], 'P10D', ',tmp');
            }
            return $xCacheResult;
        }

        $aAllowBlogs = $this->_getBlogsAllowTo($sAllow, $oUser, $xBlog, false);
        if ($bSortByTitle) {
            $this->_sortByTitle($aAllowBlogs);
            \E::Module('Cache')->set($aAllowBlogs, $sCacheKeySorted, ['blog_update', 'user_update'], 'P10D', ',tmp');
        } else {
            \E::Module('Cache')->set($aAllowBlogs, $sCacheKeyAll, ['blog_update', 'user_update'], 'P10D', ',tmp');
        }
        return $aAllowBlogs;
    }


    /**
     * @param string                    $sAllow
     * @param ModuleUser_EntityUser     $oUser
     * @param int|ModuleBlog_EntityBlog $xBlog
     * @param bool                      $bCheckOnly
     *
     * @return array|bool|mixed|ModuleBlog_EntityBlog[]
     */
    protected function _getBlogsAllowTo($sAllow, $oUser, $xBlog = null, $bCheckOnly = false)
    {
        /** @var ModuleBlog_EntityBlog $oRequestBlog */
        $oRequestBlog = null;
        if (is_object($xBlog)) {
            $iRequestBlogId = (int)$xBlog->getId();
            $oRequestBlog = $xBlog;
        } else {
            $iRequestBlogId = (int)$xBlog;
        }

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            // Если админ и если проверка на конкретный блог, то возвращаем без проверки
            if ($iRequestBlogId) {
                return $iRequestBlogId;
            }
            $aAdditionalData = ['relation_user'];
            $aAllowBlogs = $this->getBlogs($aAdditionalData);
            if ($iRequestBlogId) {
                return isset($aAllowBlogs[$iRequestBlogId]) ? $aAllowBlogs[$iRequestBlogId] : [];
            }
            return $aAllowBlogs;
        }

        // User is owner of the blog
        if ($oRequestBlog && $oRequestBlog->getOwnerId() == $oUser->getId()) {
            return $oRequestBlog;
        }

        // Блоги, созданные пользователем
        $aAllowBlogs = $this->getBlogsByOwnerId($oUser->getId());
        if ($iRequestBlogId && isset($aAllowBlogs[$iRequestBlogId])) {
            return $aAllowBlogs[$iRequestBlogId];
        }

        // Блоги, в которых состоит пользователь
        if ($iRequestBlogId) {
            // Requests one blog
            $aBlogUsers = $this->getBlogUsersByArrayBlog($iRequestBlogId, $oUser->getId());
            if ($oBlogUser = reset($aBlogUsers)) {
                if (!$oBlogUser->getBlog()) {
                    if (!$oRequestBlog) {
                        $oRequestBlog = $this->getBlogById($iRequestBlogId);
                    }
                    $oBlogUser->setBlog($oRequestBlog);
                }
            }
        } else {
            // Requests any allowed blogs
            $aBlogUsers = $this->getBlogUsersByUserId($oUser->getId());
        }

        foreach ($aBlogUsers as $oBlogUser) {
            /** @var ModuleBlog_EntityBlog $oBlog */
            $oBlog = $oBlogUser->getBlog();
            /** @var ModuleBlog_EntityBlogType $oBlogType */
            $oBlogType = $oBlog->GetBlogType();

            // админа и модератора блога не проверяем
            if ($oBlogUser->IsBlogAdministrator() || $oBlogUser->IsBlogModerator()) {
                $aAllowBlogs[$oBlog->getId()] = $oBlog;
            } elseif (($oBlogUser->getUserRole() !== self::BLOG_USER_ROLE_NOTMEMBER) && ($oBlogUser->getUserRole() > self::BLOG_USER_ROLE_GUEST)) {
                $bAllow = false;
                if ($oBlogType) {
                    if ($sAllow == 'write') {
                        $bAllow = ($oBlogType->GetAclWrite(self::BLOG_USER_ACL_MEMBER)
                                && $oBlogType->GetMinRateWrite() <= $oUser->getRating())
                            || \E::Module('ACL')->CheckBlogEditContent($oBlog, $oUser);
                    } elseif ($sAllow == 'read') {
                        $bAllow = $oBlogType->GetAclRead(self::BLOG_USER_ACL_MEMBER)
                            && $oBlogType->GetMinRateRead() <= $oUser->getRating();
                    } elseif ($sAllow == 'comment') {
                        $bAllow = $oBlogType->GetAclComment(self::BLOG_USER_ACL_MEMBER)
                            && $oBlogType->GetMinRateComment() <= $oUser->getRating();
                    }
                    if ($bAllow) {
                        $aAllowBlogs[$oBlog->getId()] = $oBlog;
                    }
                }
            }
            // Если задан конкретный блог и он найден, то проверять больше не нужно
            if ($iRequestBlogId && isset($aAllowBlogs[$iRequestBlogId])) {
                return $aAllowBlogs[$iRequestBlogId];
            }
        }

        $aFilter = [];
        if ($sAllow == 'list') {
            // Blogs which user can list
            $aFilter['allow_list'] = true;
        } elseif ($sAllow == 'read') {
            // Blogs which can be read without subscribing
            $aFilter = array(
                'acl_read'      => self::BLOG_USER_ACL_USER,
                'min_rate_read' => $oUser->GetUserRating(),
            );
        } elseif ($sAllow == 'comment') {
            // Blogs in which user can comment without subscription
            $aFilter = array(
                'acl_comment'      => self::BLOG_USER_ACL_USER,
                'min_rate_comment' => $oUser->GetUserRating(),
            );
        } elseif ($sAllow == 'write') {
            // Blogs in which user can write without subscription
            $aFilter = array(
                'acl_write'      => self::BLOG_USER_ACL_USER,
                'min_rate_write' => $oUser->GetUserRating(),
            );
        }

        // Получаем типы блогов
        if ($aFilter && ($aBlogTypes = $this->getBlogTypes($aFilter, true))) {
            // Получаем ID блогов
            $aCriteria = [
                'filter' => ['blog_type' => $aBlogTypes]
            ];
            // Получаем ID блогов
            $aResult = $this->oMapper->getBlogsIdByCriteria($aCriteria);

            // Получаем сами блоги
            if ($aResult['data']) {
                // если задана только проверка, то сам блог(и) не нужен
                if ($iRequestBlogId && $bCheckOnly) {
                    return in_array($iRequestBlogId, $aResult['data']);
                }
                if ($aBlogs = $this->getBlogsAdditionalData($aResult['data'], [])) {
                    foreach ($aBlogs as $oBlog) {
                        if (!isset($aAllowBlogs[$oBlog->getId()])) {
                            $aAllowBlogs[$oBlog->getId()] = $oBlog;
                        }
                    }
                }
            }
        }
        if ($iRequestBlogId) {
            return isset($aAllowBlogs[$iRequestBlogId]) ? $aAllowBlogs[$iRequestBlogId] : [];
        }

        return $aAllowBlogs;
    }

    /**
     * Получаем массив блогов, которые являются открытыми для пользователя
     *
     * @param  ModuleUser_EntityUser $oUser    Объект пользователя
     *
     * @return array
     */
    public function getAccessibleBlogsByUser($oUser) {

        if ($oUser->isAdministrator() || $oUser->isModerator()) {
            return $this->getBlogs(true);
        }
        if (false === ($aOpenBlogsUser = \E::Module('Cache')->get("blog_accessible_user_{$oUser->getId()}"))) {
            //  Заносим блоги, созданные пользователем
            $aOpenBlogsUser = $this->getBlogsByOwnerId($oUser->getId(), true);

            // Добавляем блоги, в которых состоит пользователь
            // (читателем, модератором, или администратором)
            $aOpenBlogsUser = array_merge($aOpenBlogsUser, $this->getBlogUsersByUserId($oUser->getId(), null, true));
            \E::Module('Cache')->set(
                $aOpenBlogsUser, "blog_accessible_user_{$oUser->getId()}",
                array('blog_new', 'blog_update', "blog_relation_change_{$oUser->getId()}"), 60 * 60 * 24
            );
        }
        return $aOpenBlogsUser;
    }

    /**
     * Получаем массив идентификаторов блогов, которые являются закрытыми для пользователя
     *
     * @param  ModuleUser_EntityUser|null $oUser    Пользователь
     *
     * @return array
     */
    public function getInaccessibleBlogsByUser($oUser = null) {

        if ($oUser && ($oUser->isAdministrator() || $oUser->isModerator())) {
            return [];
        }
        $nUserId = $oUser ? $oUser->getId() : 0;
        $sCacheKey = 'blog_inaccessible_user_' . $nUserId;
        if (false === ($aCloseBlogsId = \E::Module('Cache')->get($sCacheKey))) {
            $aCloseBlogsId = $this->oMapper->getCloseBlogsId($oUser);

            if ($oUser) {
                // * Получаем массив идентификаторов блогов, которые являются откытыми для данного пользователя
                $aOpenBlogsId = $this->getBlogUsersByUserId($nUserId, null, true);

                // * Получаем закрытые блоги, где пользователь является автором
                $aCloseBlogTypes = $this->getCloseBlogTypes($oUser);
                if ($aCloseBlogTypes) {
                    $aOwnerBlogs = $this->getBlogsByFilter(
                        array(
                            'type' => $aCloseBlogTypes,
                            'user_owner_id' => $nUserId,
                        ),
                        array(), 1, 1000, array()
                    );
                    $aOwnerBlogsId = array_keys($aOwnerBlogs['collection']);
                    $aCloseBlogsId = array_diff($aCloseBlogsId, $aOpenBlogsId, $aOwnerBlogsId);
                }
            }

            // * Сохраняем в кеш
            if ($oUser) {
                \E::Module('Cache')->set(
                    $aCloseBlogsId, $sCacheKey,
                    array('blog_new', 'blog_update', "blog_relation_change_{$nUserId}"), 'P1D'
                );
            } else {
                \E::Module('Cache')->set(
                    $aCloseBlogsId, $sCacheKey, array('blog_new', 'blog_update'), 'P3D'
                );
            }
        }
        return $aCloseBlogsId;
    }

    /**
     * Удаляет блог
     *
     * @param   int|array $aBlogsId   ID блога|массив ID блогов
     *
     * @return  bool
     */
    public function deleteBlog($aBlogsId) {

        // Получаем массив ID, если передан объект или массив объектов
        $aBlogsId = $this->_entitiesId($aBlogsId);
        if ($aBlogsId) {
            // * Получаем идентификаторы топиков блога. Удаляем топики блога.
            // * При удалении топиков удаляются комментарии к ним и голоса.
            $aTopicsId = \E::Module('Topic')->getTopicsByBlogId($aBlogsId);

            // * Если блог не удален, возвращаем false
            if (!$this->oMapper->deleteBlog($aBlogsId)) {
                return false;
            }

            if ($aTopicsId) {
                // * Удаляем топики
                \E::Module('Topic')->DeleteTopics($aTopicsId);
            }

            // * Удаляем связи пользователей блога.
            $this->oMapper->deleteBlogUsersByBlogId($aBlogsId);

            // * Удаляем голосование за блог
            \E::Module('Vote')->DeleteVoteByTarget($aBlogsId, 'blog');

            // * Чистим кеш
            \E::Module('Cache')->cleanByTags(array('blog_update', 'topic_update', 'comment_online_update_topic', 'comment_update'));
            foreach ($aBlogsId as $nBlogId) {
                \E::Module('Cache')->cleanByTags(array("blog_relation_change_blog_{$nBlogId}"));
                \E::Module('Cache')->delete("blog_{$nBlogId}");
            }
        }

        return true;
    }

    /**
     * Удаление блогов по ID владельцев
     *
     * @param array $aUsersId
     *
     * @return bool
     */
    public function deleteBlogsByUsers($aUsersId)
    {
        $aBlogsId = $this->oMapper->getBlogsIdByOwnersId($aUsersId);
        return $this->deleteBlog($aBlogsId);
    }

    /**
     * Загружает аватар в блог
     *
     * @param array                 $aFile - Массив $_FILES при загрузке аватара
     * @param ModuleBlog_EntityBlog $oBlog - Блог
     *
     * @return bool
     */
    public function uploadBlogAvatar($aFile, $oBlog)
    {
        $sTmpFile = \E::Module('Uploader')->UploadLocal($aFile);
        if ($sTmpFile && ($oImg = \E::Module('Img')->CropSquare($sTmpFile))) {
            if ($sTmpFile = $oImg->Save($sTmpFile)) {
                if ($oStoredFile = \E::Module('Uploader')->StoreImage($sTmpFile, 'blog_avatar', $oBlog->getId())) {
                    return $oStoredFile->GetUrl();
                }
            }
        }

        // * В случае ошибки, возвращаем false
        return false;
    }

    /**
     * Удаляет аватар блога с сервера
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     */
    public function deleteBlogAvatar($oBlog)
    {
        $this->deleteAvatar($oBlog);
    }

    /**
     * Удаляет аватар блога с сервера
     *
     * @param ModuleBlog_EntityBlog $oBlog    Блог
     */
    public function deleteAvatar($oBlog) {

        if ($oBlog) {
            // * Если аватар есть, удаляем его и его рейсайзы (старая схема)
            if ($sUrl = $oBlog->getAvatar()) {
                \E::Module('Img')->delete(\E::Module('Uploader')->Url2Dir($sUrl));
            }
            // Deletes blog avatar from media resources
            \E::Module('Media')->DeleteMresourcesRelByTarget('blog_avatar', $oBlog->getid());
        }
    }

    /**
     * Пересчет количества топиков в блогах
     *
     * @return bool
     */
    public function recalculateCountTopic()
    {
        $bResult = $this->oMapper->RecalculateCountTopic();
        if ($bResult) {
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('blog_update'));
        }
        return $bResult;
    }

    /**
     * Пересчет количества топиков в конкретном блоге
     *
     * @param int|array $aBlogsId - ID of blog | IDs of blogs
     *
     * @return bool
     */
    public function recalculateCountTopicByBlogId($aBlogsId)
    {
        $aBlogsId = $this->_entitiesId($aBlogsId);
        if ($aBlogsId) {
            $bResult = $this->oMapper->RecalculateCountTopic($aBlogsId);
            if ($bResult) {
                //чистим зависимые кеши
                if (is_array($aBlogsId)) {
                    $aCacheTags = array('blog_update');
                    foreach ($aBlogsId as $iBlogId) {
                        \E::Module('Cache')->delete("blog_{$iBlogId}");
                        $aCacheTags[] = "blog_update_{$iBlogId}";
                    }
                    \E::Module('Cache')->cleanByTags($aCacheTags);
                } else {
                    \E::Module('Cache')->cleanByTags(array('blog_update', "blog_update_{$aBlogsId}"));
                    \E::Module('Cache')->delete("blog_{$aBlogsId}");
                }
                return $bResult;
            }
        }
        return true;
    }

    /**
     * Алиас для корректной работы ORM
     *
     * @param array $aBlogId    Список ID блогов
     *
     * @return array
     */
    public function getBlogItemsByArrayId($aBlogId)
    {
        return $this->getBlogsByArrayId($aBlogId);
    }

    /**
     * Возвращает список доступных типов для определенного действия
     *
     * @param ModuleUser_EntityUser $oUser
     * @param string                $sAction
     * @param bool                  $bTypeCodesOnly
     *
     * @return array
     */
    public function getAllowBlogTypes($oUser, $sAction, $bTypeCodesOnly = false)
    {
        $aFilter = [
            'exclude_type' => in_array($sAction, ['add', 'list']) ? 'personal' : null,
            'is_active' => true,
        ];

        if ($sAction && !in_array($sAction, ['add', 'list', 'write'])) {
            return [];
        }

        if (!$oUser) {
            // Если пользователь не задан
            if ($sAction == 'add') {
                $aFilter['allow_add'] = true;
            } elseif ($sAction == 'list') {
                $aFilter['allow_list'] = true;
            }
        } elseif ($oUser && !$oUser->isAdministrator() && !$oUser->isModerator()) {
            // Если пользователь задан и он не админ, то надо учитывать рейтинг
            if ($sAction == 'add') {
                $aFilter['allow_add'] = true;
                $aFilter['min_rate_add'] = $oUser->getUserRating();
            } elseif ($sAction == 'list') {
                $aFilter['allow_list'] = true;
                $aFilter['min_rate_list'] = $oUser->getUserRating();
            } elseif ($sAction == 'write') {
                $aFilter['min_rate_write'] = $oUser->getUserRating();
            }
        }
        $aBlogTypes = $this->getBlogTypes($aFilter, $bTypeCodesOnly);

        return $aBlogTypes;
    }

    /**
     * Returns types of blogs which user can read (without personal subscriptions/membership)
     *
     * @param object|null|int $xUser - If 0 then types for guest
     *
     * @return array
     */
    public function getOpenBlogTypes($xUser = null) {

        if (null === $xUser) {
            $iUserId = \E::userId();
            if (!$iUserId) {
                $iUserId = 0;
            }
        } else {
            $iUserId = (int)(is_object($xUser) ? $xUser->getId() : $xUser);
        }
        $sCacheKey = 'blog_types_open_' . ($iUserId ? 'user' : 'guest');
        if (false === ($aBlogTypes = \E::Module('Cache')->get($sCacheKey, 'tmp'))) {
            if ($this->oUserCurrent) {
                $aFilter = array(
                    'acl_read' => ModuleBlog::BLOG_USER_ACL_GUEST | ModuleBlog::BLOG_USER_ACL_USER,
                );
            } else {
                $aFilter = array(
                    'acl_read' => ModuleBlog::BLOG_USER_ACL_GUEST,
                );
            }
            // Blog types for guest and all users
            $aBlogTypes = $this->getBlogTypes($aFilter, true);
            \E::Module('Cache')->set($aBlogTypes, $sCacheKey, array('blog_update', 'blog_new'), 'P30D', 'tmp');
        }
        return $aBlogTypes;
    }

    /**
     * Returns types of blogs which user cannot read (without personal subscriptions/membership)
     *
     * @param ModuleUser_EntityUser $xUser
     *
     * @return array
     */
    public function getCloseBlogTypes($xUser = null) {

        if (null === $xUser) {
            $iUserId = \E::userId();
        } else {
            $iUserId = (int)(is_object($xUser) ? $xUser->getId() : $xUser);
        }
        $sCacheKey = 'blog_types_close_' . ($iUserId ? 'user' : 'guest');
        if (false === ($aBlogTypes = \E::Module('Cache')->get($sCacheKey, 'tmp'))) {
            if ($this->oUserCurrent) {
                $aFilter = array(
                    'acl_read' => ModuleBlog::BLOG_USER_ACL_MEMBER,
                );
            } else {
                $aFilter = array(
                    'acl_read' => ModuleBlog::BLOG_USER_ACL_USER | ModuleBlog::BLOG_USER_ACL_MEMBER,
                );
            }
            // Blog types for guest and all users
            $aBlogTypes = $this->getBlogTypes($aFilter, true);
            \E::Module('Cache')->set($aBlogTypes, $sCacheKey, array('blog_update', 'blog_new'), 'P30D', 'tmp');
        }
        return $aBlogTypes;
    }

    /**
     * Получить типы блогов
     *
     * @param   array   $aFilter
     * @param   bool    $bTypeCodesOnly
     *
     * @return  ModuleBlog_EntityBlogType[]
     */
    public function getBlogTypes($aFilter = [], $bTypeCodesOnly = false) {

        $aResult = [];
        $sCacheKey = 'blog_types';
        if (false === ($data = \E::Module('Cache')->get($sCacheKey, 'tmp'))) {
            if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
                /** @var ModuleBlog_EntityBlogType[] $data */
                $data = $this->oMapper->getBlogTypes();
                \E::Module('Cache')->set($data, $sCacheKey, array('blog_update', 'blog_new'), 'P30D');
            }
            \E::Module('Cache')->set($data, $sCacheKey, array('blog_update', 'blog_new'), 'P30D', 'tmp');
        }
        $aBlogTypes = [];
        if ($data) {
            foreach ($data as $nKey => $oBlogType) {
                $bOk = true;
                if (isset($aFilter['include_type'])) {
                    $bOk = $bOk && ($aFilter['include_type'] == $oBlogType->GetTypeCode());
                    if (!$bOk) continue;
                }
                if (isset($aFilter['exclude_type'])) {
                    $bOk = $bOk && ($aFilter['exclude_type'] != $oBlogType->GetTypeCode());
                    if (!$bOk) continue;
                }
                if (isset($aFilter['is_active'])) {
                    $bOk = $bOk && $oBlogType->IsActive();
                    if (!$bOk) continue;
                }
                if (isset($aFilter['not_active'])) {
                    $bOk = $bOk && !$oBlogType->IsActive();
                    if (!$bOk) continue;
                }
                if (isset($aFilter['allow_add'])) {
                    $bOk = $bOk && $oBlogType->IsAllowAdd();
                    if (!$bOk) continue;
                }
                if (isset($aFilter['allow_list'])) {
                    $bOk = $bOk && $oBlogType->IsShowTitle();
                    if (!$bOk) continue;
                }
                if (isset($aFilter['min_rate_add'])) {
                    $bOk = $bOk && ($oBlogType->GetMinRateAdd() <= $aFilter['min_rate_add']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['min_rate_list'])) {
                    $bOk = $bOk && ($oBlogType->GetMinRateList() <= $aFilter['min_rate_list']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['min_rate_write'])) {
                    $bOk = $bOk && ($oBlogType->GetMinRateWrite() <= $aFilter['min_rate_write']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['min_rate_read'])) {
                    $bOk = $bOk && ($oBlogType->GetMinRateRead() <= $aFilter['min_rate_read']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['min_rate_comment'])) {
                    $bOk = $bOk && ($oBlogType->GetMinRateComment() <= $aFilter['min_rate_comment']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['acl_write'])) {
                    $bOk = $bOk && ($oBlogType->GetAclWrite() & $aFilter['acl_write']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['acl_read'])) {
                    $bOk = $bOk && ($oBlogType->GetAclRead() & $aFilter['acl_read']);
                    if (!$bOk) continue;
                }
                if (isset($aFilter['acl_comment'])) {
                    $bOk = $bOk && ($oBlogType->GetAclComment() & $aFilter['acl_comment']);
                    if (!$bOk) continue;
                }
                // Проверим, есть ли в данном типе блога вообще типы контента
                /** @var ModuleTopic_EntityContentType[] $aContentTypes */
                if ($aContentTypes = $oBlogType->getContentTypes()) {
                    foreach ($aContentTypes as $iCTId => $oContentType) {
                        // Тип контента не активирован
                        if (!$oContentType->getActive()) {
                            unset($aContentTypes[$iCTId]);
                        }
                        // Тип контента включен, но создавать могу только админы
                        if (!$oContentType->isAccessible()) {
                            unset($aContentTypes[$iCTId]);
                        }
                    }
                }
                // Проверим существующие типы контента на возможность создания пользователей

                if ($bOk) {
                    $aBlogTypes[$oBlogType->GetTypeCode()] = $oBlogType;
                }
                $data[$nKey] = null;
            }
        }
        if ($aBlogTypes) {
            if ($bTypeCodesOnly) {
                $aResult = array_keys($aBlogTypes);
            } else {
                $aResult = $aBlogTypes;
            }
        }
        return $aResult;
    }

    /**
     * Получить объект типа блога по его ID
     *
     * @param int $iBlogTypeId
     *
     * @return null|ModuleBlog_EntityBlogType
     */
    public function getBlogTypeById($iBlogTypeId) {

        $sCacheKey = 'blog_type_' . $iBlogTypeId;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getBlogTypeById($iBlogTypeId);
            \E::Module('Cache')->set($data, $sCacheKey, array('blog_update', 'blog_new'), 'PT30M');
        }
        return $data;
    }

    /**
     * Получить объект типа блога по его коду
     *
     * @param string $sTypeCode
     *
     * @return null|ModuleBlog_EntityBlogType
     */
    public function getBlogTypeByCode($sTypeCode) {

        $aBlogTypes = $this->getBlogTypes();
        if (isset($aBlogTypes[$sTypeCode])) {
            return $aBlogTypes[$sTypeCode];
        }
        return null;
    }

    /**
     * @return ModuleBlog_EntityBlogType|null
     */
    public function getBlogTypeDefault() {

        $oBlogType = $this->getBlogTypeByCode('open');
        return $oBlogType;
    }

    /**
     * Добавить тип блога
     *
     * @param ModuleBlog_EntityBlogType$oBlogType
     *
     * @return bool
     */
    public function addBlogType($oBlogType) {

        $nId = $this->oMapper->addBlogType($oBlogType);
        if ($nId) {
            $oBlogType->SetId($nId);
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('blog_update'));
            \E::Module('Cache')->delete("blog_type_{$oBlogType->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Обновить тип блога
     *
     * @param ModuleBlog_EntityBlogType$oBlogType
     *
     * @return bool
     */
   public function updateBlogType($oBlogType) {

        $bResult = $this->oMapper->updateBlogType($oBlogType);
        if ($bResult) {
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array('blog_update'));
            \E::Module('Cache')->delete("blog_type_{$oBlogType->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Удалить тип блога
     *
     * @param ModuleBlog_EntityBlogType$oBlogType
     *
     * @return bool
     */
    public function deleteBlogType($oBlogType) {

        $aInfo = $this->oMapper->getBlogCountsByTypes($oBlogType->GetTypeCode());
        // Если есть блоги такого типа, то НЕ удаляем тип
        if (empty($aInfo[$oBlogType->GetTypeCode()])) {
            $bResult = $this->oMapper->deleteBlogType($oBlogType->GetTypeCode());
            if ($bResult) {
                //чистим зависимые кеши
                \E::Module('Cache')->cleanByTags(array('blog_update'));
                \E::Module('Cache')->delete("blog_type_{$oBlogType->getId()}");
                return true;
            }
        }
        return false;
    }

    /**
     * Активен ли этот тип блога
     *
     * @param string $sBlogType
     *
     * @return bool
     */
    public function blogTypeEnabled($sBlogType) {

        $oBlogType = $this->getBlogTypeByCode($sBlogType);
        return $oBlogType && $oBlogType->IsActive();
    }

    /**
     * Статистка блогов
     *
     * @param array $aExcludeTypes
     *
     * @return array
     */
    public function getBlogsData($aExcludeTypes = array('personal')) {

        return $this->oMapper->getBlogsData($aExcludeTypes);
    }

    /*********************************************************/

    public function getBlogsId($aFilter) {


    }
}

// EOF