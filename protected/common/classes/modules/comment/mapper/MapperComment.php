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
 * @package modules.comment
 * @since   1.0
 */
class ModuleComment_MapperComment extends Mapper
{
    /**
     * Получить ID комментатриев по рейтингу и дате
     *
     * @param  string   $sDate                   Дата за которую выводить рейтинг
     * @param  string   $sTargetType             Тип владельца комментария
     * @param  int      $iLimit                  Количество элементов
     * @param  int[]    $aExcludeTarget          Список ID владельцев, которые необходимо исключить из выдачи
     * @param  int[]    $aExcludeParentTarget    Список ID родителей владельцев, которые необходимо исключить из выдачи
     *
     * @return int[]
     */
    public function getCommentsIdByRatingAndDate($sDate, $sTargetType, $iLimit, $aExcludeTarget = [], $aExcludeParentTarget = []) {

        $sql = "SELECT
					comment_id
				FROM 
					?_comment
				WHERE 
					target_type = ?
					AND 
					comment_date >= ?
					AND 
					comment_rating >= 0
					AND
					comment_delete = 0
					AND 
					comment_publish = 1 
					{ AND target_id NOT IN(?a) }  
					{ AND target_parent_id NOT IN (?a) }
				ORDER by comment_rating desc, comment_id desc
				LIMIT 0, ?d ";

        $aCommentsId = $this->oDb->selectCol($sql,
            $sTargetType,
            $sDate,
            (is_array($aExcludeTarget) && count($aExcludeTarget)) ? $aExcludeTarget : DBSIMPLE_SKIP,
            (count($aExcludeParentTarget) ? $aExcludeParentTarget : DBSIMPLE_SKIP),
            $iLimit
        );
        return $aCommentsId ?: [];
    }

    /**
     * Получает уникальный коммент, это помогает спастись от дублей комментов
     *
     * @param int    $iTargetId      ID владельца комментария
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $iUserId        ID пользователя
     * @param int    $iCommentPId    ID родительского комментария
     * @param string $sHash          Хеш строка текста комментария
     *
     * @return int|null
     */
    public function getCommentUnique($iTargetId, $sTargetType, $iUserId, $iCommentPId, $sHash) {

        $sql = "
            SELECT comment_id
            FROM ?_comment
			WHERE 
				target_id = ?d 
				AND
				target_type = ? 
				AND
				user_id = ?d
				AND
				((comment_pid = ?) OR (? is NULL and comment_pid is NULL))
				AND
				comment_text_hash =?
			LIMIT 1
				";
        $iCommentId = $this->oDb->selectCell($sql, $iTargetId, $sTargetType, $iUserId, $iCommentPId, $iCommentPId, $sHash);
        return $iCommentId ?: null;
    }

    /**
     * Получить ID комментариев по типу
     *
     * @param string $sTargetType             Тип владельца комментария
     * @param int    $iCount                  Возвращает общее количество элементов
     * @param int    $iCurrPage               Номер страницы
     * @param int    $iPerPage                Количество элементов на страницу
     * @param array  $aExcludeTarget          Список ID владельцев, которые необходимо исключить из выдачи
     * @param array  $aExcludeParentTarget    Список ID родителей владельцев, которые необходимо исключить из выдачи, например, исключить комментарии топиков к определенным блогам(закрытым)
     *
     * @return int[]
     */
    public function getCommentsIdByTargetType($sTargetType, &$iCount, $iCurrPage, $iPerPage, $aExcludeTarget = [], $aExcludeParentTarget = [])
    {
        $aFilter = [
            'target_type' => $sTargetType,
            'delete' => 0,
            'publish' => 1,
            'not_target_id' => $aExcludeTarget,
            'not_target_parent_id' => $aExcludeParentTarget,
        ];
        return $this->getCommentsIdByFilter($aFilter, $iCount, $iCurrPage, $iPerPage);
    }

