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
 * Модуль для работы с избранным
 *
 * @package modules.favourite
 * @since   1.0
 */
class ModuleFavourite extends Module {

    /** @var ModuleFavourite_MapperFavourite  */
    protected $oMapper;

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * Получает информацию о том, найден ли таргет в избранном или нет
     *
     * @param  int    $nTargetId      ID владельца
     * @param  string $sTargetType    Тип владельца
     * @param  int    $nUserId        ID пользователя
     *
     * @return ModuleFavourite_EntityFavourite|null
     */
    public function getFavourite($nTargetId, $sTargetType, $nUserId) {

        if (!is_numeric($nTargetId) || !is_string($sTargetType)) {
            return null;
        }
        $data = $this->GetFavouritesByArray($nTargetId, $sTargetType, $nUserId);
        return (isset($data[$nTargetId])) ? $data[$nTargetId] : null;
    }

    /**
     * Получить список избранного по списку айдишников
     *
     * @param  array  $aTargetsId      Список ID владельцев
     * @param  string $sTargetType    Тип владельца
     * @param  int    $iUserId        ID пользователя
     *
     * @return ModuleFavourite_EntityFavourite[]
     */
    public function getFavouritesByArray($aTargetsId, $sTargetType, $iUserId) {

        if (!$aTargetsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->GetFavouritesByArraySolid($aTargetsId, $sTargetType, $iUserId);
        }
        if (!is_array($aTargetsId)) {
            $aTargetsId = array($aTargetsId);
        }
        $aTargetsId = array_unique($aTargetsId);
        $aFavourite = [];
        $aIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aTargetsId, "favourite_{$sTargetType}_", '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aFavourite[$data[$sKey]->getTargetId()] = $data[$sKey];
                    } else {
                        $aIdNotNeedQuery[] = $aTargetsId[$iIndex];
                    }
                }
            }
        }
        // * Смотрим чего не было в кеше и делаем запрос в БД
        $aIdNeedQuery = array_diff($aTargetsId, array_keys($aFavourite));
        $aIdNeedQuery = array_diff($aIdNeedQuery, $aIdNotNeedQuery);
        $aIdNeedStore = $aIdNeedQuery;

        if ($aIdNeedQuery) {
            if ($data = $this->oMapper->getFavouritesByArray($aIdNeedQuery, $sTargetType, $iUserId)) {
                foreach ($data as $oFavourite) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aFavourite[$oFavourite->getTargetId()] = $oFavourite;
                    \E::Module('Cache')->set(
                        $oFavourite, "favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$iUserId}",
                        array(), 60 * 60 * 24 * 7
                    );
                    $aIdNeedStore = array_diff($aIdNeedStore, array($oFavourite->getTargetId()));
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aIdNeedStore as $sId) {
            \E::Module('Cache')->set(null, "favourite_{$sTargetType}_{$sId}_{$iUserId}", array(), 60 * 60 * 24 * 7);
        }

        // * Сортируем результат согласно входящему массиву
        $aFavourite = F::Array_SortByKeysArray($aFavourite, $aTargetsId);

        return $aFavourite;
    }

    /**
     * Получить список избранного по списку айдишников, но используя единый кеш
     *
     * @param  array  $aTargetId      Список ID владельцев
     * @param  string $sTargetType    Тип владельца
     * @param  int    $nUserId        ID пользователя
     *
     * @return array
     */
    public function getFavouritesByArraySolid($aTargetId, $sTargetType, $nUserId) {

        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        $aTargetId = array_unique($aTargetId);
        $aFavourites = [];

        $sCacheKey = "favourite_{$sTargetType}_{$nUserId}_id_" . join(',', $aTargetId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getFavouritesByArray($aTargetId, $sTargetType, $nUserId);
            foreach ($data as $oFavourite) {
                $aFavourites[$oFavourite->getTargetId()] = $oFavourite;
            }
            \E::Module('Cache')->set($aFavourites, $sCacheKey, array("favourite_{$sTargetType}_change_user_{$nUserId}"), 'P1D');
            return $aFavourites;
        }
        return $data;
    }

    /**
     * Получает список таргетов из избранного
     *
     * @param  int    $nUserId           ID пользователя
     * @param  string $sTargetType       Тип владельца
     * @param  int    $iCurrPage         Номер страницы
     * @param  int    $iPerPage          Количество элементов на страницу
     * @param  array  $aExcludeTarget    Список ID владельцев для исклчения
     *
     * @return array
     */
    public function getFavouritesByUserId($nUserId, $sTargetType, $iCurrPage, $iPerPage, $aExcludeTarget = array()) {

        $sCacheKey = "{$sTargetType}_favourite_user_{$nUserId}_{$iCurrPage}_{$iPerPage}_" . serialize($aExcludeTarget);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getFavouritesByUserId($nUserId, $sTargetType, $iCount, $iCurrPage, $iPerPage, $aExcludeTarget),
                'count'      => $iCount
            );
            \E::Module('Cache')->set(
                $data,
                $sCacheKey,
                array(
                    "favourite_{$sTargetType}_change",
                    "favourite_{$sTargetType}_change_user_{$nUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Возвращает число таргетов определенного типа в избранном по ID пользователя
     *
     * @param  int    $sUserId           ID пользователя
     * @param  string $sTargetType       Тип владельца
     * @param  array  $aExcludeTarget    Список ID владельцев для исклчения
     *
     * @return array
     */
    public function getCountFavouritesByUserId($sUserId, $sTargetType, $aExcludeTarget = array()) {

        $s = serialize($aExcludeTarget);
        if (false === ($data = \E::Module('Cache')->get("{$sTargetType}_count_favourite_user_{$sUserId}_{$s}"))) {
            $data = $this->oMapper->getCountFavouritesByUserId($sUserId, $sTargetType, $aExcludeTarget);
            \E::Module('Cache')->set(
                $data,
                "{$sTargetType}_count_favourite_user_{$sUserId}_{$s}",
                array(
                    "favourite_{$sTargetType}_change",
                    "favourite_{$sTargetType}_change_user_{$sUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Получает список комментариев к записям открытых блогов
     * из избранного указанного пользователя
     *
     * @param  int $sUserId      ID пользователя
     * @param  int $iCurrPage    Номер страницы
     * @param  int $iPerPage     Количество элементов на страницу
     *
     * @return array
     */
    public function getFavouriteOpenCommentsByUserId($sUserId, $iCurrPage, $iPerPage) {

        if (false === ($data = \E::Module('Cache')->get("comment_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}_open"))) {
            $data = array(
                'collection' => $this->oMapper->getFavouriteOpenCommentsByUserId(
                    $sUserId, $iCount, $iCurrPage, $iPerPage
                ),
                'count'      => $iCount
            );
            \E::Module('Cache')->set(
                $data,
                "comment_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}_open",
                array(
                    "favourite_comment_change",
                    "favourite_comment_change_user_{$sUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Возвращает число комментариев к открытым блогам в избранном по ID пользователя
     *
     * @param  int $sUserId    ID пользователя
     *
     * @return array
     */
    public function getCountFavouriteOpenCommentsByUserId($sUserId) {

        if (false === ($data = \E::Module('Cache')->get("comment_count_favourite_user_{$sUserId}_open"))) {
            $data = $this->oMapper->getCountFavouriteOpenCommentsByUserId($sUserId);
            \E::Module('Cache')->set(
                $data,
                "comment_count_favourite_user_{$sUserId}_open",
                array(
                    "favourite_comment_change",
                    "favourite_comment_change_user_{$sUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Получает список топиков из открытых блогов
     * из избранного указанного пользователя
     *
     * @param  int $sUserId      ID пользователя
     * @param  int $iCurrPage    Номер страницы
     * @param  int $iPerPage     Количество элементов на страницу
     *
     * @return array
     */
    public function getFavouriteOpenTopicsByUserId($sUserId, $iCurrPage, $iPerPage) {

        if (false === ($data = \E::Module('Cache')->get("topic_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}_open"))) {
            $data = array(
                'collection' => $this->oMapper->getFavouriteOpenTopicsByUserId(
                    $sUserId, $iCount, $iCurrPage, $iPerPage
                ),
                'count'      => $iCount
            );
            \E::Module('Cache')->set(
                $data,
                "topic_favourite_user_{$sUserId}_{$iCurrPage}_{$iPerPage}_open",
                array(
                    "favourite_topic_change",
                    "favourite_topic_change_user_{$sUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Возвращает число топиков в открытых блогах из избранного по ID пользователя
     *
     * @param  string $sUserId    ID пользователя
     *
     * @return array
     */
    public function getCountFavouriteOpenTopicsByUserId($sUserId) {

        if (false === ($data = \E::Module('Cache')->get("topic_count_favourite_user_{$sUserId}_open"))) {
            $data = $this->oMapper->getCountFavouriteOpenTopicsByUserId($sUserId);
            \E::Module('Cache')->set(
                $data,
                "topic_count_favourite_user_{$sUserId}_open",
                array(
                    "favourite_topic_change",
                    "favourite_topic_change_user_{$sUserId}"
                ),
                60 * 60 * 24 * 1
            );
        }
        return $data;
    }

    /**
     * Добавляет таргет в избранное
     *
     * @param  ModuleFavourite_EntityFavourite $oFavourite Объект избранного
     *
     * @return bool
     */
    public function addFavourite($oFavourite) {

        if (!$oFavourite->getTags()) {
            $oFavourite->setTags('');
        }
        $this->SetFavouriteTags($oFavourite);
        //чистим зависимые кеши
        \E::Module('Cache')->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            array("favourite_{$oFavourite->getTargetType()}_change_user_{$oFavourite->getUserId()}")
        );
        \E::Module('Cache')->delete(
            "favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getUserId()}"
        );
        return $this->oMapper->addFavourite($oFavourite);
    }

    /**
     * Обновляет запись об избранном
     *
     * @param ModuleFavourite_EntityFavourite $oFavourite    Объект избранного
     *
     * @return bool
     */
    public function updateFavourite($oFavourite) {

        if (!$oFavourite->getTags()) {
            $oFavourite->setTags('');
        }
        $this->SetFavouriteTags($oFavourite);
        \E::Module('Cache')->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            array("favourite_{$oFavourite->getTargetType()}_change_user_{$oFavourite->getUserId()}")
        );
        \E::Module('Cache')->delete(
            "favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getUserId()}"
        );
        return $this->oMapper->updateFavourite($oFavourite);
    }

    /**
     * Устанавливает список тегов для избранного
     *
     * @param ModuleFavourite_EntityFavourite $oFavourite    Объект избранного
     * @param bool                            $bAddNew       Добавлять новые теги или нет
     */
    public function setFavouriteTags($oFavourite, $bAddNew = true)
    {
        // * Удаляем все теги
        $this->oMapper->deleteTags($oFavourite);
        // * Добавляем новые
//      issue 252, {@link https://github.com/altocms/altocms/issues/252} В избранном не отображаются теги
//      Свойство $oFavourite->getTags() содержит только пользовательские теги, а не все теги избранного объекта,
//      соответственно при отсутствии пользовательских тегов в условие не заходили и теги избранного
//      объекта не добалялись.
//      if ($bAddNew && $oFavourite->getTags()) {
        if ($bAddNew) {
            // * Добавляем теги объекта избранного, если есть
            if ($aTags = $this->getTagsTarget($oFavourite->getTargetType(), $oFavourite->getTargetId())) {
                foreach ($aTags as $sTag) {
                    /** @var ModuleFavourite_EntityTag $oTag */
                    $oTag = \E::getEntity('ModuleFavourite_EntityTag', $oFavourite->getAllProps());
                    $oTag->setText(htmlspecialchars($sTag));
                    $oTag->setIsUser(0);
                    $this->oMapper->addTag($oTag);
                }
            }
            // * Добавляем пользовательские теги
            foreach ($oFavourite->getTagsArray() as $sTag) {
                $oTag = \E::getEntity('ModuleFavourite_EntityTag', $oFavourite->getAllProps());
                $oTag->setText($sTag); // htmlspecialchars уже используется при установке тегов
                $oTag->setIsUser(1);
                $this->oMapper->addTag($oTag);
            }
        }
    }

    /**
     * Удаляет таргет из избранного
     *
     * @param  ModuleFavourite_EntityFavourite $oFavourite    Объект избранного
     *
     * @return bool
     */
    public function deleteFavourite($oFavourite)
    {
        $this->setFavouriteTags($oFavourite, false);
        //чистим зависимые кеши
        \E::Module('Cache')->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_TAG,
            ["favourite_{$oFavourite->getTargetType()}_change_user_{$oFavourite->getUserId()}"]
        );
        \E::Module('Cache')->delete(
            "favourite_{$oFavourite->getTargetType()}_{$oFavourite->getTargetId()}_{$oFavourite->getUserId()}"
        );

        return $this->oMapper->deleteFavourite($oFavourite);
    }

    /**
     * Меняет параметры публикации у таргета
     *
     * @param  array|int $aTargetId      Список ID владельцев
     * @param  string    $sTargetType    Тип владельца
     * @param  int       $iPublish       Флаг публикации
     *
     * @return bool
     */
    public function setFavouriteTargetPublish($aTargetId, $sTargetType, $iPublish)
    {
        if (!is_array($aTargetId)) {
            $aTargetId = [$aTargetId];
        }

        \E::Module('Cache')->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, ["favourite_{$sTargetType}_change"]);

        return $this->oMapper->SetFavouriteTargetPublish($aTargetId, $sTargetType, $iPublish);
    }

    /**
     * Удаляет избранное по списку идентификаторов таргетов
     *
     * @param  array|int $aTargetsId     Список ID владельцев
     * @param  string    $sTargetType    Тип владельца
     *
     * @return bool
     */
    public function deleteFavouriteByTargetId($aTargetsId, $sTargetType)
    {
        if (!is_array($aTargetsId)) {
            $aTargetsId = [$aTargetsId];
        }
        $this->deleteTagByTarget($aTargetsId, $sTargetType);
        $bResult = $this->oMapper->deleteFavouriteByTargetId($aTargetsId, $sTargetType);

        // * Чистим зависимые кеши
        \E::Module('Cache')->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, ["favourite_{$sTargetType}_change"]);

        return $bResult;
    }

    /**
     * Удаление тегов по таргету
     *
     * @param   array  $aTargetsId     - Список ID владельцев
     * @param   string $sTargetType    - Тип владельца
     *
     * @return  bool
     */
    public function deleteTagByTarget($aTargetsId, $sTargetType)
    {
        return $this->oMapper->deleteTagByTarget($aTargetsId, $sTargetType);
    }

    /**
     * Возвращает список тегов для объекта избранного
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $nTargetId      ID владельца
     *
     * @return bool|array
     */
    public function getTagsTarget($sTargetType, $nTargetId)
    {
        $sMethod = 'GetTagsTarget' . \F::StrCamelize($sTargetType);
        if (method_exists($this, $sMethod)) {
            return $this->$sMethod($nTargetId);
        }
        return false;
    }

    /**
     * Возвращает наиболее часто используемые теги
     *
     * @param int    $iUserId        ID пользователя
     * @param string $sTargetType    Тип владельца
     * @param bool   $bIsUser        Возвращает все теги ли только пользовательские
     * @param int    $iLimit         Количество элементов
     *
     * @return array
     */
    public function getGroupTags($iUserId, $sTargetType, $bIsUser, $iLimit)
    {
        return $this->oMapper->getGroupTags($iUserId, $sTargetType, $bIsUser, $iLimit);
    }

    /**
     * Возвращает список тегов по фильтру
     *
     * @param array $aFilter      Фильтр
     * @param array $aOrder       Сортировка
     * @param int   $iCurrPage    Номер страницы
     * @param int   $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getTags($aFilter, $aOrder, $iCurrPage, $iPerPage)
    {
        return [
            'collection' => $this->oMapper->getTags($aFilter, $aOrder, $iCount, $iCurrPage, $iPerPage),
            'count'      => $iCount
        ];
    }

    /**
     * Возвращает список тегов для топика, название метода формируется автоматически из GetTagsTarget()
     *
     * @see GetTagsTarget
     *
     * @param int $iTargetId    ID владельца
     *
     * @return bool|array
     */
    public function getTagsTargetTopic($iTargetId)
    {
        if ($oTopic = \E::Module('Topic')->getTopicById($iTargetId)) {
            return $oTopic->getTagsArray();
        }
        return false;
    }
}

// EOF