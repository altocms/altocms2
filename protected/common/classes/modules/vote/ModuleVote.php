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
 * Модуль для работы с голосованиями
 *
 * @package modules.vote
 * @since   1.0
 */
class ModuleVote extends Module {
    /**
     * Объект маппера
     *
     * @var ModuleVote_MapperVote
     */
    protected $oMapper;

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * Добавляет голосование
     *
     * @param ModuleVote_EntityVote $oVote    Объект голосования
     *
     * @return bool
     */
    public function addVote($oVote) {

        if (!$oVote->getIp()) {
            $oVote->setIp(\F::GetUserIp());
        }
        if ($this->oMapper->addVote($oVote)) {
            \E::Module('Cache')->delete("vote_{$oVote->getTargetType()}_{$oVote->getTargetId()}_{$oVote->getVoterId()}");
            \E::Module('Cache')->cleanByTags(
                array(
                    "vote_update_{$oVote->getTargetType()}_{$oVote->getVoterId()}",
                    "vote_update_{$oVote->getTargetType()}_{$oVote->getTargetId()}",
                    "vote_update_{$oVote->getTargetType()}",
                )
            );

            return true;
        }
        return false;
    }

    /**
     * Получает голосование
     *
     * @param int    $iTargetId      ID владельца
     * @param string $sTargetType    Тип владельца
     * @param int    $iUserId        ID пользователя
     *
     * @return ModuleVote_EntityVote|null
     */
    public function getVote($iTargetId, $sTargetType, $iUserId) {

        $aData = $this->GetVoteByArray($iTargetId, $sTargetType, $iUserId);
        if (isset($aData[$iTargetId])) {
            return $aData[$iTargetId];
        }
        return null;
    }

    /**
     * Получить список голосований по списку айдишников
     *
     * @param array  $aTargetsId      Список ID владельцев
     * @param string $sTargetType    Тип владельца
     * @param int    $iUserId        ID пользователя
     *
     * @return array
     */
    public function getVoteByArray($aTargetsId, $sTargetType, $iUserId) {

        if (!$aTargetsId) {
            return [];
        }
        if (\C::get('sys.cache.solid')) {
            return $this->GetVoteByArraySolid($aTargetsId, $sTargetType, $iUserId);
        }
        if (!is_array($aTargetsId)) {
            $aTargetsId = array($aTargetsId);
        }
        $aTargetsId = array_unique($aTargetsId);
        $aVote = [];
        $aIdNotNeedQuery = [];

        // * Делаем мульти-запрос к кешу
        $aCacheKeys = F::Array_ChangeValues($aTargetsId, "vote_{$sTargetType}_", '_' . $iUserId);
        if (false !== ($data = \E::Module('Cache')->get($aCacheKeys))) {
            // * проверяем что досталось из кеша
            foreach ($aCacheKeys as $iIndex => $sKey) {
                if (array_key_exists($sKey, $data)) {
                    if ($data[$sKey]) {
                        $aVote[$data[$sKey]->getTargetId()] = $data[$sKey];
                    } else {
                        $aIdNotNeedQuery[] = $aTargetsId[$iIndex];
                    }
                }
            }
        }
        // * Смотрим, каких элементов не было в кеше, и делаем запрос в БД
        $aIdNeedQuery = array_diff($aTargetsId, array_keys($aVote));
        $aIdNeedQuery = array_diff($aIdNeedQuery, $aIdNotNeedQuery);
        $aIdNeedStore = $aIdNeedQuery;

        if ($aIdNeedQuery) {
            if ($data = $this->oMapper->getVoteByArray($aIdNeedQuery, $sTargetType, $iUserId)) {
                foreach ($data as $oVote) {
                    // * Добавляем к результату и сохраняем в кеш
                    $aVote[$oVote->getTargetId()] = $oVote;
                    \E::Module('Cache')->set($oVote, "vote_{$oVote->getTargetType()}_{$oVote->getTargetId()}_{$oVote->getVoterId()}", array(), 'P7D');
                    $aIdNeedStore = array_diff($aIdNeedStore, array($oVote->getTargetId()));
                }
            }
        }

        // * Сохраняем в кеш запросы не вернувшие результата
        foreach ($aIdNeedStore as $iTargetId) {
            \E::Module('Cache')->set(null, "vote_{$sTargetType}_{$iTargetId}_{$iUserId}", array(), 'P7D');
        }

        // * Сортируем результат согласно входящему массиву
        $aVote = F::Array_SortByKeysArray($aVote, $aTargetsId);
        return $aVote;
    }