    /**
     * Список комментов по ID
     *
     * @param array $aCommentsId    Список ID комментариев
     *
     * @return ModuleComment_EntityComment[]
     */
    public function getCommentsByArrayId($aCommentsId) {

        if (!$aCommentsId) {
            return [];
        }
        if (!is_array($aCommentsId)) {
            $aCommentsId = [(int)$aCommentsId];
        }

        $iLimit = count($aCommentsId);
        $sql = "
            SELECT
                c.comment_id AS ARRAY_KEYS,
                c.*
            FROM
                ?_comment AS c
            WHERE
                c.comment_id IN(?a)
            LIMIT $iLimit
            ";
        $aComments = [];
        if ($aRows = $this->oDb->select($sql, $aCommentsId)) {
            $aComments = \E::getEntityRows('Comment', $aRows, $aCommentsId);
        }
        return $aComments;
    }

    /**
     * Получить ID комментариев, сгрупированных по типу (для вывода прямого эфира)
     *
     * @param string $sTargetType        Тип владельца комментария
     * @param array  $aExcludeTargets    Список ID владельцев для исключения
     * @param int    $iLimit             Количество элементов
     *
     * @return int[]
     */
    public function getCommentsIdOnline($sTargetType, $aExcludeTargets, $iLimit)
    {
        $sql = "SELECT
					comment_id
				FROM 
					?_comment
				WHERE
				    comment_last = 1
					AND target_type = ?
				    { AND target_parent_id NOT IN(?a) }
				ORDER by comment_id DESC
				LIMIT 0, ?d ;";

        $aCommentsId = $this->oDb->selectCol($sql,
            $sTargetType,
            (count($aExcludeTargets) ? $aExcludeTargets : DBSIMPLE_SKIP),
            $iLimit);

        return $aCommentsId ?: [];
    }

    /**
     * Получить ID комментов по владельцу
     *
     * @param   array   $aTargetsId     ID владельца коммента
     * @param   string  $sTargetType    Тип владельца комментария
     *
     * @return  int[]
     */
    public function getCommentsIdByTargetsId($aTargetsId, $sTargetType) {

        $aTargetsId = $this->_arrayId($aTargetsId);
        $sql = "
            SELECT comment_id
            FROM ?_comment
            WHERE target_id IN (?a) AND target_type = ?
        ";

        $aCommentsId = $this->oDb->selectCol($sql, $aTargetsId, $sTargetType);

        return $aCommentsId ?: [];
    }

    /**
     * Получить комменты по владельцу
     *
     * @param  int    $iTargetId      ID владельца коммента
     * @param  string $sTargetType    Тип владельца комментария
     *
     * @return array
     */
    public function getCommentsByTargetId($iTargetId, $sTargetType) {

        $sql = "SELECT
					comment_id,
					comment_id as ARRAY_KEY,
					comment_pid as PARENT_KEY
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
				ORDER BY comment_id ASC;
					";
        if ($aRows = $this->oDb->select($sql, $iTargetId, $sTargetType)) {
            return $aRows;
        }
        return null;
    }

    /**
     * Получает комменты используя nested set
     *
     * @param int    $sId            ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     *
     * @return int[]
     */
    public function getCommentsTreeByTargetId($sId, $sTargetType) {

        $sql = "SELECT
					comment_id 
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
				ORDER BY comment_left ASC;
					";

        $aCommentsId = $this->oDb->selectCol($sql, $sId, $sTargetType);

        return $aCommentsId;
    }

    /**
     * Получает комменты используя nested set
     *
     * @param int    $sId            ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $iCount         Возвращает общее количество элементов
     * @param int    $iPage          Номер страницы
     * @param int    $iPerPage       Количество элементов на страницу
     *
     * @return int[]
     */
    public function getCommentsTreePageByTargetId($sId, $sTargetType, &$iCount, $iPage, $iPerPage) {

        // * Сначала получаем корни и определяем границы выборки веток
        $sql = "SELECT
					comment_left,
					comment_right 
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ? 
					AND
					comment_pid IS NULL
				ORDER BY comment_left DESC
				LIMIT ?d , ?d ;";

        if ($aRows = $this->oDb->selectPage($iCount, $sql, $sId, $sTargetType, ($iPage - 1) * $iPerPage, $iPerPage)) {
            $aCmt = array_pop($aRows);
            $iLeft = $aCmt['comment_left'];
            if ($aRows) {
                $aCmt = array_shift($aRows);
            }
            $iRight = $aCmt['comment_right'];
        } else {
            return [];
        }

        // * Теперь получаем полный список комментов
        $sql = "SELECT
					comment_id 
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ? 
					AND
					comment_left >= ?d
					AND
					comment_right <= ?d
				ORDER BY comment_left ASC;
					";

        $aCommentsId = $this->oDb->selectCol($sql, $sId, $sTargetType, $iLeft, $iRight);

        return $aCommentsId ?: [];
    }

