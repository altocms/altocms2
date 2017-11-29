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
 * Модуль Geo - привязка объектов к географии (страна/регион/город)
 * Терминология:
 *        объект - который привязываем к гео-объекту
 *        гео-объект - географический объект(страна/регион/город)
 *
 * @package modules.geo
 * @since   1.0
 */
class ModuleGeo extends Module {
    /**
     * Объект маппера
     *
     * @var ModuleGeo_MapperGeo
     */
    protected $oMapper;
    /**
     * Объект текущего пользователя
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent;
    /**
     * Список доступных типов объектов
     * На данный момент доступен параметр allow_multi=>1 - указывает на возможность создавать несколько связей для одного объекта
     *
     * @var array
     */
    protected $aTargetTypes
        = array(
            'user' => array(),
        );
    /**
     * Список доступных типов гео-объектов
     *
     * @var array
     */
    protected $aGeoTypes
        = array(
            'country',
            'region',
            'city',
        );

    /**
     * Инициализация
     *
     */
    public function init() {

        $this->oMapper = \E::getMapper(__CLASS__);
    }

    /**
     * Возвращает список типов объектов
     *
     * @return array
     */
    public function getTargetTypes() {

        return $this->aTargetTypes;
    }

    /**
     * Добавляет в разрешенные новый тип
     * @param string $sTargetType    Тип владельца
     * @param array  $aParams        Параметры
     *
     * @return bool
     */
    public function addTargetType($sTargetType, $aParams = array()) {

        if (!array_key_exists($sTargetType, $this->aTargetTypes)) {
            $this->aTargetTypes[$sTargetType] = $aParams;
            return true;
        }
        return false;
    }

    /**
     * Проверяет разрешен ли данный тип
     *
     * @param string $sTargetType    Тип владельца
     *
     * @return bool
     */
    public function isAllowTargetType($sTargetType)
    {
        return array_key_exists($sTargetType, $this->aTargetTypes);
    }

    /**
     * Проверяет разрешен ли данный гео-тип
     *
     * @param string $sGeoType    Тип владельца
     *
     * @return bool
     */
    public function isAllowGeoType($sGeoType)
    {
        return in_array($sGeoType, $this->aGeoTypes, true);
    }

    /**
     * Проверка объекта
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iTargetId      ID владельца
     *
     * @return bool
     */
    public function checkTarget($sTargetType, $iTargetId) {

        if (!$this->IsAllowTargetType($sTargetType)) {
            return false;
        }
        $sMethod = 'CheckTarget' . F::StrCamelize($sTargetType);
        if (method_exists($this, $sMethod)) {
            return $this->$sMethod($iTargetId);
        }
        return false;
    }

