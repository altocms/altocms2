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
use \alto\engine\generic\Mapper;

/**
 * Объект маппера для работы с БД
 *
 * @package modules.topic
 * @since   1.0
 */
class ModuleTag_MapperTag extends Mapper
{
    /**
     * Добавление тега к топику
     *
     * @param ModuleTag_EntityTag $oTag    Объект тега
     *
     * @return int
     */
    public function addTopicTag($oTag)
    {
        $sql = "INSERT INTO ?_tag (
            target_id,
            target_type,
			target_parent_id,
			user_id,
			tag_text
			)
			VALUES(?d, ?d, ?d, ?)
		";
        $nId = $this->oDb->query($sql, $oTag->getTargetId(), $oTag->getTargetType(), $oTag->getTargetParentId(), $oTag->getUserId(), $oTag->getText());

        return $nId ?: false;
    }

    /**
     * Удаляет теги
     *
     * @param   string $sTargetType
     * @param   int|array $aTargetIds   - ID или массив ID
     *
     * @return  bool
     */
    public function deleteTagsByTarget($sTargetType, $aTargetIds)
    {
        $aIds = $this->_arrayId($aTargetIds);
        $sql
            = "
            DELETE FROM ?_tag
            WHERE target_type=? AND topic_id IN (?a)
        ";
        return ($this->oDb->query($sql, $sTargetType, $aIds) !== false);
    }

    /**
     * Получает список ID сущностей по тегу
     *
     * @param  array  $aFilter
     * @param  int    $iCount          Возвращает общее количество элементов
     * @param  int    $iCurrPage       Номер страницы
     * @param  int    $iPerPage        Количество элементов на страницу
     *
     * @return array
     */
    public function getTargetsIdByFilter($aFilter, &$iCount, $iCurrPage, $iPerPage)
    {

        $sql
            = "
            SELECT
			    target_id
			FROM
			    ?_tag
			WHERE
			    1=1
			    { AND tag_text = ? }
			    { AND target_type = ? }
			    { AND target_type IN (?a) }
				{ AND target_parent_id IN (?a) }
				{ AND target_parent_id NOT IN (?a) }
            ORDER BY target_id DESC
            LIMIT ?d, ?d ";

        $aTopicsId = [];
        $aRows = $this->oDb->selectPage(
            $iCount,
            $sql,
            isset($aFilter['tag_text']) ? $aFilter['tag_text'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id']) && is_array($aFilter['target_parent_id'])) ? $aFilter['target_parent_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id:not']) && is_array($aFilter['target_parent_id:not'])) ? $aFilter['target_parent_id:not'] : DBSIMPLE_SKIP,
            ($iCurrPage - 1) * $iPerPage, $iPerPage
        );
        if ($aRows) {
            return array_column($aRows, 'target_id');
        }
        return [];
    }

    /**
     * Получает список тегов топиков
     *
     * @param array $aFilter
     * @param int   $iLimit           Количество
     *
     * @return array
     */
    public function getTags($aFilter, $iLimit)
    {
        $sql = "
            SELECT
			  t.tag_text,
			  COUNT(t.tag_text) as tag_count
			FROM 
				?_tag as t
			WHERE 
				1=1
			    { AND tag_text = ? }
			    { AND target_type = ? }
			    { AND target_type IN (?a) }
				{ AND target_id IN (?a) }
				{ AND target_id NOT IN (?a) }
				{ AND target_parent_id IN (?a) }
				{ AND target_parent_id NOT IN (?a) }
			GROUP BY 
				t.tag_text
			ORDER BY 
				tag_count DESC
			LIMIT 0, ?d
				";

        $aResult = [];
        $aRows = $this->oDb->select(
            $sql,
            isset($aFilter['tag_text']) ? $aFilter['tag_text'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id']) && is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id:not']) && is_array($aFilter['target_id:not'])) ? $aFilter['target_id:not'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id']) && is_array($aFilter['target_parent_id'])) ? $aFilter['target_parent_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id:not']) && is_array($aFilter['target_parent_id:not'])) ? $aFilter['target_parent_id:not'] : DBSIMPLE_SKIP,
            $iLimit
        );
        if ($aRows) {
            $aData = [];
            foreach ($aRows as $aRow) {
                $aData[mb_strtolower($aRow['topic_tag_text'], 'UTF-8')] = $aRow;
            }
            ksort($aData);
            $aResult = \E::getEntityRows('Tag_Tag', $aData);
        }
        return $aResult;
    }

    /**
     * Получает список тегов по первым буквам тега
     *
     * @param string $sTag      Тэг
     * @param int    $iLimit    Количество
     *
     * @return array
     */
    public function getTagsByLike($sTag, $iLimit)
    {
        $sTag = mb_strtolower($sTag, 'UTF-8');
        $sql
            = "SELECT
				tag_text
			FROM 
				?_tag
			WHERE
				tag_text LIKE ?
			GROUP BY 
				tag_text
			LIMIT 0, ?d
				";
        $aResult = [];
        if ($aRows = $this->oDb->select($sql, $sTag . '%', $iLimit)) {
            $aResult = \E::getEntityRows('Tag_Tag', $aRows);
        }
        return $aResult;
    }

    /**
     * Перемещает теги топиков в другой блог
     *
     * @param array $aFilter
     * @param int $iParentTargetIdNew
     *
     * @return bool
     */
    public function changeTargetParentId($aFilter, $iParentTargetIdNew)
    {
        $sql = "UPDATE ?_tag
			SET 
				target_parent_id= ?d
			WHERE
				1=1
			    { AND tag_text = ? }
			    { AND target_type = ? }
			    { AND target_type IN (?a) }
				{ AND target_id IN (?a) }
				{ AND target_id NOT IN (?a) }
				{ AND target_parent_id IN (?a) }
				{ AND target_parent_id NOT IN (?a) }
		";
        $bResult = $this->oDb->query(
            $sql,
            $iParentTargetIdNew,
            isset($aFilter['tag_text']) ? $aFilter['tag_text'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_type']) && is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id']) && is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_id:not']) && is_array($aFilter['target_id:not'])) ? $aFilter['target_id:not'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id']) && is_array($aFilter['target_parent_id'])) ? $aFilter['target_parent_id'] : DBSIMPLE_SKIP,
            (isset($aFilter['target_parent_id:not']) && is_array($aFilter['target_parent_id:not'])) ? $aFilter['target_parent_id:not'] : DBSIMPLE_SKIP
        );

        return $bResult !== false;
    }

}

// EOF