    /**
     * Получить список голосований по списку айдишников, но используя единый кеш
     *
     * @param array|int $aTargetId   Список ID владельцев
     * @param string    $sTargetType Тип владельца
     * @param int       $iUserId     ID пользователя
     *
     * @return array
     */
    public function getVoteByArraySolid($aTargetId, $sTargetType, $iUserId) {

        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        $aTargetId = array_unique($aTargetId);
        $aVote = [];

        $sCacheKey = "vote_{$sTargetType}_{$iUserId}_id_" . join(',', $aTargetId);
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getVoteByArray($aTargetId, $sTargetType, $iUserId);
            foreach ($data as $oVote) {
                $aVote[$oVote->getTargetId()] = $oVote;
            }
            \E::Module('Cache')->set(
                $aVote, $sCacheKey,
                array("vote_update_{$sTargetType}_{$iUserId}", "vote_update_{$sTargetType}"),
                'P1D'
            );
            return $aVote;
        }
        return $data;
    }

    /**
     * Удаляет голосование из базы по списку идентификаторов таргета
     *
     * @param  array|int $aTargetId      Список ID владельцев
     * @param  string    $sTargetType    Тип владельца
     *
     * @return bool
     */
    public function deleteVoteByTarget($aTargetId, $sTargetType) {

        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        $aTargetId = array_unique($aTargetId);
        $bResult = $this->oMapper->deleteVoteByTarget($aTargetId, $sTargetType);
        // * Чистим зависимые кеши
        \E::Module('Cache')->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array("vote_update_{$sTargetType}"));

        return $bResult;
    }

    /**
     * Обновляет голосование
     *
     * @param $oVote
     *
     * @return bool
     */
   public function update($oVote) {

        if ($this->oMapper->update($oVote)) {
            \E::Module('Cache')->delete("vote_{$oVote->getTargetType()}_{$oVote->getTargetId()}_{$oVote->getVoterId()}");
            \E::Module('Cache')->cleanByTags(array("vote_update_{$oVote->getTargetType()}_{$oVote->getVoterId()}"));

            return true;
        }
        return false;
    }

    /**
     * Получить статистику по юзерам
     * cnt_topics_p / cnt_topics_m - Количество голосований за топик +/-
     * sum_topics_p / sum_topics_m - Количество голосований за топик +/-
     * cnt_comments_p / cnt_comments_m - Количество голосований за комментарий +/-
     * sum_comments_p / sum_comments_m - Количество голосований за комментарий +/-
     * cnt_user_p / cnt_user_m - Количество голосований за пользователя +/-
     * sum_user_p / sum_user_m - Количество голосований за пользователя +/-
     *
     * @param int $iUserId ID пользователя
     *
     * @return array
     */
    public function getUserVoteStats($iUserId) {

        $sCacheKey = 'user_vote_stats_' . $iUserId;
        if (false === ($aResult = \E::Module('Cache')->get($sCacheKey))) {
            $aResult = $this->oMapper->getUserVoteStats($iUserId);
            \E::Module('Cache')->set(
                $aResult, $sCacheKey,
                array(
                    "vote_update_topic_{$iUserId}",
                    "vote_update_comment_{$iUserId}",
                    "vote_update_user_{$iUserId}"
                )
            );

        }

        return $aResult;

    }

}

// EOF