    /**
     * Возвращает количество дочерних комментариев у корневого коммента
     *
     * @param int    $iTargetId   - ID владельца коммента
     * @param string $sTargetType - Тип владельца комментария
     *
     * @return int
     */
    public function getCountCommentsRootByTargetId($iTargetId, $sTargetType) {

        $sql = "SELECT
					COUNT(comment_id) AS cnt
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
					AND
					comment_pid IS NULL;";

        $iCount = $this->oDb->selectCell($sql, $iTargetId, $sTargetType);

        return $iCount ?: 0;
    }

    /**
     * Возвращает количество комментариев
     *
     * @param int    $iTargetId   - ID владельца коммента
     * @param string $sTargetType - Тип владельца комментария
     * @param int    $iLeft       - Значение left для дерева nested set
     *
     * @return int
     */
    public function getCountCommentsAfterByTargetId($iTargetId, $sTargetType, $iLeft) {

        $sql = "SELECT
					COUNT(comment_id) AS cnt
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
					AND
					comment_pid IS NULL	
					AND 
					comment_left >= ?d ;";

        $iCount = $this->oDb->selectCell($sql, $iTargetId, $sTargetType, $iLeft);

        return $iCount ?: 0;
    }

    /**
     * Возвращает корневой комментарий
     *
     * @param int    $iTargetId   ID владельца коммента
     * @param string $sTargetType Тип владельца комментария
     * @param int    $iLeft       Значение left для дерева nested set
     *
     * @return ModuleComment_EntityComment|null
     */
    public function getCommentRootByTargetIdAndChildren($iTargetId, $sTargetType, $iLeft) {

        $sql = "SELECT
					*
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
					AND
					comment_pid IS NULL	
					AND 
					comment_left < ?d 
					AND 
					comment_right > ?d 
				LIMIT 0,1 ;";

        if ($aRow = $this->oDb->selectRow($sql, $iTargetId, $sTargetType, $iLeft, $iLeft)) {
            return E::getEntity('Comment', $aRow);
        }
        return null;
    }

    /**
     * Получить новые комменты для владельца
     *
     * @param int    $iTargetId      ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     * @param int    $sIdCommentLast ID последнего прочитанного комментария
     *
     * @return int[]
     */
    public function getCommentsIdNewByTargetId($iTargetId, $sTargetType, $sIdCommentLast) {

        $sql = "SELECT
					comment_id
				FROM 
					?_comment
				WHERE 
					target_id = ?d 
					AND
					target_type = ?
					AND
					comment_id > ?d
				ORDER BY comment_id ASC;
					";

        $aCommentsId = $this->oDb->selectCol($sql, $iTargetId, $sTargetType, $sIdCommentLast);

        return $aCommentsId ?: [];
    }

    /**
     * Получить комменты по юзеру
     *
     * @param  int    $iUserId                 ID пользователя
     * @param  string $sTargetType             Тип владельца комментария
     * @param  int    $iCount                  Возращает общее количество элементов
     * @param  int    $iCurrPage               Номер страницы
     * @param  int    $iPerPage                Количество элементов на страницу
     * @param array   $aExcludeTarget          Список ID владельцев, которые необходимо исключить из выдачи
     * @param array   $aExcludeParentTarget    Список ID родителей владельцев, которые необходимо исключить из выдачи
     *
     * @return int[]
     */
    public function getCommentsIdByUserId($iUserId, $sTargetType, &$iCount, $iCurrPage, $iPerPage, $aExcludeTarget = [], $aExcludeParentTarget = [])
    {
        $sql = "SELECT
					comment_id
				FROM 
					?_comment
				WHERE 
					user_id = ?d 
					AND
					target_type= ? 
					AND
					comment_delete = 0
					AND
					comment_publish = 1 
					{ AND target_id NOT IN (?a) }
					{ AND target_parent_id NOT IN (?a) }
				ORDER BY comment_id DESC
				LIMIT ?d, ?d ";

        $aCommentsId = [];
        $aRows = $this->oDb->selectPage($iCount, $sql,
            $iUserId,
            $sTargetType,
            (!empty($aExcludeTarget) ? $aExcludeTarget : DBSIMPLE_SKIP),
            (!empty($aExcludeParentTarget) ? $aExcludeParentTarget : DBSIMPLE_SKIP),
            ($iCurrPage - 1) * $iPerPage, $iPerPage
        );
        if ($aRows) {
            foreach ((array)$aRows as $aRow) {
                $aCommentsId[] = $aRow['comment_id'];
            }
        }
        return $aCommentsId;
    }

