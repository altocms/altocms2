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
 * Модуль для работы с комментариями
 *
 * @package modules.comment
 * @since 1.0
 */
class ModuleComment extends Module 
{
    /**
     * Объект маппера
     *
     * @var ModuleComment_MapperComment
     */
    protected $oMapper;
    /**
     * Объект текущего пользователя
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;

    protected $aAdditionalData = ['vote', 'target', 'favourite', 'user' => []];

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
     * Получить коммент по ID
     *
     * @param int $nId    ID комментария
     *
     * @return ModuleComment_EntityComment|null
     */
    public function getCommentById($nId) 
    {
        if (!(int)$nId) {
            return null;
        }
        $aComments = $this->getCommentsAdditionalData($nId);
        if (isset($aComments[$nId])) {
            return $aComments[$nId];
        }
        return null;
    }

    /**
     * Получает уникальный коммент, это помогает спастись от дублей комментов
     *
     * @param int    $nTargetId      ID владельца комментария
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $nUserId        ID пользователя
     * @param int    $nCommentPid    ID родительского комментария
     * @param string $sHash          Хеш строка текста комментария
     *
     * @return ModuleComment_EntityComment|null
     */
    public function getCommentUnique($nTargetId, $sTargetType, $nUserId, $nCommentPid, $sHash) 
    {
        $nId = $this->oMapper->getCommentUnique($nTargetId, $sTargetType, $nUserId, $nCommentPid, $sHash);

        return $this->getCommentById($nId);
    }

