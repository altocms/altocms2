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
 * Объект сущности гео-объекта
 *
 * @package modules.geo
 * @since   1.0
 */
class ModuleGeo_EntityGeo extends Entity {

    /**
     * Возвращает имя гео-объекта в зависимости от языка
     *
     * @return string
     */
    public function getName() {

        return $this->getLangSuffixProp('name');
    }

    /**
     * Возвращает тип гео-объекта
     *
     * @return null|string
     */
    public function getType() {

        if ($this instanceof ModuleGeo_EntityCity) {
            return 'city';
        } elseif ($this instanceof ModuleGeo_EntityRegion) {
            return 'region';
        } elseif ($this instanceof ModuleGeo_EntityCountry) {
            return 'country';
        }
        return null;
    }

    /**
     * Возвращает гео-объект страны
     *
     * @return ModuleGeo_EntityGeo|null
     */
    public function getCountry() {

        if ($this->getType() == 'country') {
            return $this;
        }
        if ($oCountry = $this->getProp('country')) {
            return $oCountry;
        }
        if ($this->getCountryId()) {
            $oCountry = \E::Module('Geo')->getCountryById($this->getCountryId());
            $this->setProp('country', $oCountry);
            return $oCountry;
        }
        return null;
    }

    /**
     * Возвращает гео-объект региона
     *
     * @return ModuleGeo_EntityGeo|null
     */
    public function getRegion() {

        if ($this->getType() == 'region') {
            return $this;
        }
        if ($oRegion = $this->getProp('region')) {
            return $oRegion;
        }
        if ($this->getRegionId()) {
            $oRegion = \E::Module('Geo')->getRegionById($this->getRegionId());
            $this->setProp('region', $oRegion);
            return $oRegion;
        }
        return null;
    }

    /**
     * Возвращает гео-объект города
     *
     * @return ModuleGeo_EntityGeo|null
     */
    public function getCity() {

        if ($this->getType() == 'city') {
            return $this;
        }
        if ($oCity = $this->getProp('city')) {
            return $oCity;
        }
        if ($this->getCityId()) {
            $oCity = \E::Module('Geo')->getCityById($this->getCityId());
            $this->setProp('city', $oCity);
            return $oCity;
        }
        return null;
    }

}

// EOF