    /**
     * Получает количество комментариев одного пользователя
     *
     * @param int    $iUserId              ID пользователя
     * @param string $sTargetType          Тип владельца комментария
     * @param array  $aExcludeTarget       Список ID владельцев, которые необходимо исключить из выдачи
     * @param array  $aExcludeParentTarget Список ID родителей владельцев, которые необходимо исключить из выдачи
     *
     * @return int
     */
    public function getCountCommentsByUserId($iUserId, $sTargetType, $aExcludeTarget = array(), $aExcludeParentTarget = array()) {

        $sql = "SELECT
					COUNT(comment_id) AS cnt
				FROM 
					?_comment
				WHERE 
					user_id = ?d 
					AND
					target_type= ? 
					AND
					comment_delete = 0
					AND
					comment_publish = 1
					{ AND target_id NOT IN (?a) }
					{ AND target_parent_id NOT IN (?a) }
					";
        $iCount = $this->oDb->selectCell($sql,
            $iUserId,
            $sTargetType,
            (!empty($aExcludeTarget) ? $aExcludeTarget : DBSIMPLE_SKIP),
            (!empty($aExcludeParentTarget) ? $aExcludeParentTarget : DBSIMPLE_SKIP)
        );
        return $iCount ?: 0;
    }

    /**
     * Добавляет коммент
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool|int
     */
    public function addComment($oComment) {

        $sql = "INSERT INTO ?_comment
          (
              comment_pid,
              target_id,
              target_type,
              target_parent_id,
              user_id,
              comment_text,
              comment_date,
              comment_user_ip,
              comment_publish,
              comment_text_hash
          )
          VALUES (
              ?, ?d, ?, ?d, ?d, ?, ?, ?, ?d, ?
          )
        ";
        $iId = $this->oDb->query($sql,
            $oComment->getPid(),
            $oComment->getTargetId(),
            $oComment->getTargetType(),
            $oComment->getTargetParentId(),
            $oComment->getUserId(),
            $oComment->getText(),
            $oComment->getDate(),
            $oComment->getUserIp(),
            $oComment->getPublish(),
            $oComment->getTextHash()
        );
        return $iId ?: false;
    }

    /**
     * Добавляет коммент в дерево nested set
     *
     * @param  ModuleComment_EntityComment $oComment    Объект комментария
     *
     * @return bool|int
     */
    public function addCommentTree($oComment) {

        $this->oDb->transaction();

        if ($oComment->getPid() && $oCommentParent = $this->GetCommentsByArrayId(array($oComment->getPid()))) {
            $oCommentParent = $oCommentParent[$oComment->getPid()];
            $iLeft = $oCommentParent->getRight();
            $iLevel = $oCommentParent->getLevel() + 1;

            $sql = "UPDATE ?_comment SET comment_left=comment_left+2 WHERE target_id=?d AND target_type=? AND comment_left>? ;";
            $this->oDb->query($sql, $oComment->getTargetId(), $oComment->getTargetType(), $iLeft - 1);

            $sql = "UPDATE ?_comment SET comment_right=comment_right+2 WHERE target_id=?d AND target_type=? AND comment_right>? ;";
            $this->oDb->query($sql, $oComment->getTargetId(), $oComment->getTargetType(), $iLeft - 1);
        } else {
            if ($oCommentLast = $this->GetCommentLast($oComment->getTargetId(), $oComment->getTargetType())) {
                $iLeft = $oCommentLast->getRight() + 1;
            } else {
                $iLeft = 1;
            }
            $iLevel = 0;
        }

        if ($iId = $this->AddComment($oComment)) {
            $sql = "UPDATE ?_comment SET comment_left = ?d, comment_right = ?d, comment_level = ?d WHERE comment_id = ? ;";
            $this->oDb->query($sql, $iLeft, $iLeft + 1, $iLevel, $iId);
            $this->oDb->commit();
            return $iId;
        }

        if (strtolower(\C::get('db.tables.engine')) == 'innodb') {
            $this->oDb->rollback();
        }

        return false;
    }