    /**
     * Получить все комменты
     *
     * @param string $sTargetType             Тип владельца комментария
     * @param int    $iPage                   Номер страницы
     * @param int    $iPerPage                Количество элементов на страницу
     * @param array  $aExcludeTarget          Список ID владельцев, которые необходимо исключить из выдачи
     * @param array  $aExcludeParentTarget    Список ID родителей владельцев, которые необходимо исключить из выдачи,
     *                                        например, исключить комментарии топиков к определенным блогам(закрытым)
     *
     * @return array('collection'=>array, 'count'=>int)
     */
    public function getCommentsAll($sTargetType, $iPage, $iPerPage, $aExcludeTarget = array(), $aExcludeParentTarget = array())
    {

        $sCacheKey = "comment_all_" . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getCommentsIdByTargetType($sTargetType, $iCount, $iPage, $iPerPage, $aExcludeTarget, $aExcludeParentTarget),
                'count'      => $iCount,
            );
            \E::Module('Cache')->set($data, $sCacheKey, array("comment_new_{$sTargetType}", "comment_update_status_{$sTargetType}"), 'P1D');
        }
        if ($data['collection']) {
            $data['collection'] = $this->getCommentsAdditionalData($data['collection'], array('target', 'favourite', 'user' => array()));
        }
        return $data;
    }

    /**
     * Получает дополнительные данные(объекты) для комментов по их ID
     *
     * @param array|int  $aCommentId      Список ID комментов
     * @param array|null $aAllowData      Список типов дополнительных данных, которые нужно получить для комментариев
     * @param array      $aAdditionalData Predefined additional data
     *
     * @return array
     */
    public function getCommentsAdditionalData($aCommentId, $aAllowData = null, $aAdditionalData = [])
    {
        if (!$aCommentId) {
            return [];
        }
        if (null === $aAllowData) {
            $aAllowData = $this->aAdditionalData;
        }
        $aAllowData = F::Array_FlipIntKeys($aAllowData);
        if (!is_array($aCommentId)) {
            $aCommentId = [$aCommentId];
        }

        // * Получаем комменты
        $aComments = $this->getCommentsByArrayId($aCommentId);

        // * Формируем ID дополнительных данных, которые нужно получить
        $aUserId = [];
        $aTargetTypeId = [];
        foreach ($aComments as $oComment) {
            if (isset($aAllowData['user'])) {
                $aUserId[] = $oComment->getUserId();
            }
            if (isset($aAllowData['target'])) {
                $aTargetTypeId[$oComment->getTargetType()][] = $oComment->getTargetId();
            }
        }

        // * Получаем дополнительные данные
        if ($aUserId) {
            $aUsers = (isset($aAllowData['user']) && is_array($aAllowData['user']))
                ?   \E::Module('User')->getUsersAdditionalData($aUserId, $aAllowData['user'])
                :   \E::Module('User')->getUsersAdditionalData($aUserId);
        }

        // * В зависимости от типа target_type достаем данные
        $aTargets = [];
        foreach ($aTargetTypeId as $sTargetType => $aTargetId) {
            if (isset($aAdditionalData['target'][$sTargetType])) {
                // predefined targets' data
                $aTargets[$sTargetType] = $aAdditionalData['target'][$sTargetType];
            } else {
                if (isset($aTargetTypeId['topic']) && $aTargetTypeId['topic']) {
                    // load targets' data
                    $aTargets['topic'] = \E::Module('Topic')->getTopicsAdditionalData(
                        $aTargetTypeId['topic'], ['blog' => ['owner' => [], 'relation_user'], 'user' => []]
                    );
                } else {
                    // we don't know how to get targets' data
                    $aTargets['topic'] = [];
                }
            }
        }

        if (isset($aAllowData['vote']) && $this->oUserCurrent) {
            $aVote = \E::Module('Vote')->getVoteByArray($aCommentId, 'comment', $this->oUserCurrent->getId());
        } else {
            $aVote = [];
        }

        if (isset($aAllowData['favourite']) && $this->oUserCurrent) {
            $aFavouriteComments = \E::Module('Favourite')->getFavouritesByArray($aCommentId, 'comment', $this->oUserCurrent->getId());
        } else {
            $aFavouriteComments = [];
        }

        // * Добавляем данные к результату
        foreach ($aComments as $oComment) {
            if (isset($aUsers[$oComment->getUserId()])) {
                $oComment->setUser($aUsers[$oComment->getUserId()]);
            } else {
                $oComment->setUser(null); // или $oComment->setUser(new ModuleUser_EntityUser());
            }
            if (isset($aTargets[$oComment->getTargetType()][$oComment->getTargetId()])) {
                $oComment->setTarget($aTargets[$oComment->getTargetType()][$oComment->getTargetId()]);
            } else {
                $oComment->setTarget(null);
            }
            if (isset($aVote[$oComment->getId()])) {
                $oComment->setVote($aVote[$oComment->getId()]);
            } else {
                $oComment->setVote(null);
            }
            if (isset($aFavouriteComments[$oComment->getId()])) {
                $oComment->setIsFavourite(true);
            } else {
                $oComment->setIsFavourite(false);
            }
        }
        return $aComments;
    }

    /**
     * Список комментов по ID
     *
     * @param array|int $aCommentsId    Список ID комментариев
     *
     * @return array
     */
    public function getCommentsByArrayId($aCommentsId)
    {
        if (!$aCommentsId) {
            return [];
        }

        if (!is_array($aCommentsId)) {
            $aCommentsId = [$aCommentsId];
        }
        $aCommentsId = array_unique($aCommentsId);

        $sTotalCacheKey = $this->makeCacheKey($aCommentsId);
        if (false !== ($data = \E::Module('Cache')->get($sTotalCacheKey))) {
            return $data;
        }

        $aComments = [];
        $aCommentIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aCommentsId, 'comment_');
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * Проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aComments[$data[$sKey]->getId()] = $data[$sKey];
                    } else {
                        $aCommentIdNotNeedQuery[] = $aCommentsId[$iIndex];
                    }
                }
            }
        }
        // * Смотрим каких комментов не было в кеше и делаем запрос в БД
        $aCommentIdNeedQuery = array_diff($aCommentsId, array_keys($aComments));
        $aCommentIdNeedQuery = array_diff($aCommentIdNeedQuery, $aCommentIdNotNeedQuery);
        $aCommentIdNeedStore = $aCommentIdNeedQuery;

        if ($aCommentIdNeedQuery) {
            if ($data = $this->oMapper->getCommentsByArrayId($aCommentIdNeedQuery)) {
                foreach ($data as $oComment) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aComments[$oComment->getId()] = $oComment;
                    \E::Module('Cache')->set($oComment, "comment_{$oComment->getId()}", [], 'P4D');
                    $aCommentIdNeedStore = array_diff($aCommentIdNeedStore, [$oComment->getId()]);
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aCommentIdNeedStore as $nId) {
            \E::Module('Cache')->set(null, "comment_{$nId}", [], 'P4D');
        }
        // * Сортируем результат согласно входящему массиву
        $aComments = F::Array_SortByKeysArray($aComments, $aCommentsId);

        \E::Module('Cache')->set($aComments, $sTotalCacheKey, ['comment_update'], 'P4D');

        return $aComments;
    }

    /**
     * Получить все комменты сгрупированные по типу(для вывода прямого эфира)
     *
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $iLimit         Количество элементов
     *
     * @return array
     */
    public function getCommentsOnline($sTargetType, $iLimit) {
        /**
         * Исключаем из выборки идентификаторы закрытых блогов (target_parent_id)
         */
        $aCloseBlogs = ($this->oUserCurrent)
            ? \E::Module('Blog')->getInaccessibleBlogsByUser($this->oUserCurrent)
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        $s = serialize($aCloseBlogs);

        $sCacheKey = "comment_online_{$sTargetType}_{$s}_{$iLimit}";
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getCommentsIdOnline($sTargetType, $aCloseBlogs, $iLimit);
            \E::Module('Cache')->set($data, $sCacheKey, ["comment_online_update_{$sTargetType}"], 'P1D');
        }
        if ($data) {
            $data = $this->getCommentsAdditionalData($data);
        }
        return $data;
    }

    /**
     * Получить комменты по юзеру
     *
     * @param  int    $iUserId        ID пользователя
     * @param  string $sTargetType    Тип владельца комментария
     * @param  int    $iPage          Номер страницы
     * @param  int    $iPerPage       Количество элементов на страницу
     *
     * @return array
     */
    public function getCommentsByUserId($iUserId, $sTargetType, $iPage, $iPerPage) {
        /**
         * Исключаем из выборки идентификаторы закрытых блогов
         */
        $aCloseBlogs = ($this->oUserCurrent && $iUserId == $this->oUserCurrent->getId())
            ? array()
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        $sCacheKey = "comment_user_{$iUserId}_{$sTargetType}_{$iPage}_{$iPerPage}_" . serialize($aCloseBlogs);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->GetCommentsIdByUserId($iUserId, $sTargetType, $iCount, $iPage, $iPerPage, array(), $aCloseBlogs),
                'count'      => $iCount,
            );
            \E::Module('Cache')->set(
                $data, $sCacheKey,
                array("comment_new_user_{$iUserId}_{$sTargetType}", "comment_update_status_{$sTargetType}"), 'P2D'
            );
        }
        if ($data['collection']) {
            $data['collection'] = $this->getCommentsAdditionalData($data['collection']);
        }
        return $data;
    }

    /**
     * Получает количество комментариев одного пользователя
     *
     * @param  int    $iUserId     ID пользователя
     * @param  string $sTargetType Тип владельца комментария
     *
     * @return int
     */
    public function getCountCommentsByUserId($iUserId, $sTargetType) {
        /**
         * Исключаем из выборки идентификаторы закрытых блогов
         */
        if ($this->oUserCurrent && $iUserId == $this->oUserCurrent->getId()) {
            $aCloseBlogs = \E::Module('Blog')->getInaccessibleBlogsByUser();
        } else {
            $aCloseBlogs = [];
        }

        $sCacheKey = "comment_count_user_{$iUserId}_{$sTargetType}_" . serialize($aCloseBlogs);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getCountCommentsByUserId($iUserId, $sTargetType, array(), $aCloseBlogs);
            \E::Module('Cache')->set(
                $data, $sCacheKey,
                array("comment_new_user_{$iUserId}_{$sTargetType}", "comment_update_status_{$sTargetType}"), 'P2D'
            );
        }
        return $data;
    }

    /**
     * Получить комменты по рейтингу и дате
     *
     * @param  string $sDate          Дата за которую выводить рейтинг, т.к. кеширование происходит по дате, то дату лучше передавать с точностью до часа (минуты и секунды как 00:00)
     * @param  string $sTargetType    Тип владельца комментария
     * @param  int    $iLimit         Количество элементов
     *
     * @return array
     */
    public function getCommentsRatingByDate($sDate, $sTargetType, $iLimit = 20) {
        /**
         * Выбираем топики, комметарии к которым являются недоступными для пользователя
         */
        $aCloseBlogs = ($this->oUserCurrent)
            ? \E::Module('Blog')->getInaccessibleBlogsByUser($this->oUserCurrent)
            : \E::Module('Blog')->getInaccessibleBlogsByUser();

        $sCacheKey = "comment_rating_{$sDate}_{$sTargetType}_{$iLimit}_" . serialize($aCloseBlogs);
        /**
         * Т.к. время передаётся с точностью 1 час то можно по нему замутить кеширование
         */
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getCommentsIdByRatingAndDate($sDate, $sTargetType, $iLimit, array(), $aCloseBlogs);
            \E::Module('Cache')->set(
                $data, $sCacheKey,
                array("comment_new_{$sTargetType}", "comment_update_status_{$sTargetType}",
                      "comment_update_rating_{$sTargetType}"), 'P2D'
            );
        }
        if ($data) {
            $data = $this->getCommentsAdditionalData($data);
        }
        return $data;
    }

    /**
     * Получить комменты по владельцу
     *
     * @param  int|object $xTarget     ID/экземпляр владельца комментария
     * @param  string     $sTargetType Тип владельца комментария
     * @param  int        $iPage       Номер страницы
     * @param  int        $iPerPage    Количество элементов на страницу
     *
     * @return array('comments'=>array,'iMaxIdComment'=>int)
     */
    public function getCommentsByTargetId($xTarget, $sTargetType, $iPage = 1, $iPerPage = 0) {

        if (\C::get('module.comment.use_nested')) {
            return $this->getCommentsTreeByTargetId($xTarget, $sTargetType, $iPage, $iPerPage);
        }

        if (is_object($xTarget)) {
            $iTargetId = $xTarget->getId();
            $oTarget = $xTarget;
        } else {
            $iTargetId = (int)$xTarget;
            $oTarget = null;
        }

        $sCacheKey = "comment_target_{$iTargetId}_{$sTargetType}";
        if (false === ($aCommentsRec = \E::Module('Cache')->get($sCacheKey))) {
            $aCommentsRow = $this->oMapper->getCommentsByTargetId($iTargetId, $sTargetType);
            if (count($aCommentsRow)) {
                $aCommentsRec = $this->BuildCommentsRecursive($aCommentsRow);
            }
            \E::Module('Cache')->set($aCommentsRec, $sCacheKey, array("comment_new_{$sTargetType}_{$iTargetId}"), 'P2D');
        }
        if (!isset($aCommentsRec['comments'])) {
            return array('comments' => array(), 'iMaxIdComment' => 0);
        }
        $aComments = $aCommentsRec;
        $aCommentsId = array_keys($aCommentsRec['comments']);
        if ($aCommentsId) {
            if ($oTarget) {
                $aAdditionalData = array(
                    'target' => array(
                        $sTargetType => array($iTargetId => $oTarget),
                    ),
                );
            } else {
                $aAdditionalData = [];
            }
            $aComments['comments'] = $this->getCommentsAdditionalData($aCommentsId, null, $aAdditionalData);
            foreach ($aComments['comments'] as $oComment) {
                $oComment->setLevel($aCommentsRec['comments'][$oComment->getId()]);
            }
        }
        return $aComments;
    }

    /**
     * Возвращает массив ID комментариев
     *
     * @param   array   $aTargetsId
     * @param   string  $sTargetType
     * @return  array
     */
    public function getCommentsIdByTargetsId($aTargetsId, $sTargetType) {

        return $this->oMapper->getCommentsIdByTargetsId($aTargetsId, $sTargetType);
    }

    /**
     * Получает комменты используя nested set
     *
     * @param int|object $xTarget     ID/экземпляр владельца коммента
     * @param string     $sTargetType Тип владельца комментария
     * @param  int       $iPage       Номер страницы
     * @param  int       $iPerPage    Количество элементов на страницу
     *
     * @return array('comments'=>array, 'iMaxIdComment'=>int, 'count'=>int)
     */
    public function getCommentsTreeByTargetId($xTarget, $sTargetType, $iPage = 1, $iPerPage = 0) {

        if (is_object($xTarget)) {
            $iTargetId = $xTarget->getId();
            $oTarget = $xTarget;
        } else {
            $iTargetId = (int)$xTarget;
            $oTarget = null;
        }
        if (!Config::get('module.comment.nested_page_reverse')
            && $iPerPage
            && $iCountPage = ceil($this->getCountCommentsRootByTargetId($iTargetId, $sTargetType) / $iPerPage)
        ) {
            $iPage = $iCountPage - $iPage + 1;
        }
        $iPage = $iPage < 1 ? 1 : $iPage;
        $sCacheKey = "comment_tree_target_{$iTargetId}_{$sTargetType}_{$iPage}_{$iPerPage}";
        if (false === ($aReturn = \E::Module('Cache')->get($sCacheKey))) {
            // * Нужно или нет использовать постраничное разбиение комментариев
            if ($iPerPage) {
                $aComments = $this->oMapper->getCommentsTreePageByTargetId($iTargetId, $sTargetType, $iCount, $iPage, $iPerPage);
            } else {
                $aComments = $this->oMapper->getCommentsTreeByTargetId($iTargetId, $sTargetType);
                $iCount = count($aComments);
            }
            $iMaxIdComment = count($aComments) ? max($aComments) : 0;
            $aReturn = array('comments' => $aComments, 'iMaxIdComment' => $iMaxIdComment, 'count' => $iCount);
            \E::Module('Cache')->set(
                $aReturn, $sCacheKey,
                array("comment_new_{$sTargetType}_{$iTargetId}"), 'P2D'
            );
        }
        if ($aReturn['comments']) {
            if ($oTarget) {
                // If there'is target object in arguments then sets in as predefined data
                $aAdditionalData = array(
                    'target' => array(
                        $sTargetType => array($iTargetId => $oTarget),
                    ),
                );
            } else {
                $aAdditionalData = [];
            }
            $aReturn['comments'] = $this->getCommentsAdditionalData($aReturn['comments'], null, $aAdditionalData);
        }
        return $aReturn;
    }

    /**
     * Возвращает количество дочерних комментариев у корневого коммента
     *
     * @param int    $iTargetId      ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     *
     * @return int
     */
    public function getCountCommentsRootByTargetId($iTargetId, $sTargetType)
    {
        return $this->oMapper->getCountCommentsRootByTargetId($iTargetId, $sTargetType);
    }

    /**
     * Возвращает номер страницы, на которой расположен комментарий
     *
     * @param int                         $iTargetId            ID владельца коммента
     * @param string                      $sTargetType    Тип владельца комментария
     * @param ModuleComment_EntityComment $oComment       Объект комментария
     *
     * @return bool|int
     */
    public function getPageCommentByTargetId($iTargetId, $sTargetType, $oComment) {

        if (!Config::get('module.comment.nested_per_page')) {
            return 1;
        }
        if (is_numeric($oComment)) {
            if (!($oComment = $this->getCommentById($oComment))) {
                return false;
            }
            if (($oComment->getTargetId() != $iTargetId) || ($oComment->getTargetType() != $sTargetType)) {
                return false;
            }
        }
        // * Получаем корневого родителя
        if ($oComment->getPid()) {
            $oCommentRoot = $this->oMapper->getCommentRootByTargetIdAndChildren($iTargetId, $sTargetType, $oComment->getLeft());
            if (!$oCommentRoot) {
                return false;
            }
        } else {
            $oCommentRoot = $oComment;
        }
        $iCount = ceil(
            $this->oMapper->getCountCommentsAfterByTargetId($iTargetId, $sTargetType, $oCommentRoot->getLeft()) / \C::get('module.comment.nested_per_page')
        );

        if (!Config::get('module.comment.nested_page_reverse')
            && $iCountPage = ceil($this->getCountCommentsRootByTargetId($iTargetId, $sTargetType) / \C::get('module.comment.nested_per_page'))
        ) {
            $iCount = $iCountPage - $iCount + 1;
        }
        return $iCount ? $iCount : 1;
    }

    /**
     * Добавляет коммент
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool|ModuleComment_EntityComment
     */
    public function addComment($oComment) {

        if (\C::get('module.comment.use_nested')) {
            $iCommentId = $this->oMapper->addCommentTree($oComment);
            \E::Module('Cache')->cleanByTags(array("comment_update"));
        } else {
            $iCommentId = $this->oMapper->addComment($oComment);
        }
        if ($iCommentId) {
            $oComment->setId($iCommentId);
            if ($oComment->getTargetType() == 'topic') {
                \E::Module('Topic')->RecalcCountOfComments($oComment->getTargetId());
            }

            // Освежим хранилище картинок
            \E::Module('Media')->CheckTargetTextForImages(
                $oComment->getTargetType() . '_comment',
                $iCommentId,
                $oComment->getText()
            );

            if (\E::isUser()) {
                // * Сохраняем дату последнего коммента для юзера
                E::User()->setDateCommentLast(\F::Now());
                \E::Module('User')->Update(\E::User());
                // чистим зависимые кеши
                \E::Module('Cache')->cleanByTags(
                    array("comment_new", "comment_new_{$oComment->getTargetType()}",
                          "comment_new_user_{$oComment->getUserId()}_{$oComment->getTargetType()}",
                          "comment_new_{$oComment->getTargetType()}_{$oComment->getTargetId()}")
                );
            } else {
                // чистим зависимые кеши
                \E::Module('Cache')->cleanByTags(
                    array("comment_new", "comment_new_{$oComment->getTargetType()}",
                          "comment_new_{$oComment->getTargetType()}_{$oComment->getTargetId()}")
                );
            }

            return $oComment;
        }
        return false;
    }

    /**
     * Обновляет коммент
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool
     */
    public function updateComment($oComment)
    {
        if ($this->oMapper->updateComment($oComment)) {
            // Освежим хранилище картинок
            \E::Module('Media')->CheckTargetTextForImages(
                $oComment->getTargetType() . '_comment',
                $oComment->getId(),
                $oComment->getText()
            );

            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(
                array("comment_update", "comment_update_{$oComment->getTargetType()}_{$oComment->getTargetId()}")
            );
            \E::Module('Cache')->delete("comment_{$oComment->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Обновляет рейтинг у коммента
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool
     */
    public function updateCommentRating($oComment)
    {
        if ($this->oMapper->updateComment($oComment)) {
            //чистим зависимые кеши
            \E::Module('Cache')->cleanByTags(array("comment_update_rating_{$oComment->getTargetType()}"));
            \E::Module('Cache')->delete("comment_{$oComment->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Обновляет статус у коммента - delete или publish
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool
     */
    public function updateCommentStatus($oComment)
    {
        if ($this->oMapper->updateComment($oComment)) {
            // * Если комментарий удаляется, удаляем его из прямого эфира
            if ($oComment->getDelete()) {
                $this->deleteCommentOnlineByArrayId(
                    $oComment->getId(), $oComment->getTargetType()
                );
            }
            // * Обновляем избранное
            \E::Module('Favourite')->setFavouriteTargetPublish($oComment->getId(), 'comment', !$oComment->getDelete());

            // * Чистим зависимые кеши
            if (\C::get('sys.cache.solid')) {
                \E::Module('Cache')->cleanByTags(['comment_update']);
            }
            \E::Module('Cache')->cleanByTags(["comment_update_status_{$oComment->getTargetType()}"]);
            \E::Module('Cache')->delete("comment_{$oComment->getId()}");
            return true;
        }
        return false;
    }

    /**
     * Устанавливает publish у коммента
     *
     * @param  int    $iTargetId      ID владельца коммента
     * @param  string $sTargetType    Тип владельца комментария
     * @param  int    $iPublish       Статус отображать комментарии или нет
     *
     * @return bool
     */
    public function setCommentsPublish($iTargetId, $sTargetType, $iPublish) {

        $aComments = $this->getCommentsByTargetId($iTargetId, $sTargetType);
        if (!$aComments || !isset($aComments['comments']) || count($aComments['comments']) == 0) {
            return false;
        }

        $bResult = false;
        /**
         * Если статус публикации успешно изменен, то меняем статус в отметке "избранное".
         * Если комментарии снимаются с публикации, удаляем их из прямого эфира.
         */
        if ($this->oMapper->SetCommentsPublish($iTargetId, $sTargetType, $iPublish)) {
            \E::Module('Favourite')->SetFavouriteTargetPublish(array_keys($aComments['comments']), 'comment', $iPublish);
            if ($iPublish != 1) {
                $this->deleteCommentOnlineByTargetId($iTargetId, $sTargetType);
            }
            $bResult = true;
        }
        \E::Module('Cache')->cleanByTags(array("comment_update_status_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Удаляет коммент из прямого эфира
     *
     * @param  int    $iTargetId      ID владельца коммента
     * @param  string $sTargetType    Тип владельца комментария
     *
     * @return bool
     */
    public function deleteCommentOnlineByTargetId($iTargetId, $sTargetType) {

        $bResult = $this->oMapper->deleteCommentOnlineByTargetId($iTargetId, $sTargetType);
        \E::Module('Cache')->cleanByTags(array("comment_online_update_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Добавляет новый коммент в прямой эфир
     *
     * @param ModuleComment_EntityCommentOnline $oCommentOnline    Объект онлайн комментария
     *
     * @return bool|int
     */
    public function addCommentOnline($oCommentOnline) {

        $bResult = $this->oMapper->addCommentOnline($oCommentOnline);
        \E::Module('Cache')->cleanByTags(array("comment_online_update_{$oCommentOnline->getTargetType()}"));

        return $bResult;
    }

    /**
     * Получить новые комменты для владельца
     *
     * @param int    $nTargetId      ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $nIdCommentLast ID последнего прочитанного комментария
     *
     * @return array('comments'=>array,'iMaxIdComment'=>int)
     */
    public function getCommentsNewByTargetId($nTargetId, $sTargetType, $nIdCommentLast) {

        $sCacheKey = "comment_target_{$nTargetId}_{$sTargetType}_{$nIdCommentLast}";
        if (false === ($aCommentsId = \E::Module('Cache')->get($sCacheKey))) {
            $aCommentsId = $this->oMapper->getCommentsIdNewByTargetId($nTargetId, $sTargetType, $nIdCommentLast);
            \E::Module('Cache')->set($aCommentsId, $sCacheKey, array("comment_new_{$sTargetType}_{$nTargetId}"), 'P1D');
        }
        $aComments = [];
        if (count($aCommentsId)) {
            $aComments = $this->getCommentsAdditionalData($aCommentsId);
        }
        if (!$aComments) {
            return array('comments' => array(), 'iMaxIdComment' => $nIdCommentLast);
        }

        $iMaxIdComment = max($aCommentsId);

        $aVars = array(
            'oUserCurrent' =>   \E::User(),
            'bOneComment'  => true,
        );
        if ($sTargetType !== 'topic') {
            $aVars['bNoCommentFavourites'] = true;
        }
        $aCommentsHtml = [];

        $bAllowToComment = false;
        if ($sTargetType === 'talk') {
            $bAllowToComment = TRUE;
        } elseif ($oUserCurrent = \E::User()) {
            $oComment = reset($aComments);
            if ($oComment->getTarget() && $oComment->getTarget()->getBlog()) {
                $iBlogId = $oComment->getTarget()->getBlog()->GetId();
                $bAllowToComment = \E::Module('Blog')->getBlogsAllowTo('comment', $oUserCurrent, $iBlogId, TRUE);
            }
        }
        $aVars['bAllowToComment'] = $bAllowToComment;
        foreach ($aComments as $oComment) {
            $aVars['oComment'] = $oComment;
            $sText = \E::Module('Viewer')->fetch($this->getTemplateCommentByTarget($nTargetId, $sTargetType), $aVars);
            $aCommentsHtml[] = array(
                'html' => $sText,
                'obj'  => $oComment,
            );
        }
        return array('comments' => $aCommentsHtml, 'iMaxIdComment' => $iMaxIdComment);
    }

    /**
     * Возвращает шаблон комментария для рендеринга
     * Плагин может переопределить данный метод и вернуть свой шаблон в зависимости от типа
     *
     * @param int    $iTargetId      ID объекта комментирования
     * @param string $sTargetType    Типа объекта комментирования
     *
     * @return string
     */
    public function getTemplateCommentByTarget($iTargetId, $sTargetType) {

        return 'comments/comment.single.tpl';
    }

    /**
     * Строит дерево комментариев
     *
     * @param array $aComments    Список комментариев
     * @param bool  $bBegin       Флаг начала построения дерева, для инициализации параметров внутри метода
     *
     * @return array('comments'=>array,'iMaxIdComment'=>int)
     */
    protected function BuildCommentsRecursive($aComments, $bBegin = true) {
        static $aResultCommnets;
        static $iLevel;
        static $iMaxIdComment;

        if ($bBegin) {
            $aResultCommnets = [];
            $iLevel = 0;
            $iMaxIdComment = 0;
        }
        foreach ($aComments as $aComment) {
            $aTemp = $aComment;
            if ($aComment['comment_id'] > $iMaxIdComment) {
                $iMaxIdComment = $aComment['comment_id'];
            }
            $aTemp['level'] = $iLevel;
            unset($aTemp['childNodes']);
            $aResultCommnets[$aTemp['comment_id']] = $aTemp['level'];
            if (isset($aComment['childNodes']) && count($aComment['childNodes']) > 0) {
                $iLevel++;
                $this->BuildCommentsRecursive($aComment['childNodes'], false);
            }
        }
        $iLevel--;

        return array('comments' => $aResultCommnets, 'iMaxIdComment' => $iMaxIdComment);
    }

    /**
     * Получает привязку комментария к ибранному(добавлен ли коммент в избранное у юзера)
     *
     * @param  int $iCommentId    ID комментария
     * @param  int $iUserId       ID пользователя
     *
     * @return ModuleFavourite_EntityFavourite|null
     */
    public function getFavouriteComment($iCommentId, $iUserId) {

        return \E::Module('Favourite')->getFavourite($iCommentId, 'comment', $iUserId);
    }

    /**
     * Получить список избранного по списку айдишников
     *
     * @param array $aCommentId    Список ID комментов
     * @param int   $iUserId       ID пользователя
     *
     * @return array
     */
    public function getFavouriteCommentsByArray($aCommentId, $iUserId) {

        return \E::Module('Favourite')->getFavouritesByArray($aCommentId, 'comment', $iUserId);
    }

    /**
     * Получить список избранного по списку айдишников, но используя единый кеш
     *
     * @param array  $aCommentId    Список ID комментов
     * @param int    $iUserId       ID пользователя
     *
     * @return array
     */
    public function getFavouriteCommentsByArraySolid($aCommentId, $iUserId) {

        return \E::Module('Favourite')->getFavouritesByArraySolid($aCommentId, 'comment', $iUserId);
    }

    /**
     * Получает список комментариев из избранного пользователя
     *
     * @param  int    $iUserId      ID пользователя
     * @param  int    $iCurrPage    Номер страницы
     * @param  int    $iPerPage     Количество элементов на страницу
     *
     * @return array
     */
    public function getCommentsFavouriteByUserId($iUserId, $iCurrPage, $iPerPage) {

        $aCloseTopics = [];
        /**
         * Получаем список идентификаторов избранных комментов
         */
        $data = ($this->oUserCurrent && $iUserId == $this->oUserCurrent->getId())
            ? \E::Module('Favourite')->getFavouritesByUserId($iUserId, 'comment', $iCurrPage, $iPerPage, $aCloseTopics)
            : \E::Module('Favourite')->getFavouriteOpenCommentsByUserId($iUserId, $iCurrPage, $iPerPage);
        /**
         * Получаем комменты по переданому массиву айдишников
         */
        if ($data['collection']) {
            $data['collection'] = $this->getCommentsAdditionalData($data['collection']);
        }
        //if ($data['collection'] && !\E::IsAdmin()) {
        if ($data['collection']) {
            $aAllowBlogTypes = \E::Module('Blog')->getOpenBlogTypes();
            if ($this->oUserCurrent) {
                $aClosedBlogs = \E::Module('Blog')->getAccessibleBlogsByUser($this->oUserCurrent);
            } else {
                $aClosedBlogs = [];
            }
            foreach ($data['collection'] as $oComment) {
                $oTopic = $oComment->getTarget();
                if ($oTopic && ($oBlog = $oTopic->getBlog())) {
                    if (!in_array($oBlog->getType(), $aAllowBlogTypes) && !in_array($oBlog->getId(), $aClosedBlogs)) {
                        $oTopic->setTitle('...');
                        $oComment->setText(\E::Module('Lang')->get('acl_cannot_show_content'));
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Возвращает число комментариев в избранном
     *
     * @param  int $iUserId    ID пользователя
     *
     * @return int
     */
    public function getCountCommentsFavouriteByUserId($iUserId) {

        return ($this->oUserCurrent && $iUserId == $this->oUserCurrent->getId())
            ? \E::Module('Favourite')->getCountFavouritesByUserId($iUserId, 'comment')
            : \E::Module('Favourite')->getCountFavouriteOpenCommentsByUserId($iUserId);
    }

    /**
     * Добавляет комментарий в избранное
     *
     * @param  ModuleFavourite_EntityFavourite $oFavourite    Объект избранного
     *
     * @return bool|ModuleFavourite_EntityFavourite
     */
    public function addFavouriteComment($oFavourite) {

        if (($oFavourite->getTargetType() == 'comment')
            && ($oComment = \E::Module('Comment')->getCommentById($oFavourite->getTargetId()))
            && in_array($oComment->getTargetType(), \C::get('module.comment.favourite_target_allow'))
        ) {
            return \E::Module('Favourite')->AddFavourite($oFavourite);
        }
        return false;
    }

    /**
     * Удаляет комментарий из избранного
     *
     * @param  ModuleFavourite_EntityFavourite $oFavourite    Объект избранного
     *
     * @return bool
     */
    public function deleteFavouriteComment($oFavourite) {

        if (($oFavourite->getTargetType() === 'comment')
            && ($oComment = \E::Module('Comment')->getCommentById($oFavourite->getTargetId()))
            && in_array($oComment->getTargetType(), \C::get('module.comment.favourite_target_allow'))
        ) {
            return \E::Module('Favourite')->DeleteFavourite($oFavourite);
        }
        return false;
    }

    /**
     * Удаляет комментарии из избранного по списку
     *
     * @param  array $aCommentsId    Список ID комментариев
     *
     * @return bool
     */
    public function deleteFavouriteCommentsByArrayId($aCommentsId) {

        return \E::Module('Favourite')->DeleteFavouriteByTargetId($aCommentsId, 'comment');
    }

    /**
     * Удаляет комментарии из базы данных
     *
     * @param   array|int   $aTargetsId      Список ID владельцев
     * @param   string      $sTargetType     Тип владельцев
     *
     * @return  bool
     */
    public function deleteCommentByTargetId($aTargetsId, $sTargetType) {

        if (!is_array($aTargetsId)) {
            $aTargetsId = array($aTargetsId);
        }

        // * Получаем список идентификаторов удаляемых комментариев
        $aCommentsId = $this->getCommentsIdByTargetsId($aTargetsId, $sTargetType);

        // * Если ни одного комментария не найдено, выходим
        if (!count($aCommentsId)) {
            return true;
        }
        $bResult = $this->oMapper->deleteCommentByTargetId($aTargetsId, $sTargetType);
        if ($bResult) {

            // * Удаляем комментарии из избранного
            $this->deleteFavouriteCommentsByArrayId($aCommentsId);

            // * Удаляем комментарии к топику из прямого эфира
            $this->deleteCommentOnlineByArrayId($aCommentsId, $sTargetType);

            // * Удаляем голосование за комментарии
            \E::Module('Vote')->DeleteVoteByTarget($aCommentsId, 'comment');
        }

        // * Чистим зависимые кеши, даже если что-то не так пошло
        if (\C::get('sys.cache.solid')) {
            foreach ($aTargetsId as $nTargetId) {
                \E::Module('Cache')->cleanByTags(
                    array("comment_update", "comment_target_{$nTargetId}_{$sTargetType}")
                );
            }
        } else {
            foreach ($aTargetsId as $nTargetId) {
                \E::Module('Cache')->cleanByTags(array("comment_target_{$nTargetId}_{$sTargetType}")
                );
            }
            if ($aCommentsId) {
                // * Удаляем кеш для каждого комментария
                foreach ($aCommentsId as $iCommentId) {
                    \E::Module('Cache')->delete("comment_{$iCommentId}");
                }
            }
        }
        return $bResult;
    }

    /**
     * Удаляет коммент из прямого эфира по массиву переданных идентификаторов
     *
     * @param  array|int $aCommentId
     * @param  string      $sTargetType    Тип владельцев
     * @return bool
     */
    public function deleteCommentOnlineByArrayId($aCommentId, $sTargetType) {

        if (!is_array($aCommentId)) {
            $aCommentId = array($aCommentId);
        }
        $bResult = $this->oMapper->deleteCommentOnlineByArrayId($aCommentId, $sTargetType);

        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(array("comment_online_update_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Меняем target parent по массиву идентификаторов
     *
     * @param  int       $iParentId      Новый ID родителя владельца
     * @param  string    $sTargetType    Тип владельца
     * @param  array|int $aTargetId      Список ID владельцев
     *
     * @return bool
     */
    public function updateTargetParentByTargetId($iParentId, $sTargetType, $aTargetId)
    {

        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        $bResult = $this->oMapper->updateTargetParentByTargetId($iParentId, $sTargetType, $aTargetId);

        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(array("comment_new_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Меняем target parent по массиву идентификаторов в таблице комментариев online
     *
     * @param  int       $iParentId      Новый ID родителя владельца
     * @param  string    $sTargetType    Тип владельца
     * @param  array|int $aTargetId      Список ID владельцев
     *
     * @return bool
     */
    public function updateTargetParentByTargetIdOnline($iParentId, $sTargetType, $aTargetId)
    {
        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        $bResult = $this->oMapper->updateTargetParentByTargetIdOnline($iParentId, $sTargetType, $aTargetId);

        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(array("comment_online_update_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Меняет target parent на новый
     *
     * @param int    $iParentId       Прежний ID родителя владельца
     * @param string $sTargetType     Тип владельца
     * @param int    $iParentIdNew    Новый ID родителя владельца
     *
     * @return bool
     */
    public function moveTargetParent($iParentId, $sTargetType, $iParentIdNew)
    {
        $bResult = $this->oMapper->moveTargetParent($iParentId, $sTargetType, $iParentIdNew);

        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(array("comment_new_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Меняет target parent на новый в прямом эфире
     *
     * @param int    $iParentId       Прежний ID родителя владельца
     * @param string $sTargetType     Тип владельца
     * @param int    $iParentIdNew    Новый ID родителя владельца
     *
     * @return bool
     */
    public function moveTargetParentOnline($iParentId, $sTargetType, $iParentIdNew)
    {
        $bResult = $this->oMapper->moveTargetParentOnline($iParentId, $sTargetType, $iParentIdNew);

        // чистим зависимые кеши
        \E::Module('Cache')->cleanByTags(array("comment_online_update_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Перестраивает дерево комментариев
     * Восстанавливает значения left, right и level
     *
     * @param int    $aTargetId      Список ID владельцев
     * @param string $sTargetType    Тип владельца
     */
    public function restoreTree($aTargetId = null, $sTargetType = null) {

        // обработать конкретную сущность
        if (null !== $aTargetId && null !== $sTargetType) {
            $this->oMapper->restoreTree(null, 0, -1, $aTargetId, $sTargetType);
            return;
        }
        $aType = [];
        // обработать все сущности конкретного типа
        if (null !== $sTargetType) {
            $aType[] = $sTargetType;
        } else {
            // обработать все сущности всех типов
            $aType = $this->oMapper->getCommentTypes();
        }
        foreach ($aType as $sTargetByType) {
            // для каждого типа получаем порциями ID сущностей
            $iPage = 1;
            $iPerPage = 50;
            while ($aResult = $this->oMapper->getTargetIdByType($sTargetByType, $iPage, $iPerPage)) {
                foreach ($aResult as $Row) {
                    $this->oMapper->restoreTree(null, 0, -1, $Row['target_id'], $sTargetByType);
                }
                $iPage++;
            }
        }
    }

    /**
     * Пересчитывает счетчик избранных комментариев
     *
     * @return bool
     */
    public function recalculateFavourite() {

        return $this->oMapper->RecalculateFavourite();
    }

    /**
     * Получает список комментариев по фильтру
     *
     * @param array         $aFilter           Фильтр выборки
     * @param array|string  $aOrder            Сортировка
     * @param int           $iCurrPage         Номер текущей страницы
     * @param int           $iPerPage          Количество элементов на одну страницу
     * @param array         $aAllowData        Список типов данных, которые нужно подтянуть к списку комментов
     *
     * @return array
     */
    public function getCommentsByFilter($aFilter, $aOrder, $iCurrPage, $iPerPage, $aAllowData = null) {

        if (is_null($aAllowData)) {
            $aAllowData = array('target', 'user' => array());
        }
        $aFilter['order'] = $aOrder;
        $aCollection = $this->oMapper->getCommentsIdByFilter($aFilter, $iCount, $iCurrPage, $iPerPage);
        if ($aCollection) {
            $aCollection = $this->getCommentsAdditionalData($aCollection, $aAllowData);
        }
        return array('collection' => $aCollection, 'count' => $iCount);
    }

    /**
     * Алиас для корректной работы ORM
     *
     * @param array $aCommentId  Список ID комментариев
     *
     * @return array
     */
    public function getCommentItemsByArrayId($aCommentId) 
    {
        return $this->getCommentsByArrayId($aCommentId);
    }

}

// EOF