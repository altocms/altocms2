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
 * @package modules.media
 * @since   1.0
 */
class ModuleMedia_MapperMedia extends Mapper
{
    /**
     * @param ModuleMedia_EntityMedia $oMedia
     *
     * @return int|bool
     */
    public function add($oMedia)
    {
        $aParams = [
            ':date_add'     => F::Now(),
            ':user_id'      => $oMedia->getUserId(),
            ':link'         => $oMedia->isLink() ? 1 : 0,
            ':type'         => $oMedia->getType(),
            ':path_url'     => $oMedia->getPathUrl(),
            ':path_file'    => $oMedia->getPathFile(),
            ':hash_url'     => $oMedia->getHashUrl(),
            ':hash_file'    => $oMedia->getHashFile(),
            ':storage'      => $oMedia->getStorage(),
            ':uuid'         => $oMedia->getUuid(),
        ];
        $sql = "
            SELECT media_id
            FROM ?_media
            WHERE
                storage = ?:storage AND uuid = ?:uuid
            LIMIT 1
            ";
        $nId = $this->oDb->sqlSelectCell($sql, $aParams);
        if (!$nId) {
            $sql = "
            INSERT INTO ?_media
            (
                date_add,
                user_id,
                link,
                type,
                path_url,
                path_file,
                hash_url,
                hash_file,
                storage,
                uuid
            )
            VALUES (
                ?:date_add,
                ?d:user_id,
                ?d:link,
                ?d:type,
                ?:path_url,
                ?:path_file,
                ?:hash_url,
                ?:hash_file,
                ?:storage,
                ?:uuid
            )
            ";
            $nId = $this->oDb->sqlQuery($sql, $aParams);
        }
        return $nId ? $nId : false;
    }

    /**
     * @param ModuleMedia_EntityMedia $oMedia
     *
     * @return bool
     */
    public function addTargetRel($oMedia)
    {
        $aParams = [
            ':id'           => $oMedia->getMediaId(),
            ':target_type'  => $oMedia->getTargetType(),
            ':target_id'    => $oMedia->getTargetId(),
            ':date_add'     => F::Now(),
            ':description'  => $oMedia->getDescription(),
            ':incount'      => $oMedia->getIncount() ?: 1,
        ];
        $sql = "
            SELECT media_id
            FROM ?_media_target
            WHERE
                target_type = ?:target_type
                AND target_id = ?d:target_id
                AND media_id = ?d:id
            LIMIT 1
        ";
        if ($iId = $this->oDb->sqlSelectCell($sql, $aParams)) {
            $sql = "
                UPDATE ?_media_target
                SET incount=incount+?d:incount
                WHERE media_id = ?d:id
            ";
            if ($this->oDb->sqlQuery($sql, $aParams)) {
                return $iId;
            }
        } else {
            $sql = "
                INSERT INTO ?_media_target
                (
                    media_id,
                    target_type,
                    target_id,
                    date_add,
                    description,
                    target_tmp,
                    incount
                )
                VALUES (
                    ?d:id,
                    ?:target_type,
                    ?d:target_id,
                    ?:date_add,
                    ?:description,
                    ?:target_tmp,
                    ?d:incount
                )
            ";
            if ($iId = $this->oDb->sqlQuery($sql, $aParams)) {
                return $iId ? $iId : false;
            }
        }
        return false;
    }