    /**
     * Возвращает последний комментарий
     *
     * @param int    $iTargetId      ID владельца коммента
     * @param string $sTargetType    Тип владельца комментария
     *
     * @return ModuleComment_EntityComment|null
     */
    public function getCommentLast($iTargetId, $sTargetType) {

        $sql = "
            SELECT *
            FROM ?_comment
			WHERE 
				target_id = ?d 
				AND
				target_type = ? 
			ORDER BY comment_right DESC
			LIMIT 0,1
				";
        if ($aRow = $this->oDb->selectRow($sql, $iTargetId, $sTargetType)) {
            return E::getEntity('Comment', $aRow);
        }
        return null;
    }

    /**
     * Добавляет новый коммент в прямой эфир
     *
     * @param ModuleComment_EntityCommentOnline $oCommentOnline    Объект онлайн комментария
     *
     * @return bool|int
     */
    public function addCommentOnline($oCommentOnline) {

        $this->deleteCommentOnlineByTargetId($oCommentOnline->getTargetId(), $oCommentOnline->getTargetType());
        $sql = "
                INSERT INTO ?_comment_online
                (
                  target_id, target_type, target_parent_id, comment_id
                )
                VALUES (
                  ?d, ?, ?d, ?d
                )
            ";
        $xResult = $this->oDb->query($sql,
            $oCommentOnline->getTargetId(),
            $oCommentOnline->getTargetType(),
            $oCommentOnline->getTargetParentId(),
            $oCommentOnline->getCommentId()
        );
        return $xResult !== false;
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

        $sql = "DELETE FROM ?_comment_online WHERE target_id = ?d AND target_type = ? ";
        if ($this->oDb->query($sql, $iTargetId, $sTargetType)) {
            return true;
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
        $sql = "UPDATE ?_comment
			SET 
				comment_text= ?,
				comment_rating= ?f,
				comment_count_vote= ?d,
				comment_count_favourite= ?d,
				comment_delete = ?d ,
				comment_publish = ?d ,
				comment_date_edit = CASE comment_text_hash WHEN ? THEN comment_date_edit ELSE ? END,
				comment_text_hash = ?
			WHERE
				comment_id = ?d
		";
        $bResult = $this->oDb->query(
            $sql,
            $oComment->getText(),
            $oComment->getRating(),
            $oComment->getCountVote(),
            $oComment->getCountFavourite(),
            $oComment->getDelete(),
            $oComment->getPublish(),
            $oComment->getTextHash(), // проверка на изменение
            F::Now(),
            $oComment->getTextHash(), // новый хеш
            $oComment->getId()
        );
        return $bResult !== false;
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
    public function setCommentsPublish($iTargetId, $sTargetType, $iPublish)
    {
        $sql = "UPDATE ?_comment
			SET 
				comment_publish = ?
			WHERE
				target_id = ?d AND target_type = ? 
		";
        $bResult = $this->oDb->query($sql, $iPublish, $iTargetId, $sTargetType);
        return $bResult !== false;
    }

    /**
     * Удаляет комментарии из базы данных
     *
     * @param   array|int   $aTargetsId     - Список ID владельцев
     * @param   string      $sTargetType    - Тип владельцев
     *
     * @return  bool
     */
    public function deleteCommentByTargetId($aTargetsId, $sTargetType) {

        $aTargetsId = $this->_arrayId($aTargetsId);
        $sql = "
			DELETE FROM ?_comment
			WHERE
				target_id IN (?a)
				AND
				target_type = ?
		";
        return ($this->oDb->query($sql, $aTargetsId, $sTargetType) !== false);
    }

    /**
     * Удаляет коммент из прямого эфира по массиву переданных идентификаторов
     *
     * @param  array|int    $aCommentsId
     * @param  string       $sTargetType    - Тип владельцев
     *
     * @return bool
     */
    public function deleteCommentOnlineByArrayId($aCommentsId, $sTargetType) {

        $aCommentsId = $this->_arrayId($aCommentsId);
        $sql = "
			DELETE FROM ?_comment_online
			WHERE 
				comment_id IN (?a) 
				AND 
				target_type = ? 
		";
        return ($this->oDb->query($sql, $aCommentsId, $sTargetType) !== false);
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
        $sql = "
			UPDATE ?_comment
			SET 
				target_parent_id = ?d
			WHERE 
				target_id IN (?a)
				AND 
				target_type = ? 
		";
        $bResult = $this->oDb->query($sql, $iParentId, $aTargetId, $sTargetType);
        return $bResult !== false;
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
    public function updateTargetParentByTargetIdOnline($iParentId, $sTargetType, $aTargetId) {

        $sql = "
			UPDATE ?_comment_online
			SET 
				target_parent_id = ?d
			WHERE 
				target_id IN (?a)
				AND 
				target_type = ? 
		";
        $bResult = $this->oDb->query($sql, $iParentId, $aTargetId, $sTargetType);
        return $bResult !== false;
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
        $sql = "
			UPDATE ?_comment
			SET 
				target_parent_id = ?d
			WHERE 
				target_parent_id = ?d
				AND 
				target_type = ? 
		";
        $bResult = $this->oDb->query($sql, $iParentIdNew, $iParentId, $sTargetType);
        return $bResult !== false;
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
        $sql = "
			UPDATE ?_comment_online
			SET 
				target_parent_id = ?d
			WHERE 
				target_parent_id = ?d
				AND 
				target_type = ? 
		";
        $bResult = $this->oDb->query($sql, $iParentIdNew, $iParentId, $sTargetType);

        return $bResult !== false;
    }

    /**
     * Перестраивает дерево комментариев
     * Восстанавливает значения left, right и level
     *
     * @param int    $iPid           ID родителя
     * @param int    $iLft           Значение left для дерева nested set
     * @param int    $iLevel         Уровень
     * @param int    $aTargetId      Список ID владельцев
     * @param string $sTargetType    Тип владельца
     *
     * @return int
     */
    public function restoreTree($iPid, $iLft, $iLevel, $aTargetId, $sTargetType) {

        $iRgt = $iLft + 1;
        $iLevel++;
        $sql = "
              SELECT comment_id
              FROM ?_comment
              WHERE target_id = ? AND target_type = ? { AND comment_pid = ?  } { AND comment_pid IS NULL AND 1=?d}
              ORDER BY  comment_id ASC";

        $aRows = $this->oDb->select($sql,
            $aTargetId,
            $sTargetType,
            (null !== $iPid) ? $iPid : DBSIMPLE_SKIP,
            (null === $iPid) ? 1 : DBSIMPLE_SKIP
        );
        if ($aRows) {
            foreach ($aRows as $aRow) {
                $iRgt = $this->restoreTree($aRow['comment_id'], $iRgt, $iLevel, $aTargetId, $sTargetType);
            }
        }
        $iLevel--;
        if (null !== $iPid) {
            $sql = "UPDATE ?_comment
				SET comment_left=?d, comment_right=?d , comment_level =?d
				WHERE comment_id = ?";
            $this->oDb->query($sql, $iLft, $iRgt, $iLevel, $iPid);
        }

        return $iRgt + 1;
    }

    /**
     * Возвращает список всех используемых типов владельца
     *
     * @return string[]
     */
    public function getCommentTypes() {

        $sql = "
            SELECT target_type
            FROM ?_comment
			GROUP BY target_type ";
        $aTypes = [];
        if ($aRows = $this->oDb->select($sql)) {
            foreach ($aRows as $aRow) {
                $aTypes[] = $aRow['target_type'];
            }
        }
        return $aTypes;
    }

    /**
     * Возвращает список ID владельцев
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iPage          Номер страницы
     * @param int    $iPerPage       Количество элементов на одну старницу
     *
     * @return int[]
     */
    public function getTargetIdByType($sTargetType, $iPage, $iPerPage)
    {
        $sql = "
            SELECT target_id
            FROM ?_comment
			WHERE  target_type = ?
			GROUP BY target_id
			ORDER BY target_id LIMIT ?d, ?d ";

        if ($aRows = $this->oDb->select($sql, $sTargetType, ($iPage - 1) * $iPerPage, $iPerPage)) {
            return $aRows;
        }
        return [];
    }

    /**
     * Пересчитывает счетчик избранных комментариев
     *
     * @return bool
     */
    public function recalculateFavourite() {

        $sql = "
            UPDATE ?_comment c
            SET c.comment_count_favourite = (
                SELECT count(f.user_id)
                FROM ?_favourite f
                WHERE 
                    f.target_id = c.comment_id
                AND
					f.target_publish = 1
				AND
					f.target_type = 'comment'
            )
		";
        $bResult = $this->oDb->query($sql);

        return $bResult !== false;
    }

    /**
     * Получает список комментариев по фильтру
     *
     * @param array $aFilter         Фильтр выборки
     * @param int   $iCount          Возвращает общее количество элментов
     * @param int   $iCurrPage       Номер текущей страницы
     * @param int   $iPerPage        Количество элементов на одну страницу
     *
     * @return int[]
     */
    public function getCommentsIdByFilter($aFilter, &$iCount, $iCurrPage = 0, $iPerPage = 0)
    {
        $aOrderAllow = ['comment_id', 'comment_pid', 'comment_rating', 'comment_date'];
        $sOrder = '';
        if (!empty($aFilter['order'])) {
            if (is_string($aFilter['order'])) {
                $sOrder = $aFilter['order'];
            } elseif (is_array($aFilter['order'])) {
                $aOrders = [];
                foreach ($aFilter['order'] as $key => $value) {
                    if (is_numeric($key)) {
                        if (strpos($value, ' ')) {
                            list($key, $value) = explode(' ', $value);
                        } else {
                            $key = $value;
                            $value = 'asc';
                        }
                    }
                    if (in_array($key, $aOrderAllow, true)) {
                        if (!$value) {
                            $aOrders[] = $key;
                        } elseif (in_array($value, array('asc', 'desc'), true)) {
                            $aOrders[] = $key . ' ' . $value;
                        }
                    }
                }
                if ($aOrders) {
                    $sOrder = implode(',', $aOrders);
                }
            }
        }
        if (empty($sOrder)) {
            $sOrder = ' comment_id DESC ';
        }

        if (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) {
            $aFilter['target_type'] = array($aFilter['target_type']);
        }

        if ($iPerPage) {
            if ($iCurrPage < 1) {
                $iCurrPage = 1;
            }
            $iLimitOffset = ($iCurrPage - 1) * $iPerPage;
            $iLimitCount = $iPerPage;
            $sLimit = " LIMIT $iLimitOffset, $iLimitCount";
        } else {
            $sLimit = '';
        }

        $sql = "SELECT
					comment_id
				FROM
					?_comment
				WHERE
					1 = 1
					{ AND comment_id = ?d }
					{ AND user_id = ?d }
					{ AND target_type = ? }
					{ AND target_type IN (?a) }
					{ AND target_id = ?d }
					{ AND target_id IN (?a) }
					{ AND target_id NOT IN (?a) }
					{ AND target_parent_id = ?d }
					{ AND target_parent_id IN (?a) }
					{ AND target_parent_id NOT IN (?a) }
					{ AND comment_delete = ?d }
					{ AND comment_publish = ?d }
				ORDER BY {$sOrder}
				$sLimit;
					";
        $aResult = [];
        $aRows = $this->oDb->selectPage($iCount, $sql,
            isset($aFilter['id']) ? $aFilter['id'] : DBSIMPLE_SKIP,
            isset($aFilter['user_id']) ? $aFilter['user_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id']) && !is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id']) && is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['not_target_id']) && is_array($aFilter['not_target_id'])) ? $aFilter['not_target_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id']) && !is_array($aFilter['target_parent_id'])) ? $aFilter['target_parent_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id']) && is_array($aFilter['target_parent_id'])) ? $aFilter['target_parent_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['not_target_parent_id']) && is_array($aFilter['not_target_parent_id'])) ? $aFilter['not_target_parent_id'] : DBSIMPLE_SKIP,
            isset($aFilter['delete']) ? $aFilter['delete'] : DBSIMPLE_SKIP,
            isset($aFilter['publish']) ? $aFilter['publish'] : DBSIMPLE_SKIP
        );
        if ($aRows) {
            foreach ((array)$aRows as $aRow) {
                $aResult[] = $aRow['comment_id'];
            }
        }
        return $aResult;
    }
}

// EOF