<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 */

namespace alto\engine\generic;
use avadim\DbSimple\Database;

/**
 * Абстрактный класс мапера
 * Вся задача маппера сводится в выполнению запроса к базе данных (или либому другому источнику данных)
 * и возвращения результата в модуль.
 *
 * @package engine
 * @since 1.0
 */
abstract class Mapper extends Component
{
    const CRITERIA_CALC_TOTAL_SKIP = 0;
    const CRITERIA_CALC_TOTAL_AUTO = 1;
    const CRITERIA_CALC_TOTAL_FORCE = 2;
    const CRITERIA_CALC_TOTAL_ONLY = 3;

    /** @var \ModuleDatabase  */
    protected $oModuleDatabase;

    /** @var Database Default database */
    protected $oDb;

    /** @var Database[]  */
    protected $aDb = [];

    /**
     * Передаем коннект к БД
     *
     * @param \ModuleDatabase $oModuleDatabase
     */
    public function __construct($oModuleDatabase)
    {
        parent::__construct();
        $this->oModuleDatabase = $oModuleDatabase;
        $this->oDb = $this->db(0);
    }

    /**
     * Select database
     *
     * @param int $iDbIndex
     *
     * @return Database
     */
    protected function db($iDbIndex = null)
    {
        if (null === $iDbIndex) {
            $iDbIndex = 0;
        }
        if (!isset($this->aDb[$iDbIndex])) {
            $this->aDb[$iDbIndex] = $this->oModuleDatabase->getConnect($iDbIndex);
        }
        return $this->aDb[$iDbIndex];
    }

    /**
     * @param $aIds
     *
     * @return array
     */
    protected function _arrayId($aIds)
    {
        if (!is_array($aIds)) {
            $aIds = [(int)$aIds];
        } else {
            foreach ($aIds as $n => $nId)
                $aIds[$n] = (int)$nId;
        }
        array_unique($aIds);

        return $aIds;
    }

    /**
     * @param $aCriteria
     *
     * @return array
     */
    protected function _prepareLimit($aCriteria)
    {
        if (isset($aCriteria['limit'])) {
            // Если массив, то первое значение - смещение, а второе - лимит
            if (is_array($aCriteria['limit'])) {
                $nOffset = (int)array_shift($aCriteria['limit']);
                $nLimit = (int)array_shift($aCriteria['limit']);
            } else {
                $nOffset = false;
                $nLimit = (int)$aCriteria['limit'];
            }
        } else {
            $nOffset = false;
            $nLimit = false;
        }
        return [$nOffset, $nLimit];
    }

    /**
     * @param int $iDbIndex
     * @param EntityRecord $oEntity
     *
     * @return int|bool
     */
    public function insertEntityDb($iDbIndex, $oEntity)
    {
        $sTable = $oEntity::getTableName();
        $aData = $oEntity->getInsertAttributeValues();

        $sql = "INSERT INTO $sTable(?#) VALUES(?a)";

        $nId = $this->db($iDbIndex)->query($sql, array_keys($aData), array_values($aData));

        return $nId ? $nId : false;
    }

    /**
     * @param int $iDbIndex
     * @param EntityRecord $oEntity
     *
     * @return bool
     */
    public function updateEntityDb($iDbIndex, $oEntity)
    {
        $sTable = $oEntity::getTableName();
        $aData = $oEntity->getUpdateAttributeValues();

        $sql = "UPDATE $sTable SET ?a";

        $xResult = $this->db($iDbIndex)->query($sql, $aData);

        return $xResult !== false;
    }

    public function readEntity($sEntityClass, $oCriteria)
    {
        $sTable = $sEntityClass::getTableName();
        $aKeys = $sEntityClass::getAttributeKeys();
    }

    public function deleteEntity($sEntityClass, $oCriteria)
    {
        $sTable = $sEntityClass::getTableName();
    }

    /**
     * @param EntityRecord $oEntity
     *
     * @return bool|int
     */
    public function insertEntity($oEntity)
    {
        return $this->insertEntityDb(0, $oEntity);
    }

    /**
     * @param EntityRecord $oEntity
     *
     * @return bool
     */
    public function updateEntity($oEntity)
    {
        return $this->updateEntityDb(0, $oEntity);
    }

}

// EOF