    /**
     * @param array $aCriteria
     *
     * @return array
     */
    protected function _getMediaRelByCriteria($aCriteria)
    {
        $aFilter = (isset($aCriteria['filter']) ? $aCriteria['filter'] : []);
        if (isset($aCriteria['fields'])) {
            if (is_array($aCriteria['fields'])) {
                $sFields = implode(',', $aCriteria['fields']);
            } else {
                $sFields = (string)$aCriteria['fields'];
            }
        } else {
            $sFields = 'mrt.*, mr.*';
        }
        $oSql = $this->oDb->sql("
            SELECT
                id AS ARRAY_KEY,
                $sFields
            FROM ?_media_target AS mrt
                INNER JOIN ?_media AS mr ON mr.media_id=mrt.media_id
            WHERE
                1=1
                {AND mrt.id=?d:id}
                {AND mrt.id IN (?a:ids)}
                {AND mrt.media_id=(?d:media_id)}
                {AND mrt.media_id IN (?a:media_ids)}
                {AND mrt.target_type=?:target_type}
                {AND mrt.target_type IN (?a:target_types)}
                {AND mrt.target_id=?d:target_id}
                {AND mrt.target_id IN (?a:target_ids)}
                {AND mr.user_id=?d:user_id}
                {AND mr.user_id IN (?a:user_ids)}
                {AND mr.link=(?d:link)}
                {AND (mr.type & ?d:type)>0}
                {AND mr.hash_url=?:hash_url}
                {AND mr.hash_file=?:hash_file}
                {AND mrt.target_tmp=?:target_tmp}
            ORDER BY mr.media_order DESC, mr.media_id ASC
        ");
        $aParams = array(
                ':id' => (isset($aFilter['id']) && !is_array($aFilter['id'])) ? $aFilter['id'] : DBSIMPLE_SKIP,
                ':ids' => (isset($aFilter['id']) && is_array($aFilter['id'])) ? $aFilter['id'] : DBSIMPLE_SKIP,
                ':media_id' => (isset($aFilter['media_id']) && !is_array($aFilter['media_id'])) ? $aFilter['media_id'] : DBSIMPLE_SKIP,
                ':media_ids' => (isset($aFilter['media_id']) && is_array($aFilter['media_id'])) ? $aFilter['media_id'] : DBSIMPLE_SKIP,
                ':target_type' => (isset($aFilter['target_type']) && !is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
                ':target_types' => (isset($aFilter['target_type']) && is_array($aFilter['target_type'])) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
                ':target_id' => (isset($aFilter['target_id']) && !is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
                ':target_ids' => (isset($aFilter['target_id']) && is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP,
                ':user_id' => (isset($aFilter['user_id']) && !is_array($aFilter['user_id'])) ? $aFilter['user_id'] : DBSIMPLE_SKIP,
                ':user_ids' => (isset($aFilter['user_id']) && is_array($aFilter['user_id'])) ? $aFilter['user_id'] : DBSIMPLE_SKIP,
                ':link' => isset($aFilter['link']) ? $aFilter['link'] : DBSIMPLE_SKIP,
                ':type' => isset($aFilter['type']) ? $aFilter['type'] : DBSIMPLE_SKIP,
                ':hash_url' => isset($aFilter['hash_url']) ? $aFilter['hash_url'] : DBSIMPLE_SKIP,
                ':hash_file' => isset($aFilter['hash_file']) ? $aFilter['hash_file'] : DBSIMPLE_SKIP,
                ':target_tmp' => isset($aFilter['target_tmp']) ? $aFilter['target_tmp'] : DBSIMPLE_SKIP,
            );
        $aRows = $oSql->bind($aParams)->select();

        return ['data' => $aRows ? $aRows : []];
    }

    /**
     * @param array $aCriteria
     *
     * @return array
     */
    public function getMediaByCriteria($aCriteria)
    {
        $aFilter = (isset($aCriteria['filter']) ? $aCriteria['filter'] : []);
        $aParams = [];
        $aUuidFilter = [];
        if (isset($aFilter['id']) && !isset($aFilter['media_id'])) {
            $aFilter['media_id'] = $aFilter['id'];
        }
        if (isset($aFilter['storage_uuid'])) {
            if (is_array($aFilter['storage_uuid'])) {
                $nUniqUid = 0;
                foreach ($aFilter['storage_uuid'] as $nCnt => $aStorageUuid) {
                    if ($aStorageUuid['storage']) {
                        $aUuidFilter[] = '(storage=?:storage' . $nCnt . ' AND uuid=?:uuid' . $nCnt . ')';
                        $aParams[':storage' . $nCnt] = $aStorageUuid['storage'];
                        $aParams[':uuid' . $nCnt] = $aStorageUuid['uuid'];
                        $nUniqUid++;
                    } else {
                        $aUuidFilter[] = '(uuid=?:uuid' . $nCnt . ')';
                        $aParams[':uuid' . $nCnt] = $aStorageUuid['uuid'];
                    }
                }
                if (count($aFilter['storage_uuid']) == $nUniqUid && !isset($aCriteria['limit'])) {
                    $aCriteria['limit'] = $nUniqUid;
                }
                unset($aFilter['storage_uuid']);
            }
        }
        if (isset($aFilter['media_id']) && !isset($aCriteria['limit'])) {
            if (is_array($aFilter['media_id'])) {
                $aCriteria['limit'] = count($aFilter['media_id']);
            } else {
                $aCriteria['limit'] = 1;
            }
        }
        if (isset($aFilter['target_type'])) {
            if (!is_array($aFilter['target_type'])) {
                $aFilter['target_type'] = [$aFilter['target_type']];
            }
            $aUuidFilter[] = '(target_type IN (?a:target_type))';
            $aParams[':target_type'] = $aFilter['target_type'];
        }
        list($nOffset, $nLimit) = $this->_prepareLimit($aCriteria);

        // Формируем строку лимита и автосчетчик общего числа записей
        if ($nOffset !== false && $nLimit !== false) {
            $sSqlLimit = 'LIMIT ' . $nOffset . ', ' . $nLimit;
        } elseif ($nLimit != false) {
            $sSqlLimit = 'LIMIT ' . $nLimit;
        } else {
            $sSqlLimit = '';
        }

        if (isset($aCriteria['order'])) {
            $sOrder = $aCriteria['order'];
        } else {
            $sOrder = 'media_order DESC, media_id ASC';
        }
        if ($sOrder) {
            $sSqlOrder = 'ORDER BY ' . $sOrder;
        }

        $bTargetsCount = false;
        if (isset($aCriteria['fields'])) {
            if (is_array($aCriteria['fields'])) {
                if ($sKey = array_search('targets_count', $aCriteria['fields'])) {
                    $bTargetsCount = true;
                    unset($aCriteria['fields'][$sKey]);
                }
                $sFields = implode(',', $aCriteria['fields']);
            } else {
                $sFields = (string)$aCriteria['fields'];
            }
        } else {
            $sFields = 'mr.*';
        }
        if ($bTargetsCount) {
            $sFields .= ', 0 AS targets_count';
        }

        if ($aUuidFilter) {
            $sUuidFilter = '1=1 AND (' . implode(' OR ', $aUuidFilter) . ')';
        } else {
            $sUuidFilter = '1=1';
        }

        if (!isset($aFilter['target_type'])) {
            $oSql = $this->oDb->sql("
            SELECT
                media_id AS ARRAY_KEY,
                $sFields
            FROM ?_media AS mr
            WHERE
                $sUuidFilter
                {AND mr.media_id=?d:media_id}
                {AND mr.media_id IN (?a:media_ids)}
                {AND mr.user_id=?d:user_id}
                {AND mr.user_id IN (?a:user_ids)}
                {AND mr.link=?d:link}
                {AND (mr.type & ?d:type)>0}
                {AND mr.hash_url=?:hash_url}
                {AND mr.hash_url IN (?a:hash_url_a)}
                {AND mr.hash_file=?:hash_file}
                {AND mr.hash_file IN (?:hash_file_a)}
            $sSqlOrder
            $sSqlLimit
        ");
        } else
        $oSql = $this->oDb->sql("
            SELECT
                mr.media_id AS ARRAY_KEY,
                mrt.target_type,
                mrt.target_id,
                $sFields
            FROM ?_media AS mr, ?_media_target as mrt
            WHERE
                $sUuidFilter
                AND mrt.media_id = mr.media_id
                {AND mr.media_id=?d:media_id}
                {AND mr.media_id IN (?a:media_ids)}
                {AND mr.user_id=?d:user_id}
                {AND mr.user_id IN (?a:user_ids)}
                {AND mr.link=?d:link}
                {AND (mr.type & ?d:type)>0}
                {AND mr.hash_url=?:hash_url}
                {AND mr.hash_url IN (?a:hash_url_a)}
                {AND mr.hash_file=?:hash_file}
                {AND mr.hash_file IN (?:hash_file_a)}
                {AND mrt.target_type IN (?a:target_type)}
                {AND mrt.target_id = ?d:target_id}
            $sSqlOrder
            $sSqlLimit
        ");
        $aParams = array_merge(
            $aParams,
            [
                 ':media_id' => (isset($aFilter['media_id']) && !is_array($aFilter['media_id'])) ? $aFilter['media_id'] : DBSIMPLE_SKIP,
                 ':media_ids' => (isset($aFilter['media_id']) && is_array($aFilter['media_id'])) ? $aFilter['media_id'] : DBSIMPLE_SKIP,
                 ':user_id' => (isset($aFilter['user_id']) && !is_array($aFilter['user_id'])) ? $aFilter['user_id'] : DBSIMPLE_SKIP,
                 ':user_ids' => (isset($aFilter['user_id']) && is_array($aFilter['user_id'])) ? $aFilter['user_id'] : DBSIMPLE_SKIP,
                 ':link' => isset($aFilter['link']) ? ($aFilter['link'] ? 1 : 0) : DBSIMPLE_SKIP,
                 ':type' => isset($aFilter['type']) ? $aFilter['type'] : DBSIMPLE_SKIP,
                 ':hash_url' => (isset($aFilter['hash_url']) && !is_array($aFilter['hash_url'])) ? $aFilter['hash_url'] : DBSIMPLE_SKIP,
                 ':hash_url_a' => (isset($aFilter['hash_url']) && is_array($aFilter['hash_url'])) ? $aFilter['hash_url'] : DBSIMPLE_SKIP,
                 ':hash_file' => (isset($aFilter['hash_file']) && !is_array($aFilter['hash_file'])) ? $aFilter['hash_file'] : DBSIMPLE_SKIP,
                 ':hash_file_a' => (isset($aFilter['hash_file']) && is_array($aFilter['hash_file'])) ? $aFilter['hash_file'] : DBSIMPLE_SKIP,
                 ':target_type' => isset($aFilter['target_type']) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
                 ':target_id' => (isset($aFilter['target_id']) && !is_array($aFilter['target_id'])) ? $aFilter['target_id'] : DBSIMPLE_SKIP
            ]
        );
        $aRows = $oSql->bind($aParams)->select();

        if ($aRows && $bTargetsCount) {
            $aId = array_keys($aRows);
            $sql = "
                SELECT
                  media_id AS ARRAY_KEY,
                  COUNT(*) AS cnt1,
                  SUM(incount) AS cnt2
                FROM ?_media_target
                WHERE media_id IN (?a)
                GROUP BY media_id
            ";
            $aCnt = $this->oDb->select($sql, $aId);
            if ($aCnt) {
                foreach($aCnt as $nId=>$aRow) {
                    if (isset($aRows[$nId])) {
                        $aRows[$nId]['targets_count'] = max($aRow['cnt1'], $aRow['cnt2']);
                    }
                }
            }
        }
        return [
            'data' => $aRows ? $aRows : [],
            'total' => -1,
        ];
    }

    /**
     * @param $aFilter
     * @param $nPage
     * @param $nPerPage
     *
     * @return array
     */
    public function getMediaByFilter($aFilter, $nPage, $nPerPage)
    {
        $aCriteria = [
            'filter' => $aFilter,
            'limit'  => [($nPage - 1) * $nPerPage, $nPerPage],
        ];
        $aData = $this->getMediaByCriteria($aCriteria);
        if ($aData['data']) {
            $aData['data'] = \E::getEntityRows('Media', $aData['data']);
        }
        return $aData;
    }

    /**
     * @param string[] $aUrls
     * @param int|null $nUserId
     *
     * @return array
     */
    public function getMediaIdByUrl($aUrls, $nUserId = null)
    {
        if (!is_array($aUrls)) {
            $aUrls = [$aUrls];
        } else {
            $aUrls = array_unique($aUrls);
        }
        $aHash = [];
        foreach($aUrls as $sLink) {
            $aHash[] = md5(\E::Module('Media')->normalizeUrl($sLink));
        }
        return $this->GetMresourcesIdByHashUrl($aHash, $nUserId);
    }

    /**
     * @param string[] $aHashUrls
     * @param int|null $nUserId
     *
     * @return array
     */
    public function getMresourcesIdByHashUrl($aHashUrls, $nUserId = null) {

        if (!is_array($aHashUrls)) {
            $aHashUrls = array($aHashUrls);
        }
        $aCritera = array(
            'filter' => array(
                'hash_url' => $aHashUrls,
            ),
            'fields' => 'mr.media_id'
        );
        if ($nUserId) {
            $aCritera['filter']['user_id'] = $nUserId;
        }
        $aData = $this->getMediaByCriteria($aCritera);
        if ($aData['data']) {
            return F::Array_Column($aData['data'], 'media_id');
        }
        return [];
    }

    /**
     * @param string[] $aUrls
     * @param int|null $nUserId
     *
     * @return array
     */
    public function getMresourcesByUrl($aUrls, $nUserId = null) {

        if (!is_array($aUrls)) {
            $aUrls = array($aUrls);
        }
        $aUrlHashs = [];
        foreach ($aUrls as $nI => $sUrl) {
            $aUrlHashs = md5($sUrl);
        }
        return $this->GetMresourcesByHashUrl($aUrlHashs, $nUserId);
    }

    /**
     * @param string[] $aUrlHashs
     * @param int|null $nUserId
     *
     * @return array
     */
    public function getMresourcesByHashUrl($aUrlHashs, $nUserId = null) {

        if (!is_array($aUrlHashs)) {
            $aUrlHashs = array($aUrlHashs);
        }
        $aCritera = array(
            'filter' => array(
                'hash_url' => $aUrlHashs,
            ),
            'fields' => array(
                'mr.*',
                'targets_count',
            ),
        );
        if ($nUserId) {
            $aCritera['filter']['user_id'] = $nUserId;
        }
        $aData = $this->getMediaByCriteria($aCritera);
        $aResult = [];
        if ($aData['data']) {
            $aResult = \E::getEntityRows('Media', $aData['data']);
        }
        return $aResult;
    }

    /**
     * @param int[] $aId
     *
     * @return ModuleMedia_EntityMedia[]
     */
    public function getMediaById($aId) {

        $aCriteria = array(
            'filter' => array(
                'id' => $aId,
            ),
            'fields' => array(
                'mr.*',
                'targets_count',
            ),
        );

        $aData = $this->getMediaByCriteria($aCriteria);
        $aResult = [];
        if ($aData['data']) {
            $aResult = \E::getEntityRows('Media', $aData['data']);
        }
        return $aResult;
    }

    /**
     * @param array|string $aStorageUuid
     *
     * @return array|ModuleMedia_EntityMedia
     */
    public function getMresourcesByUuid($aStorageUuid)
    {
        $aCriteria = [
            'filter' => [
                'storage_uuid' => [],
            ],
            'fields' => [
                'mr.*',
                'targets_count',
            ],
        ];
        if (!is_array($aStorageUuid)) {
            $aStorageUuid = [$aStorageUuid];
        }
        foreach ((array)$aStorageUuid as $sUuid) {
            if ($sUuid[0] === '[' && ($n = strpos($sUuid, ']'))) {
                $sStorage = substr($sUuid, 1, $n - 1);
                $sUuid = substr($sUuid, $n + 1);
            } else {
                $sStorage = null;
            }
            $aCriteria['filter']['storage_uuid'][] = array('storage' => $sStorage, 'uuid' => $sUuid);
        }

        $aData = $this->getMediaByCriteria($aCriteria);

        return $aData['data'] ? E::getEntityRows('Media', $aData['data']) : [];
    }

    /**
     * Returns media resources' relation entities by target
     *
     * @param $aId
     *
     * @return array
     */
    public function getMediaRelById($aId) {

        $aCriteria = array(
            'filter' => array(
                'id' => $aId,
            ),
        );

        $aData = $this->_getMediaRelByCriteria($aCriteria);
        $aResult = [];
        if ($aData['data']) {
            $aResult = \E::getEntityRows('Media_MediaRel', $aData['data']);
        }
        return $aResult;
    }

    /**
     * Returns media resources' relation entities by target
     *
     * @param string    $sTargetType
     * @param int|array $xTargetId
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getMediaRelByTarget($sTargetType, $xTargetId) {

        $aCriteria = array(
            'filter' => array(
                'target_type' => $sTargetType,
                'target_id' => $xTargetId,
            ),
        );

        $aData = $this->_getMediaRelByCriteria($aCriteria);
        $aResult = [];
        if ($aData['data']) {
            $aResult = \E::getEntityRows('Media_MediaRel', $aData['data']);
        }
        return $aResult;
    }

    /**
     * Returns media resources' relation entities by target
     *
     * @param string|array  $xTargetType
     * @param int|array $xTargetId
     * @param int|array $xUserId
     *
     * @return ModuleMedia_EntityMediaRel[]
     */
    public function getMediaRelByTargetAndUser($xTargetType, $xTargetId, $xUserId) {

        if (is_array($xTargetType)) {
            $aCriteria = array(
                'filter' => array(
                    'target_types' => $xTargetType,
                ),
            );
        } else {
            $aCriteria = array(
                'filter' => array(
                    'target_type' => $xTargetType,
                ),
            );
        }

        if (!is_null($xTargetId)) {
            $aCriteria['filter']['target_id'] = $xTargetId;
        }
        if (!is_null($xUserId)) {
            $aCriteria['filter']['user_id'] = $xUserId;
        }
        $aData = $this->_getMediaRelByCriteria($aCriteria);
        $aResult = [];
        if ($aData['data']) {
            $aResult = \E::getEntityRows('Media_MediaRel', $aData['data']);
        }
        return $aResult;
    }

    /**
     * Deletes media resources by ID
     *
     * @param $aId
     *
     * @return bool
     */
    public function deleteMedia($aId) {

        if (is_array($aId)) {
            $aId = $this->_arrayId($aId);
            $nId = 0;
            $nLimit = count($aId);
        } else {
            $nId = (int)$aId;
            $aId = [];
            $nLimit = 1;
        }
        if (!count($aId) && !$nId) {
            return;
        }
        $sql = "
            DELETE FROM ?_media
            WHERE
                1=1
                {AND media_id=?d}
                {AND media_id IN (?a)}
            LIMIT ?d
        ";
        $xResult = $this->oDb->query(
            $sql,
            $nId ? $nId : DBSIMPLE_SKIP,
            count($aId) ? $aId : DBSIMPLE_SKIP,
            $nLimit
        );
        return $xResult !== false;
    }

    /**
     * Deletes media resources' relations by rel ID
     *
     * @param $aId
     *
     * @return bool
     */
    public function deleteMediaRel($aId) {

        if (is_array($aId)) {
            $aId = $this->_arrayId($aId);
            $nId = 0;
            $nLimit = count($aId);
        } else {
            $nId = (int)$aId;
            $aId = [];
            $nLimit = 1;
        }
        if (!count($aId) && !$nId) {
            return;
        }
        $sql = "
            DELETE FROM ?_media_target
            WHERE
                1=1
                {AND id=?d}
                {AND id IN (?a)}
            LIMIT ?d
        ";
        $xResult = $this->oDb->query(
            $sql,
            $nId ? $nId : DBSIMPLE_SKIP,
            count($aId) ? $aId : DBSIMPLE_SKIP,
            $nLimit
        );
        return $xResult !== false;
    }

    /**
     * Deletes media resources' relations by target
     *
     * @param string $sTargetType
     * @param int    $iTargetId
     *
     * @return bool
     */
    public function deleteTargetRel($sTargetType, $iTargetId) {

        $sql = "
            DELETE FROM ?_media_target
            WHERE
                target_type=?
                AND
                target_id=?d
        ";
        $xResult = $this->oDb->query(
            $sql,
            $sTargetType,
            $iTargetId
        );
        return $xResult !== false;
    }

    /**
     * Получает все типы целей
     *
     * @return string[]
     */
    public function getTargetTypes() {

        return $this->oDb->selectCol("select DISTINCT target_type from ?_media_target");
    }

    /**
     * Получает количество ресурсов по типу
     *
     * @param string $sTargetType
     *
     * @return int
     */
    public function getMediaCountByTarget($sTargetType) {

        if ($sTargetType == 'all') {
            $aRow =  $this->oDb->selectRow("SELECT COUNT(*) AS count FROM ?_media");
        } else {
            if (!is_array($sTargetType)) {
                $sTargetType = array($sTargetType);
            }
            $aRow =  $this->oDb->selectRow("
              SELECT
                COUNT(*) AS count
              FROM ?_media_target t, ?_media m
                WHERE
              m.media_id = t.media_id
              AND t.target_type IN ( ?a )", $sTargetType);
        }


        return isset($aRow['count'])?$aRow['count']:0;
    }

    /**
     * Получает количество ресурсов по типу и пользователю
     *
     * @param string $sTargetType
     * @param int    $iUserId
     *
     * @return int
     */
    public function getMediaCountByTargetAndUserId($sTargetType, $iUserId) {

        if ($sTargetType == 'all') {
            $aRow =  $this->oDb->selectRow("select count(t.target_type) as count from ?_media_target t, ?_media m  where m.user_id = ?d and m.media_id = t.media_id", $iUserId);
        } else {
            if (!is_array($sTargetType)) {
                $sTargetType = array($sTargetType);
            }
            $aRow =  $this->oDb->selectRow("select count(t.target_type) as count from ?_media_target t, ?_media m  where m.user_id = ?d and m.media_id = t.media_id and t.target_type in ( ?a )", $iUserId, $sTargetType);
        }


        return isset($aRow['count'])?$aRow['count']:0;
    }

    /**
     * Получает количество ресурсов по типу и ид.
     *
     * @param string $sTargetType
     * @param int    $iTargetId
     * @param int    $iUserId
     *
     * @return int
     */
    public function getMediaCountByTargetIdAndUserId($sTargetType, $iTargetId, $iUserId){

        $sql = "select
                  count(t.target_type) as count
                from
                  ?_media_target t, ?_media m
                where
                  m.user_id = ?d
                  and m.media_id = t.media_id
                  and t.target_id = ?d
                  and t.target_type = ?";

        $aRow =  $this->oDb->selectRow($sql, $iUserId, $iTargetId, $sTargetType);


        return isset($aRow['count'])?$aRow['count']:0;
    }

    /**
     * Обновляет параметры ресурса
     *
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return bool
     */
    public function updateExtraData($oResource)
    {
        $sql = "UPDATE ?_media SET media_extra = ? WHERE media_id = ?d";

        return false !== $this->oDb->query($sql, $oResource->getParams(), $oResource->getMediaId());
    }

    /**
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return mixed
     */
    public function updateMresouceUrl($oResource)
    {
        $sql = "UPDATE ?_media SET
                  uuid = ?,
                  path_url = ?,
                  hash_url = ?,
                  path_file = ?,
                  hash_file = ?
                WHERE media_id = ?d";
        return $this->oDb->query($sql,
            $oResource->getUuid(),
            $oResource->getPathUrl(),
            $oResource->getHashUrl(),
            $oResource->getPathFile(),
            $oResource->getHashFile(),
            $oResource->getMediaId()
        );
    }

    /**
     * Обновляет тип ресурса
     *
     * @param ModuleMedia_EntityMedia $oResource
     *
     * @return bool
     */
    public function updateType($oResource)
    {
        $sql = "UPDATE ?_media SET type = ?d WHERE media_id = ?d";

        return $this->oDb->query($sql, $oResource->getType(), $oResource->getMediaId());
    }

    /**
     * Устанавливает главное изображение фотосета
     *
     * @param ModuleMedia_EntityMedia $oResource
     * @param string                          $sTargetType
     * @param int                             $iTargetId
     *
     * @return bool
     */
    public function updatePrimary($oResource, $sTargetType, $iTargetId)
    {
        $sql = "UPDATE ?_media SET type = ?d WHERE media_id IN (
          SELECT media_id FROM ?_media_target WHERE target_type = ? AND target_id = ?d
        )";
        $bResult = $this->oDb->query($sql, ModuleMedia::TYPE_PHOTO, $sTargetType, $iTargetId);

        $bResult = ($bResult !== false && $this->updateType($oResource));

        return $bResult;
    }

    /**
     * Устанавливает новый порядок сортировки изображений
     *
     * @param $aOrder
     * @param $sTargetType
     * @param $iTargetId
     *
     * @return bool
     */
    public function updateOrder($aOrder, $sTargetType, $iTargetId)
    {
        $sData = '';
        foreach ($aOrder as $sId => $iSort) {
            $sData .= " WHEN media_id = '$sId' THEN '$iSort' ";
        }

        $sql ="UPDATE ?_media SET media_order = (CASE $sData END) WHERE
                media_id
              IN (
                SELECT
                  media_id
                FROM
                  ?_media_target
                WHERE
                  target_type = ? AND target_id = ?d)";


        return false !== $this->oDb->query($sql, $sTargetType, $iTargetId);
    }

    /**
     * Возвращает категории изображения для пользователя
     *
     * @param int $iUserId
     * @param int $sTopicId
     *
     * @return mixed
     */
    public function getImageCategoriesByUserId($iUserId, $sTopicId)
    {
        $sql = "SELECT
                  IF(
                    ISNULL(t.target_tmp),
                    IF((t.target_type LIKE 'topic%' AND t.target_id = ?d), 'current',
                      IF(t.target_type = 'profile_avatar' OR t.target_type = 'profile_photo', 'user', t.target_type)),
                    'tmp') AS ttype
                  , count(t.target_id) AS count
                FROM
                  ?_media_target as t, ?_media as m
                WHERE
                  t.media_id = m.media_id
                  AND m.user_id = ?d
                  AND t.target_type IN ( ?a )

                GROUP  BY
                  ttype
                ORDER BY
                 m.date_add desc";

        return $this->oDb->select($sql, (int)$sTopicId, $iUserId, [
            'current',
            'tmp',
            'blog_avatar',
            'profile_avatar',
            'profile_photo'
        ]);

    }

    /**
     * Возвращает категории изображения для пользователя
     * @param $iUserId
     *
     * @return mixed
     */
    public function getAllImageCategoriesByUserId($iUserId)
    {
        $sql = "SELECT
                  IF(
                    ISNULL(t.target_tmp),
                    IF((t.target_type LIKE 'topic%'), 'topic',
                      IF(t.target_type = 'profile_avatar' OR t.target_type = 'profile_photo', 'user', t.target_type)),
                    'tmp') AS ttype
                  , count(t.target_id) AS count
                FROM
                  ?_media_target as t, ?_media as m
                WHERE
                  t.media_id = m.media_id
                  AND (m.type & (?d | ?d | ?d))
                  AND m.user_id = ?d

                GROUP  BY
                  ttype                ORDER BY
                 m.date_add desc";

        return $this->oDb->select($sql,
            ModuleMedia::TYPE_IMAGE,
            ModuleMedia::TYPE_PHOTO,
            ModuleMedia::TYPE_PHOTO_PRIMARY,
            $iUserId);

    }

    /**
     * Возвращает категории изображения для пользователя
     * @param $iUserId
     *
     * @return mixed
     */
    public function getCountImagesByUserId($iUserId)
    {
        $sql = "SELECT
                  count(media_id) AS count
                FROM
                  ?_media
                WHERE
                  user_id = ?d
                  AND (type & (?d | ?d | ?d))";

        if ($aRow = $this->oDb->selectRow($sql,
            $iUserId,
            ModuleMedia::TYPE_IMAGE,
            ModuleMedia::TYPE_PHOTO,
            ModuleMedia::TYPE_PHOTO_PRIMARY)) {
            return (int)$aRow['count'];
        }
        return 0;

    }

    /**
     * @param $iUserId
     * @param $sTopicId
     *
     * @return mixed
     */
    public function getCurrentTopicResourcesId($iUserId, $sTopicId)
    {
        $sql = "select r.media_id FROM
                  (SELECT
                  t.media_id

                FROM ?_media_target AS t, ?_media AS m
                WHERE t.media_id = m.media_id
                      AND (m.type & (?d | ?d | ?d))
                      AND m.user_id = ?d
                      AND ({1 = ?d AND t.target_tmp IS NOT NULL}{1 = ?d AND t.target_tmp IS NULL} AND ((t.target_type in ( ?a ) || t.target_type LIKE 'single-image-uploader%')  AND t.target_id = ?d))

                GROUP BY t.media_id  ORDER BY
                 m.date_add desc) as r";
        $aData = $this->oDb->selectCol($sql,
            ModuleMedia::TYPE_IMAGE,
            ModuleMedia::TYPE_PHOTO,
            ModuleMedia::TYPE_PHOTO_PRIMARY,
            $iUserId,
            $sTopicId == FALSE ? 1 : DBSIMPLE_SKIP,
            $sTopicId != FALSE ? 1 : DBSIMPLE_SKIP,
            array(
            'photoset',
            'topic'
        ), (int)$sTopicId);

        return $aData;
    }

    /**
     * Получает ид. топиков с картинками
     *
     * @param array $aFilter
     * @param int $iCount
     * @param int $iCurrPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getTopicInfo($aFilter, &$iCount, $iCurrPage, $iPerPage) {

        $sql = "SELECT
                  COUNT(DISTINCT t.topic_id) AS cnt
                FROM
                  ?_media m
                  LEFT JOIN ?_media_target mt ON m.media_id = mt.media_id
                  LEFT JOIN ?_topic t ON t.topic_id = mt.target_id
                  LEFT JOIN ?_blog b ON b.blog_id = t.blog_id
                WHERE
                  (m.user_id = ?d)
                  {AND (m.type & ?d)}
                  AND (mt.target_id <> 0)
                  {AND (mt.target_type IN (?a) OR mt.target_type LIKE 'single-image-uploader%')}
                  {AND (t.topic_publish = ?d) AND (t.topic_date_show <= NOW())}
                  {AND t.topic_index_ignore = ?d}
                  {AND (t.topic_type = ?)}
                  {AND t.topic_type IN (?a)}
                  {AND b.blog_type = 'personal' OR t.blog_id IN ( ?a )}
                ";
        $iCount = $this->oDb->selectCell($sql,
            $aFilter['user_id'],
            isset($aFilter['media_type']) ? $aFilter['media_type'] : DBSIMPLE_SKIP,
            isset($aFilter['target_type']) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_publish']) ? $aFilter['topic_publish'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_index_ignore']) ? $aFilter['topic_index_ignore'] : DBSIMPLE_SKIP,
            (isset($aFilter['topic_type']) && !is_array($aFilter['topic_type'])) ? $aFilter['topic_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['topic_type']) && is_array($aFilter['topic_type'])) ? $aFilter['topic_type'] : DBSIMPLE_SKIP,
            isset($aFilter['blog_id']) ? $aFilter['blog_id'] : DBSIMPLE_SKIP
        );

        $sql = "SELECT
                  t.topic_id AS id,
                  count(DISTINCT m.media_id) AS count
                FROM
                  ?_media m
                  LEFT JOIN ?_media_target mt ON m.media_id = mt.media_id
                  LEFT JOIN ?_topic t ON t.topic_id = mt.target_id
                  LEFT JOIN ?_blog b ON b.blog_id = t.blog_id
                WHERE
                  (m.user_id = ?d)
                  {AND (m.type & ?d)}
                  AND (mt.target_id <> 0)
                  {AND (mt.target_type IN (?a) OR mt.target_type LIKE 'single-image-uploader%')}
                  {AND (t.topic_publish = ?d) AND (t.topic_date_show <= NOW())}
                  {AND t.topic_index_ignore = ?d}
                  {AND (t.topic_type = ?)}
                  {AND t.topic_type IN (?a)}
                  {AND b.blog_type = 'personal' OR t.blog_id IN ( ?a )}
                GROUP BY t.topic_id
                LIMIT ?d, ?d
                ";
        $aRows = $this->oDb->select($sql,
            $aFilter['user_id'],
            isset($aFilter['media_type']) ? $aFilter['media_type'] : DBSIMPLE_SKIP,
            isset($aFilter['target_type']) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_publish']) ? $aFilter['topic_publish'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_index_ignore']) ? $aFilter['topic_index_ignore'] : DBSIMPLE_SKIP,
            (isset($aFilter['topic_type']) && !is_array($aFilter['topic_type'])) ? $aFilter['topic_type'] : DBSIMPLE_SKIP,
            (isset($aFilter['topic_type']) && is_array($aFilter['topic_type'])) ? $aFilter['topic_type'] : DBSIMPLE_SKIP,
            isset($aFilter['blog_id']) ? $aFilter['blog_id'] : DBSIMPLE_SKIP,
            ($iCurrPage - 1) * $iPerPage, $iPerPage
        );

        $aResult = [];
        if ($aRows) {
            foreach ($aRows as $aRow) {
                $aResult[$aRow['id']] = $aRow['count'];
            }
        }
        return $aResult;
    }

    public function getCountImagesByTopicType($aFilter) {

        $sql = "SELECT
                  t.topic_type AS id,
                  count(DISTINCT m.media_id) AS count
                FROM
                  ?_media m
                  LEFT JOIN ?_media_target mt ON m.media_id = mt.media_id
                  LEFT JOIN ?_topic t ON t.topic_id = mt.target_id
                  LEFT JOIN ?_blog b ON b.blog_id = t.blog_id
                WHERE
                  (m.user_id = ?d)
                  {AND (m.type & ?d)}
                  AND (mt.target_id <> 0)
                  {AND (mt.target_type IN (?a) OR mt.target_type LIKE 'single-image-uploader%')}
                  {AND (t.topic_publish = ?d) AND (t.topic_date_show <= NOW())}
                  {AND b.blog_type = 'personal' OR t.blog_id IN ( ?a )}
                  {AND t.topic_index_ignore = ?d}
                GROUP BY t.topic_type
                ";
        $aRows = $this->oDb->select($sql,
            $aFilter['user_id'],
            isset($aFilter['media_type']) ? $aFilter['media_type'] : DBSIMPLE_SKIP,
            isset($aFilter['target_type']) ? $aFilter['target_type'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_publish']) ? $aFilter['topic_publish'] : DBSIMPLE_SKIP,
            isset($aFilter['blog_id']) ? $aFilter['blog_id'] : DBSIMPLE_SKIP,
            isset($aFilter['topic_index_ignore']) ? $aFilter['topic_index_ignore'] : DBSIMPLE_SKIP
        );
        if ($aRows) {
            return $aRows;
        }
        return [];
    }

    /**
     * Получает ид. писем пользователя
     *
     * @param int $iUserId
     * @param int $iCount
     * @param int $iCurrPage
     * @param int $iPerPage
     *
     * @return array
     */
    public function getTalkInfo($iUserId, &$iCount, $iCurrPage, $iPerPage) 
    {
        $sql = "SELECT
                  t.target_id        AS talk_id,
                  count(t.target_id) AS count
                FROM ?_media_target t, ?_media m
                WHERE
                  m.media_id = t.media_id
                  AND m.user_id = ?d
                  AND t.target_type IN ( ?a )
                GROUP BY talk_id
                ORDER BY m.date_add desc
                LIMIT ?d, ?d";

        $aResult = [];

        if ($aRows = $this->oDb->selectPage($iCount, $sql, $iUserId, array('talk'), ($iCurrPage - 1) * $iPerPage, $iPerPage)) {
            foreach ($aRows as $aRow) {
                $aResult[$aRow['talk_id']] = $aRow['count'];
            }
        }

        return $aResult;
    }

}

// EOF