    /**
     * Проверка на возможность нескольких связей
     *
     * @param string $sTargetType    Тип владельца
     *
     * @return bool
     */
    public function isAllowTargetMulti($sTargetType) {

        if ($this->IsAllowTargetType($sTargetType)) {
            if (isset($this->aTargetTypes[$sTargetType]['allow_multi'])
                && $this->aTargetTypes[$sTargetType]['allow_multi']
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Добавляет связь объекта с гео-объектом в БД
     *
     * @param ModuleGeo_EntityTarget $oTarget    Объект связи с владельцем
     *
     * @return ModuleGeo_EntityTarget|bool
     */
    public function addTarget($oTarget) {

        if ($this->oMapper->addTarget($oTarget)) {
            \E::Module('Cache')->cleanByTags(array('geo_target_update'));
            return $oTarget;
        }
        return false;
    }

    /**
     * Создание связи
     *
     * @param ModuleGeo_EntityGeo $oGeoObject
     * @param string              $sTargetType    Тип владельца
     * @param int                 $iTargetId      ID владельца
     *
     * @return bool|ModuleGeo_EntityTarget
     */
    public function createTarget($oGeoObject, $sTargetType, $iTargetId) {
        /**
         * Проверяем объект на валидность
         */
        if (!$this->CheckTarget($sTargetType, $iTargetId)) {
            return false;
        }
        /**
         * Проверяем есть ли уже у этого объекта другие связи
         */
        $aTargets = $this->GetTargets(array('target_type' => $sTargetType, 'target_id' => $iTargetId), 1, 1);
        if ($aTargets['count']) {
            if ($this->IsAllowTargetMulti($sTargetType)) {
                /**
                 * Разрешено несколько связей
                 * Проверяем есть ли уже связь с данным гео-объектом, если есть то возвращаем его
                 */
                $aTargetSelf = $this->GetTargets(
                    array('target_type' => $sTargetType, 'target_id' => $iTargetId,
                          'geo_type'    => $oGeoObject->getType(), 'geo_id' => $oGeoObject->getId()), 1, 1
                );
                if (isset($aTargetSelf['collection'][0])) {
                    return $aTargetSelf['collection'][0];
                }
            } else {
                /**
                 * Есть другие связи и несколько связей запрещено - удаляем имеющиеся связи
                 */
                $this->deleteTargets(array('target_type' => $sTargetType, 'target_id' => $iTargetId));
            }
        }
        /**
         * Создаем связь
         */
        $oTarget = \E::getEntity('ModuleGeo_EntityTarget');
        $oTarget->setGeoType($oGeoObject->getType());
        $oTarget->setGeoId($oGeoObject->getId());
        $oTarget->setTargetType($sTargetType);
        $oTarget->setTargetId($iTargetId);
        if ($oGeoObject->getType() == 'city') {
            $oTarget->setCountryId($oGeoObject->getCountryId());
            $oTarget->setRegionId($oGeoObject->getRegionId());
            $oTarget->setCityId($oGeoObject->getId());
        } elseif ($oGeoObject->getType() == 'region') {
            $oTarget->setCountryId($oGeoObject->getCountryId());
            $oTarget->setRegionId($oGeoObject->getId());
        } elseif ($oGeoObject->getType() == 'country') {
            $oTarget->setCountryId($oGeoObject->getId());
        }
        \E::Module('Cache')->cleanByTags(array('geo_target_update'));
        return $this->AddTarget($oTarget);
    }

    /**
     * Возвращает список связей по фильтру
     *
     * @param array $aFilter      Фильтр
     * @param int   $iCurrPage    Номер страницы
     * @param int   $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getTargets($aFilter, $iCurrPage, $iPerPage) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getTargets($aFilter, $iCount, $iCurrPage, $iPerPage),
                'count'      => $iCount
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Возвращает первый объект связи по объекту
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iTargetId      ID владельца
     *
     * @return null|ModuleGeo_EntityTarget
     */
    public function getTargetByTarget($sTargetType, $iTargetId) {

        $aTargets = $this->GetTargets(array('target_type' => $sTargetType, 'target_id' => $iTargetId), 1, 1);
        if (isset($aTargets['collection'][0])) {
            return $aTargets['collection'][0];
        }
        return null;
    }

    /**
     * Возвращает список связей для списка объектов одного типа.
     *
     * @param string $sTargetType    Тип владельца
     * @param array  $aTargetId      Список ID владельцев
     *
     * @return array В качестве ключей используется ID объекта, в качестве значений массив связей этого объекта
     */
    public function getTargetsByTargetArray($sTargetType, &$aTargetId) {

        if (!is_array($aTargetId)) {
            $aTargetId = array($aTargetId);
        }
        if (!count($aTargetId)) {
            return [];
        }
        $aResult = [];
        $aTargets = $this->GetTargets(
            array('target_type' => $sTargetType, 'target_id' => $aTargetId), 1, count($aTargetId)
        );
        if ($aTargets['count']) {
            foreach ($aTargets['collection'] as $oTarget) {
                $aResult[$oTarget->getTargetId()][] = $oTarget;
            }
        }
        return $aResult;
    }

    /**
     * Удаляет связи по фильтру
     *
     * @param array $aFilter    Фильтр
     *
     * @return bool|int
     */
    public function deleteTargets($aFilter) {

        $xResult =  $this->oMapper->deleteTargets($aFilter);
        \E::Module('Cache')->cleanByTags(array('geo_target_update'));
        return $xResult;
    }

    /**
     * Удаление всех связей объекта
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iTargetId      ID владельца
     *
     * @return bool|int
     */
    public function deleteTargetsByTarget($sTargetType, $iTargetId) {

        $xResult = $this->deleteTargets(array('target_type' => $sTargetType, 'target_id' => $iTargetId));
        \E::Module('Cache')->cleanByTags(array('geo_target_update'));
        return $xResult;
    }

    /**
     * Возвращает список стран по фильтру
     *
     * @param array $aFilter      Фильтр
     * @param array $aOrder       Сортировка
     * @param int   $iCurrPage    Номер страницы
     * @param int   $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getCountries($aFilter, $aOrder, $iCurrPage, $iPerPage) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getCountries($aFilter, $aOrder, $iCount, $iCurrPage, $iPerPage),
                'count'      => $iCount
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Возвращает список регионов по фильтру
     *
     * @param array $aFilter      Фильтр
     * @param array $aOrder       Сортировка
     * @param int   $iCurrPage    Номер страницы
     * @param int   $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getRegions($aFilter, $aOrder, $iCurrPage, $iPerPage) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getRegions($aFilter, $aOrder, $iCount, $iCurrPage, $iPerPage),
                'count'      => $iCount
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Возвращает список городов по фильтру
     *
     * @param array $aFilter      Фильтр
     * @param array $aOrder       Сортировка
     * @param int   $iCurrPage    Номер страницы
     * @param int   $iPerPage     Количество элементов на страницу
     *
     * @return array('collection'=>array,'count'=>int)
     */
    public function getCities($aFilter, $aOrder, $iCurrPage, $iPerPage) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getCities($aFilter, $aOrder, $iCount, $iCurrPage, $iPerPage),
                'count'      => $iCount
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Возвращает страну по ID
     *
     * @param int $iId ID страны
     *
     * @return ModuleGeo_EntityCountry|null
     */
    public function getCountryById($iId) {

        $aRes = $this->GetCountries(array('id' => $iId), array(), 1, 1);
        if (isset($aRes['collection'][$iId])) {
            return $aRes['collection'][$iId];
        }
        return null;
    }

    /**
     * Возвращает регион по ID
     *
     * @param int $iId ID региона
     *
     * @return ModuleGeo_EntityRegion|null
     */
    public function getRegionById($iId) {

        $aRes = $this->GetRegions(array('id' => $iId), array(), 1, 1);
        if (isset($aRes['collection'][$iId])) {
            return $aRes['collection'][$iId];
        }
        return null;
    }

    /**
     * Возвращает регион по ID
     *
     * @param int $iId    ID города
     *
     * @return ModuleGeo_EntityCity|null
     */
    public function getCityById($iId) {

        $aRes = $this->GetCities(array('id' => $iId), array(), 1, 1);
        if (isset($aRes['collection'][$iId])) {
            return $aRes['collection'][$iId];
        }
        return null;
    }

    /**
     * Возвращает гео-объект
     *
     * @param string $sType    Тип гео-объекта
     * @param int    $nId      ID гео-объекта
     *
     * @return ModuleGeo_EntityGeo|null
     */
    public function getGeoObject($sType, $nId) {

        $sType = strtolower($sType);
        if (!$this->IsAllowGeoType($sType)) {
            return null;
        }
        switch ($sType) {
            case 'country':
                return $this->GetCountryById($nId);
                break;
            case 'region':
                return $this->GetRegionById($nId);
                break;
            case 'city':
                return $this->GetCityById($nId);
                break;
            default:
                return null;
        }
    }

    /**
     * Возвращает первый гео-объект для объекта
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iTargetId      ID владельца
     *
     * @return ModuleGeo_EntityCity|ModuleGeo_EntityCountry|ModuleGeo_EntityRegion|null
     */
    public function getGeoObjectByTarget($sTargetType, $iTargetId) {

        $aTargets = $this->GetTargets(array('target_type' => $sTargetType, 'target_id' => $iTargetId), 1, 1);
        if (isset($aTargets['collection'][0])) {
            $oTarget = $aTargets['collection'][0];
            return $this->GetGeoObject($oTarget->getGeoType(), $oTarget->getGeoId());
        }
        return null;
    }

    /**
     * Возвращает список стран сгруппированных по количеству использований в данном типе объектов
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iLimit         Количество элементов
     *
     * @return array
     */
    public function getGroupCountriesByTargetType($sTargetType, $iLimit) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getGroupCountriesByTargetType($sTargetType, $iLimit);
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Возвращает список городов сгруппированных по количеству использований в данном типе объектов
     *
     * @param string $sTargetType    Тип владельца
     * @param int    $iLimit         Количество элементов
     *
     * @return array
     */
    public function getGroupCitiesByTargetType($sTargetType, $iLimit) {

        $sCacheKey = 'Geo_' . __FUNCTION__ . '-' . serialize(func_get_args());
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = $this->oMapper->getGroupCitiesByTargetType($sTargetType, $iLimit);
            \E::Module('Cache')->set($data, $sCacheKey, array('geo_target_update'), 'P1D');
        }
        return $data;
    }

    /**
     * Проверка объекта с типом "user"
     * Название метода формируется автоматически
     *
     * @param int $iTargetId    ID пользователя
     *
     * @return bool
     */
    public function checkTargetUser($iTargetId) {

        if ($oUser = \E::Module('User')->getUserById($iTargetId)) {
            return true;
        }
        return false;
    }

}

// EOF